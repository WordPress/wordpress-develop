#!/usr/bin/env node

/**
 * Checkout Gutenberg Repository Script
 *
 * This script checks out the Gutenberg repository at a specific commit/branch/tag
 * as specified in the root package.json's "gutenberg" configuration.
 *
 * It handles:
 * - Initial clone if directory doesn't exist
 * - Updating existing checkout to correct ref
 * - Installing dependencies with npm ci
 * - Idempotent operation (safe to run multiple times)
 *
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

// Constants
const GUTENBERG_REPO = 'https://github.com/WordPress/gutenberg.git';

// Paths
const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );
const packageJsonPath = path.join( rootDir, 'package.json' );

/**
 * Execute a command and return a promise.
 * Captures output and only displays it on failure for cleaner logs.
 *
 * @param {string}   command - Command to execute.
 * @param {string[]} args    - Command arguments.
 * @param {Object}   options - Spawn options.
 * @return {Promise} Promise that resolves when command completes.
 */
function exec( command, args, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		let stdout = '';
		let stderr = '';

		const child = spawn( command, args, {
			cwd: options.cwd || rootDir,
			stdio: [ 'ignore', 'pipe', 'pipe' ],
			shell: process.platform === 'win32', // Use shell on Windows to find .cmd files
			...options,
		} );

		// Capture output
		if ( child.stdout ) {
			child.stdout.on( 'data', ( data ) => {
				stdout += data.toString();
			} );
		}

		if ( child.stderr ) {
			child.stderr.on( 'data', ( data ) => {
				stderr += data.toString();
			} );
		}

		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				// Show output only on failure
				if ( stdout ) {
					console.error( '\nCommand output:' );
					console.error( stdout );
				}
				if ( stderr ) {
					console.error( '\nCommand errors:' );
					console.error( stderr );
				}
				reject(
					new Error(
						`${ command } ${ args.join(
							' '
						) } failed with code ${ code }`
					)
				);
			} else {
				resolve();
			}
		} );

		child.on( 'error', reject );
	} );
}

/**
 * Execute a command and capture its output.
 *
 * @param {string}   command - Command to execute.
 * @param {string[]} args    - Command arguments.
 * @param {Object}   options - Spawn options.
 * @return {Promise<string>} Promise that resolves with command output.
 */
function execOutput( command, args, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, args, {
			cwd: options.cwd || rootDir,
			shell: process.platform === 'win32', // Use shell on Windows to find .cmd files
			...options,
		} );

		let stdout = '';
		let stderr = '';

		if ( child.stdout ) {
			child.stdout.on( 'data', ( data ) => {
				stdout += data.toString();
			} );
		}

		if ( child.stderr ) {
			child.stderr.on( 'data', ( data ) => {
				stderr += data.toString();
			} );
		}

		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				reject( new Error( `${ command } failed: ${ stderr }` ) );
			} else {
				resolve( stdout.trim() );
			}
		} );

		child.on( 'error', reject );
	} );
}

/**
 * Main execution function.
 */
async function main() {
	console.log( '🔍 Checking Gutenberg configuration...' );

	// Read Gutenberg ref from package.json
	let ref;
	try {
		const packageJson = JSON.parse(
			fs.readFileSync( packageJsonPath, 'utf8' )
		);
		ref = packageJson.gutenberg?.ref;

		if ( ! ref ) {
			throw new Error( 'Missing "gutenberg.ref" in package.json' );
		}

		console.log( `   Repository: ${ GUTENBERG_REPO }` );
		console.log( `   Reference: ${ ref }` );
	} catch ( error ) {
		console.error( '❌ Error reading package.json:', error.message );
		process.exit( 1 );
	}

	// Check if Gutenberg directory exists
	const gutenbergExists = fs.existsSync( gutenbergDir );

	if ( ! gutenbergExists ) {
		console.log( '\n📥 Cloning Gutenberg repository (shallow clone)...' );
		try {
			// Generic shallow clone approach that works for both branches and commit hashes
			// 1. Clone with no checkout and shallow depth
			await exec( 'git', [
				'clone',
				'--depth',
				'1',
				'--no-checkout',
				GUTENBERG_REPO,
				'gutenberg',
			] );

			// 2. Fetch the specific ref with depth 1 (works for branches, tags, and commits)
			await exec( 'git', [ 'fetch', '--depth', '1', 'origin', ref ], {
				cwd: gutenbergDir,
			} );

			// 3. Checkout FETCH_HEAD
			await exec( 'git', [ 'checkout', 'FETCH_HEAD' ], {
				cwd: gutenbergDir,
			} );

			console.log( '✅ Cloned successfully' );
		} catch ( error ) {
			console.error( '❌ Clone failed:', error.message );
			process.exit( 1 );
		}
	} else {
		console.log( '\n✅ Gutenberg directory already exists' );
	}

	// Fetch and checkout target ref
	console.log( `\n📡 Fetching and checking out: ${ ref }` );
	try {
		// Fetch the specific ref (works for branches, tags, and commit hashes)
		await exec( 'git', [ 'fetch', '--depth', '1', 'origin', ref ], {
			cwd: gutenbergDir,
		} );

		// Checkout what was just fetched
		await exec( 'git', [ 'checkout', 'FETCH_HEAD' ], {
			cwd: gutenbergDir,
		} );

		console.log( '✅ Checked out successfully' );
	} catch ( error ) {
		console.error( '❌ Fetch/checkout failed:', error.message );
		process.exit( 1 );
	}

	// Install dependencies
	console.log( '\n📦 Installing dependencies...' );
	const nodeModulesExists = fs.existsSync(
		path.join( gutenbergDir, 'node_modules' )
	);

	if ( ! nodeModulesExists ) {
		console.log( '   (This may take a few minutes on first run)' );
	}

	try {
		await exec( 'npm', [ 'ci' ], { cwd: gutenbergDir } );
		console.log( '✅ Dependencies installed' );
	} catch ( error ) {
		console.error( '❌ npm ci failed:', error.message );
		process.exit( 1 );
	}

	console.log( '\n✅ Gutenberg checkout complete!' );
}

// Run main function
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
