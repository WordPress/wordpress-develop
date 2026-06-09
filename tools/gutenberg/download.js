#!/usr/bin/env node

/**
 * Download Gutenberg Repository Script.
 *
 * This script downloads a pre-built Gutenberg tar.gz artifact from the GitHub
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
const { Writable } = require( 'stream' );
const { pipeline } = require( 'stream/promises' );
const path = require( 'path' );
const zlib = require( 'zlib' );
const { gutenbergDir, readGutenbergConfig, verifyGutenbergVersion } = require( './utils' );

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
		console.log( '\nℹ️  The `gutenberg` directory already exists. Use `npm run grunt gutenberg:download -- --force` to download a fresh copy.' );
	} else {
		downloaded = true;

		// Step 1: Get an anonymous GHCR token for pulling.
		console.log( '\n🔑 Fetching GHCR token...' );
		let token;
		try {
			const response = await fetch( `https://ghcr.io/token?scope=repository:${ ghcrRepo }:pull&service=ghcr.io` );
			if ( ! response.ok ) {
				throw new Error( `Failed to fetch token: ${ response.status } ${ response.statusText }` );
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

		// Remove existing gutenberg directory so the extraction is clean.
		if ( fs.existsSync( gutenbergDir ) ) {
			console.log( '\n🗑️  Removing existing gutenberg directory...' );
			fs.rmSync( gutenbergDir, { recursive: true, force: true } );
		}

		fs.mkdirSync( gutenbergDir, { recursive: true } );

		/*
		 * Step 3: Stream the blob directly through gunzip into tar, writing
		 * into ./gutenberg with no temporary file on disk.
		 */
		console.log( `\n📥 Downloading and extracting artifact...` );
		try {
			const response = await fetch( `https://ghcr.io/v2/${ ghcrRepo }/blobs/${ digest }`, {
				headers: {
					Authorization: `Bearer ${ token }`,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( `Failed to download blob: ${ response.status } ${ response.statusText }` );
			}

			/*
			 * Spawn tar to read from stdin and extract into gutenbergDir.
			 * `tar` is available on macOS, Linux, and Windows 10+.
			 */
			const tar = spawn( 'tar', [ '-x', '-C', gutenbergDir ], {
				stdio: [ 'pipe', 'inherit', 'inherit' ],
			} );

			const tarDone = new Promise( ( resolve, reject ) => {
				tar.on( 'close', ( code ) => {
					if ( code !== 0 ) {
						reject( new Error( `tar exited with code ${ code }` ) );
					} else {
						resolve();
					}
				} );
				tar.on( 'error', reject );
			} );

			/*
			 * Pipe: fetch body → gunzip → tar stdin.
			 * Decompressing in Node keeps the pipeline error handling
			 * consistent and means tar only sees plain tar data on stdin.
			 */
			await pipeline(
				response.body,
				zlib.createGunzip(),
				Writable.toWeb( tar.stdin ),
			);

			await tarDone;

			console.log( '✅ Download and extraction complete' );
		} catch ( error ) {
			console.error( '❌ Download/extraction failed:', error.message );
			process.exit( 1 );
		}
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
