import assert from 'node:assert/strict';
import { mkdtemp, readFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import {
	buildCodeReferencePreview,
	createBuildMetadata,
	createFailureMetadata,
	parseArguments,
	verifyToolchain,
} from '../build.mjs';

const SHA = 'a'.repeat( 40 );

/**
 * @param {string} cacheDirectory
 */
function resolved( cacheDirectory ) {
	return {
		schemaVersion: 1,
		cacheKey: 'docs-preview-base-v1-test',
		cacheDirectory,
		cacheInputs: { dependencyDigest: 'b'.repeat( 64 ) },
		dependencies: {
			toolchain: { nodeVersion: '20.20.2', npmVersion: '10.8.2' },
			playground: { phpVersion: '8.4' },
			validation: {
				minimumSymbols: {
					classes: 1,
					methods: 1,
					functions: 1,
					hooks: 1,
					filters: 1,
				},
			},
		},
		wordpress: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
	};
}

/**
 * @param {string[]} failures
 */
function parser( failures = [] ) {
	return {
		sourceFiles: 2,
		records: 1,
		counts: { classes: 1, methods: 1, functions: 1, hooks: 1, filters: 1 },
		failures,
	};
}

test( 'arguments recognize enforcement only at the exact true value', () => {
	assert.equal(
		parseArguments( [], { DOCS_PREVIEW_ENFORCE: 'true' } ).enforce,
		true
	);
	assert.equal(
		parseArguments( [], { DOCS_PREVIEW_ENFORCE: 'TRUE' } ).enforce,
		false
	);
	assert.throws(
		() => parseArguments( [ '--unknown', 'value' ] ),
		/Unknown option/
	);
} );

test( 'the cache cannot claim a toolchain version that did not run', async () => {
	/** @type {any[]} */
	const commands = [];
	await verifyToolchain( resolved( '/cache' ), {
		nodeVersion: '20.20.2',
		runImplementation: /** @param {string} command */ async ( command ) => {
			commands.push( command );
			return { stdout: command === 'npm' ? '10.8.2\n' : '8.4' };
		},
	} );
	assert.deepEqual( commands, [ 'npm', 'php' ] );
	await assert.rejects(
		verifyToolchain( resolved( '/cache' ), {
			nodeVersion: '22.23.2',
		} ),
		/Node 20\.20\.2 is required/
	);
} );

test( 'build metadata contains the complete publisher handoff identity', () => {
	const metadata = createBuildMetadata(
		{
			sourceRepository: 'example/wordpress-develop',
			pullRequestNumber: 12,
			sourceSha: SHA,
			workflowRunId: '123',
			workflowRunAttempt: 2,
			runUrl: 'https://github.com/example/wordpress-develop/actions/runs/123',
		},
		resolved( '/cache' ),
		{ cacheHit: true },
		parser(),
		{ filename: 'snapshot.zip', bytes: 42, sha256: 'c'.repeat( 64 ) },
		{ failures: [], checks: { index: 200 } },
		'2026-08-09T12:34:56.000Z'
	);
	assert.equal( metadata.schemaVersion, 1 );
	assert.equal( metadata.sourceSha, SHA );
	assert.equal( metadata.resolvedWordPressBeta.version, '7.2-beta1' );
	assert.equal( metadata.phpVersion, '8.4' );
	assert.equal( metadata.dependencyManifestDigest, 'b'.repeat( 64 ) );
	assert.equal( metadata.snapshotFilename, 'snapshot.zip' );
	assert.equal( metadata.snapshotBytes, 42 );
	assert.equal( metadata.snapshotSha256, 'c'.repeat( 64 ) );
	assert.equal( metadata.buildStatus, 'success' );
	assert.equal( metadata.validationStatus, 'passed' );
	assert.deepEqual( metadata.behavior.checks, { index: 200 } );
	assert.equal( metadata.generationTimestamp, '2026-08-09T12:34:56.000Z' );
} );

test( 'failed build metadata retains the available trusted handoff identity', () => {
	const metadata = createFailureMetadata(
		{
			sourceRepository: 'example/wordpress-develop',
			pullRequestNumber: 12,
			sourceSha: SHA,
			workflowRunId: '123',
			workflowRunAttempt: 2,
			runUrl: 'https://github.com/example/wordpress-develop/actions/runs/123',
		},
		resolved( '/cache' ),
		new Error( 'import broke' ),
		'2026-08-09T12:34:56.000Z'
	);
	assert.equal( metadata.buildStatus, 'failed' );
	assert.equal( metadata.validationStatus, 'not-run' );
	assert.equal( metadata.sourceSha, SHA );
	assert.equal( metadata.resolvedWordPressBeta.version, '7.2-beta1' );
	assert.equal( metadata.snapshotFilename, null );
	assert.equal( metadata.buildError, 'import broke' );
} );

test( 'resolve-only records the exact cache inputs without building', async () => {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-resolve-' )
	);
	const expected = resolved( path.join( root, 'cache/exact' ) );
	/** @type {any} */
	let written;
	const result = await buildCodeReferencePreview(
		{
			source: process.cwd(),
			sourceRepository: 'example/wordpress-develop',
			sourceSha: SHA,
			cacheRoot: path.join( root, 'cache' ),
			workRoot: path.join( root, 'work' ),
			resolveOnly: true,
		},
		{
			resolveBuildInputs: async () => expected,
			verifyToolchain: async () => {},
			writeResolvedInputs:
				/** @param {Record<string, any>} inputs @param {string} filename */ async (
					inputs,
					filename
				) => {
					written = { inputs, filename };
				},
		}
	);
	assert.equal( result.inputs, expected );
	assert.equal( written.inputs, expected );
	assert.equal(
		written.filename,
		path.join( root, 'work/resolved-inputs.json' )
	);
} );

