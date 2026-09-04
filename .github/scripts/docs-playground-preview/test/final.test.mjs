import assert from 'node:assert/strict';
import { mkdir, mkdtemp, open, readFile, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import { createFinalBlueprint, packageFinalSnapshot } from '../lib/final.mjs';

/**
 * @param {string} cacheDirectory
 */
function inputs( cacheDirectory, snapshotBytes = 1024 ) {
	return {
		cacheDirectory,
		wordpress: { version: '7.2-beta1' },
		dependencies: {
			playground: {
				blueprintSchema:
					'https://playground.wordpress.net/blueprint-schema.json',
				phpVersion: '8.4',
			},
			limits: { snapshotBytes },
		},
	};
}

test( 'the final Blueprint restores the base before the complete import', () => {
	const blueprint = createFinalBlueprint( inputs( '/cache' ) );
	assert.equal( blueprint.landingPage, '/reference/' );
	assert.equal( blueprint.login, false );
	assert.deepEqual( blueprint.features, { networking: false } );
	/** @type {any[]} */
	const steps = blueprint.steps;
	assert.deepEqual(
		steps.map( ( step ) => step.step ),
		[
			'unzip',
			'mkdir',
			'mkdir',
			'writeFile',
			'writeFile',
			'wp-cli',
			'writeFile',
			'wp-cli',
			'rmdir',
			'writeFile',
			'defineWpConfigConsts',
		]
	);
	assert.equal( blueprint.steps[ 0 ].extractToPath, '/' );
	assert.equal( blueprint.steps[ 1 ].path, '/tmp/docs-preview-version' );
	assert.equal(
		blueprint.steps[ 2 ].path,
		'/tmp/docs-preview-version/wp-includes'
	);
	assert.equal(
		blueprint.steps[ 4 ].path,
		'/tmp/docs-preview-version/wp-includes/version.php'
	);
	assert.equal( blueprint.steps[ 4 ].data.path, 'safe-version.php' );
	assert.match( blueprint.steps[ 5 ].command, /parser import.+--quick/ );
	assert.equal(
		blueprint.steps.at( -3 ).path,
		'/wordpress/wp-content/plugins/phpdoc-parser'
	);
	assert.equal(
		blueprint.steps.at( -2 ).path,
		'/wordpress/wp-content/mu-plugins/001-docs-preview-runtime.php'
	);
	assert.deepEqual( blueprint.steps.at( -1 ), {
		step: 'defineWpConfigConsts',
		method: 'rewrite-wp-config',
		consts: {
			DISABLE_WP_CRON: true,
			AUTOMATIC_UPDATER_DISABLED: true,
			WP_AUTO_UPDATE_CORE: false,
			DISALLOW_FILE_MODS: true,
		},
	} );
} );

async function fixture( snapshotBytes = 1024 ) {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-final-' )
	);
	const cache = path.join( root, 'cache' );
	await mkdir( cache );
	await writeFile( path.join( cache, 'base.zip' ), 'base' );
	const referenceJson = path.join( root, 'reference.json' );
	await writeFile( referenceJson, '[]' );
	return {
		root,
		resolved: inputs( cache, snapshotBytes ),
		referenceJson,
		output: path.join( root, 'output/snapshot.zip' ),
	};
}

test( 'final packaging returns publisher metadata for a bounded snapshot', async () => {
	const current = await fixture();
	/** @type {any} */
	let invocation;
	const buildSnapshotImplementation =
		/** @param {string} command @param {Record<string, any>} options */ async (
			command,
			options
		) => {
			invocation = { command, options };
			await writeFile( current.output, 'snapshot' );
		};
	const snapshot = await packageFinalSnapshot( current.resolved, {
		workDirectory: path.join( current.root, 'work' ),
		output: current.output,
		referenceJson: current.referenceJson,
		stagedSource: '/source',
		playgroundCli: '/tools/wp-playground-cli',
		provenance: {
			sourceRepository: 'example/wordpress-develop',
			sourceSha: 'a'.repeat( 40 ),
			generationTimestamp: '2026-08-09T12:34:56.000Z',
			runUrl: 'https://github.com/example/wordpress-develop/actions/runs/123',
		},
		buildSnapshotImplementation,
	} );
	assert.equal( snapshot.filename, 'snapshot.zip' );
	assert.equal( snapshot.bytes, 8 );
	assert.match( snapshot.sha256, /^[0-9a-f]{64}$/ );
	assert.equal( invocation.command, '/tools/wp-playground-cli' );
	assert.deepEqual( invocation.options.mount, [
		{ hostPath: '/source', vfsPath: '/tmp/docs-preview-source' },
	] );
	assert.equal( invocation.options.php, '8.4' );
	assert.equal( invocation.options.wp, '7.2-beta1' );
	const blueprint = JSON.parse(
		await readFile(
			path.join( current.root, 'work/final-blueprint.json' ),
			'utf8'
		)
	);
	assert.equal( blueprint.steps[ 0 ].zipFile.path, 'base.zip' );
} );

test( 'the 100 MiB snapshot boundary always fails closed', async () => {
	const current = await fixture( 8 );
	const buildSnapshotImplementation = async () => {
		const output = await open( current.output, 'w' );
		await output.truncate( 9 );
		await output.close();
	};
	await assert.rejects(
		packageFinalSnapshot( current.resolved, {
			workDirectory: path.join( current.root, 'work' ),
			output: current.output,
			referenceJson: current.referenceJson,
			stagedSource: '/source',
			playgroundCli: '/tools/wp-playground-cli',
			provenance: {
				sourceRepository: 'example/wordpress-develop',
				sourceSha: 'a'.repeat( 40 ),
				generationTimestamp: '2026-08-09T12:34:56.000Z',
				runUrl: 'https://github.com/example/wordpress-develop/actions/runs/123',
			},
			buildSnapshotImplementation,
		} ),
		/exceeds 100 MiB/
	);
} );
