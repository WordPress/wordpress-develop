import { spawn } from 'node:child_process';
import { copyFile, mkdir, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import path from 'node:path';

import { PLAYGROUND_WORKERS } from './playground.mjs';

const BANNER_ID = 'wporg-code-reference-preview-provenance';

function bundled( resourcePath ) {
	return { resource: 'bundled', path: resourcePath };
}

export function createValidationBlueprint( inputs ) {
	return {
		$schema: inputs.dependencies.playground.blueprintSchema,
		preferredVersions: {
			php: inputs.dependencies.playground.phpVersion,
			wp: inputs.wordpress.version,
		},
		landingPage: '/reference/',
		login: false,
		features: { networking: false },
		steps: [
			{
				step: 'unzip',
				zipFile: bundled( 'snapshot.zip' ),
				extractToPath: '/',
			},
		],
	};
}

function delay( milliseconds ) {
	return new Promise( ( resolve ) => setTimeout( resolve, milliseconds ) );
}

async function availablePort() {
	const server = createServer();
	await new Promise( ( resolve, reject ) => {
		server.once( 'error', reject );
		server.listen( 0, '127.0.0.1', resolve );
	} );
	const port = server.address().port;
	await new Promise( ( resolve ) => server.close( resolve ) );
	return port;
}

function startServer( command, args ) {
	const child = spawn( command, args, {
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
	let output = '';
	child.stdout.on( 'data', ( chunk ) => {
		output += chunk;
	} );
	child.stderr.on( 'data', ( chunk ) => {
		output += chunk;
	} );
	const closed = new Promise( ( resolve ) => {
		child.once( 'error', ( error ) => resolve( { error } ) );
		child.once( 'close', ( code, signal ) => resolve( { code, signal } ) );
	} );
	return { child, closed, output: () => output };
}

export function createValidationServerArguments(
	inputs,
	blueprint,
	baseUrl,
	port
) {
	return [
		'server',
		'--php',
		inputs.dependencies.playground.phpVersion,
		'--wp',
		inputs.wordpress.version,
		'--blueprint',
		blueprint,
		'--blueprint-may-read-adjacent-files',
		'--site-url',
		baseUrl,
		'--port',
		String( port ),
		'--workers',
		String( PLAYGROUND_WORKERS ),
		'--verbosity',
		'normal',
	];
}

async function stopServer( server ) {
	if ( server.child.exitCode !== null || server.child.signalCode !== null ) {
		return;
	}
	server.child.kill( 'SIGTERM' );
	const stopped = await Promise.race( [
		server.closed.then( () => true ),
		delay( 5000 ).then( () => false ),
	] );
	if ( ! stopped ) {
		server.child.kill( 'SIGKILL' );
		await server.closed;
	}
}

async function waitForBoot( baseUrl, server, fetchImplementation ) {
	const deadline = Date.now() + 120000;
	while ( Date.now() < deadline ) {
		const state = await Promise.race( [
			server.closed,
			delay( 250 ).then( () => null ),
		] );
		if ( state ) {
			throw new Error(
				`Playground exited before boot (${
					state.code ?? state.signal ?? state.error?.message
				}).\n${ server.output() }`
			);
		}
		try {
			const response = await fetchImplementation(
				`${ baseUrl }/wp-json/docs-preview/v1/health`
			);
			if ( response.ok ) {
				return;
			}
		} catch {
			// The server is not accepting requests yet.
		}
	}
	throw new Error(
		`Playground did not boot within 120 seconds.\n${ server.output() }`
	);
}

async function withPlaygroundServer( inputs, options, inspect ) {
	const work = path.resolve( options.workDirectory );
	await rm( work, { recursive: true, force: true } );
	await mkdir( work, { recursive: true } );
	await copyFile( options.snapshot, path.join( work, 'snapshot.zip' ) );
	const blueprint = path.join( work, 'validation-blueprint.json' );
	await writeFile(
		blueprint,
		`${ JSON.stringify( createValidationBlueprint( inputs ), null, 2 ) }\n`
	);
	const port = await availablePort();
	const baseUrl = `http://127.0.0.1:${ port }`;
	const server = startServer(
		options.playgroundCli,
		createValidationServerArguments( inputs, blueprint, baseUrl, port )
	);
	try {
		await waitForBoot( baseUrl, server, options.fetchImplementation );
		return await inspect( baseUrl );
	} finally {
		await stopServer( server );
		await rm( work, { recursive: true, force: true } );
	}
}

function provenanceFailure( actual, expected ) {
	for ( const name of [
		'sourceRepository',
		'sourceSha',
		'generationTimestamp',
		'runUrl',
	] ) {
		if ( actual?.[ name ] !== expected[ name ] ) {
			return `Health provenance ${ name } does not match the build.`;
		}
	}
	return null;
}

function checkBanner( name, body, provenance, failures ) {
	const timestamp = provenance.generationTimestamp
		.slice( 0, 19 )
		.replace( 'T', ' ' );
	const expected = [
		`id="${ BANNER_ID }"`,
		provenance.sourceRepository,
		provenance.sourceSha,
		`${ timestamp } UTC`,
	];
	if ( provenance.runUrl ) {
		expected.push( provenance.runUrl );
	}
	for ( const value of expected ) {
		if ( ! body.includes( value ) ) {
			failures.push(
				`${ name } route has incomplete provenance banner.`
			);
		}
	}
}

export async function inspectSnapshotBehavior( inputs, options ) {
	const failures = [];
	const checks = {};
	const request = async ( route ) => {
		const response = await options.fetchImplementation(
			`${ options.baseUrl }${ route }`
		);
		return {
			status: response.status,
			url: response.url,
			body: await response.text(),
		};
	};

	let health;
	try {
		const response = await request( '/wp-json/docs-preview/v1/health' );
		checks.health = response.status;
		health = JSON.parse( response.body );
		if ( response.status !== 200 ) {
			failures.push( `Health route returned HTTP ${ response.status }.` );
		}
	} catch ( error ) {
		failures.push( `Health route failed: ${ error.message }` );
	}
	if ( health ) {
		const mismatch = provenanceFailure(
			health.provenance,
			options.provenance
		);
		if ( mismatch ) {
			failures.push( mismatch );
		}
		if ( health.import?.stage !== 'complete-import' ) {
			failures.push( 'Complete import terminal stage is missing.' );
		}
		if ( health.outboundNetworkDisabled !== true ) {
			failures.push( 'WordPress outbound networking is not disabled.' );
		}
		for ( const name of [
			'DISABLE_WP_CRON',
			'AUTOMATIC_UPDATER_DISABLED',
			'WP_AUTO_UPDATE_CORE',
			'DISALLOW_FILE_MODS',
		] ) {
			if ( health.constants?.[ name ] !== true ) {
				failures.push(
					`Runtime policy constant ${ name } is not enforced.`
				);
			}
		}
	}

	for ( const [ name, target ] of Object.entries(
		inputs.dependencies.validation.routes
	) ) {
		try {
			const response = await request( target.path );
			checks[ name ] = response.status;
			if ( response.status !== 200 ) {
				failures.push(
					`${ name } route returned HTTP ${ response.status }.`
				);
			}
			const requested = new URL( target.path, options.baseUrl );
			if (
				! response.url ||
				new URL( response.url ).pathname !== requested.pathname
			) {
				failures.push(
					`${ name } route redirected away from its stable path.`
				);
			}
			if ( ! response.body.includes( target.expectedText ) ) {
				failures.push(
					`${ name } route is missing its representative symbol.`
				);
			}
			checkBanner( name, response.body, options.provenance, failures );
		} catch ( error ) {
			failures.push( `${ name } route failed: ${ error.message }` );
		}
	}

	try {
		const search = inputs.dependencies.validation.search;
		const response = await request( search.path );
		checks.search = response.status;
		if (
			response.status !== 200 ||
			! response.body.includes( search.expectedText ) ||
			! response.body.includes( search.expectedPath )
		) {
			failures.push(
				'Local Code Reference search did not return the expected result.'
			);
		}
		checkBanner( 'search', response.body, options.provenance, failures );
	} catch ( error ) {
		failures.push(
			`Local Code Reference search failed: ${ error.message }`
		);
	}

	if ( options.requireRunUrl && ! options.provenance.runUrl ) {
		failures.push( 'Published previews require an Actions run URL.' );
	}
	return { failures, checks };
}

export async function validateSnapshot( inputs, options ) {
	const fetchImplementation = options.fetchImplementation || globalThis.fetch;
	try {
		return await ( options.serveImplementation || withPlaygroundServer )(
			inputs,
			{ ...options, fetchImplementation },
			( baseUrl ) =>
				inspectSnapshotBehavior( inputs, {
					...options,
					baseUrl,
					fetchImplementation,
				} )
		);
	} catch ( error ) {
		return {
			failures: [ `Playground boot failed: ${ error.message }` ],
			checks: {},
		};
	}
}
