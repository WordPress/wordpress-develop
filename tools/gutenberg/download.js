#!/usr/bin/env node
/* global AbortSignal */

/**
 * Download Gutenberg Repository Script.
 *
 * This script downloads a pre-built Gutenberg tar.gz artifact from the GitHub
 * Container Registry and extracts it into the ./gutenberg directory. The
 * archive is downloaded and verified (SHA-256 and manifest size) before
 * extraction; the existing gutenberg directory is then removed and replaced
 * with the extracted contents.
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
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { Readable } = require( 'stream' );
const { pipeline } = require( 'stream/promises' );
const { Transform } = require( 'stream' );
const {
	gutenbergDir,
	readGutenbergConfig,
	fetchGhcrToken,
	fetchManifest,
} = require( './utils' );

const MAX_DOWNLOAD_ATTEMPTS = 3;
const RETRY_DELAY_MS = 2000;
const DOWNLOAD_TIMEOUT_MS = 120000;

/**
 * Convert bytes into a readable string for download diagnostics.
 *
 * @param {number} bytes Number of bytes.
 * @return {string} Formatted byte count.
 */
function formatBytes( bytes ) {
	return `${ ( bytes / 1024 / 1024 ).toFixed( 2 ) } MiB (${ bytes } bytes)`;
}

/**
 * Wait before retrying a failed download.
 *
 * @param {number} milliseconds Time to wait in milliseconds.
 * @return {Promise<void>} Resolves after the requested delay.
 */
function delay( milliseconds ) {
	return new Promise( ( resolve ) => setTimeout( resolve, milliseconds ) );
}

/**
 * Create an error that retains the HTTP status code for retry decisions.
 *
 * @param {string} message Error message.
 * @param {number} status HTTP status code.
 * @return {Error & { status: number }} Error with status code.
 */
function createHttpError( message, status ) {
	const error = /** @type {Error & { status: number }} */ ( new Error( message ) );
	error.status = status;
	return error;
}

/**
 * Determine whether a failed download might succeed on a later attempt.
 *
 * @param {Error & { status?: number }} error Download error.
 * @return {boolean} Whether the error is retryable.
 */
function isRetryableDownloadError( error ) {
	return ! error.status || error.status === 408 || error.status === 429 || error.status >= 500;
}

/**
 * Extract a SHA-256 hash from an OCI layer digest.
 *
 * @param {string} digest OCI layer digest.
 * @return {string} Expected SHA-256 hash.
 * @throws {Error} If the digest is not a SHA-256 digest.
 */
function getExpectedSha256( digest ) {
	const match = /^sha256:([a-f0-9]{64})$/i.exec( digest );
	if ( ! match ) {
		throw new Error( `Unsupported OCI layer digest: ${ digest }` );
	}

	return match[ 1 ].toLowerCase();
}

/**
 * Download a blob to disk and verify its SHA-256 digest and byte count.
 *
 * @param {string} url Blob URL.
 * @param {string} token Bearer token for GHCR.
 * @param {string} digest OCI layer digest.
 * @param {number|undefined} expectedSize Expected layer size from the manifest.
 * @param {string} destination Path where the compressed blob is written.
 * @return {Promise<void>} Resolves after the download is verified.
 */
async function downloadAndVerifyBlob( url, token, digest, expectedSize, destination ) {
	const expectedSha256 = getExpectedSha256( digest );
	const response = await fetch( url, {
		headers: {
			Authorization: `Bearer ${ token }`,
		},
		signal: AbortSignal.timeout( DOWNLOAD_TIMEOUT_MS ),
	} );

	console.log(
		`   Response: ${ response.status } ${ response.statusText } from ${ new URL( response.url ).hostname }`
	);

	if ( ! response.ok ) {
		throw createHttpError(
			`Failed to download blob: ${ response.status } ${ response.statusText }`,
			response.status
		);
	}

	if ( ! response.body ) {
		throw new Error( 'Blob response has no body' );
	}

	const contentLength = Number( response.headers.get( 'content-length' ) );
	if ( Number.isFinite( contentLength ) && contentLength > 0 ) {
		console.log( `   Content-Length: ${ formatBytes( contentLength ) }` );
	}
	if ( expectedSize ) {
		console.log( `   Manifest size: ${ formatBytes( expectedSize ) }` );
	}

	let downloadedBytes = 0;
	const hash = crypto.createHash( 'sha256' );
	const meter = new Transform( {
		transform( chunk, _encoding, callback ) {
			downloadedBytes += chunk.length;
			hash.update( chunk );
			callback( null, chunk );
		},
	} );

	try {
		await pipeline(
			Readable.fromWeb(
				/** @type {import('stream/web').ReadableStream} */ ( response.body )
			),
			meter,
			fs.createWriteStream( destination )
		);
	} catch ( error ) {
		throw new Error(
			`Download interrupted after ${ formatBytes( downloadedBytes ) }: ${ /** @type {Error} */ ( error ).message }`
		);
	}

	if ( expectedSize && downloadedBytes !== expectedSize ) {
		throw new Error(
			`Downloaded ${ formatBytes( downloadedBytes ) }, but manifest size was ${ formatBytes( expectedSize ) }`
		);
	}

	const actualSha256 = hash.digest( 'hex' );
	if ( actualSha256 !== expectedSha256 ) {
		throw new Error(
			`SHA-256 mismatch: expected ${ expectedSha256 } but received ${ actualSha256 }`
		);
	}

	console.log( `✅ Downloaded ${ formatBytes( downloadedBytes ) } and verified SHA-256` );
}

