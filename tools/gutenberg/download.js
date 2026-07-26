#!/usr/bin/env node
/* eslint-disable no-console */

/**
 * Download Gutenberg Repository Script.
 *
 * This script downloads a pre-built Gutenberg tar.gz artifact from the GitHub
 * Container Registry and extracts it into the ./gutenberg directory. An
 * existing gutenberg directory is replaced only after verification and
 * extraction succeed.
 *
 * @package
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
	rootDir,
	gutenbergDir,
	readGutenbergConfig,
	fetchGhcrToken,
	fetchManifest,
} = require( './utils' );

const MAX_DOWNLOAD_ATTEMPTS = 3;
const RETRY_DELAY_MS = 2000;
const DOWNLOAD_TIMEOUT_MS = 120000;
const SOURCE_SHA_PATTERN = /^[a-f0-9]{40}$/i;
const BLOB_SHA256_PATTERN = /^[a-f0-9]{64}$/i;

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
 * @param {number} status  HTTP status code.
 * @return {Error & { status: number }} Error with status code.
 */
function createHttpError( message, status ) {
	const error = /** @type {Error & { status: number }} */ (
		new Error( message )
	);
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
	return (
		! error.status ||
		error.status === 408 ||
		error.status === 429 ||
		error.status >= 500
	);
}

/**
 * Normalize an immutable Gutenberg source SHA.
 *
 * @param {string} sourceSha Source SHA.
 * @return {string} Lowercase source SHA.
 */
function normalizeSourceSha( sourceSha ) {
	if ( ! SOURCE_SHA_PATTERN.test( sourceSha ) ) {
		throw new Error(
			`Expected a 40-character Gutenberg SHA, received: ${ sourceSha }`
		);
	}

	return sourceSha.toLowerCase();
}

/**
 * Normalize an OCI blob SHA-256 value.
 *
 * @param {string} blobSha256 OCI blob SHA-256 value, with or without prefix.
 * @return {string} Lowercase SHA-256 hex value.
 */
function normalizeBlobSha256( blobSha256 ) {
	const value = blobSha256.replace( /^sha256:/i, '' );
	if ( ! BLOB_SHA256_PATTERN.test( value ) ) {
		throw new Error( `Unsupported OCI layer digest: ${ blobSha256 }` );
	}

	return value.toLowerCase();
}

/**
 * Validate metadata used to identify and verify a compressed blob.
 *
 * @param {{ sourceSha: string, blobSha256: string, blobSize: number }} metadata Blob metadata.
 * @return {{ sourceSha: string, blobSha256: string, blobSize: number }} Normalized metadata.
 */
function normalizeMetadata( metadata ) {
	const blobSize = Number( metadata.blobSize );
	if ( ! Number.isSafeInteger( blobSize ) || blobSize < 0 ) {
		throw new Error( `Invalid OCI layer size: ${ metadata.blobSize }` );
	}

	return {
		sourceSha: normalizeSourceSha( metadata.sourceSha ),
		blobSha256: normalizeBlobSha256( metadata.blobSha256 ),
		blobSize,
	};
}

/**
 * Read metadata for the first layer of an OCI manifest.
 *
 * @param {Record<string, any>} manifest  OCI manifest.
 * @param {string}              sourceSha Immutable Gutenberg source SHA.
 * @return {{ sourceSha: string, blobSha256: string, blobSize: number }} Blob metadata.
 */
function getManifestMetadata( manifest, sourceSha ) {
	const layer = manifest?.layers?.[ 0 ];
	if ( ! layer?.digest ) {
		throw new Error( 'No layer digest found in manifest' );
	}

	return normalizeMetadata( {
		sourceSha,
		blobSha256: layer.digest,
		blobSize: layer.size,
	} );
}

