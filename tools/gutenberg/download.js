#!/usr/bin/env node

/**
 * Download Gutenberg Repository Script.
 *
 * This script downloads a pre-built Gutenberg tar.gz artifact from the GitHub
 * Container Registry and extracts it into the ./gutenberg directory. Any
 * existing gutenberg directory is removed before extraction.
 *
 * The artifact is identified by the "gutenberg.sha" value in the root
 * package.json, which is used as the OCI tag for the gutenberg-wp-develop-build
 * package on GitHub Container Registry. The value is normally a Git SHA, but
 * may also be a mutable tag (e.g. "trunk", "pr-12345") in a pull request that
 * wants to track the latest build of a stream. When the ref is a mutable tag,
 * the script resolves it to the immutable SHA tag for the actual blob fetch
 * and falls back to the mutable tag's manifest when the immutable tag is
 * unavailable.
 *
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const fs = require( 'fs' );
const { Readable } = require( 'stream' );
const { Transform } = require( 'stream' );
const { pipeline } = require( 'stream/promises' );
const os = require( 'os' );
const {
	gutenbergDir,
	readGutenbergConfig,
	fetchGhcrToken,
	fetchManifest,
} = require( './utils' );

const MAX_DOWNLOAD_ATTEMPTS = 3;

/**
 * Determine whether a download error might succeed on a later attempt.
 *
 * @param {Error & { status?: number }} error Download error.
 * @return {boolean} Whether the error is retryable.
 */
function isRetryableDownloadError( error ) {
	return ! error.status ||
		error.status === 408 ||
		error.status >= 500;
}

/**
 * Download a Gutenberg archive to disk with bounded retries.
 *
 * @param {string} url Blob URL.
 * @param {string} token Bearer token for GHCR.
 * @param {number} expectedSize Expected layer size from the manifest.
 * @return {Promise<string>} Path to the completed archive.
 */
async function downloadArchive( url, token, expectedSize ) {
	const archivePath = `${ os.tmpdir() }/wordpress-gutenberg-${ process.pid }.tar.gz`;
	const host = new URL( url ).host;

	for ( let attempt = 1; attempt <= MAX_DOWNLOAD_ATTEMPTS; attempt++ ) {
		let receivedSize = 0;
		let status = 'no response';
		const startTime = Date.now();

		fs.rmSync( archivePath, { force: true } );
		console.log( `\n📥 Download attempt ${ attempt }/${ MAX_DOWNLOAD_ATTEMPTS }...` );

		try {
			const response = await fetch( url, {
				headers: {
					Authorization: `Bearer ${ token }`,
				},
			} );
			status = `${ response.status } ${ response.statusText }`;

			if ( ! response.ok ) {
				const error = /** @type {Error & { status?: number }} */ (
					new Error( `Failed to download blob: ${ status }` )
				);
				error.status = response.status;
				throw error;
			}
			if ( ! response.body ) {
				throw new Error( 'Blob response has no body' );
			}

			const meter = new Transform( {
				transform( chunk, _encoding, callback ) {
					receivedSize += chunk.length;
					callback( null, chunk );
				},
			} );
			await pipeline(
				Readable.fromWeb(
					/** @type {import('stream/web').ReadableStream} */ ( response.body )
				),
				meter,
				fs.createWriteStream( archivePath )
			);

			if ( receivedSize !== expectedSize ) {
				throw new Error(
					`Received ${ receivedSize } bytes, expected ${ expectedSize } bytes from the manifest`
				);
			}

			console.log(
				`   ${ status } from ${ host }; expected ${ expectedSize } bytes, received ${ receivedSize } bytes in ${ Date.now() - startTime }ms.`
			);
			return archivePath;
		} catch ( error ) {
			const downloadError = /** @type {Error & { status?: number }} */ ( error );
			console.error(
				`❌ Attempt ${ attempt }/${ MAX_DOWNLOAD_ATTEMPTS }: ${ status } from ${ host }; expected ${ expectedSize } bytes, received ${ receivedSize } bytes in ${ Date.now() - startTime }ms; ${ downloadError.message }`
			);
			fs.rmSync( archivePath, { force: true } );

			if (
				attempt === MAX_DOWNLOAD_ATTEMPTS ||
				! isRetryableDownloadError( downloadError )
			) {
				throw downloadError;
			}

			const retryDelay = attempt * 1000 + Math.floor( Math.random() * 250 );
			console.log( `⏳ Waiting ${ retryDelay }ms before retrying...` );
			await new Promise( ( resolve ) => setTimeout( resolve, retryDelay ) );
		}
	}

	throw new Error( 'Download failed without an error' );
}

/**
 * Resolve the manifest to use for downloading.
 *
 * For immutable refs (SHA values), the ref is used directly.
 *
 * For mutable refs, the mutable tag's manifest is fetched first and the
 * `image.revision` annotation is read. The corresponding immutable SHA tag is
 * then preferred. If the immutable SHA tag is unavailable, fall back to the
 * manifest already fetched via the mutable tag.
 *
 * @param {{ ref: string, ghcrRepo: string, isMutable: boolean }} config
 * @param {string} token
 * @return {Promise<{ manifest: Record<string, any>, resolvedRef: string }>}
 */
