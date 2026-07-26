#!/usr/bin/env node

/**
 * Tests for the Gutenberg archive downloader.
 *
 * @package
 */

const { test, describe } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const crypto = require( 'node:crypto' );
const fs = require( 'node:fs' );
const http = require( 'node:http' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );
const {
	downloadAndExtract,
	downloadBlobWithRetries,
	downloadDefault,
	getManifestMetadata,
	main,
	resolveDownloadManifest,
	writeMetadataOutput,
} = require( '../download.js' );

const SOURCE_SHA = 'a'.repeat( 40 );
const IMMUTABLE_SHA = 'b'.repeat( 40 );

/**
 * Create a disposable directory.
 *
 * @return {string} Fixture directory.
 */
function createFixture() {
	return fs.mkdtempSync(
		path.join( os.tmpdir(), 'gutenberg-download-test-' )
	);
}

/**
 * Calculate a SHA-256 digest.
 *
 * @param {Buffer} value Content to hash.
 * @return {string} SHA-256 hex digest.
 */
function sha256( value ) {
	return crypto.createHash( 'sha256' ).update( value ).digest( 'hex' );
}

/**
 * Create an archive whose extraction has the expected Gutenberg SHA.
 *
 * @param {string} fixture   Fixture directory.
 * @param {string} sourceSha Expected source SHA.
 * @return {{ archivePath: string, body: Buffer, metadata: { sourceSha: string, blobSha256: string, blobSize: number } }} Archive fixture.
 */
function createArchive( fixture, sourceSha = SOURCE_SHA ) {
	const sourceDir = path.join( fixture, 'source' );
	const archivePath = path.join( fixture, 'build.tar.gz' );
	fs.mkdirSync( sourceDir );
	fs.writeFileSync(
		path.join( sourceDir, '.gutenberg-hash' ),
		`${ sourceSha }\n`
	);
	fs.writeFileSync(
		path.join( sourceDir, 'build.txt' ),
		'Gutenberg build fixture\n'
	);

	const result = spawnSync( 'tar', [
		'-czf',
		archivePath,
		'-C',
		sourceDir,
		'.',
	] );
	assert.equal( result.status, 0, result.stderr.toString() );

	const body = fs.readFileSync( archivePath );
	return {
		archivePath,
		body,
		metadata: {
			sourceSha,
			blobSha256: sha256( body ),
			blobSize: body.length,
		},
	};
}

/**
 * Start a local HTTP server with scripted responses.
 *
 * @param {Array<(res: import('http').ServerResponse) => void>} responses Response handlers.
 * @return {Promise<{ url: string, requests: () => number, close: () => Promise<void> }>} Server controls.
 */
function createServer( responses ) {
	let count = 0;
	const server = http.createServer( ( _request, response ) => {
		const handler = responses[ count++ ];
		if ( ! handler ) {
			response.writeHead( 599 );
			response.end();
			return;
		}
		handler( response );
	} );

	return new Promise( ( resolve, reject ) => {
		server.on( 'error', reject );
		server.listen( 0, '127.0.0.1', () => {
			const address = /** @type {import('net').AddressInfo} */ (
				server.address()
			);
			resolve( {
				url: `http://127.0.0.1:${ address.port }/blob`,
				requests: () => count,
				close: () => new Promise( ( done ) => server.close( done ) ),
			} );
		} );
	} );
}

/**
 * Send an archive response.
 *
 * @param {Buffer} body Archive bytes.
 * @return {(res: import('http').ServerResponse) => void} Response handler.
 */
function archiveResponse( body ) {
	return ( response ) => {
		response.writeHead( 200, { 'Content-Length': String( body.length ) } );
		response.end( body );
	};
}