/**
 * Resolve the immutable manifest used to identify a Gutenberg build.
 *
 * Mutable refs first resolve their image revision, then fetch the manifest
 * again at that immutable revision. A mutable manifest is never used for a
 * download.
 *
 * @param {{ ref: string, ghcrRepo: string, isMutable: boolean }}                          config            Gutenberg configuration.
 * @param {string}                                                                         token             GHCR pull token.
 * @param {(ref: string, ghcrRepo: string, token: string) => Promise<Record<string, any>>} [fetchManifestFn] Manifest fetcher.
 * @return {Promise<{ manifest: Record<string, any>, sourceSha: string }>} Immutable manifest and source SHA.
 */
async function resolveDownloadManifest(
	config,
	token,
	fetchManifestFn = fetchManifest
) {
	const { ref, ghcrRepo, isMutable } = config;

	if ( ! isMutable ) {
		const sourceSha = normalizeSourceSha( ref );
		return {
			manifest: await fetchManifestFn( sourceSha, ghcrRepo, token ),
			sourceSha,
		};
	}

	const mutableManifest = await fetchManifestFn( ref, ghcrRepo, token );
	const revision =
		mutableManifest?.annotations?.[ 'org.opencontainers.image.revision' ];
	if ( ! revision || ! SOURCE_SHA_PATTERN.test( revision ) ) {
		throw new Error(
			`Manifest for mutable ref "${ ref }" has no valid org.opencontainers.image.revision SHA`
		);
	}

	const sourceSha = revision.toLowerCase();
	return {
		manifest: await fetchManifestFn( sourceSha, ghcrRepo, token ),
		sourceSha,
	};
}

/**
 * Resolve the source SHA and blob metadata for a Gutenberg build.
 *
 * @param {{ ref: string, ghcrRepo: string, isMutable: boolean }}                          config            Gutenberg configuration.
 * @param {string}                                                                         token             GHCR pull token.
 * @param {(ref: string, ghcrRepo: string, token: string) => Promise<Record<string, any>>} [fetchManifestFn] Manifest fetcher.
 * @return {Promise<{ sourceSha: string, blobSha256: string, blobSize: number }>} Resolved blob metadata.
 */
async function resolveDownloadMetadata(
	config,
	token,
	fetchManifestFn = fetchManifest
) {
	const { manifest, sourceSha } = await resolveDownloadManifest(
		config,
		token,
		fetchManifestFn
	);
	return getManifestMetadata( manifest, sourceSha );
}

/**
 * Write resolved metadata to the GitHub Actions step output file.
 *
 * @param {{ sourceSha: string, blobSha256: string, blobSize: number }} metadata   Blob metadata.
 * @param {string}                                                      outputPath GitHub Actions output file.
 * @return {void}
 */
function writeMetadataOutput( metadata, outputPath ) {
	const normalized = normalizeMetadata( metadata );
	if ( ! outputPath ) {
		throw new Error(
			'GITHUB_OUTPUT is required when resolving Gutenberg metadata'
		);
	}

	fs.appendFileSync(
		outputPath,
		`source-sha=${ normalized.sourceSha }\n` +
			`blob-sha256=${ normalized.blobSha256 }\n` +
			`blob-size=${ normalized.blobSize }\n`
	);
}

/**
 * Download a blob to disk and verify its SHA-256 digest and byte count.
 *
 * @param {string}                                   url         Blob URL.
 * @param {string}                                   token       Bearer token for GHCR.
 * @param {{ blobSha256: string, blobSize: number }} metadata    Blob metadata.
 * @param {string}                                   destination Path where the compressed blob is written.
 * @return {Promise<void>} Resolves after the download is verified.
 */
