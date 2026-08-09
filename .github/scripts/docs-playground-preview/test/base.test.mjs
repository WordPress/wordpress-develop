import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import {
	createInvariantBaseBlueprint,
	dependencyBuildPlan,
	ensureInvariantBase,
	prunePreviewFonts,
} from '../lib/base.mjs';
import { zipDirectory } from '../lib/archive.mjs';
import { run } from '../lib/process.mjs';

function inputs( cacheDirectory ) {
	return {
		cacheKey: 'docs-preview-base-v1-' + 'a'.repeat( 64 ),
		cacheDirectory,
		cacheInputs: {
			dependencyDigest: 'b'.repeat( 64 ),
			harnessDigest: 'c'.repeat( 64 ),
		},
		wordpress: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
		dependencies: {
			playground: {
				blueprintSchema:
					'https://playground.wordpress.net/blueprint-schema.json',
				phpVersion: '8.4',
			},
		},
	};
}

test( 'the invariant Blueprint installs dependencies without importing source', () => {
	const blueprint = createInvariantBaseBlueprint( inputs( '/cache' ) );
	assert.deepEqual( blueprint.preferredVersions, {
		php: '8.4',
		wp: '7.2-beta1',
	} );
	assert.deepEqual( blueprint.features, { networking: false } );
	assert.equal( blueprint.login, false );
	assert.equal(
		blueprint.steps.some( ( step ) =>
			/import|reference\.json/.test( JSON.stringify( step ) )
		),
		false
	);
	assert.deepEqual(
		blueprint.steps
			.filter( ( step ) => step.step === 'installTheme' )
			.map( ( step ) => step.themeData.path ),
		[ 'bundles/wporg-parent-2021.zip', 'bundles/wporg-developer-2023.zip' ]
	);
} );

test( 'dependency build plan uses pinned harness tools and upstream locks', () => {
	const roots = {
		phpdocParser: '/repos/parser',
		wporgParent2021: '/repos/parent',
		wporgDeveloperTheme: '/repos/developer/theme',
		wporgMuPlugins: '/repos/mu',
		postsToPosts: '/repos/p2p',
		codeSyntaxBlock: '/repos/syntax',
	};
	const plan = dependencyBuildPlan( roots, '/tools/composer.phar' );
	assert.equal( plan.length, 10 );
	assert.deepEqual( plan[ 0 ].args.slice( 0, 2 ), [
		'/tools/composer.phar',
		'install',
	] );
	assert.match( plan[ 1 ].command, /node_modules\/\.bin\/yarn$/ );
	assert.deepEqual( plan[ 1 ].args, [
		'install',
		'--frozen-lockfile',
		'--ignore-scripts',
	] );
	assert.match( plan[ 3 ].command, /node_modules\/\.bin\/wp-scripts$/ );
	assert.deepEqual( plan[ 4 ].args.slice( 0, 2 ), [
		'ci',
		'--ignore-scripts',
	] );
} );

test( 'the invariant base marker is written last and enables exact reuse', async () => {
	const cache = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-base-' )
	);
	const resolved = inputs( path.join( cache, 'entry' ) );
	let builds = 0;
	const buildImplementation = async ( current ) => {
		builds++;
		await writeFile(
			path.join( current.cacheDirectory, 'base.zip' ),
			'base'
		);
		await mkdir( path.join( current.cacheDirectory, 'parser' ) );
		await writeFile(
			path.join(
				current.cacheDirectory,
				'parser/generate-json-manually.php'
			),
			'<?php'
		);
	};
	const cold = await ensureInvariantBase( resolved, { buildImplementation } );
	assert.equal( cold.cacheHit, false );
	assert.equal( builds, 1 );
	const marker = JSON.parse(
		await readFile(
			path.join( resolved.cacheDirectory, 'base.json' ),
			'utf8'
		)
	);
	assert.equal( marker.cacheKey, resolved.cacheKey );

	const warm = await ensureInvariantBase( resolved, { buildImplementation } );
	assert.equal( warm.cacheHit, true );
	assert.equal( builds, 1 );
} );

test( 'build-time policy does not apply final runtime file restrictions', async () => {
	const policy = await readFile(
		new URL( '../php/base-policy.php', import.meta.url ),
		'utf8'
	);
	assert.doesNotMatch(
		policy,
		/DISALLOW_FILE_MODS|AUTOMATIC_UPDATER_DISABLED/
	);
} );

test( 'dependency bundles have the requested install root and omit build tools', async () => {
	const temporary = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-bundle-' )
	);
	const source = path.join( temporary, 'source' );
	await mkdir( path.join( source, 'node_modules' ), { recursive: true } );
	await writeFile( path.join( source, 'plugin.php' ), '<?php' );
	await writeFile( path.join( source, 'node_modules/dependency' ), 'unused' );
	const bundle = path.join( temporary, 'plugin.zip' );
	await zipDirectory( source, bundle, 'plugin' );
	const listing = await run( 'unzip', [ '-Z1', bundle ], {
		capture: true,
		quiet: true,
	} );
	assert.match( listing.stdout, /^plugin\/plugin\.php$/m );
	assert.doesNotMatch( listing.stdout, /node_modules/ );
} );

test( 'unused CJK preview fonts are pruned before the size boundary', async () => {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-fonts-' )
	);
	const fonts = path.join( root, 'global-fonts' );
	await mkdir( path.join( fonts, 'NotoSerif' ), { recursive: true } );
	await writeFile( path.join( fonts, 'NotoSerif/font.woff2' ), 'large' );
	await writeFile(
		path.join( fonts, 'style.css' ),
		'@import "./NotoSerif/NotoSerifJP/style.css";\nbody { color: black; }\n'
	);
	await prunePreviewFonts( root );
	await assert.rejects(
		readFile( path.join( fonts, 'NotoSerif/font.woff2' ) )
	);
	assert.equal(
		await readFile( path.join( fonts, 'style.css' ), 'utf8' ),
		'body { color: black; }\n'
	);
} );
