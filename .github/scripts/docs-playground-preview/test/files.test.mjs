import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, symlink, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import {
	acquireRepositories,
	digestTree,
	exists,
	stageCorePhp,
} from '../lib/files.mjs';
import { resolveBuildInputs } from '../lib/inputs.mjs';
import { run } from '../lib/process.mjs';

const repositoryRoot = path.resolve(
	path.dirname( new URL( import.meta.url ).pathname ),
	'../../../..'
);

test( 'run captures output and reports ordinary child failure', async () => {
	const result = await run(
		process.execPath,
		[ '-e', 'process.stdout.write("ready")' ],
		{ capture: true, quiet: true }
	);
	assert.equal( result.stdout, 'ready' );
	await assert.rejects(
		run(
			process.execPath,
			[ '-e', 'process.stderr.write("broken");process.exit(2)' ],
			{
				capture: true,
				quiet: true,
			}
		),
		/broken/
	);
} );

test( 'stageCorePhp copies only eligible Core PHP source', async () => {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-source-' )
	);
	const source = path.join( root, 'src' );
	for ( const relative of [
		'wp-includes/version.php',
		'wp-admin/admin.php',
		'wp-content/plugins/hello.php',
		'wp-content/themes/twentytwenty/functions.php',
	] ) {
		await mkdir( path.dirname( path.join( source, relative ) ), {
			recursive: true,
		} );
		await writeFile(
			path.join( source, relative ),
			`<?php // ${ relative }`
		);
	}
	await writeFile( path.join( source, 'readme.html' ), 'not parser input' );
	await symlink(
		path.join( source, 'wp-admin/admin.php' ),
		path.join( source, 'wp-admin/linked.php' )
	);

	const destination = path.join( root, 'staged' );
	const result = await stageCorePhp( root, destination );
	assert.equal( result.files, 2 );
	assert.equal(
		await readFile(
			path.join( destination, 'wp-admin/admin.php' ),
			'utf8'
		),
		'<?php // wp-admin/admin.php'
	);
	assert.equal(
		await exists(
			path.join( destination, 'wp-content/plugins/hello.php' )
		),
		false
	);
	assert.equal(
		await exists( path.join( destination, 'wp-admin/linked.php' ) ),
		false
	);
} );

test( 'digestTree reflects names and contents but ignores excluded files', async () => {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-digest-' )
	);
	await writeFile( path.join( root, 'a' ), 'one' );
	await writeFile( path.join( root, 'ignored' ), 'first' );
	const first = await digestTree(
		root,
		( relative ) => relative !== 'ignored'
	);
	await writeFile( path.join( root, 'ignored' ), 'second' );
	assert.equal(
		await digestTree( root, ( relative ) => relative !== 'ignored' ),
		first
	);
	await writeFile( path.join( root, 'a' ), 'two' );
	assert.notEqual( await digestTree( root ), first );
} );

test( 'acquireRepositories uses Git with each validated full commit', async () => {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-repos-' )
	);
	/** @type {any[]} */
	const calls = [];
	const fakeRun =
		/** @param {string} command @param {string[]} args @param {Record<string, any>} [options] */ async (
			command,
			args,
			options
		) => {
			calls.push( { command, args, options } );
			if ( args[ 0 ] === 'clone' ) {
				const target = args.at( -1 );
				assert.ok( target );
				await mkdir( path.join( target, '.git' ), {
					recursive: true,
				} );
			}
		};
	const repositories = {
		parser: {
			repository: 'WordPress/phpdoc-parser',
			commit: 'a'.repeat( 40 ),
			path: '.',
		},
	};
	const acquired = await acquireRepositories( repositories, root, fakeRun );
	assert.equal( acquired.parser, path.join( root, 'parser' ) );
	assert.deepEqual( calls[ 0 ].args.slice( 0, 3 ), [
		'clone',
		'--quiet',
		'--no-checkout',
	] );
	assert.deepEqual( calls[ 1 ].args, [
		'fetch',
		'--quiet',
		'--depth=1',
		'origin',
		'a'.repeat( 40 ),
	] );
	assert.equal( calls[ 2 ].args.at( -1 ), 'a'.repeat( 40 ) );

	calls.length = 0;
	repositories.parser.commit = 'b'.repeat( 40 );
	await acquireRepositories( repositories, root, fakeRun );
	assert.equal( calls.length, 2 );
	assert.equal( calls[ 0 ].args[ 0 ], 'fetch' );
	assert.equal( calls[ 0 ].args.at( -1 ), 'b'.repeat( 40 ) );
	assert.equal( calls[ 1 ].args.at( -1 ), 'b'.repeat( 40 ) );
} );

test( 'resolveBuildInputs binds the concrete beta and runner to an exact cache', async () => {
	const cacheRoot = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-cache-' )
	);
	const fetchImplementation = async () => ( {
		ok: true,
		json: async () => ( {
			offers: [
				{
					response: 'development',
					version: '7.2-beta1',
					download:
						'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
				},
			],
		} ),
	} );
	const inputs = await resolveBuildInputs( {
		repositoryRoot,
		cacheRoot,
		platform: 'linux',
		architecture: 'x64',
		runnerImage: 'ubuntu-24.04',
		fetchImplementation,
	} );
	assert.equal( inputs.wordpress.version, '7.2-beta1' );
	assert.match( inputs.cacheKey, /^docs-preview-base-v1-[0-9a-f]{64}$/ );
	assert.equal(
		inputs.cacheDirectory,
		path.join( cacheRoot, inputs.cacheKey )
	);
} );