async function downloadAndVerifyBlob( url, token, metadata, destination ) {
	const { blobSha256, blobSize } = normalizeMetadata( {
		sourceSha: '0'.repeat( 40 ),
		...metadata,
	} );
	const response = await fetch( url, {
		headers: {
			Authorization: `Bearer ${ token }`,
		},
		signal: AbortSignal.timeout( DOWNLOAD_TIMEOUT_MS ),
	} );

	console.log( `   Response: ${ response.status } ${ response.statusText }` );

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
	if ( Number.isFinite( contentLength ) && contentLength >= 0 ) {
		console.log( `   Content-Length: ${ formatBytes( contentLength ) }` );
	}
	console.log( `   Manifest size: ${ formatBytes( blobSize ) }` );

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
				/** @type {import('stream/web').ReadableStream} */ (
					response.body
				)
			),
			meter,
			fs.createWriteStream( destination )
		);
	} catch ( error ) {
		throw new Error(
			`Download interrupted after ${ formatBytes( downloadedBytes ) }: ${
				/** @type {Error} */ ( error ).message
			}`
		);
	}

	if (
		Number.isFinite( contentLength ) &&
		downloadedBytes !== contentLength
	) {
		throw new Error(
			`Downloaded ${ formatBytes(
				downloadedBytes
			) }, but Content-Length was ${ formatBytes( contentLength ) }`
		);
	}

	if ( downloadedBytes !== blobSize ) {
		throw new Error(
			`Downloaded ${ formatBytes(
				downloadedBytes
			) }, but manifest size was ${ formatBytes( blobSize ) }`
		);
	}

	const actualSha256 = hash.digest( 'hex' );
	if ( actualSha256 !== blobSha256 ) {
		throw new Error(
			`SHA-256 mismatch: expected ${ blobSha256 } but received ${ actualSha256 }`
		);
	}

	console.log(
		`✅ Downloaded ${ formatBytes( downloadedBytes ) } and verified SHA-256`
	);
}

/**
 * Remove partial archives left by a failed or interrupted download.
 *
 * @param {string} archivePath Stable archive path.
 * @return {void}
 */
function removePartialArchives( archivePath ) {
	const directory = path.dirname( archivePath );
	const prefix = `${ path.basename( archivePath ) }.partial-`;
	if ( ! fs.existsSync( directory ) ) {
		return;
	}

	for ( const file of fs.readdirSync( directory ) ) {
		if ( file.startsWith( prefix ) ) {
			fs.rmSync( path.join( directory, file ), { force: true } );
		}
	}
}

/**
 * Download a blob with bounded retries. Each attempt writes to a unique
 * partial path, which is renamed to the stable path only after verification.
 *
 * @param {string}                                                      url         Blob URL.
 * @param {string}                                                      token       Bearer token for GHCR.
 * @param {{ sourceSha: string, blobSha256: string, blobSize: number }} metadata    Blob metadata.
 * @param {string}                                                      archivePath Stable archive path.
 * @param {(milliseconds: number) => Promise<void>}                     [delayFn]   Retry delay function.
 * @return {Promise<string>} Path to the verified compressed blob.
 */
async function downloadBlobWithRetries(
	url,
	token,
	metadata,
	archivePath,
	delayFn = delay
) {
	const normalized = normalizeMetadata( metadata );
	fs.mkdirSync( path.dirname( archivePath ), { recursive: true } );
	fs.rmSync( archivePath, { force: true } );
	removePartialArchives( archivePath );

	for ( let attempt = 1; attempt <= MAX_DOWNLOAD_ATTEMPTS; attempt++ ) {
		const partialPath = `${ archivePath }.partial-${ process.pid }-${ attempt }`;
		console.log(
			`\n📥 Download attempt ${ attempt }/${ MAX_DOWNLOAD_ATTEMPTS }...`
		);
		fs.rmSync( partialPath, { force: true } );

		try {
			await downloadAndVerifyBlob( url, token, normalized, partialPath );
			fs.renameSync( partialPath, archivePath );
			return archivePath;
		} catch ( error ) {
			const downloadError = /** @type {Error & { status?: number }} */ (
				error
			);
			fs.rmSync( partialPath, { force: true } );
			fs.rmSync( archivePath, { force: true } );
			removePartialArchives( archivePath );
			console.error(
				`❌ Download attempt ${ attempt } failed: ${ downloadError.message }`
			);

			if (
				attempt === MAX_DOWNLOAD_ATTEMPTS ||
				! isRetryableDownloadError( downloadError )
			) {
				throw downloadError;
			}

			console.log(
				`   Retrying in ${ RETRY_DELAY_MS / 1000 } seconds...`
			);
			await delayFn( RETRY_DELAY_MS );
		}
	}

	throw new Error( 'Download failed without an error' );
}

