#!/usr/bin/env node

/**
 * Gutenberg build utilities.
 *
 * Shared helpers used by the Gutenberg download script. When run directly,
 * verifies that the installed Gutenberg build matches the SHA in package.json.
 *
 * @package WordPress
 */

const fs = require( 'fs' );
const path = require( 'path' );

// Paths.
const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );

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
 * Verify that the installed Gutenberg version matches the expected SHA in
 * package.json. Logs progress to the console and exits with a non-zero code
 * on failure.
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

	const hashFilePath = path.join( gutenbergDir, '.gutenberg-hash' );
	try {
		const installedHash = fs.readFileSync( hashFilePath, 'utf8' ).trim();
		if ( installedHash !== sha ) {
			console.error(
				`❌ SHA mismatch: expected ${ sha } but found ${ installedHash }. Run \`npm run grunt gutenberg:download -- --force\` to download the correct version.`
			);
			process.exit( 1 );
		}
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			console.error( `❌ .gutenberg-hash not found. Run \`npm run grunt gutenberg:download\` to download Gutenberg.` );
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
