#!/usr/bin/env node

import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

import { ensureInvariantBase } from './lib/base.mjs';
import { packageFinalSnapshot } from './lib/final.mjs';
import {
	readResolvedInputs,
	resolveBuildInputs,
	writeResolvedInputs,
} from './lib/inputs.mjs';
import { generateParserJson } from './lib/parser.mjs';
import { run } from './lib/process.mjs';
import { validateSnapshot } from './lib/validate.mjs';

const SCRIPT_ROOT = path.dirname( fileURLToPath( import.meta.url ) );
const REPOSITORY_ROOT = path.resolve( SCRIPT_ROOT, '../../..' );
const TOOLING_ROOT = path.join(
	REPOSITORY_ROOT,
	'.github/docs-playground-preview'
);
const FULL_COMMIT = /^[0-9a-f]{40}$/;
const REPOSITORY = /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/;

/**
 * @param {string[]} args
 * @param {number} index
 * @param {string} name
 */
function argumentValue( args, index, name ) {
	const value = args[ index + 1 ];
	if ( ! value || value.startsWith( '--' ) ) {
		throw new Error( `${ name } requires a value.` );
	}
	return value;
}

/**
 * @param {string[]} args
 */
export function parseArguments( args, environment = process.env ) {
	/** @type {Record<string, string | boolean>} */
	const values = {};
	for ( let index = 0; index < args.length; index++ ) {
		const name = args[ index ];
		if ( name === '--resolve-only' ) {
			values.resolveOnly = true;
			continue;
		}
		if ( ! name.startsWith( '--' ) ) {
			throw new Error( `Unexpected argument ${ name }.` );
		}
		const key = name
			.slice( 2 )
			.replace(
				/-([a-z])/g,
				/** @param {string} _ @param {string} letter */ ( _, letter ) =>
					letter.toUpperCase()
			);
		if (
			! [
				'source',
				'sourceRepository',
				'sourceSha',
				'pullRequest',
				'runId',
				'runAttempt',
				'runUrl',
				'runnerImage',
				'cacheRoot',
				'workRoot',
				'output',
				'metadata',
				'inputsOutput',
				'resolvedInputs',
			].includes( key )
		) {
			throw new Error( `Unknown option ${ name }.` );
		}
		values[ key ] = argumentValue( args, index, name );
		index++;
	}
	return {
		...values,
		enforce: environment.DOCS_PREVIEW_ENFORCE === 'true',
	};
}

/**
 * @param {unknown} value
 * @param {string} label
 */
function positiveInteger( value, label, nullable = true ) {
	if (
		( value === undefined || value === null || value === '' ) &&
		nullable
	) {
		return null;
	}
	const number = Number( value );
	if ( ! Number.isSafeInteger( number ) || number < 1 ) {
		throw new Error( `${ label } must be a positive integer.` );
	}
	return number;
}

/**
 * @param {string} remote
 */
function githubRepository( remote ) {
	const match = remote
		.trim()
		.match(
			/^(?:git@github\.com:|https:\/\/github\.com\/)([^/]+\/[^/]+?)(?:\.git)?$/
		);
	return match?.[ 1 ] || null;
}

/**
 * @param {string} source
 * @param {(...args: any[]) => any} runImplementation
 */
async function gitIdentity( source, runImplementation ) {
	const sha = await runImplementation( 'git', [ 'rev-parse', 'HEAD' ], {
		cwd: source,
		capture: true,
		quiet: true,
		label: 'read source commit',
	} );
	const remote = await runImplementation(
		'git',
		[ 'config', '--get', 'remote.origin.url' ],
		{
			cwd: source,
			capture: true,
			quiet: true,
			label: 'read source repository',
		}
	);
	return {
		sha: sha.stdout.trim(),
		repository: githubRepository( remote.stdout ),
	};
}

/**
 * @param {Record<string, any>} raw
 * @param {Record<string, any>} implementations
 * @returns {Promise<Record<string, any>>}
 */
async function normalizeOptions( raw, implementations ) {
	const workspace = path.join(
		REPOSITORY_ROOT,
		'.cache/docs-playground-preview'
	);
	const source = path.resolve( raw.source || REPOSITORY_ROOT );
	let sourceSha = raw.sourceSha;
	let sourceRepository = raw.sourceRepository;
	if ( ! sourceSha || ! sourceRepository ) {
		const inferred = await gitIdentity( source, implementations.run );
		sourceSha ||= inferred.sha;
		sourceRepository ||= inferred.repository;
	}
	if ( ! FULL_COMMIT.test( sourceSha || '' ) ) {
		throw new Error( 'sourceSha must be a full lowercase commit hash.' );
	}
	if ( ! REPOSITORY.test( sourceRepository || '' ) ) {
		throw new Error(
			'sourceRepository must be a GitHub owner/repository.'
		);
	}
	const workRoot = path.resolve(
		raw.workRoot || path.join( workspace, 'work' )
	);
	const outputRoot = path.join( workspace, 'output' );
	return {
		...raw,
		source,
		sourceSha,
		sourceRepository,
		pullRequestNumber: positiveInteger( raw.pullRequest, 'pullRequest' ),
		workflowRunId: raw.runId || null,
		workflowRunAttempt: positiveInteger( raw.runAttempt, 'runAttempt' ),
		runUrl: raw.runUrl || null,
		runnerImage:
			raw.runnerImage || `${ process.platform }-${ process.arch }`,
		cacheRoot: path.resolve(
			raw.cacheRoot || path.join( workspace, 'cache' )
		),
		workRoot,
		output: path.resolve(
			raw.output ||
				path.join( outputRoot, `code-reference-${ sourceSha }.zip` )
		),
		metadata: path.resolve(
			raw.metadata || path.join( outputRoot, 'build.json' )
		),
		inputsOutput: path.resolve(
			raw.inputsOutput || path.join( workRoot, 'resolved-inputs.json' )
		),
	};
}

