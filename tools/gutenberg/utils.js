#!/usr/bin/env node

/**
 * Gutenberg build utilities.
 *
 * Shared helpers used by the Gutenberg download script. When run directly,
 * verifies that the installed Gutenberg build matches the SHA in package.json,
 * and automatically downloads the correct version when needed.
 *
 * @package WordPress
 */

const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

// Paths.
const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );
const hashFilePath = path.join( gutenbergDir, '.gutenberg-hash' );

/**
 * Read Gutenberg configuration from package.json.
 *
 * @return {{ sha: string, ghcrRepo: string }} The Gutenberg configuration.
 * @throws {Error} If the configuration is missing or invalid.
 */
function readGutenbergConfig() {
	const packageJson = require( path.join( rootDir, 'package.json' ) );
	const sha = packageJson.gutenberg?.sha;
	const ghcrRepo = packageJson.gutenberg?.ghcrRepo;

	if ( ! sha ) {
		throw new Error( 'Missing "gutenberg.sha" in package.json' );
	}

	if ( ! ghcrRepo ) {
		throw new Error( 'Missing "gutenberg.ghcrRepo" in package.json' );
	}

	return { sha, ghcrRepo };
}

/**
 * Trigger a fresh download of the Gutenberg artifact by spawning download.js,
 * then run `grunt build:gutenberg --dev` to copy the build to src/.
 * Exits the process if either step fails.
 */
function downloadGutenberg() {
	const downloadResult = spawnSync( 'node', [ path.join( __dirname, 'download.js' ) ], { stdio: 'inherit' } );
	if ( downloadResult.status !== 0 ) {
		process.exit( downloadResult.status ?? 1 );
	}

	const buildResult = spawnSync( 'grunt', [ 'build:gutenberg', '--dev' ], { stdio: 'inherit', shell: true } );
	if ( buildResult.status !== 0 ) {
		process.exit( buildResult.status ?? 1 );
	}
}

/**
 * Verify that the installed Gutenberg version matches the expected SHA in
 * package.json. Automatically downloads the correct version when the directory
 * is missing, the hash file is absent, or the hash does not match. Logs
 * progress to the console and exits with a non-zero code on failure.
 */
function verifyGutenbergVersion() {
	console.log( '\n🔍 Verifying Gutenberg version...' );

	let sha;
	try {
		( { sha } = readGutenbergConfig() );
	} catch ( error ) {
		console.error( '❌ Error reading package.json:', error.message );
		process.exit( 1 );
	}

	// Check for conditions that require a fresh download.
	if ( ! fs.existsSync( gutenbergDir ) ) {
		console.log( 'ℹ️  Gutenberg directory not found. Downloading...' );
		downloadGutenberg();
	} else {
		let installedHash = null;
		try {
			installedHash = fs.readFileSync( hashFilePath, 'utf8' ).trim();
		} catch ( error ) {
			if ( error.code !== 'ENOENT' ) {
				console.error( `❌ ${ error.message }` );
				process.exit( 1 );
			}
		}

		if ( installedHash === null ) {
			console.log( 'ℹ️  Hash file not found. Downloading expected version...' );
			downloadGutenberg();
		} else if ( installedHash !== sha ) {
			console.log( `ℹ️  Hash mismatch (found ${ installedHash }, expected ${ sha }). Downloading expected version...` );
			downloadGutenberg();
		}
	}

	// Final verification — confirms the download (if any) produced the correct version.
	try {
		const installedHash = fs.readFileSync( hashFilePath, 'utf8' ).trim();
		if ( installedHash !== sha ) {
			console.error( `❌ SHA mismatch after download: expected ${ sha } but found ${ installedHash }.` );
			process.exit( 1 );
		}
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			console.error( '❌ .gutenberg-hash not found after download. This is unexpected.' );
		} else {
			console.error( `❌ ${ error.message }` );
		}
		process.exit( 1 );
	}

	console.log( '✅ Version verified' );
}

module.exports = { rootDir, gutenbergDir, readGutenbergConfig, verifyGutenbergVersion };

if ( require.main === module ) {
	verifyGutenbergVersion();
}
