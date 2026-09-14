#!/usr/bin/env node
/* global AbortSignal */

/**
 * Gutenberg build utilities.
 *
 * Shared helpers used by the Gutenberg download script. When run directly,
 * verifies that the installed Gutenberg build matches the value in
 * package.json and automatically downloads the correct version when needed.
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

// A 40-character lowercase hex string is treated as an immutable Git SHA tag.
// Anything else (e.g. "trunk", "release-19.5", "pr-12345") is treated as a
// mutable tag published by the Gutenberg build-plugin-zip workflow.
const SHA_PATTERN = /^[a-f0-9]{40}$/i;

const MANIFEST_ACCEPT = 'application/vnd.oci.image.manifest.v1+json';

// Retry/timeout settings for the token and manifest requests. These run
// before the blob download and are now a single point of failure for the
// whole build, so they share the blob download's attempt count and backoff
// (see MAX_DOWNLOAD_ATTEMPTS and RETRY_DELAY_MS in download.js).
const MAX_METADATA_ATTEMPTS = 3;
const RETRY_DELAY_MS = 2000;
const METADATA_TIMEOUT_MS = 30000;

/**
 * Wait before retrying a failed metadata request.
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
 * Determine whether a failed metadata request might succeed on a later attempt.
 *
 * @param {Error & { status?: number }} error Request error.
 * @return {boolean} Whether the error is retryable.
 */
function isRetryableMetadataError( error ) {
	return ! error.status || error.status === 408 || error.status === 429 || error.status >= 500;
}

/**
 * Run a metadata request with bounded retries, matching the blob download's
 * retry semantics. Non-retryable errors (e.g. a 404) are thrown immediately.
 *
 * @param {string} description Human-readable label for retry log messages.
 * @param {() => Promise<any>} request Function that performs one request attempt.
 * @return {Promise<any>} Resolves with the request's result.
 */
async function withMetadataRetries( description, request ) {
	for ( let attempt = 1; attempt <= MAX_METADATA_ATTEMPTS; attempt++ ) {
		try {
			return await request();
		} catch ( error ) {
			const requestError = /** @type {Error & { status?: number }} */ ( error );
			if ( attempt === MAX_METADATA_ATTEMPTS || ! isRetryableMetadataError( requestError ) ) {
				throw requestError;
			}
			console.error( `❌ ${ description } attempt ${ attempt } failed: ${ requestError.message }` );
			console.log( `   Retrying in ${ RETRY_DELAY_MS / 1000 } seconds...` );
			await delay( RETRY_DELAY_MS );
		}
	}

	throw new Error( `${ description } failed without an error` );
}

/**
 * Read Gutenberg configuration from package.json.
 *
 * `gutenberg.sha` is always committed as a pinned SHA, but a contributor
 * may temporarily set it to a mutable tag published by the Gutenberg repository
 * (e.g. "trunk", "release-19.5", "pr-12345") to track the latest build of that
 * stream or test changes before merging.
 *
 * @return {{ ref: string, ghcrRepo: string, isMutable: boolean }} The
 *     resolved configuration. `ref` is the OCI tag to look up; `isMutable`
 *     is true when the value is not a SHA-shaped string.
 * @throws {Error} If the configuration is missing or invalid.
 */
function readGutenbergConfig() {
	const packageJson = require( path.join( rootDir, 'package.json' ) );
	const ref = packageJson.gutenberg?.sha;
	const ghcrRepo = packageJson.gutenberg?.ghcrRepo;

	if ( ! ref ) {
		throw new Error( 'Missing "gutenberg.sha" in package.json' );
	}

	if ( ! ghcrRepo ) {
		throw new Error( 'Missing "gutenberg.ghcrRepo" in package.json' );
	}

	const isMutable = ! SHA_PATTERN.test( ref );

	return { ref, ghcrRepo, isMutable };
}

/**
 * Fetch an anonymous pull token for the given GHCR repository.
 *
 * @param {string} ghcrRepo The "owner/repo/package" path on ghcr.io.
 * @return {Promise<string>} The bearer token.
 */
async function fetchGhcrToken( ghcrRepo ) {
	return withMetadataRetries( 'Fetch GHCR token', async () => {
		const response = await fetch(
			`https://ghcr.io/token?scope=repository:${ ghcrRepo }:pull&service=ghcr.io`,
			{ signal: AbortSignal.timeout( METADATA_TIMEOUT_MS ) }
		);
		if ( ! response.ok ) {
			throw createHttpError(
				`Failed to fetch GHCR token: ${ response.status } ${ response.statusText }`,
				response.status
			);
		}
		const data = await response.json();
		if ( ! data.token ) {
			throw new Error( 'No token in GHCR response' );
		}
		return data.token;
	} );
}

/**
 * Fetch a manifest from GHCR by tag.
 *
 * @param {string} ref      The tag (SHA or mutable tag).
 * @param {string} ghcrRepo The "owner/repo/package" path on ghcr.io.
 * @param {string} token    Bearer token from fetchGhcrToken.
 * @return {Promise<Record<string, any>>} Parsed manifest JSON.
 */
