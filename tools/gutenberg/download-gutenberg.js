#!/usr/bin/env node

/**
 * Download Gutenberg Repository Script.
 *
 * This script downloads a pre-built Gutenberg zip artifact from the GitHub
 * Container Registry and extracts it into the ./gutenberg directory.
 *
 * The artifact is identified by the "gutenberg.sha" value in the root
 * package.json, which is used as the OCI image tag for the gutenberg-build
 * package on GitHub Container Registry.
 *
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const fs = require( 'fs' );
const { pipeline } = require( 'stream/promises' );
const path = require( 'path' );
const { rootDir, gutenbergDir, readGutenbergConfig, verifyGutenbergVersion } = require( './gutenberg-utils' );

/**
 * Execute a command. By default, stdio is inherited so progress is visible in
 * the terminal. When `options.captureOutput` is true, stdout is piped and the
 * promise resolves with the captured stdout once the process exits.
 *
 * @param {string}   command - Command to execute.
 * @param {string[]} args    - Command arguments.
 * @param {Object}   options - Spawn options.
 * @return {Promise<string>} Promise that resolves with stdout when command completes successfully.
 */
function exec( command, args, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		let stdout = '';

		const child = spawn( command, args, {
			cwd: options.cwd || rootDir,
			stdio: options.captureOutput ? [ 'ignore', 'pipe', 'inherit' ] : 'inherit',
			shell: process.platform === 'win32',
			...options,
		} );

		if ( options.captureOutput && child.stdout ) {
			child.stdout.on( 'data', ( data ) => {
				stdout += data.toString();
			} );
		}

		child.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				reject(
					new Error(
						`${ command } ${ args.join( ' ' ) } failed with code ${ code }`
					)
				);
			} else {
				resolve( stdout.trim() );
			}
		} );

		child.on( 'error', reject );
	} );
}

/**
 * Main execution function.
 *
 * @param {boolean} force - Whether to force a fresh download even if the gutenberg directory exists.
 */
async function main( force ) {
	console.log( '🔍 Checking Gutenberg configuration...' );

	/*
	 * Read Gutenberg configuration from package.json.
	 *
	 * Note: ghcr stands for GitHub Container Registry where wordpress-develop ready builds of the Gutenberg plugin
	 * are published on every repository push event.
	 */
	let sha, ghcrRepo;
	try {
		( { sha, ghcrRepo } = readGutenbergConfig() );
		console.log( `   SHA: ${ sha }` );
		console.log( `   GHCR repository: ${ ghcrRepo }` );
	} catch ( error ) {
		console.error( '❌ Error reading package.json:', error.message );
		process.exit( 1 );
	}

	// Skip download if the gutenberg directory already exists and --force is not set.
	let downloaded = false;
	if ( ! force && fs.existsSync( gutenbergDir ) ) {
		console.log( '\nℹ️  The `gutenberg` directory already exists. Use `npm run grunt gutenberg-download -- --force` to download a fresh copy.' );
	} else {
		downloaded = true;
		const zipName = `gutenberg-${ sha }.zip`;
		const zipPath = path.join( rootDir, zipName );

		// Step 1: Get an anonymous GHCR token for pulling.
		console.log( '\n🔑 Fetching GHCR token...' );
		let token;
		try {
			const response = await fetch( `https://ghcr.io/token?scope=repository:${ghcrRepo}:pull&service=ghcr.io` );
			if ( ! response.ok ) {
			    throw new Error( `Failed to fetch token: ${response.status} ${response.statusText}` );
			}
			const data = await response.json();
			token = data.token;
			if ( ! token ) {
				throw new Error( 'No token in response' );
			}
			console.log( '✅ Token acquired' );
		} catch ( error ) {
			console.error( '❌ Failed to fetch token:', error.message );
			process.exit( 1 );
		}

		// Step 2: Get the manifest to find the blob digest.
		console.log( `\n📋 Fetching manifest for ${ sha }...` );
		let digest;
		try {
			const response = await fetch( `https://ghcr.io/v2/${ ghcrRepo }/manifests/${ sha }`, {
				headers: {
					Authorization: `Bearer ${ token }`,
					Accept: 'application/vnd.oci.image.manifest.v1+json',
				},
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to fetch manifest: ${ response.status } ${ response.statusText }` );
			}
			const manifest = await response.json();
			digest = manifest?.layers?.[ 0 ]?.digest;
			if ( ! digest ) {
				throw new Error( 'No layer digest found in manifest' );
			}
			console.log( `✅ Blob digest: ${ digest }` );
		} catch ( error ) {
			console.error( '❌ Failed to fetch manifest:', error.message );
			process.exit( 1 );
		}

		// Step 3: Download the blob (the zip file).
		console.log( `\n📥 Downloading ${ zipName }...` );
		try {
			const response = await fetch( `https://ghcr.io/v2/${ ghcrRepo }/blobs/${ digest }`, {
				headers: {
					Authorization: `Bearer ${ token }`,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to download blob: ${ response.status } ${ response.statusText }` );
			}
			await pipeline( response.body, fs.createWriteStream( zipPath ) );
			console.log( '✅ Download complete' );
		} catch ( error ) {
			console.error( '❌ Download failed:', error.message );
			process.exit( 1 );
		}

		// Remove existing gutenberg directory so the unzip is clean.
		if ( fs.existsSync( gutenbergDir ) ) {
			console.log( '\n🗑️  Removing existing gutenberg directory...' );
			fs.rmSync( gutenbergDir, { recursive: true, force: true } );
		}

		fs.mkdirSync( gutenbergDir, { recursive: true } );

		// Extract the zip into ./gutenberg.
		console.log( `\n📦 Extracting ${ zipName } into ./gutenberg...` );
		try {
			await exec( 'unzip', [ '-q', zipPath, '-d', gutenbergDir ] );
			console.log( '✅ Extraction complete' );
		} catch ( error ) {
			console.error( '❌ Extraction failed:', error.message );
			process.exit( 1 );
		}

		// Clean up the zip file.
		fs.rmSync( zipPath );
	}

	// Verify the downloaded version matches the expected SHA.
	verifyGutenbergVersion();

	if ( downloaded ) {
		console.log( '\n✅ Gutenberg download complete!' );
	}
}

// Run main function.
const force = process.argv.includes( '--force' );
main( force ).catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