/**
 * Verify an archive against the independently resolved blob metadata.
 *
 * @param {string}                                                      archivePath Archive path.
 * @param {{ sourceSha: string, blobSha256: string, blobSize: number }} metadata    Blob metadata.
 * @return {void}
 */
function verifyArchive( archivePath, metadata ) {
	const normalized = normalizeMetadata( metadata );
	const stat = fs.statSync( archivePath );
	if ( stat.size !== normalized.blobSize ) {
		throw new Error(
			`Archive size mismatch: expected ${ formatBytes(
				normalized.blobSize
			) } but received ${ formatBytes( stat.size ) }`
		);
	}

	const actualSha256 = crypto
		.createHash( 'sha256' )
		.update( fs.readFileSync( archivePath ) )
		.digest( 'hex' );
	if ( actualSha256 !== normalized.blobSha256 ) {
		throw new Error(
			`Archive SHA-256 mismatch: expected ${ normalized.blobSha256 } but received ${ actualSha256 }`
		);
	}
}

/**
 * Extract a verified archive into a staging directory, then replace the
 * existing Gutenberg directory.
 *
 * @param {string}                                  archivePath Path to the verified compressed blob.
 * @param {string}                                  expectedSha Expected immutable Gutenberg source SHA.
 * @param {{ root?: string, destination?: string }} [paths]     Paths used for extraction.
 * @return {Promise<void>} Resolves after extraction completes.
 */