/**
 * @param {string[]} failures
 */
async function fixture( failures = [], enforce = false ) {
	const root = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-build-' )
	);
	const cache = path.join( root, 'cache/exact' );
	/** @type {any[]} */
	const calls = [];
	/** @type {Record<string, any>} */
	const overrides = {
		run: async () => {},
		verifyToolchain: async () => {},
		readResolvedInputs: async () => resolved( cache ),
		ensureInvariantBase: async () => {
			calls.push( 'base' );
			return { cacheHit: true };
		},
		generateParserJson: /** @param {Record<string, any>} options */ async (
			options
		) => {
			calls.push( 'parser' );
			assert.equal( options.parser, path.join( cache, 'parser' ) );
			return parser( failures );
		},
		packageFinalSnapshot: async () => {
			calls.push( 'package' );
			return {
				filename: 'snapshot.zip',
				bytes: 42,
				sha256: 'c'.repeat( 64 ),
			};
		},
		validateSnapshot: async () => {
			calls.push( 'validate' );
			return { failures: [], checks: { index: 200 } };
		},
	};
	const options = {
		source: process.cwd(),
		sourceRepository: 'example/wordpress-develop',
		sourceSha: SHA,
		resolvedInputs: path.join( root, 'inputs.json' ),
		workRoot: path.join( root, 'work' ),
		output: path.join( root, 'snapshot.zip' ),
		metadata: path.join( root, 'build.json' ),
		enforce,
	};
	return { root, calls, overrides, options };
}

test( 'one command builds the base, parser data, package, and handoff in order', async () => {
	const current = await fixture();
	const result = await buildCodeReferencePreview(
		current.options,
		current.overrides
	);
	assert.deepEqual( current.calls, [
		'base',
		'parser',
		'package',
		'validate',
	] );
	assert.equal( result.metadata.validationStatus, 'passed' );
	const written = JSON.parse(
		await readFile( path.join( current.root, 'build.json' ), 'utf8' )
	);
	assert.equal( written.snapshotSha256, 'c'.repeat( 64 ) );
} );

test( 'fatal packaging writes failed terminal metadata and still rejects', async () => {
	const current = await fixture();
	current.overrides.packageFinalSnapshot = async () => {
		throw new Error( 'snapshot is oversized' );
	};
	await assert.rejects(
		buildCodeReferencePreview( current.options, current.overrides ),
		/snapshot is oversized/
	);
	const written = JSON.parse(
		await readFile( path.join( current.root, 'build.json' ), 'utf8' )
	);
	assert.equal( written.buildStatus, 'failed' );
	assert.equal( written.sourceSha, SHA );
	assert.equal( written.snapshotFilename, null );
	assert.equal( written.buildError, 'snapshot is oversized' );
} );

test( 'advisory failures are recorded without rejecting the build', async () => {
	const current = await fixture( [ 'catastrophic undercount' ] );
	const result = await buildCodeReferencePreview(
		current.options,
		current.overrides
	);
	assert.equal( result.metadata.validationStatus, 'failed' );
	assert.deepEqual( result.metadata.validationFailures, [
		'catastrophic undercount',
	] );
} );

test( 'behavioral failures prevent passed metadata in advisory mode', async () => {
	const current = await fixture();
	current.overrides.validateSnapshot = async () => ( {
		failures: [ 'class route returned HTTP 404.' ],
		checks: { class: 404 },
	} );
	const result = await buildCodeReferencePreview(
		current.options,
		current.overrides
	);
	assert.equal( result.metadata.validationStatus, 'failed' );
	assert.deepEqual( result.metadata.validationFailures, [
		'class route returned HTTP 404.',
	] );
	assert.deepEqual( result.metadata.behavior.checks, { class: 404 } );
} );

test( 'enforcement rejects only after failed validation metadata is written', async () => {
	const current = await fixture( [ 'catastrophic undercount' ], true );
	await assert.rejects(
		buildCodeReferencePreview( current.options, current.overrides ),
		/catastrophic undercount/
	);
	const written = JSON.parse(
		await readFile( path.join( current.root, 'build.json' ), 'utf8' )
	);
	assert.equal( written.validationStatus, 'failed' );
} );
