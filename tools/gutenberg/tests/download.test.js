#!/usr/bin/env node

/**
 * Tests for the Gutenberg download retry logic.
 *
 * Run on demand with `npm run test:tools`. These are NOT part of CI: the
 * retry paths they cover only matter when a download fails, which is rare
 * enough that a permanent gate costs more than it returns. Reach for them
 * when changing the retry logic, or when diagnosing a Gutenberg download
 * failure in CI, to confirm the recovery behavior still holds.
 *
 * Exercises `downloadArchive()` against a
 * real local HTTP server (no fetch stubbing) so that the actual
 * fetch/undici/stream/pipeline behavior is what gets verified. The retry
 * delay is linear with jitter (attempt * 1000ms + up to 250ms), not
 * exponential.
 *
 * Backoff is proven two ways at once, because either alone is escapable.
 * The "⏳ Waiting Nms" lines `downloadArchive()` prints pin the formula but
 * would still pass if the implementation logged a delay and skipped the
 * wait; the measured elapsed time is therefore also checked against the sum
 * of those reported delays. See assertBackoff().
 *
 * A separate subprocess test spawns `download.js` end-to-end against a
 * temporary fixture root, to prove `main()`'s failure path: exit code 1,
 * extraction never runs, and the temp archive is cleaned up.
 *
 * @package WordPress
 */