async function extractVerifiedArchive( archivePath, expectedSha, paths = {} ) {
	const extractionRoot = paths.root || rootDir;
	const destination = paths.destination || gutenbergDir;
	const stagingDir = path.join(
		extractionRoot,
		`.gutenberg-download-${ process.pid }`
	);
	const backupDir = path.join(
		extractionRoot,
		`.gutenberg-backup-${ process.pid }`
	);
	let preserveBackup = false;
	fs.rmSync( stagingDir, { recursive: true, force: true } );
	fs.mkdirSync( stagingDir, { recursive: true } );

	try {
		const tar = spawn( 'tar', [ '-xzf', archivePath, '-C', stagingDir ], {
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

		const extractedHashPath = path.join( stagingDir, '.gutenberg-hash' );
		const extractedSha = fs
			.readFileSync( extractedHashPath, 'utf8' )
			.trim();
		if ( extractedSha !== expectedSha ) {
			throw new Error(
				`Extracted Gutenberg SHA mismatch: expected ${ expectedSha } but found ${ extractedSha }`
			);
		}

		if ( fs.existsSync( destination ) ) {
			fs.rmSync( backupDir, { recursive: true, force: true } );
			fs.renameSync( destination, backupDir );
		}

		try {
			fs.renameSync( stagingDir, destination );
		} catch ( error ) {
			if (
				fs.existsSync( backupDir ) &&
				! fs.existsSync( destination )
			) {
				try {
					fs.renameSync( backupDir, destination );
				} catch ( restoreError ) {
					preserveBackup = true;
					throw new Error(
						`Could not replace Gutenberg directory: ${
							/** @type {Error} */ ( error ).message
						}; ` +
							`could not restore the backup at ${ backupDir }: ${
								/** @type {Error} */ ( restoreError ).message
							}`
					);
				}
			}

			throw error;
		}

		fs.rmSync( backupDir, { recursive: true, force: true } );
	} finally {
		fs.rmSync( stagingDir, { recursive: true, force: true } );
		if ( ! preserveBackup ) {
			fs.rmSync( backupDir, { recursive: true, force: true } );
		}
	}
}

/**
 * Validate an existing archive or fetch one, then extract it.
 *
 * @param {{ archivePath: string, metadata: { sourceSha: string, blobSha256: string, blobSize: number }, fetchArchive?: () => Promise<void>, extractionPaths?: { root?: string, destination?: string } }} options Archive preparation options.
 * @return {Promise<void>} Resolves after the verified archive is extracted.
 */
async function prepareArchive( options ) {
	const metadata = normalizeMetadata( options.metadata );
	if ( ! fs.existsSync( options.archivePath ) ) {
		if ( ! options.fetchArchive ) {
			throw new Error(
				`Archive does not exist: ${ options.archivePath }`
			);
		}
		await options.fetchArchive();
	}

	verifyArchive( options.archivePath, metadata );
	await extractVerifiedArchive(
		options.archivePath,
		metadata.sourceSha,
		options.extractionPaths
	);
}

/**
 * Download and extract a build using a caller-provided archive path.
 *
 * @param {{ archivePath: string, metadata: { sourceSha: string, blobSha256: string, blobSize: number }, config?: { ref: string, ghcrRepo: string, isMutable: boolean }, fetchToken?: (ghcrRepo: string) => Promise<string>, blobUrl?: string, extractionPaths?: { root?: string, destination?: string }, delayFn?: (milliseconds: number) => Promise<void> }} options Download options.
 * @return {Promise<void>} Resolves after extraction completes.
 */
async function downloadAndExtract( options ) {
	const metadata = normalizeMetadata( options.metadata );
	const config = options.config || readGutenbergConfig();
	await prepareArchive( {
		archivePath: options.archivePath,
		metadata,
		extractionPaths: options.extractionPaths,
		fetchArchive: async () => {
			const token = await ( options.fetchToken || fetchGhcrToken )(
				config.ghcrRepo
			);
			const url =
				options.blobUrl ||
				`https://ghcr.io/v2/${ config.ghcrRepo }/blobs/sha256:${ metadata.blobSha256 }`;
			await downloadBlobWithRetries(
				url,
				token,
				metadata,
				options.archivePath,
				options.delayFn
			);
		},
	} );
}

/**
 * Return the temporary archive path used by no-argument downloads.
 *
 * @return {string} Temporary archive path.
 */
function getTemporaryArchivePath() {
	return path.join(
		os.tmpdir(),
		`wordpress-gutenberg-${ process.pid }.tar.gz`
	);
}

/**
 * Return the only archive path accepted by cache-mode commands.
 *
 * @param {string} archivePath Archive path supplied on the command line.
 * @return {string} Stable archive path in the checkout.
 */
function getCacheArchivePath( archivePath ) {
	const stableArchivePath = path.join(
		rootDir,
		'.ci',
		'gutenberg',
		'archive.tar.gz'
	);
	const resolvedArchivePath = path.resolve( rootDir, archivePath );
	if ( resolvedArchivePath !== stableArchivePath ) {
		throw new Error(
			'Archive mode requires the checkout path .ci/gutenberg/archive.tar.gz'
		);
	}

	return stableArchivePath;
}

/**
 * Preserve the no-argument downloader behavior, including temporary archive
 * cleanup after extraction or failure.
 *
 * @param {{ config?: { ref: string, ghcrRepo: string, isMutable: boolean }, fetchToken?: (ghcrRepo: string) => Promise<string>, fetchManifestFn?: (ref: string, ghcrRepo: string, token: string) => Promise<Record<string, any>>, blobUrl?: string, archivePath?: string, extractionPaths?: { root?: string, destination?: string }, delayFn?: (milliseconds: number) => Promise<void> }} [options] Download options.
 * @return {Promise<void>} Resolves after extraction completes.
 */
async function downloadDefault( options = {} ) {
	const config = options.config || readGutenbergConfig();
	const token = await ( options.fetchToken || fetchGhcrToken )(
		config.ghcrRepo
	);
	const metadata = await resolveDownloadMetadata(
		config,
		token,
		options.fetchManifestFn
	);
	const archivePath = options.archivePath || getTemporaryArchivePath();

	try {
		await downloadAndExtract( {
			archivePath,
			metadata,
			config,
			fetchToken: async () => token,
			blobUrl: options.blobUrl,
			extractionPaths: options.extractionPaths,
			delayFn: options.delayFn,
		} );
	} finally {
		fs.rmSync( archivePath, { force: true } );
	}
}

/**
 * Parse command-line arguments for the metadata and stable archive modes.
 *
 * @param {string[]} argv Command-line arguments.
 * @return {{ metadata: boolean, archivePath?: string, sourceSha?: string, blobSha256?: string, blobSize?: string }} Parsed arguments.
 */
function parseArguments( argv ) {
	const parsed = { metadata: false };
	for ( let index = 0; index < argv.length; index++ ) {
		const argument = argv[ index ];
		if ( argument === '--metadata' ) {
			parsed.metadata = true;
			continue;
		}

		const values = {
			'--archive': 'archivePath',
			'--source-sha': 'sourceSha',
			'--blob-sha256': 'blobSha256',
			'--blob-size': 'blobSize',
		};
		const property = values[ argument ];
		if ( ! property || ! argv[ index + 1 ] ) {
			throw new Error( `Unknown or incomplete argument: ${ argument }` );
		}
		parsed[ property ] = argv[ ++index ];
	}

	return parsed;
}

/**
 * Main execution function.
 *
 * @param {string[]} [argv] Command-line arguments.
 * @return {Promise<void>} Resolves after the requested operation completes.
 */
async function main( argv = process.argv.slice( 2 ) ) {
	const args = parseArguments( argv );
	if ( args.metadata ) {
		if (
			args.archivePath ||
			args.sourceSha ||
			args.blobSha256 ||
			args.blobSize
		) {
			throw new Error(
				'--metadata cannot be combined with archive arguments'
			);
		}

		const config = readGutenbergConfig();
		const token = await fetchGhcrToken( config.ghcrRepo );
		const metadata = await resolveDownloadMetadata( config, token );
		writeMetadataOutput( metadata, process.env.GITHUB_OUTPUT );
		return;
	}

	if (
		! args.archivePath &&
		! args.sourceSha &&
		! args.blobSha256 &&
		! args.blobSize
	) {
		await downloadDefault();
		return;
	}

	if (
		! args.archivePath ||
		! args.sourceSha ||
		! args.blobSha256 ||
		! args.blobSize
	) {
		throw new Error(
			'Archive mode requires --archive, --source-sha, --blob-sha256, and --blob-size'
		);
	}

	await downloadAndExtract( {
		archivePath: getCacheArchivePath( args.archivePath ),
		metadata: {
			sourceSha: args.sourceSha,
			blobSha256: args.blobSha256,
			blobSize: Number( args.blobSize ),
		},
	} );
}

module.exports = {
	downloadAndExtract,
	downloadBlobWithRetries,
	downloadDefault,
	extractVerifiedArchive,
	getCacheArchivePath,
	getManifestMetadata,
	getTemporaryArchivePath,
	isRetryableDownloadError,
	main,
	normalizeMetadata,
	parseArguments,
	prepareArchive,
	resolveDownloadManifest,
	resolveDownloadMetadata,
	removePartialArchives,
	verifyArchive,
	writeMetadataOutput,
};

if ( require.main === module ) {
	main().catch( ( error ) => {
		console.error( '❌ Gutenberg download failed:', error.message );
		process.exit( 1 );
	} );
}
