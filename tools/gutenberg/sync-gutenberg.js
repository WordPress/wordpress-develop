#!/usr/bin/env node

/**
 * Sync Gutenberg Script
 *
 * This script ensures Gutenberg is checked out and built for the correct ref.
 * It follows the same pattern as install-changed:
 * - Stores the built ref in .gutenberg-hash
 * - Compares current package.json ref with stored hash
 * - Only runs checkout + build when they differ
 *
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

// Paths
const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );
const gutenbergBuildDir = path.join( gutenbergDir, 'build' );
const packageJsonPath = path.join( rootDir, 'package.json' );
const hashFilePath = path.join( rootDir, '.gutenberg-hash' );

/**
 * Execute a command and return a promise.
 *
 * @param {string}   command - Command to execute.
 * @param {string[]} args    - Command arguments.
 * @param {Object}   options - Spawn options.
 * @return {Promise} Promise that resolves when command completes.
 */
function exec( command, args, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, args, {
			cwd: options.cwd || rootDir,
			stdio: 'inherit',
			shell: process.platform === 'win32',
			...options,
		} );

		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				reject(
					new Error(
						`${ command } ${ args.join( ' ' ) } failed with code ${ code }`
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
 * Read the expected Gutenberg ref from package.json.
 *
 * @return {string} The Gutenberg ref.
 */
function getExpectedRef() {
	const packageJson = JSON.parse( fs.readFileSync( packageJsonPath, 'utf8' ) );
	const ref = packageJson.gutenberg?.ref;

	if ( ! ref ) {
		throw new Error( 'Missing "gutenberg.ref" in package.json' );
	}

	return ref;
}

/**
 * Read the stored hash from .gutenberg-hash file.
 *
 * @return {string|null} The stored ref, or null if file doesn't exist.
 */
function getStoredHash() {
	try {
		return fs.readFileSync( hashFilePath, 'utf8' ).trim();
	} catch ( error ) {
		return null;
	}
}

/**
 * Write the ref to .gutenberg-hash file.
 *
 * @param {string} ref - The ref to store.
 */
function writeHash( ref ) {
	fs.writeFileSync( hashFilePath, ref + '\n' );
}

/**
 * Check if Gutenberg build exists.
 *
 * @return {boolean} True if build directory exists.
 */
function hasBuild() {
	return fs.existsSync( gutenbergBuildDir );
}

/**
 * Main execution function.
 */
async function main() {
	console.log( '🔍 Checking Gutenberg sync status...' );

	const expectedRef = getExpectedRef();
	const storedHash = getStoredHash();

	console.log( `   Expected ref: ${ expectedRef }` );
	console.log( `   Stored hash:  ${ storedHash || '(none)' }` );

	// Check if we need to rebuild
	if ( storedHash === expectedRef && hasBuild() ) {
		console.log( '✅ Gutenberg is already synced and built' );
		return;
	}

	if ( storedHash !== expectedRef ) {
		console.log( '\n📦 Gutenberg ref has changed, rebuilding...' );
	} else {
		console.log( '\n📦 Gutenberg build not found, building...' );
	}

	// Run checkout
	console.log( '\n🔄 Running gutenberg:checkout...' );
	await exec( 'node', [ 'tools/gutenberg/checkout-gutenberg.js' ] );

	// Run build
	console.log( '\n🔄 Running gutenberg:build...' );
	await exec( 'node', [ 'tools/gutenberg/build-gutenberg.js' ] );

	// Write the hash after successful build
	writeHash( expectedRef );
	console.log( `\n✅ Updated .gutenberg-hash to ${ expectedRef }` );

	console.log( '\n✅ Gutenberg sync complete!' );
}

// Run main function
main().catch( ( error ) => {
	console.error( '❌ Sync failed:', error.message );
	process.exit( 1 );
} );
