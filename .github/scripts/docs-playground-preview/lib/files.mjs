import { createHash } from 'node:crypto';
import { copyFile, mkdir, readdir, readFile, rm, stat } from 'node:fs/promises';
import path from 'node:path';

import { run } from './process.mjs';

const EXCLUDED_SOURCE_DIRECTORIES = new Set( [
	'wp-content/plugins',
	'wp-content/themes',
] );

/**
 * @param {string} filename
 */
export async function exists( filename ) {
	try {
		await stat( filename );
		return true;
	} catch ( error ) {
		if (
			error instanceof Error &&
			'code' in error &&
			error.code === 'ENOENT'
		) {
			return false;
		}
		throw error;
	}
}

/**
 * @param {string} candidate
 */
export async function findWordPressSourceRoot( candidate ) {
	const root = path.resolve( candidate );
	for ( const directory of [ root, path.join( root, 'src' ) ] ) {
		if (
			await exists( path.join( directory, 'wp-includes/version.php' ) )
		) {
			return directory;
		}
	}
	throw new Error( `No WordPress source tree found beneath ${ root }.` );
}

/**
 * @param {string} root
 * @returns {Promise<string[]>}
 */
async function listFiles( root, relative = '' ) {
	const files = [];
	const entries = await readdir( path.join( root, relative ), {
		withFileTypes: true,
	} );
	for ( const entry of entries.sort( ( a, b ) =>
		a.name.localeCompare( b.name )
	) ) {
		const child = relative ? `${ relative }/${ entry.name }` : entry.name;
		if ( entry.isDirectory() ) {
			files.push( ...( await listFiles( root, child ) ) );
		} else if ( entry.isFile() ) {
			files.push( child );
		}
	}
	return files;
}

/**
 * @param {string} root
 * @param {(relative: string) => boolean} [include]
 */
export async function digestTree( root, include = () => true ) {
	const hash = createHash( 'sha256' );
	for ( const relative of await listFiles( root ) ) {
		if ( ! include( relative ) ) {
			continue;
		}
		const bytes = await readFile( path.join( root, relative ) );
		hash.update( `${ Buffer.byteLength( relative ) }:${ relative }` );
		hash.update( `${ bytes.length }:` );
		hash.update( bytes );
	}
	return hash.digest( 'hex' );
}

/**
 * @param {string} filename
 */
export async function sha256File( filename ) {
	return createHash( 'sha256' )
		.update( await readFile( filename ) )
		.digest( 'hex' );
}

/**
 * @param {string} candidate
 * @param {string} destination
 */
export async function stageCorePhp( candidate, destination ) {
	const source = await findWordPressSourceRoot( candidate );
	await rm( destination, { recursive: true, force: true } );
	let count = 0;
	for ( const relative of await listFiles( source ) ) {
		if (
			! relative.endsWith( '.php' ) ||
			[ ...EXCLUDED_SOURCE_DIRECTORIES ].some(
				( excluded ) =>
					relative === excluded ||
					relative.startsWith( `${ excluded }/` )
			)
		) {
			continue;
		}
		const target = path.join( destination, relative );
		await mkdir( path.dirname( target ), { recursive: true } );
		await copyFile( path.join( source, relative ), target );
		count++;
	}
	if ( count === 0 ) {
		throw new Error(
			'The WordPress source tree contains no eligible PHP files.'
		);
	}
	return { source, destination, files: count };
}

/**
 * @param {Record<string, any>} repositories
 * @param {string} destination
 */
export async function acquireRepositories(
	repositories,
	destination,
	runImplementation = run
) {
	await mkdir( destination, { recursive: true } );
	/** @type {Record<string, string>} */
	const acquired = {};
	for ( const [ name, dependency ] of Object.entries( repositories ) ) {
		const target = path.join( destination, name );
		if ( ! ( await exists( path.join( target, '.git' ) ) ) ) {
			await rm( target, { recursive: true, force: true } );
			await runImplementation(
				'git',
				[
					'clone',
					'--quiet',
					'--no-checkout',
					`https://github.com/${ dependency.repository }.git`,
					target,
				],
				{ label: `clone ${ dependency.repository }` }
			);
		}
		await runImplementation(
			'git',
			[ 'fetch', '--quiet', '--depth=1', 'origin', dependency.commit ],
			{ cwd: target, label: `fetch ${ dependency.repository }` }
		);
		await runImplementation(
			'git',
			[
				'-c',
				'advice.detachedHead=false',
				'checkout',
				'--quiet',
				dependency.commit,
			],
			{ cwd: target, label: `checkout ${ dependency.repository }` }
		);
		acquired[ name ] = path.join( target, dependency.path );
	}
	return acquired;
}