const { test, describe } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const http = require( 'node:http' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { spawn } = require( 'node:child_process' );

const { downloadArchive } = require( '../download.js' );

// Small payload so tests run fast, but big enough to span multiple TCP reads.
const FULL_SIZE = 256 * 1024; // 256 KB.
const FULL_BODY = Buffer.alloc( FULL_SIZE, 'a' );

// A body that completes cleanly but doesn't match the size the test will
// tell downloadArchive() to expect, triggering the size-mismatch branch.
const SHORT_SIZE = FULL_SIZE - 1000;
const SHORT_BODY = Buffer.alloc( SHORT_SIZE, 'b' );

const archivePathForThisProcess = () =>
	`${ os.tmpdir() }/wordpress-gutenberg-${ process.pid }.tar.gz`;

/** Respond 200 with the complete FULL_SIZE-byte body. */
function full() {
	return ( _req, res ) => {
		res.writeHead( 200, { 'Content-Length': String( FULL_SIZE ) } );
		res.end( FULL_BODY );
	};
}

/** Respond with a bare HTTP status and no body. */
function status( code ) {
	return ( _req, res ) => {
		res.writeHead( code );
		res.end();
	};
}

/**
 * Advertise a full-size body via Content-Length, write about a quarter of
 * it, then abruptly kill the socket. Reproduces a real "premature close" /
 * terminated fetch, which surfaces as an error with no `.status`.
 */
function truncate() {
	return ( _req, res ) => {
		res.writeHead( 200, { 'Content-Length': String( FULL_SIZE ) } );
		res.write( FULL_BODY.subarray( 0, Math.floor( FULL_SIZE / 4 ) ) );
		setTimeout( () => {
			res.socket.destroy();
		}, 20 );
	};
}

/** Respond 200 with a complete, cleanly-ended body of the "wrong" size. */
function shortBody() {
	return ( _req, res ) => {
		res.writeHead( 200, { 'Content-Length': String( SHORT_SIZE ) } );
		res.end( SHORT_BODY );
	};
}

/**
 * Start a scripted HTTP server on 127.0.0.1 with one behavior per request,
 * in order. Extra requests beyond the behaviors array get a loud 599 so a
 * bug that causes unexpected retries fails visibly instead of hanging.
 *
 * @param {Array<(req: import('http').IncomingMessage, res: import('http').ServerResponse) => void>} behaviors
 * @return {Promise<{ url: string, getRequestCount: () => number, close: () => Promise<void> }>}
 */
function createMockServer( behaviors ) {
	let requestCount = 0;
	const server = http.createServer( ( req, res ) => {
		const behavior = behaviors[ requestCount ];
		requestCount++;
		if ( ! behavior ) {
			res.writeHead( 599 );
			res.end( 'unexpected extra request' );
			return;
		}
		behavior( req, res );
	} );

	return new Promise( ( resolve, reject ) => {
		server.on( 'error', reject );
		server.listen( 0, '127.0.0.1', () => {
			const { port } = server.address();
			resolve( {
				url: `http://127.0.0.1:${ port }/blob`,
				getRequestCount: () => requestCount,
				close: () =>
					new Promise( ( res ) => server.close( () => res() ) ),
			} );
		} );
	} );
}

/**
 * Temporarily replace `console.log` and `console.error` so tests can assert
 * on exactly what `downloadArchive()` printed -- e.g. the precise backoff
 * delay, or which failure branch produced a given error line -- instead of
 * depending on wall-clock timing. Call `restore()` in a `finally` block so
 * the real console methods come back even if the code under test throws.
 *
 * @return {{ logLines: string[], errorLines: string[], restore: () => void }}
 */
function captureConsole() {
	const originalLog = console.log;
	const originalError = console.error;
	const logLines = [];
	const errorLines = [];

	console.log = ( ...args ) => {
		logLines.push( args.join( ' ' ) );
	};
	console.error = ( ...args ) => {
		errorLines.push( args.join( ' ' ) );
	};

	return {
		logLines,
		errorLines,
		restore() {
			console.log = originalLog;
			console.error = originalError;
		},
	};
}

/**
 * Extract the millisecond delays from any captured
 * "⏳ Waiting Nms before retrying..." console.log lines, in print order.
 *
 * @param {string[]} logLines
 * @return {number[]}
 */
function parseWaitDelays( logLines ) {
	return logLines
		.map( ( line ) => line.match( /⏳ Waiting (\d+)ms/ ) )
		.filter( Boolean )
		.map( ( match ) => Number( match[ 1 ] ) );
}

/**
 * Assert that the downloader both reported the expected backoff delays and
 * actually waited them.
 *
 * The reported values pin the formula (attempt * 1000 plus up to 249ms of
 * jitter). On their own they would not catch an implementation that logged a
 * delay and then skipped the wait, so the measured elapsed time is checked
 * against their sum as well.
 *
 * Only a lower bound is asserted. A slow or loaded runner can only push
 * elapsed time up, never below the sum of the sleeps that genuinely
 * happened, so this cannot fail spuriously under CI load.
 *
 * @param {number[]} waits    Delays parsed from the captured log lines.
 * @param {number}   elapsed  Measured wall-clock duration of the call, in ms.
 * @param {number}   expected How many backoff waits should have occurred.
 */
function assertBackoff( waits, elapsed, expected ) {
	assert.equal( waits.length, expected, `expected exactly ${ expected } backoff wait(s), got ${ waits.length }` );

	waits.forEach( ( wait, index ) => {
		const attempt = index + 1;
		const floor = attempt * 1000;
		assert.ok(
			wait >= floor && wait < floor + 250,
			`expected attempt-${ attempt } backoff in [${ floor }, ${ floor + 250 })ms, got ${ wait }ms`
		);
	} );

	const total = waits.reduce( ( sum, wait ) => sum + wait, 0 );
	assert.ok(
		elapsed >= total,
		`expected elapsed >= ${ total }ms (the reported delays must actually be waited), got ${ elapsed }ms`
	);
}

/*
 * isRetryableDownloadError() has no tests of its own. Each of its outcomes
 * is already proven through the real retry loop below, which is the stronger
 * check: a unit test on the predicate would still pass if the loop stopped
 * consulting it.
 *
 *   no status -> retryable    the truncated-body and size-mismatch cases
 *   >= 500    -> retryable    the 503 exhaustion case
 *   4xx       -> not retryable the 404 case
 *
 * 429 is deliberately unasserted anywhere. Whether it should be retried is
 * an open question: the original reason for excluding it was that there was
 * no backoff, and backoff has since been added. Pinning today's behavior
 * would mean a future fix has to delete a passing test first.
 */

// The tests below all share one temp archive path derived from this
// process's pid (see archivePathForThisProcess()), so they must never run
// concurrently -- concurrent runs would race on the same file.
describe( 'downloadArchive', { concurrency: false }, () => {
	test( 'truncated body then success: retries and resolves', { timeout: 30000 }, async () => {
		const mock = await createMockServer( [ truncate(), full() ] );
		const capture = captureConsole();
		try {
			const start = Date.now();
			const archivePath = await downloadArchive( mock.url, 'test-token', FULL_SIZE );
			const elapsed = Date.now() - start;

			assert.equal( archivePath, archivePathForThisProcess() );
			assert.equal( fs.statSync( archivePath ).size, FULL_SIZE );
			assert.equal( mock.getRequestCount(), 2, 'expected exactly 2 requests (1 failed + 1 succeeded)' );

			assertBackoff( parseWaitDelays( capture.logLines ), elapsed, 1 );

			// Prove this actually exercised the stream-error branch (a
			// premature socket close), not the size-mismatch branch -- both
			// look identical from the outside ("retried once, then
			// succeeded").
			assert.equal( capture.errorLines.length, 1 );
			assert.doesNotMatch(
				capture.errorLines[ 0 ],
				/Received \d+ bytes, expected \d+ bytes/,
				'attempt 1 must not have failed via the size-mismatch branch'
			);
			// Match a small set of undici error strings for a premature
			// close / aborted stream. The exact wording is not a stable
			// public contract, so this match is deliberately loose.
			assert.match(
				capture.errorLines[ 0 ],
				/terminated|Premature close|aborted|socket hang up/i,
				'attempt 1 must have failed via a stream/socket error'
			);
		} finally {
			capture.restore();
			fs.rmSync( archivePathForThisProcess(), { force: true } );
			await mock.close();
		}
	} );

	test( 'non-retryable HTTP (404): fails immediately without backoff', { timeout: 30000 }, async () => {
		const mock = await createMockServer( [ status( 404 ) ] );
		const capture = captureConsole();
		try {
			await assert.rejects(
				() => downloadArchive( mock.url, 'test-token', FULL_SIZE ),
				( error ) => {
					assert.match( error.message, /404/ );
					assert.equal( error.status, 404 );
					return true;
				}
			);

			assert.equal( mock.getRequestCount(), 1, 'expected exactly 1 request; 404 must not be retried' );

			/*
			 * A single request plus no reported wait is the whole proof that
			 * nothing was retried. An upper bound on elapsed time was tried
			 * here and removed: it is the one assertion shape that a slow
			 * runner can break on its own, and it adds nothing once the
			 * request count is pinned at 1.
			 */
			assert.deepEqual(
				parseWaitDelays( capture.logLines ),
				[],
				'a non-retryable status must not report any backoff wait'
			);
			assert.equal( fs.existsSync( archivePathForThisProcess() ), false, 'temp archive must be cleaned up' );
		} finally {
			capture.restore();
			await mock.close();
		}
	} );

	test( 'size mismatch on every attempt: exhausts retries and cleans up', { timeout: 30000 }, async () => {
		const mock = await createMockServer( [ shortBody(), shortBody(), shortBody() ] );
		const capture = captureConsole();
		try {
			const start = Date.now();
			await assert.rejects(
				() => downloadArchive( mock.url, 'test-token', FULL_SIZE ),
				( error ) => {
					assert.match( error.message, /Received \d+ bytes, expected \d+ bytes/ );
					// This branch's error must NOT carry an HTTP status.
					assert.equal( error.status, undefined );
					return true;
				}
			);
			const elapsed = Date.now() - start;

			assert.equal( mock.getRequestCount(), 3, 'expected exactly 3 requests (retries exhausted)' );

			assertBackoff( parseWaitDelays( capture.logLines ), elapsed, 2 );

			assert.equal(
				fs.existsSync( archivePathForThisProcess() ),
				false,
				'temp archive must be cleaned up after exhausting retries'
			);
		} finally {
			capture.restore();
			await mock.close();
		}
	} );

	test( 'retryable HTTP exhausts all attempts: fails after 3 tries and cleans up', { timeout: 30000 }, async () => {
		const mock = await createMockServer( [ status( 503 ), status( 503 ), status( 503 ) ] );
		const capture = captureConsole();
		try {
			const start = Date.now();
			await assert.rejects(
				() => downloadArchive( mock.url, 'test-token', FULL_SIZE ),
				( error ) => {
					assert.match( error.message, /503/ );
					// The original HTTP error must survive exhaustion, not be
					// replaced by a generic one.
					assert.equal( error.status, 503 );
					return true;
				}
			);
			const elapsed = Date.now() - start;

			assert.equal( mock.getRequestCount(), 3, 'expected exactly 3 requests (retries exhausted)' );

			assertBackoff( parseWaitDelays( capture.logLines ), elapsed, 2 );

			assert.equal(
				fs.existsSync( archivePathForThisProcess() ),
				false,
				'temp archive must be cleaned up after exhausting retries'
			);
		} finally {
			capture.restore();
			await mock.close();
		}
	} );

	/*
	 * A 204 response is `ok`, but per the fetch specification it carries a
	 * null body, which reaches the `! response.body` guard in
	 * downloadArchive(). That error is thrown without a `status` property,
	 * so it classifies as retryable and burns all three attempts. This
	 * pins that behavior, which is otherwise easy to change by accident.
	 */
	test( 'ok response with no body: retries and then fails', { timeout: 30000 }, async () => {
		const mock = await createMockServer( [ status( 204 ), status( 204 ), status( 204 ) ] );
		const capture = captureConsole();
		try {
			const start = Date.now();
			await assert.rejects(
				() => downloadArchive( mock.url, 'test-token', FULL_SIZE ),
				( error ) => {
					assert.match( error.message, /Blob response has no body/ );
					// No status means isRetryableDownloadError() returns true.
					assert.equal( error.status, undefined );
					return true;
				}
			);
			const elapsed = Date.now() - start;

			assert.equal( mock.getRequestCount(), 3, 'expected exactly 3 requests (retries exhausted)' );

			assertBackoff( parseWaitDelays( capture.logLines ), elapsed, 2 );

			assert.equal(
				fs.existsSync( archivePathForThisProcess() ),
				false,
				'temp archive must be cleaned up after exhausting retries'
			);
		} finally {
			capture.restore();
			await mock.close();
		}
	} );
} );

// Single test, but kept serialized for consistency with the rest of the
// file; the spawned subprocess also writes to a pid-derived temp path (its
// own pid, not this process's).
describe( 'main() end-to-end (subprocess)', { concurrency: false }, () => {
	test( 'exhausted download retries: exit code 1, no extraction, gutenberg dir left intact, temp file cleaned', { timeout: 60000 }, async () => {
		const fixtureRoot = fs.mkdtempSync( path.join( os.tmpdir(), 'gutenberg-download-test-' ) );
		const fixtureToolsDir = path.join( fixtureRoot, 'tools', 'gutenberg' );
		fs.mkdirSync( fixtureToolsDir, { recursive: true } );

		// Copy the real scripts under test into the fixture root.
		fs.copyFileSync(
			path.join( __dirname, '..', 'download.js' ),
			path.join( fixtureToolsDir, 'download.js' )
		);
		fs.copyFileSync(
			path.join( __dirname, '..', 'utils.js' ),
			path.join( fixtureToolsDir, 'utils.js' )
		);

		const REF = 'a'.repeat( 40 ); // 40-char hex string: treated as an immutable SHA.
		const GHCR_REPO = 'test-owner/test-repo/test-package';
		const DIGEST = 'sha256:' + 'b'.repeat( 64 );
		const LAYER_SIZE = 12345;

		fs.writeFileSync(
			path.join( fixtureRoot, 'package.json' ),
			JSON.stringify(
				{
					name: 'fixture-root',
					gutenberg: { sha: REF, ghcrRepo: GHCR_REPO },
				},
				null,
				2
			)
		);

		// Sentinel gutenberg/ directory that must survive untouched, since
		// download.js only removes/recreates it AFTER a successful download.
		const sentinelDir = path.join( fixtureRoot, 'gutenberg' );
		fs.mkdirSync( sentinelDir, { recursive: true } );
		const markerPath = path.join( sentinelDir, 'marker.txt' );
		fs.writeFileSync( markerPath, 'sentinel-marker-should-survive' );

		// Mock GHCR: token + manifest succeed; every blob request fails 503.
		let blobRequestCount = 0;
		const mockServer = http.createServer( ( req, res ) => {
			const url = new URL( req.url, 'http://127.0.0.1' );
			if ( url.pathname === '/token' ) {
				res.writeHead( 200, { 'Content-Type': 'application/json' } );
				res.end( JSON.stringify( { token: 'test-token' } ) );
				return;
			}
			if ( url.pathname === `/v2/${ GHCR_REPO }/manifests/${ REF }` ) {
				res.writeHead( 200, { 'Content-Type': 'application/json' } );
				res.end(
					JSON.stringify( {
						layers: [ { digest: DIGEST, size: LAYER_SIZE } ],
					} )
				);
				return;
			}
			if ( url.pathname === `/v2/${ GHCR_REPO }/blobs/${ DIGEST }` ) {
				blobRequestCount++;
				res.writeHead( 503 );
				res.end();
				return;
			}
			res.writeHead( 404 );
			res.end( `unexpected mock request: ${ req.method } ${ req.url }` );
		} );

		await new Promise( ( resolve, reject ) => {
			mockServer.on( 'error', reject );
			mockServer.listen( 0, '127.0.0.1', resolve );
		} );
		const mockPort = mockServer.address().port;

		// Preload script: redirect any https://ghcr.io/... fetch to the mock
		// server, leaving everything else (there is nothing else) untouched.
		const preloadPath = path.join( fixtureRoot, 'fetch-redirect-preload.js' );
		fs.writeFileSync(
			preloadPath,
			`
			const originalFetch = globalThis.fetch;
			const MOCK_ORIGIN = 'http://127.0.0.1:${ mockPort }';
			globalThis.fetch = function ( input, init ) {
				let url = typeof input === 'string' || input instanceof URL ? String( input ) : input.url;
				if ( url.startsWith( 'https://ghcr.io' ) ) {
					url = url.replace( 'https://ghcr.io', MOCK_ORIGIN );
				}
				return originalFetch( url, init );
			};
			`
		);

		let child;
		try {
			child = spawn(
				process.execPath,
				[ '--require', preloadPath, path.join( fixtureToolsDir, 'download.js' ) ],
				{ cwd: fixtureRoot }
			);

			let stdout = '';
			let stderr = '';
			child.stdout.on( 'data', ( chunk ) => {
				stdout += chunk.toString();
			} );
			child.stderr.on( 'data', ( chunk ) => {
				stderr += chunk.toString();
			} );

			const exitCode = await new Promise( ( resolve, reject ) => {
				child.on( 'error', reject );
				child.on( 'close', ( code ) => resolve( code ) );
			} );

			assert.equal( exitCode, 1, `expected exit code 1; stderr was:\n${ stderr }` );
			assert.match( stdout, /Download attempt 1\/3/ );
			assert.match( stdout, /Download attempt 2\/3/ );
			assert.match( stdout, /Download attempt 3\/3/ );
			assert.doesNotMatch( stdout, /Extracting artifact/ );
			assert.equal( blobRequestCount, 3, 'expected exactly 3 blob requests' );

			assert.equal( fs.readFileSync( markerPath, 'utf8' ), 'sentinel-marker-should-survive' );
			assert.equal(
				fs.existsSync( `${ os.tmpdir() }/wordpress-gutenberg-${ child.pid }.tar.gz` ),
				false,
				'temp archive for the child pid must be cleaned up'
			);
		} finally {
			await new Promise( ( resolve ) => mockServer.close( () => resolve() ) );
			fs.rmSync( fixtureRoot, { recursive: true, force: true } );
		}
	} );
} );