async function fetchManifest( ref, ghcrRepo, token ) {
	return withMetadataRetries( `Fetch manifest for "${ ref }"`, async () => {
		const response = await fetch(
			`https://ghcr.io/v2/${ ghcrRepo }/manifests/${ ref }`,
			{
				headers: {
					Authorization: `Bearer ${ token }`,
					Accept: MANIFEST_ACCEPT,
				},
				signal: AbortSignal.timeout( METADATA_TIMEOUT_MS ),
			}
		);
		if ( ! response.ok ) {
			throw createHttpError(
				`Failed to fetch manifest for "${ ref }": ${ response.status } ${ response.statusText }`,
				response.status
			);
		}
		return response.json();
	} );
}

/**
 * Resolve the expected source SHA for the configured ref.
 *
 * For immutable refs (SHA), the expected SHA is the ref itself and no network
 * call is required. For mutable refs, the manifest's
 * `org.opencontainers.image.revision` annotation is fetched and returned,
 * which reflects the SHA value published to the mutable tag most recently.
 *
 * @param {{ ref: string, ghcrRepo: string, isMutable: boolean }} config
 * @return {Promise<string>} The expected SHA.
 */
async function resolveExpectedSha( { ref, ghcrRepo, isMutable } ) {
	const workflowSha = process.env.GUTENBERG_EXPECTED_SHA;
	if ( workflowSha ) {
		if ( ! SHA_PATTERN.test( workflowSha ) ) {
			throw new Error(
				`GUTENBERG_EXPECTED_SHA must be a 40-character Git SHA, received "${ workflowSha }"`
			);
		}

		return workflowSha;
	}

	if ( ! isMutable ) {
		return ref;
	}

	const token = await fetchGhcrToken( ghcrRepo );
	const manifest = await fetchManifest( ref, ghcrRepo, token );
	const revision =
		manifest?.annotations?.[ 'org.opencontainers.image.revision' ];
	if ( ! revision ) {
		throw new Error(
			`Manifest for "${ ref }" has no org.opencontainers.image.revision annotation`
		);
	}
	return revision;
}

/**
 * Trigger a fresh download of the Gutenberg artifact by spawning download.js,
 * then run `grunt build:gutenberg` to copy the build into src/.
 * Exits the process if either step fails.
 */
function downloadGutenberg() {
	const downloadResult = spawnSync( 'node', [ path.join( __dirname, 'download.js' ) ], { stdio: 'inherit' } );
	if ( downloadResult.status !== 0 ) {
		process.exit( downloadResult.status ?? 1 );
	}

	const buildResult = spawnSync( 'grunt', [ 'build:gutenberg' ], { stdio: 'inherit', shell: true } );
	if ( buildResult.status !== 0 ) {
		process.exit( buildResult.status ?? 1 );
	}
}

/**
 * Verify that the installed Gutenberg version matches the expected SHA.
 *
 * A calling workflow may supply GUTENBERG_EXPECTED_SHA after resolving a build
 * once. This avoids re-resolving a mutable tag in every matrix job. Otherwise,
 * SHA refs use the configured value and mutable refs resolve their current
 * image.revision annotation. The installed `.gutenberg-hash` is compared
 * against the expected SHA; on mismatch, a fresh download is triggered.
 */
async function verifyGutenbergVersion() {
	console.log( '\n🔍 Verifying Gutenberg version...' );

	let config;
	try {
		config = readGutenbergConfig();
	} catch ( error ) {
		console.error( '❌ Error reading package.json:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	const { ref, isMutable } = config;
	console.log(
		`   Ref: ${ ref }${ isMutable ? ' (mutable tag)' : '' }`
	);

	let expectedSha;
	try {
		expectedSha = await resolveExpectedSha( config );
	} catch ( error ) {
		console.error( '❌ Failed to resolve expected SHA:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	if ( isMutable ) {
		console.log( `   Latest build for "${ ref }": ${ expectedSha }` );
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
			const err = /** @type {NodeJS.ErrnoException} */ ( error );
			if ( err.code !== 'ENOENT' ) {
				console.error( `❌ ${ err.message }` );
				process.exit( 1 );
			}
		}

		if ( installedHash === null ) {
			console.log( 'ℹ️  Hash file not found. Downloading expected version...' );
			downloadGutenberg();
		} else if ( installedHash !== expectedSha ) {
			console.log( `ℹ️  Hash mismatch (found ${ installedHash }, expected ${ expectedSha }). Downloading expected version...` );
			downloadGutenberg();
		}
	}

	// Final verification — confirms the download (if any) produced the correct version.
	try {
		const installedHash = fs.readFileSync( hashFilePath, 'utf8' ).trim();
		if ( installedHash !== expectedSha ) {
			console.error( `❌ SHA mismatch after download: expected ${ expectedSha } but found ${ installedHash }.` );
			process.exit( 1 );
		}
	} catch ( error ) {
		const err = /** @type {NodeJS.ErrnoException} */ ( error );
		if ( err.code === 'ENOENT' ) {
			console.error( '❌ .gutenberg-hash not found after download. This is unexpected.' );
		} else {
			console.error( `❌ ${ err.message }` );
		}
		process.exit( 1 );
	}

	console.log( '✅ Version verified' );
}

module.exports = {
	rootDir,
	gutenbergDir,
	readGutenbergConfig,
	verifyGutenbergVersion,
	fetchGhcrToken,
	fetchManifest,
	resolveExpectedSha,
};

if ( require.main === module ) {
	verifyGutenbergVersion().catch( ( error ) => {
		console.error( '❌ Unexpected error:', error );
		process.exit( 1 );
	} );
}
