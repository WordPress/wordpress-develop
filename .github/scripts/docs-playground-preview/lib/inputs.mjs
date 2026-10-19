import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

import {
	loadDependencies,
	makeBaseCacheKey,
	resolveWordPressBeta,
} from './config.mjs';
import { digestTree } from './files.mjs';

/**
 * @param {Record<string, any>} options
 */
export async function resolveBuildInputs( options ) {
	const repositoryRoot = path.resolve( options.repositoryRoot );
	const configRoot = path.join(
		repositoryRoot,
		'.github/docs-playground-preview'
	);
	const scriptsRoot = path.join(
		repositoryRoot,
		'.github/scripts/docs-playground-preview'
	);
	const { dependencies, digest: dependencyDigest } = await loadDependencies(
		path.join( configRoot, 'dependencies.json' )
	);
	const wordpress = await resolveWordPressBeta( options.fetchImplementation );
	const configDigest = await digestTree(
		configRoot,
		/**
		 * @param {string} relative
		 */
		( relative ) => ! relative.startsWith( 'node_modules/' )
	);
	const scriptsDigest = await digestTree(
		scriptsRoot,
		/**
		 * @param {string} relative
		 */
		( relative ) => ! relative.startsWith( 'test/' )
	);
	const harnessDigest = await digestTreePair( configDigest, scriptsDigest );
	const cacheInputs = {
		cacheSchemaVersion: dependencies.cacheSchemaVersion,
		platform: options.platform,
		architecture: options.architecture,
		runnerImage: options.runnerImage,
		phpVersion: dependencies.playground.phpVersion,
		wordpressVersion: wordpress.version,
		dependencyDigest,
		harnessDigest,
	};
	const cacheKey = makeBaseCacheKey( cacheInputs );
	return {
		schemaVersion: 1,
		cacheKey,
		cacheDirectory: path.join(
			path.resolve( options.cacheRoot ),
			cacheKey
		),
		cacheInputs,
		dependencies,
		wordpress,
	};
}

/**
 * @param {string} first
 * @param {string} second
 */
async function digestTreePair( first, second ) {
	return createHash( 'sha256' )
		.update( first )
		.update( second )
		.digest( 'hex' );
}

/**
 * @param {Record<string, any>} inputs
 * @param {string} filename
 */
export async function writeResolvedInputs( inputs, filename ) {
	await mkdir( path.dirname( filename ), { recursive: true } );
	await writeFile( filename, `${ JSON.stringify( inputs, null, 2 ) }\n` );
	if ( process.env.GITHUB_OUTPUT ) {
		await writeFile(
			process.env.GITHUB_OUTPUT,
			`cache-key=${ inputs.cacheKey }\ncache-directory=${ inputs.cacheDirectory }\n`,
			{ flag: 'a' }
		);
	}
}

/**
 * @param {string} filename
 */
export async function readResolvedInputs( filename ) {
	return JSON.parse( await readFile( filename, 'utf8' ) );
}