async function resolveDownloadManifest( config, token ) {
	const { ref, ghcrRepo, isMutable } = config;

	const initialManifest = await fetchManifest( ref, ghcrRepo, token );

	if ( ! isMutable ) {
		return { manifest: initialManifest, resolvedRef: ref };
	}

	const revision =
		initialManifest?.annotations?.[ 'org.opencontainers.image.revision' ];
	if ( ! revision ) {
		console.log(
			`ℹ️  No image.revision annotation on "${ ref }"; using mutable tag for download.`
		);
		return { manifest: initialManifest, resolvedRef: ref };
	}

	try {
		const immutableManifest = await fetchManifest( revision, ghcrRepo, token );
		return { manifest: immutableManifest, resolvedRef: revision };
	} catch ( error ) {
		if ( /** @type {{ status?: number }} */ ( error ).status === 404 ) {
			console.log(
				`ℹ️  Immutable SHA tag ${ revision } unavailable; falling back to mutable tag "${ ref }".`
			);
			return { manifest: initialManifest, resolvedRef: ref };
		}
		throw error;
	}
}

/**
 * Main execution function.
 */
async function main() {
	console.log( '🔍 Checking Gutenberg configuration...' );

	/*
	 * Read Gutenberg configuration from package.json.
	 *
	 * Note: ghcr stands for GitHub Container Registry where wordpress-develop ready builds of the Gutenberg plugin
	 * are published by the Gutenberg build-plugin-zip workflow.
	 */
	let config;
	try {
		config = readGutenbergConfig();
		console.log(
			`   Ref: ${ config.ref }${
				config.isMutable ? ' (mutable tag)' : ''
			}`
		);
		console.log( `   GHCR repository: ${ config.ghcrRepo }` );
	} catch ( error ) {
		console.error( '❌ Error reading package.json:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	// Step 1: Get an anonymous GHCR token for pulling.
	console.log( '\n🔑 Fetching GHCR token...' );
	let token;
	try {
		token = await fetchGhcrToken( config.ghcrRepo );
		console.log( '✅ Token acquired' );
	} catch ( error ) {
		console.error( '❌ Failed to fetch token:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	// Step 2: Resolve the manifest to use for download.
	console.log( `\n📋 Fetching manifest for ${ config.ref }...` );
	let manifest, resolvedRef;
	try {
		( { manifest, resolvedRef } = await resolveDownloadManifest(
			config,
			token
		) );
		if ( resolvedRef !== config.ref ) {
			console.log( `   Resolved to immutable SHA tag: ${ resolvedRef }` );
		}
	} catch ( error ) {
		console.error( '❌ Failed to fetch manifest:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	const layer = manifest?.layers?.[ 0 ];
	const digest = layer?.digest;
	const expectedSize = layer?.size;
	if ( ! digest || ! Number.isInteger( expectedSize ) || expectedSize < 0 ) {
		console.error( '❌ No valid layer digest and size found in manifest' );
		process.exit( 1 );
	}
	console.log( `✅ Blob digest: ${ digest } (${ expectedSize } bytes)` );

	/*
	 * Step 3: Download the complete blob to a temporary archive before
	 * extracting it into ./gutenberg.
	 */
	let archivePath;
	try {
		archivePath = await downloadArchive(
			`https://ghcr.io/v2/${ config.ghcrRepo }/blobs/${ digest }`,
			token,
			expectedSize
		);

		// Remove existing gutenberg directory so the extraction is clean.
		if ( fs.existsSync( gutenbergDir ) ) {
			console.log( '\n🗑️  Removing existing gutenberg directory...' );
			fs.rmSync( gutenbergDir, { recursive: true, force: true } );
		}
		fs.mkdirSync( gutenbergDir, { recursive: true } );

		console.log( '\n📦 Extracting artifact...' );

		/*
		 * `tar` is available on macOS, Linux, and Windows 10+.
		 */
		const tar = spawn( 'tar', [ '-xzf', archivePath, '-C', gutenbergDir ], {
			stdio: 'inherit',
		} );

		await new Promise( ( resolve, reject ) => {
			tar.on( 'close', ( code ) => {
				if ( code !== 0 ) {
					reject( new Error( `tar exited with code ${ code }` ) );
				} else {
					resolve( undefined );
				}
			} );
			tar.on( 'error', reject );
		} );

		console.log( '✅ Download and extraction complete' );
	} catch ( error ) {
		console.error( '❌ Download/extraction failed:', /** @type {Error} */ ( error ).message );
		process.exitCode = 1;
		return;
	} finally {
		if ( archivePath ) {
			fs.rmSync( archivePath, { force: true } );
		}
	}

	console.log( '\n✅ Gutenberg download complete!' );
}

module.exports = {
	downloadArchive,
};

// Run main function.
if ( require.main === module ) {
	main().catch( ( error ) => {
		console.error( '❌ Unexpected error:', error );
		process.exit( 1 );
	} );
}