/**
 * Download a blob with bounded retries and remove incomplete files between attempts.
 *
 * The GHCR bearer token can age out during a long retry window. If a 401 is
 * encountered, a fresh token is fetched once (via `fetchGhcrToken`) and the
 * download is retried with it.
 *
 * @param {string} url Blob URL.
 * @param {string} token Bearer token for GHCR.
 * @param {string} digest OCI layer digest.
 * @param {number|undefined} expectedSize Expected layer size from the manifest.
 * @param {string} ghcrRepo The "owner/repo/package" path on ghcr.io, used to refresh an expired token.
 * @return {Promise<string>} Path to the verified compressed blob.
 */
async function downloadBlobWithRetries( url, token, digest, expectedSize, ghcrRepo ) {
	const destination = path.join(
		os.tmpdir(),
		`wordpress-gutenberg-${ process.pid }.tar.gz`
	);

	let currentToken = token;
	let hasRefetchedToken = false;

	for ( let attempt = 1; attempt <= MAX_DOWNLOAD_ATTEMPTS; attempt++ ) {
		console.log( `\n📥 Download attempt ${ attempt }/${ MAX_DOWNLOAD_ATTEMPTS }...` );
		fs.rmSync( destination, { force: true } );

		try {
			await downloadAndVerifyBlob(
				url,
				currentToken,
				digest,
				expectedSize,
				destination
			);
			return destination;
		} catch ( error ) {
			const downloadError = /** @type {Error & { status?: number }} */ ( error );
			fs.rmSync( destination, { force: true } );
			console.error( `❌ Download attempt ${ attempt } failed: ${ downloadError.message }` );

			if (
				downloadError.status === 401 &&
				! hasRefetchedToken &&
				attempt < MAX_DOWNLOAD_ATTEMPTS
			) {
				hasRefetchedToken = true;
				console.log( '   Bearer token may have expired mid-retry; fetching a fresh token...' );
				currentToken = await fetchGhcrToken( ghcrRepo );

				console.log( `   Retrying in ${ RETRY_DELAY_MS / 1000 } seconds...` );
				await delay( RETRY_DELAY_MS );
				continue;
			}

			if (
				attempt === MAX_DOWNLOAD_ATTEMPTS ||
				! isRetryableDownloadError( downloadError )
			) {
				throw downloadError;
			}

			console.log( `   Retrying in ${ RETRY_DELAY_MS / 1000 } seconds...` );
			await delay( RETRY_DELAY_MS );
		}
	}

	throw new Error( 'Download failed without an error' );
}

/**
 * Extract a verified archive directly into the Gutenberg directory, removing
 * any existing directory first.
 *
 * @param {string} archivePath Path to the verified compressed blob.
 * @param {string} expectedSha Expected immutable Gutenberg source SHA.
 * @return {Promise<void>} Resolves after extraction completes.
 */
async function extractVerifiedArchive( archivePath, expectedSha ) {
	fs.rmSync( gutenbergDir, { recursive: true, force: true } );
	fs.mkdirSync( gutenbergDir, { recursive: true } );

	const tar = spawn( 'tar', [ '-xzf', archivePath, '-C', gutenbergDir ], {
		stdio: [ 'ignore', 'inherit', 'inherit' ],
	} );

	await new Promise( ( resolve, reject ) => {
		tar.on( 'close', ( code ) => {
			if ( code !== 0 ) {
				reject( new Error( `tar exited with code ${ code }` ) );
				return;
			}
			resolve( undefined );
		} );
		tar.on( 'error', reject );
	} );

	const extractedHashPath = path.join( gutenbergDir, '.gutenberg-hash' );
	const extractedSha = fs.readFileSync( extractedHashPath, 'utf8' ).trim();
	if ( extractedSha !== expectedSha ) {
		throw new Error(
			`Extracted Gutenberg SHA mismatch: expected ${ expectedSha } but found ${ extractedSha }`
		);
	}
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
 * @return {Promise<{ manifest: Record<string, any>, resolvedRef: string, expectedSha: string }>}
 */
async function resolveDownloadManifest( config, token ) {
	const { ref, ghcrRepo, isMutable } = config;

	const initialManifest = await fetchManifest( ref, ghcrRepo, token );

	if ( ! isMutable ) {
		return { manifest: initialManifest, resolvedRef: ref, expectedSha: ref };
	}

	const revision =
		initialManifest?.annotations?.[ 'org.opencontainers.image.revision' ];
	if ( ! revision || ! /^[a-f0-9]{40}$/i.test( revision ) ) {
		throw new Error(
			`Manifest for mutable ref "${ ref }" has no valid org.opencontainers.image.revision SHA`
		);
	}

	try {
		const immutableManifest = await fetchManifest( revision, ghcrRepo, token );
		return { manifest: immutableManifest, resolvedRef: revision, expectedSha: revision };
	} catch ( error ) {
		if ( /** @type {{ status?: number }} */ ( error ).status === 404 ) {
			console.log(
				`ℹ️  Immutable SHA tag ${ revision } unavailable; falling back to mutable tag "${ ref }".`
			);
			return { manifest: initialManifest, resolvedRef: ref, expectedSha: revision };
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
	let manifest, resolvedRef, expectedSha;
	try {
		( { manifest, resolvedRef, expectedSha } = await resolveDownloadManifest(
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
	if ( ! digest ) {
		console.error( '❌ No layer digest found in manifest' );
		process.exit( 1 );
	}
	console.log( `✅ Blob digest: ${ digest }` );

	// Step 3: Download and verify the compressed blob before extraction.
	let archivePath;
	try {
		archivePath = await downloadBlobWithRetries(
			`https://ghcr.io/v2/${ config.ghcrRepo }/blobs/${ digest }`,
			token,
			digest,
			layer.size,
			config.ghcrRepo
		);

		console.log( '\n📦 Extracting verified artifact...' );
		await extractVerifiedArchive( archivePath, expectedSha );
		console.log( '✅ Extraction complete' );
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

// Run main function.
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