/**
 * @param {Record<string, any>} implementations
 */
async function ensureNodeTools( implementations ) {
	const playgroundCli = path.join(
		TOOLING_ROOT,
		`node_modules/.bin/wp-playground-cli${
			process.platform === 'win32' ? '.cmd' : ''
		}`
	);
	await implementations.run(
		'npm',
		[ 'ci', '--ignore-scripts', '--no-audit', '--no-fund' ],
		{ cwd: TOOLING_ROOT, label: 'install pinned preview tools' }
	);
	return playgroundCli;
}

/**
 * @param {Record<string, any>} inputs
 * @param {Record<string, any>} [options]
 */
export async function verifyToolchain( inputs, options = {} ) {
	const runImplementation = options.runImplementation || run;
	const expected = inputs.dependencies.toolchain;
	const nodeVersion = options.nodeVersion || process.versions.node;
	if ( nodeVersion !== expected.nodeVersion ) {
		throw new Error(
			`Node ${ expected.nodeVersion } is required; found ${ nodeVersion }.`
		);
	}
	const npm = await runImplementation( 'npm', [ '--version' ], {
		capture: true,
		quiet: true,
		label: 'read npm version',
	} );
	if ( npm.stdout.trim() !== expected.npmVersion ) {
		throw new Error(
			`npm ${
				expected.npmVersion
			} is required; found ${ npm.stdout.trim() }.`
		);
	}
	const php = await runImplementation(
		'php',
		[ '-r', 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' ],
		{
			capture: true,
			quiet: true,
			label: 'read PHP version',
		}
	);
	if ( php.stdout.trim() !== inputs.dependencies.playground.phpVersion ) {
		throw new Error(
			`PHP ${
				inputs.dependencies.playground.phpVersion
			} is required; found ${ php.stdout.trim() }.`
		);
	}
}

/**
 * @param {Record<string, any>} options
 * @param {Record<string, any>} inputs
 * @param {Record<string, any>} base
 * @param {Record<string, any>} parser
 * @param {Record<string, any>} snapshot
 * @param {Record<string, any>} validation
 * @param {string} generationTimestamp
 */
export function createBuildMetadata(
	options,
	inputs,
	base,
	parser,
	snapshot,
	validation,
	generationTimestamp
) {
	const validationFailures = [ ...parser.failures, ...validation.failures ];
	return {
		schemaVersion: 1,
		sourceRepository: options.sourceRepository,
		pullRequestNumber: options.pullRequestNumber,
		sourceSha: options.sourceSha,
		workflowRunId: options.workflowRunId,
		workflowRunAttempt: options.workflowRunAttempt,
		runUrl: options.runUrl,
		resolvedWordPressBeta: inputs.wordpress,
		phpVersion: inputs.dependencies.playground.phpVersion,
		dependencyManifestDigest: inputs.cacheInputs.dependencyDigest,
		snapshotFilename: snapshot.filename,
		snapshotBytes: snapshot.bytes,
		snapshotSha256: snapshot.sha256,
		buildStatus: 'success',
		validationStatus: validationFailures.length === 0 ? 'passed' : 'failed',
		validationFailures,
		parser: {
			sourceFiles: parser.sourceFiles,
			records: parser.records,
			counts: parser.counts,
		},
		behavior: { checks: validation.checks },
		cache: { key: inputs.cacheKey, hit: base.cacheHit },
		generationTimestamp,
	};
}

/**
 * @param {Record<string, any>} options
 * @param {Record<string, any>} inputs
 * @param {unknown} error
 * @param {string} generationTimestamp
 */
export function createFailureMetadata(
	options,
	inputs,
	error,
	generationTimestamp
) {
	return {
		schemaVersion: 1,
		sourceRepository: options.sourceRepository,
		pullRequestNumber: options.pullRequestNumber,
		sourceSha: options.sourceSha,
		workflowRunId: options.workflowRunId,
		workflowRunAttempt: options.workflowRunAttempt,
		runUrl: options.runUrl,
		resolvedWordPressBeta: inputs?.wordpress || null,
		phpVersion: inputs?.dependencies?.playground?.phpVersion || null,
		dependencyManifestDigest: inputs?.cacheInputs?.dependencyDigest || null,
		snapshotFilename: null,
		snapshotBytes: null,
		snapshotSha256: null,
		buildStatus: 'failed',
		validationStatus: 'not-run',
		validationFailures: [],
		generationTimestamp,
		buildError: error instanceof Error ? error.message : String( error ),
	};
}

/**
 * @param {string} filename
 * @param {unknown} value
 */
async function writeJson( filename, value ) {
	await mkdir( path.dirname( filename ), { recursive: true } );
	await writeFile( filename, `${ JSON.stringify( value, null, 2 ) }\n` );
}

const DEFAULT_IMPLEMENTATIONS = {
	run,
	resolveBuildInputs,
	readResolvedInputs,
	writeResolvedInputs,
	ensureInvariantBase,
	generateParserJson,
	packageFinalSnapshot,
	validateSnapshot,
	verifyToolchain,
};

/**
 * @param {Record<string, any>} rawOptions
 * @returns {Promise<Record<string, any>>}
 */
export async function buildCodeReferencePreview( rawOptions, overrides = {} ) {
	const implementations = { ...DEFAULT_IMPLEMENTATIONS, ...overrides };
	const options = await normalizeOptions( rawOptions, implementations );
	let generationTimestamp;
	let inputs;
	let result;
	try {
		inputs = options.resolvedInputs
			? await implementations.readResolvedInputs( options.resolvedInputs )
			: await implementations.resolveBuildInputs( {
					repositoryRoot: REPOSITORY_ROOT,
					cacheRoot: options.cacheRoot,
					platform: process.platform,
					architecture: process.arch,
					runnerImage: options.runnerImage,
			  } );
		await implementations.verifyToolchain( inputs, {
			runImplementation: implementations.run,
		} );
		if ( options.resolveOnly ) {
			await implementations.writeResolvedInputs(
				inputs,
				options.inputsOutput
			);
			return { inputs, options };
		}

		generationTimestamp = new Date().toISOString();
		const provenance = {
			sourceRepository: options.sourceRepository,
			sourceSha: options.sourceSha,
			generationTimestamp,
			runUrl: options.runUrl,
		};
		const playgroundCli = await ensureNodeTools( implementations );
		const base = await implementations.ensureInvariantBase( inputs, {
			playgroundCli,
		} );
		const parser = await implementations.generateParserJson( {
			source: options.source,
			stagedSource: path.join( options.workRoot, 'source' ),
			parser: path.join( inputs.cacheDirectory, 'parser' ),
			output: path.join( options.workRoot, 'reference.json' ),
			logFile: path.join( options.workRoot, 'parser.log' ),
			minimumSymbols: inputs.dependencies.validation.minimumSymbols,
		} );
		const snapshot = await implementations.packageFinalSnapshot( inputs, {
			workDirectory: path.join( options.workRoot, 'final' ),
			output: options.output,
			referenceJson: path.join( options.workRoot, 'reference.json' ),
			stagedSource: path.join( options.workRoot, 'source' ),
			playgroundCli,
			provenance,
		} );
		const validation = await implementations.validateSnapshot( inputs, {
			snapshot: options.output,
			workDirectory: path.join( options.workRoot, 'validation' ),
			playgroundCli,
			provenance,
			requireRunUrl: options.workflowRunId !== null,
		} );
		const metadata = createBuildMetadata(
			options,
			inputs,
			base,
			parser,
			snapshot,
			validation,
			generationTimestamp
		);
		await writeJson( options.metadata, metadata );
		result = {
			inputs,
			base,
			parser,
			snapshot,
			validation,
			metadata,
			options,
		};
	} catch ( error ) {
		try {
			await writeJson(
				options.metadata,
				createFailureMetadata(
					options,
					inputs,
					error,
					generationTimestamp || new Date().toISOString()
				)
			);
		} catch {
			// Preserve the build error when its terminal metadata cannot be written.
		}
		throw error;
	}
	const { metadata } = result;
	if ( metadata.validationStatus === 'failed' && options.enforce ) {
		throw new Error( metadata.validationFailures.join( '\n' ) );
	}
	return result;
}

async function main() {
	const result = await buildCodeReferencePreview(
		parseArguments( process.argv.slice( 2 ) )
	);
	for ( const failure of result.metadata?.validationFailures || [] ) {
		process.stderr.write( `::warning::${ failure }\n` );
	}
	process.stdout.write(
		`${ JSON.stringify(
			result.metadata || {
				cacheKey: result.inputs.cacheKey,
				resolvedWordPressBeta: result.inputs.wordpress,
			},
			null,
			2
		) }\n`
	);
}

if (
	import.meta.url === pathToFileURL( path.resolve( process.argv[ 1 ] ) ).href
) {
	main().catch( ( error ) => {
		process.stderr.write( `${ error.message }\n` );
		process.exitCode = 1;
	} );
}
