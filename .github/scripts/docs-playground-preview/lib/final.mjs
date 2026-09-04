import { copyFile, mkdir, rm, stat, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

import { sha256File } from './files.mjs';
import { buildSnapshot } from './playground.mjs';
import { renderRuntimePlugin } from './runtime.mjs';

const LIBRARY_ROOT = path.dirname( fileURLToPath( import.meta.url ) );
const PHP_ROOT = path.resolve( LIBRARY_ROOT, '../php' );

/**
 * @param {string} resourcePath
 */
function bundled( resourcePath ) {
	return { resource: 'bundled', path: resourcePath };
}

/**
 * @param {Record<string, any>} inputs
 * @returns {Record<string, any>}
 */
export function createFinalBlueprint( inputs ) {
	return {
		$schema: inputs.dependencies.playground.blueprintSchema,
		meta: {
			title: 'WordPress Core Code Reference preview',
			author: 'WordPress',
			description:
				'Complete Core Code Reference imported into the cached site.',
		},
		preferredVersions: {
			php: inputs.dependencies.playground.phpVersion,
			wp: inputs.wordpress.version,
		},
		landingPage: '/reference/',
		login: false,
		features: { networking: false },
		extraLibraries: [ 'wp-cli' ],
		steps: [
			{
				step: 'unzip',
				zipFile: bundled( 'base.zip' ),
				extractToPath: '/',
			},
			{
				step: 'mkdir',
				path: '/tmp/docs-preview-version',
			},
			{
				step: 'mkdir',
				path: '/tmp/docs-preview-version/wp-includes',
			},
			{
				step: 'writeFile',
				path: '/tmp/reference.json',
				data: bundled( 'reference.json' ),
			},
			{
				step: 'writeFile',
				path: '/tmp/docs-preview-version/wp-includes/version.php',
				data: bundled( 'safe-version.php' ),
			},
			{
				step: 'wp-cli',
				command:
					'wp parser import /tmp/reference.json --quick --user=1',
			},
			{
				step: 'writeFile',
				path: '/tmp/docs-preview-complete-import.php',
				data: bundled( 'complete-import.php' ),
			},
			{
				step: 'wp-cli',
				command: 'wp eval-file /tmp/docs-preview-complete-import.php',
			},
			{
				step: 'rmdir',
				path: '/wordpress/wp-content/plugins/phpdoc-parser',
			},
			{
				step: 'writeFile',
				path: '/wordpress/wp-content/mu-plugins/001-docs-preview-runtime.php',
				data: bundled( 'runtime.php' ),
			},
			{
				step: 'defineWpConfigConsts',
				method: 'rewrite-wp-config',
				consts: {
					DISABLE_WP_CRON: true,
					AUTOMATIC_UPDATER_DISABLED: true,
					WP_AUTO_UPDATE_CORE: false,
					DISALLOW_FILE_MODS: true,
				},
			},
		],
	};
}

/**
 * @param {Record<string, any>} inputs
 * @param {Record<string, any>} options
 */
export async function packageFinalSnapshot( inputs, options ) {
	const work = path.resolve( options.workDirectory );
	const output = path.resolve( options.output );
	await rm( work, { recursive: true, force: true } );
	await mkdir( work, { recursive: true } );
	await mkdir( path.dirname( output ), { recursive: true } );
	await copyFile(
		path.join( inputs.cacheDirectory, 'base.zip' ),
		path.join( work, 'base.zip' )
	);
	await copyFile(
		options.referenceJson,
		path.join( work, 'reference.json' )
	);
	await copyFile(
		path.join( PHP_ROOT, 'complete-import.php' ),
		path.join( work, 'complete-import.php' )
	);
	await copyFile(
		path.join( PHP_ROOT, 'safe-version.php' ),
		path.join( work, 'safe-version.php' )
	);
	await writeFile(
		path.join( work, 'runtime.php' ),
		renderRuntimePlugin( options.provenance )
	);
	const blueprint = path.join( work, 'final-blueprint.json' );
	await writeFile(
		blueprint,
		`${ JSON.stringify( createFinalBlueprint( inputs ), null, 2 ) }\n`
	);
	await ( options.buildSnapshotImplementation || buildSnapshot )(
		options.playgroundCli,
		{
			php: inputs.dependencies.playground.phpVersion,
			wp: inputs.wordpress.version,
			blueprint,
			'blueprint-may-read-adjacent-files': true,
			mount: [
				{
					hostPath: path.resolve( options.stagedSource ),
					vfsPath: '/tmp/docs-preview-source',
				},
			],
			outfile: output,
			verbosity: 'normal',
		}
	);
	const snapshot = await stat( output );
	if ( snapshot.size > inputs.dependencies.limits.snapshotBytes ) {
		throw new Error(
			`Snapshot exceeds 100 MiB: ${ snapshot.size } > ${ inputs.dependencies.limits.snapshotBytes }.`
		);
	}
	return {
		filename: path.basename( output ),
		bytes: snapshot.size,
		sha256: await sha256File( output ),
	};
}
