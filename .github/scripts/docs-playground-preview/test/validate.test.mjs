import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
	createValidationBlueprint,
	createValidationServerArguments,
	inspectSnapshotBehavior,
	validateSnapshot,
} from '../lib/validate.mjs';

const provenance = {
	sourceRepository: 'example/wordpress-develop',
	sourceSha: 'a'.repeat( 40 ),
	generationTimestamp: '2026-08-09T12:34:56.000Z',
	runUrl: 'https://github.com/example/wordpress-develop/actions/runs/123',
};

function inputs() {
	return {
		wordpress: { version: '7.2-beta1' },
		dependencies: {
			playground: {
				blueprintSchema:
					'https://playground.wordpress.net/blueprint-schema.json',
				phpVersion: '8.4',
			},
			validation: {
				routes: {
					index: {
						path: '/reference/',
						expectedText: 'Reference index',
					},
					class: {
						path: '/reference/classes/example/',
						expectedText: 'Example_Class',
					},
					method: {
						path: '/reference/classes/example/run/',
						expectedText: 'Example_Class::run',
					},
					function: {
						path: '/reference/functions/example/',
						expectedText: 'example_function',
					},
					hook: {
						path: '/reference/hooks/example_action/',
						expectedText: 'example_action',
					},
					filter: {
						path: '/reference/hooks/example_filter/',
						expectedText: 'example_filter',
					},
				},
				search: {
					path: '/?s=example',
					expectedText: 'example_result',
					expectedPath: '/reference/functions/example/',
				},
			},
		},
	};
}

/**
 * @param {unknown} body
 */
function response( body, status = 200, url = 'http://127.0.0.1:9400/' ) {
	return {
		status,
		url,
		text: async () =>
			typeof body === 'string' ? body : JSON.stringify( body ),
	};
}

/**
 * @param {string} url
 */
function healthyFetch( url ) {
	if ( url.endsWith( '/wp-json/docs-preview/v1/health' ) ) {
		return response(
			{
				provenance,
				import: { stage: 'complete-import' },
				outboundNetworkDisabled: true,
				constants: {
					DISABLE_WP_CRON: true,
					AUTOMATIC_UPDATER_DISABLED: true,
					WP_AUTO_UPDATE_CORE: true,
					DISALLOW_FILE_MODS: true,
				},
			},
			200,
			url
		);
	}
	if ( url.includes( '?s=example' ) ) {
		return response(
			`<aside id="wporg-code-reference-preview-provenance">${ provenance.sourceRepository } ${ provenance.sourceSha } 2026-08-09 12:34:56 UTC <a href="${ provenance.runUrl }">Build run</a></aside> example_result <a href="/reference/functions/example/">example_function</a>`,
			200,
			url
		);
	}
	const target = Object.values(
		inputs().dependencies.validation.routes
	).find( ( route ) => url.endsWith( route.path ) );
	if ( ! target ) {
		throw new Error( `Unexpected validation URL: ${ url }` );
	}
	return response(
		`<aside id="wporg-code-reference-preview-provenance">${ provenance.sourceRepository } ${ provenance.sourceSha } 2026-08-09 12:34:56 UTC <a href="${ provenance.runUrl }">Build run</a></aside> ${ target.expectedText }`,
		200,
		url
	);
}

test( 'validation Blueprint boots the exact snapshot without login or networking', () => {
	const blueprint = createValidationBlueprint( inputs() );
	assert.equal( blueprint.login, false );
	assert.deepEqual( blueprint.features, { networking: false } );
	assert.equal( blueprint.preferredVersions.php, '8.4' );
	assert.equal( blueprint.preferredVersions.wp, '7.2-beta1' );
	assert.deepEqual( blueprint.steps, [
		{
			step: 'unzip',
			zipFile: { resource: 'bundled', path: 'snapshot.zip' },
			extractToPath: '/',
		},
	] );
} );

test( 'validation server uses six Playground workers', () => {
	const args = createValidationServerArguments(
		inputs(),
		'/tmp/validation-blueprint.json',
		'http://127.0.0.1:9400',
		9400
	);
	assert.equal( args[ args.indexOf( '--workers' ) + 1 ], '6' );
	assert.deepEqual( args.slice( 0, 5 ), [
		'server',
		'--php',
		'8.4',
		'--wp',
		'7.2-beta1',
	] );
} );

test( 'behavioral validation covers health, routes, search, and banner', async () => {
	const result = await inspectSnapshotBehavior( inputs(), {
		baseUrl: 'http://127.0.0.1:9400',
		fetchImplementation: healthyFetch,
		provenance,
		requireRunUrl: true,
	} );
	assert.deepEqual( result.failures, [] );
	assert.deepEqual( result.checks, {
		health: 200,
		index: 200,
		class: 200,
		method: 200,
		function: 200,
		hook: 200,
		filter: 200,
		search: 200,
	} );
} );

test( 'behavioral defects are advisory validation failures', async () => {
	const result = await inspectSnapshotBehavior( inputs(), {
		baseUrl: 'http://127.0.0.1:9400',
		fetchImplementation: /** @param {string} url */ async ( url ) => {
			if ( url.endsWith( '/health' ) ) {
				return response(
					{
						provenance: {
							...provenance,
							sourceSha: 'b'.repeat( 40 ),
						},
						import: null,
						outboundNetworkDisabled: false,
						constants: {},
					},
					200,
					url
				);
			}
			return response( 'broken', 404, url );
		},
		provenance: { ...provenance, runUrl: null },
		requireRunUrl: true,
	} );
	assert.ok(
		result.failures.some( ( failure ) => failure.includes( 'sourceSha' ) )
	);
	assert.ok(
		result.failures.some( ( failure ) =>
			failure.includes( 'terminal stage' )
		)
	);
	assert.ok(
		result.failures.some( ( failure ) =>
			failure.includes( 'outbound networking' )
		)
	);
	assert.ok(
		result.failures.some( ( failure ) =>
			failure.includes( 'DISABLE_WP_CRON' )
		)
	);
	assert.ok(
		result.failures.some( ( failure ) => failure.includes( 'class route' ) )
	);
	assert.ok(
		result.failures.some( ( failure ) => failure.includes( 'search' ) )
	);
	assert.ok(
		result.failures.some( ( failure ) =>
			failure.includes( 'Actions run URL' )
		)
	);
} );

test( 'symbol routes may not redirect to a generic successful page', async () => {
	const baseUrl = 'http://127.0.0.1:9400';
	const result = await inspectSnapshotBehavior( inputs(), {
		baseUrl,
		fetchImplementation: /** @param {string} url */ async ( url ) => {
			const current = await healthyFetch( url );
			if ( url.includes( '/reference/classes/example/' ) ) {
				return { ...current, url: `${ baseUrl }/reference/` };
			}
			return current;
		},
		provenance,
		requireRunUrl: true,
	} );
	assert.ok(
		result.failures.includes(
			'class route redirected away from its stable path.'
		)
	);
} );

test( 'a Playground boot failure is returned as validation state', async () => {
	const result = await validateSnapshot( inputs(), {
		serveImplementation: async () => {
			throw new Error( 'did not boot' );
		},
	} );
	assert.deepEqual( result, {
		failures: [ 'Playground boot failed: did not boot' ],
		checks: {},
	} );
} );