describe( 'metadata resolution', { concurrency: false }, () => {
	test( 'writes strict immutable metadata to the step output file', async () => {
		const fixture = createFixture();
		const outputPath = path.join( fixture, 'github-output' );
		try {
			const manifest = {
				layers: [
					{ digest: `sha256:${ 'C'.repeat( 64 ) }`, size: 123 },
				],
			};
			const metadata = getManifestMetadata(
				manifest,
				SOURCE_SHA.toUpperCase()
			);
			writeMetadataOutput( metadata, outputPath );

			assert.equal(
				fs.readFileSync( outputPath, 'utf8' ),
				`source-sha=${ SOURCE_SHA }\nblob-sha256=${ 'c'.repeat(
					64
				) }\nblob-size=123\n`
			);
		} finally {
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );

	test( 'uses immutable refs directly and resolves mutable refs again by revision', async () => {
		const calls = [];
		const fetchManifest = async ( ref ) => {
			calls.push( ref );
			if ( ref === 'trunk' ) {
				return {
					annotations: {
						'org.opencontainers.image.revision':
							IMMUTABLE_SHA.toUpperCase(),
					},
				};
			}
			return { layers: [] };
		};

		const immutable = await resolveDownloadManifest(
			{ ref: SOURCE_SHA, ghcrRepo: 'owner/package', isMutable: false },
			'token',
			fetchManifest
		);
		assert.equal( immutable.sourceSha, SOURCE_SHA );
		assert.deepEqual( calls, [ SOURCE_SHA ] );

		calls.length = 0;
		const mutable = await resolveDownloadManifest(
			{ ref: 'trunk', ghcrRepo: 'owner/package', isMutable: true },
			'token',
			fetchManifest
		);
		assert.equal( mutable.sourceSha, IMMUTABLE_SHA );
		assert.deepEqual( calls, [ 'trunk', IMMUTABLE_SHA ] );
	} );

	test( 'rejects a mutable ref without a valid immutable manifest', async () => {
		await assert.rejects(
			() =>
				resolveDownloadManifest(
					{
						ref: 'trunk',
						ghcrRepo: 'owner/package',
						isMutable: true,
					},
					'token',
					async ( ref ) => {
						if ( ref === 'trunk' ) {
							return {
								annotations: {
									'org.opencontainers.image.revision':
										IMMUTABLE_SHA,
								},
							};
						}
						const error = new Error(
							'immutable manifest unavailable'
						);
						error.status = 404;
						throw error;
					}
				),
			/immutable manifest unavailable/
		);
	} );
} );

describe( 'archive verification', { concurrency: false }, () => {
	test( 'rejects a poisoned supplied archive before extraction', async () => {
		const fixture = createFixture();
		const archive = createArchive( fixture );
		const destination = path.join( fixture, 'gutenberg' );
		const markerPath = path.join( destination, 'marker' );
		try {
			fs.mkdirSync( destination );
			fs.writeFileSync( markerPath, 'existing Gutenberg directory' );
			await assert.rejects(
				() =>
					downloadAndExtract( {
						archivePath: archive.archivePath,
						metadata: {
							...archive.metadata,
							blobSha256: '0'.repeat( 64 ),
						},
						config: {
							ref: SOURCE_SHA,
							ghcrRepo: 'owner/package',
							isMutable: false,
						},
						extractionPaths: { root: fixture, destination },
					} ),
				/Archive SHA-256 mismatch/
			);
			assert.equal(
				fs.readFileSync( markerPath, 'utf8' ),
				'existing Gutenberg directory'
			);

			await assert.rejects(
				() =>
					downloadAndExtract( {
						archivePath: archive.archivePath,
						metadata: {
							...archive.metadata,
							blobSize: archive.metadata.blobSize + 1,
						},
						config: {
							ref: SOURCE_SHA,
							ghcrRepo: 'owner/package',
							isMutable: false,
						},
						extractionPaths: { root: fixture, destination },
					} ),
				/Archive size mismatch/
			);
			assert.equal(
				fs.readFileSync( markerPath, 'utf8' ),
				'existing Gutenberg directory'
			);
		} finally {
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );
} );

describe( 'archive path validation', { concurrency: false }, () => {
	test( 'rejects an archive path outside the checkout', async () => {
		await assert.rejects(
			() =>
				main( [
					'--archive',
					'../archive.tar.gz',
					'--source-sha',
					SOURCE_SHA,
					'--blob-sha256',
					'0'.repeat( 64 ),
					'--blob-size',
					'1',
				] ),
			/Archive mode requires the checkout path/
		);
	} );
} );

describe( 'archive preparation', { concurrency: false }, () => {
	test( 'fetches a cold archive at the stable path, verifies it, and extracts it', async () => {
		const fixture = createFixture();
		const archive = createArchive( fixture );
		const stablePath = path.join(
			fixture,
			'.ci',
			'gutenberg',
			'archive.tar.gz'
		);
		const destination = path.join( fixture, 'gutenberg' );
		const server = await createServer( [
			archiveResponse( archive.body ),
		] );
		try {
			fs.mkdirSync( path.dirname( stablePath ), { recursive: true } );
			fs.writeFileSync(
				`${ stablePath }.partial-interrupted`,
				'stale partial archive'
			);
			await downloadAndExtract( {
				archivePath: stablePath,
				metadata: archive.metadata,
				config: {
					ref: SOURCE_SHA,
					ghcrRepo: 'owner/package',
					isMutable: false,
				},
				fetchToken: async () => 'token',
				blobUrl: server.url,
				extractionPaths: { root: fixture, destination },
				delayFn: async () => {},
			} );

			assert.equal( server.requests(), 1 );
			assert.equal( fs.existsSync( stablePath ), true );
			assert.equal(
				fs.existsSync( `${ stablePath }.partial-interrupted` ),
				false
			);
			assert.equal(
				fs
					.readFileSync(
						path.join( destination, '.gutenberg-hash' ),
						'utf8'
					)
					.trim(),
				SOURCE_SHA
			);
		} finally {
			await server.close();
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );

	test( 'validates and extracts a supplied archive without requesting a token', async () => {
		const fixture = createFixture();
		const archive = createArchive( fixture );
		const destination = path.join( fixture, 'gutenberg' );
		try {
			await downloadAndExtract( {
				archivePath: archive.archivePath,
				metadata: archive.metadata,
				config: {
					ref: SOURCE_SHA,
					ghcrRepo: 'owner/package',
					isMutable: false,
				},
				fetchToken: async () => {
					throw new Error( 'a supplied archive must not fetch' );
				},
				extractionPaths: { root: fixture, destination },
			} );

			assert.equal( fs.existsSync( archive.archivePath ), true );
			assert.equal(
				fs
					.readFileSync(
						path.join( destination, '.gutenberg-hash' ),
						'utf8'
					)
					.trim(),
				SOURCE_SHA
			);
		} finally {
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );
} );

describe( 'download retries and cleanup', { concurrency: false }, () => {
	test( 'retries a transient failure and leaves no partial archive', async () => {
		const fixture = createFixture();
		const archive = createArchive( fixture );
		const stablePath = path.join(
			fixture,
			'.ci',
			'gutenberg',
			'archive.tar.gz'
		);
		const server = await createServer( [
			( response ) => {
				response.writeHead( 503 );
				response.end();
			},
			archiveResponse( archive.body ),
		] );
		try {
			await downloadBlobWithRetries(
				server.url,
				'token',
				archive.metadata,
				stablePath,
				async () => {}
			);

			assert.equal( server.requests(), 2 );
			assert.equal( fs.existsSync( stablePath ), true );
			assert.deepEqual(
				fs
					.readdirSync( path.dirname( stablePath ) )
					.filter( ( file ) => file.includes( '.partial-' ) ),
				[]
			);
		} finally {
			await server.close();
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );

	test( 'removes partial and stable paths after retry exhaustion', async () => {
		const fixture = createFixture();
		const stablePath = path.join(
			fixture,
			'.ci',
			'gutenberg',
			'archive.tar.gz'
		);
		const server = await createServer(
			Array.from( { length: 3 }, () => ( response ) => {
				response.writeHead( 503 );
				response.end();
			} )
		);
		try {
			await assert.rejects(
				() =>
					downloadBlobWithRetries(
						server.url,
						'token',
						{
							sourceSha: SOURCE_SHA,
							blobSha256: '0'.repeat( 64 ),
							blobSize: 1,
						},
						stablePath,
						async () => {}
					),
				/503/
			);
			assert.equal( server.requests(), 3 );
			assert.equal( fs.existsSync( stablePath ), false );
			assert.deepEqual(
				fs
					.readdirSync( path.dirname( stablePath ) )
					.filter( ( file ) => file.includes( '.partial-' ) ),
				[]
			);
		} finally {
			await server.close();
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );

	test( 'removes partial and stable paths after digest mismatches', async () => {
		const fixture = createFixture();
		const archive = createArchive( fixture );
		const stablePath = path.join(
			fixture,
			'.ci',
			'gutenberg',
			'archive.tar.gz'
		);
		const server = await createServer( [
			archiveResponse( archive.body ),
			archiveResponse( archive.body ),
			archiveResponse( archive.body ),
		] );
		try {
			await assert.rejects(
				() =>
					downloadBlobWithRetries(
						server.url,
						'token',
						{
							...archive.metadata,
							blobSha256: '0'.repeat( 64 ),
						},
						stablePath,
						async () => {}
					),
				/SHA-256 mismatch/
			);
			assert.equal( server.requests(), 3 );
			assert.equal( fs.existsSync( stablePath ), false );
			assert.deepEqual(
				fs
					.readdirSync( path.dirname( stablePath ) )
					.filter( ( file ) => file.includes( '.partial-' ) ),
				[]
			);
		} finally {
			await server.close();
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );
} );

describe( 'default downloader', { concurrency: false }, () => {
	test( 'removes its temporary archive after a successful extraction', async () => {
		const fixture = createFixture();
		const archive = createArchive( fixture );
		const temporaryPath = path.join( fixture, 'temporary.tar.gz' );
		const destination = path.join( fixture, 'gutenberg' );
		const server = await createServer( [
			archiveResponse( archive.body ),
		] );
		try {
			await downloadDefault( {
				config: {
					ref: SOURCE_SHA,
					ghcrRepo: 'owner/package',
					isMutable: false,
				},
				fetchToken: async () => 'token',
				fetchManifestFn: async () => ( {
					layers: [
						{
							digest: `sha256:${ archive.metadata.blobSha256 }`,
							size: archive.metadata.blobSize,
						},
					],
				} ),
				blobUrl: server.url,
				archivePath: temporaryPath,
				extractionPaths: { root: fixture, destination },
				delayFn: async () => {},
			} );

			assert.equal( fs.existsSync( temporaryPath ), false );
			assert.equal(
				fs.existsSync( path.join( destination, '.gutenberg-hash' ) ),
				true
			);
		} finally {
			await server.close();
			fs.rmSync( fixture, { recursive: true, force: true } );
		}
	} );
} );
