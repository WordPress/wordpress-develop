import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import { generateParserJson, inspectParserRecords } from '../lib/parser.mjs';

/** @returns {any[]} */
function records() {
	return [
		{
			path: 'wp-includes/example.php',
			functions: [
				{
					name: 'example',
					hooks: [
						{ type: 'filter' },
						{ type: 'action_deprecated' },
					],
				},
			],
			classes: [
				{
					name: 'Example',
					methods: [
						{
							name: 'first',
							hooks: [ { type: 'filter_reference' } ],
						},
						{ name: 'second', hooks: [] },
					],
				},
			],
			hooks: [ { type: 'action' } ],
		},
	];
}

test( 'parser inspection counts every supported reference type', () => {
	const parsed = records();
	const result = inspectParserRecords( parsed, {
		classes: 1,
		methods: 2,
		functions: 1,
		hooks: 2,
		filters: 2,
	} );
	assert.deepEqual( result, {
		records: 1,
		counts: {
			classes: 1,
			methods: 2,
			functions: 1,
			hooks: 2,
			filters: 2,
		},
		failures: [],
	} );
	assert.equal( parsed[ 0 ].root, '/tmp/docs-preview-source' );
} );

test( 'sanity floors report catastrophic undercounts by symbol type', () => {
	const result = inspectParserRecords( records(), {
		classes: 2,
		methods: 3,
		functions: 2,
		hooks: 3,
		filters: 3,
	} );
	assert.equal( result.failures.length, 5 );
	assert.match( result.failures[ 0 ], /1 classes; expected at least 2/ );
} );

test( 'an empty parser array is an advisory validation failure', () => {
	const result = inspectParserRecords( [], {
		classes: 1,
		methods: 1,
		functions: 1,
		hooks: 1,
		filters: 1,
	} );
	assert.deepEqual( result.counts, {
		classes: 0,
		methods: 0,
		functions: 0,
		hooks: 0,
		filters: 0,
	} );
	assert.equal( result.failures.length, 6 );
	assert.equal( result.failures[ 0 ], 'Parser produced no file records.' );
} );

test( 'parser inspection rejects excluded bundled extension source', () => {
	const parsed = records();
	parsed[ 0 ].path = 'wp-content/plugins/hello.php';
	assert.throws(
		() => inspectParserRecords( parsed, {} ),
		/contains excluded source/
	);
} );

test( 'the importer never receives pull-request PHP as its version include', () => {
	const parsed = records();
	parsed.push( {
		path: 'wp-includes/version.php',
		functions: [],
		classes: [],
		hooks: [],
	} );
	inspectParserRecords( parsed, {} );
	assert.equal( parsed[ 0 ].root, '/tmp/docs-preview-source' );
	assert.equal( parsed[ 1 ].root, '/tmp/docs-preview-version' );
} );

test( 'generateParserJson stages PHP, invokes the pinned parser, and normalizes output', async () => {
	const temporary = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-parser-' )
	);
	const source = path.join( temporary, 'wordpress/src' );
	await mkdir( path.join( source, 'wp-includes' ), { recursive: true } );
	await writeFile( path.join( source, 'wp-includes/version.php' ), '<?php' );
	await writeFile( path.join( source, 'wp-includes/example.php' ), '<?php' );
	const output = path.join( temporary, 'output/reference.json' );
	/** @type {any} */
	let invocation;
	const runImplementation =
		/** @param {string} command @param {string[]} args */ async (
			command,
			args
		) => {
			invocation = { command, args };
			await writeFile( output, JSON.stringify( records() ) );
		};
	const result = await generateParserJson( {
		source: path.dirname( source ),
		stagedSource: path.join( temporary, 'staged' ),
		parser: '/cache/parser',
		output,
		minimumSymbols: {
			classes: 1,
			methods: 2,
			functions: 1,
			hooks: 2,
			filters: 2,
		},
		runImplementation,
	} );
	assert.equal( result.sourceFiles, 2 );
	assert.equal( invocation.command, 'php' );
	assert.equal( invocation.args.at( -1 ), output );
	const normalized = JSON.parse( await readFile( output, 'utf8' ) );
	assert.equal( normalized[ 0 ].root, '/tmp/docs-preview-source' );
} );
