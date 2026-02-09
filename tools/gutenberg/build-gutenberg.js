#!/usr/bin/env node

/**
 * Build Gutenberg Script
 *
 * This script builds the Gutenberg repository using its build command
 * as specified in the root package.json's "gutenberg" configuration.
 *
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

// Paths
const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );

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
 * Main execution function.
 */
async function main() {
	console.log( '🔍 Checking Gutenberg setup...' );

	// Verify Gutenberg directory exists
	if ( ! fs.existsSync( gutenbergDir ) ) {
		console.error( '❌ Gutenberg directory not found at:', gutenbergDir );
		console.error( '   Run: node tools/gutenberg/checkout-gutenberg.js' );
		process.exit( 1 );
	}

	// Verify node_modules exists
	const nodeModulesPath = path.join( gutenbergDir, 'node_modules' );
	if ( ! fs.existsSync( nodeModulesPath ) ) {
		console.error( '❌ Gutenberg dependencies not installed' );
		console.error( '   Run: node tools/gutenberg/checkout-gutenberg.js' );
		process.exit( 1 );
	}

	console.log( '✅ Gutenberg directory found' );

	// Modify Gutenberg's package.json for Core build
	console.log( '\n⚙️  Configuring build for WordPress Core...' );
	const gutenbergPackageJsonPath = path.join( gutenbergDir, 'package.json' );

	try {
		const content = fs.readFileSync( gutenbergPackageJsonPath, 'utf8' );
		const gutenbergPackageJson = JSON.parse( content );

		// Set Core environment variables
		gutenbergPackageJson.config = gutenbergPackageJson.config || {};
		gutenbergPackageJson.config.IS_GUTENBERG_PLUGIN = false;
		gutenbergPackageJson.config.IS_WORDPRESS_CORE = true;

		// Set wpPlugin.name for Core naming convention
		gutenbergPackageJson.wpPlugin = gutenbergPackageJson.wpPlugin || {};
		gutenbergPackageJson.wpPlugin.name = 'wp';

		fs.writeFileSync(
			gutenbergPackageJsonPath,
			JSON.stringify( gutenbergPackageJson, null, '\t' ) + '\n'
		);

		console.log( '   ✅ IS_GUTENBERG_PLUGIN = false' );
		console.log( '   ✅ IS_WORDPRESS_CORE = true' );
		console.log( '   ✅ wpPlugin.name = wp' );
	} catch ( error ) {
		console.error(
			'❌ Error modifying Gutenberg package.json:',
			error.message
		);
		process.exit( 1 );
	}

	// Build Gutenberg
	console.log( '\n🔨 Building Gutenberg for WordPress Core...' );
	console.log( '   (This may take a few minutes)' );

	const startTime = Date.now();

	try {
		// On Windows, shell mode is used and needs the argument wrapped in quotes
		// On Unix, arguments are passed directly without shell parsing
		const baseUrlArg =
			process.platform === 'win32'
				? '--base-url="includes_url( \'build\' )"'
				: "--base-url=includes_url( 'build' )";

		await exec( 'npm', [ 'run', 'build', '--', '--fast', baseUrlArg ], {
			cwd: gutenbergDir,
		} );

		const duration = Math.round( ( Date.now() - startTime ) / 1000 );
		console.log( `✅ Build completed in ${ duration }s` );
	} catch ( error ) {
		console.error( '❌ Build failed:', error.message );
		throw error;
	} finally {
		// Restore Gutenberg's package.json regardless of success or failure
		await restorePackageJson();
	}
}

/**
 * Restore Gutenberg's package.json to its original state.
 */
async function restorePackageJson() {
	console.log( '\n🔄 Restoring Gutenberg package.json...' );
	try {
		await exec( 'git', [ 'checkout', '--', 'package.json' ], {
			cwd: gutenbergDir,
		} );
		console.log( '✅ package.json restored' );
	} catch ( error ) {
		console.warn( '⚠️  Could not restore package.json:', error.message );
	}
}

// Run main function
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
