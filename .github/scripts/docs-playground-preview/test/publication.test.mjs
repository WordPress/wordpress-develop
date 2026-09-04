import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

import {
	PLAYGROUND_ORIGIN,
	createLaunchBlueprint,
	createReuseHandoff,
	metadataAssetName,
	playgroundUrl,
	releaseAssetUrl,
	snapshotAssetName,
	validatePublicSnapshot,
	validateReusableMetadata,
} from '../lib/publication.mjs';
import { findReusablePreview } from '../reuse.mjs';

const identity = {
	repository: 'WordPress/wordpress-develop',
	sourceRepository: 'contributor/wordpress-develop',
	pullRequestNumber: 123,
	sourceSha: 'a'.repeat( 40 ),
	workflowRunId: '456',
	workflowRunAttempt: 1,
	runUrl: 'https://github.com/WordPress/wordpress-develop/actions/runs/456/attempts/1',
	maximumBytes: 104857600,
};

function published( bytes = Buffer.from( 'snapshot' ) ) {
	const snapshotFilename = snapshotAssetName( identity );
	return {
		schemaVersion: 1,
		sourceRepository: identity.sourceRepository,
		pullRequestNumber: identity.pullRequestNumber,
		sourceSha: identity.sourceSha,
		workflowRunId: identity.workflowRunId,
		workflowRunAttempt: identity.workflowRunAttempt,
		runUrl: identity.runUrl,
		resolvedWordPressBeta: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
		phpVersion: '8.4',
		dependencyManifestDigest: 'b'.repeat( 64 ),
		snapshotFilename,
		snapshotBytes: bytes.byteLength,
		snapshotSha256: createHash( 'sha256' ).update( bytes ).digest( 'hex' ),
		buildStatus: 'success',
		validationStatus: 'passed',
		validationFailures: [],
		generationTimestamp: '2026-08-09T12:34:56.000Z',
	};
}

/**
 * @param {Record<string, string>} values
 */
function headers( values ) {
	return {
		get: /** @param {string} name */ ( name ) =>
			values[ name.toLowerCase() ] || null,
	};
}

test( 'immutable asset names bind the PR, SHA, run, and attempt', () => {
	const snapshot = snapshotAssetName( identity );
	assert.equal(
		snapshot,
		`code-reference-pr-123-${ 'a'.repeat( 40 ) }-456-1.zip`
	);
	assert.equal(
		metadataAssetName( snapshot ),
		`code-reference-pr-123-${ 'a'.repeat( 40 ) }-456-1.json`
	);
	assert.throws(
		() => snapshotAssetName( { ...identity, sourceSha: 'trunk' } ),
		/full lowercase commit/
	);
} );

test( 'the launch Blueprint opens the snapshot at the reference index', () => {
	const snapshot = snapshotAssetName( identity );
	const publicUrl = releaseAssetUrl( identity.repository, snapshot );
	const runtime = {
		blueprintSchema:
			'https://playground.wordpress.net/blueprint-schema.json',
		phpVersion: '8.4',
		wordpressVersion: '7.2-beta1',
	};
	const blueprint = createLaunchBlueprint( publicUrl, runtime );
	assert.equal( blueprint.landingPage, '/reference/' );
	assert.equal( blueprint.login, false );
	assert.equal( blueprint.features.networking, false );
	assert.equal( blueprint.steps[ 0 ].extractToPath, '/' );
	assert.match(
		blueprint.steps[ 0 ].zipFile.url,
		/^https:\/\/wordpress-playground-cors-proxy\.net\/https:\/\/github\.com\//
	);
	assert.match(
		decodeURIComponent( playgroundUrl( publicUrl, runtime ) ),
		/"landingPage":"\/reference\/"/
	);
} );

test( 'public delivery is checked by status, headers, size, and digest', async () => {
	const bytes = Buffer.from( 'snapshot' );
	const expected = published( bytes );
	const result = await validatePublicSnapshot(
		releaseAssetUrl( identity.repository, expected.snapshotFilename ),
		{
			bytes: expected.snapshotBytes,
			sha256: expected.snapshotSha256,
			maximumBytes: identity.maximumBytes,
		},
		async ( url, options ) => {
			assert.match( url, /wordpress-playground-cors-proxy/ );
			assert.equal( options.headers.Origin, PLAYGROUND_ORIGIN );
			return {
				status: 200,
				headers: headers( {
					'x-playground-cors-proxy': 'true',
					'access-control-allow-origin': PLAYGROUND_ORIGIN,
				} ),
				arrayBuffer: async () => bytes,
			};
		}
	);
	assert.equal( result.sha256, expected.snapshotSha256 );

	await assert.rejects(
		validatePublicSnapshot(
			'https://github.com/WordPress/wordpress-develop/releases/download/x/y.zip',
			{
				bytes: bytes.byteLength,
				sha256: '0'.repeat( 64 ),
				maximumBytes: identity.maximumBytes,
			},
			async () => ( {
				status: 200,
				headers: headers( {
					'x-playground-cors-proxy': 'true',
					'access-control-allow-origin': PLAYGROUND_ORIGIN,
				} ),
				arrayBuffer: async () => bytes,
			} )
		),
		/digest/
	);
} );

test( 'reusable metadata must identify a passed same-PR snapshot', () => {
	const metadata = published();
	assert.equal( validateReusableMetadata( metadata, identity ), metadata );
	assert.throws(
		() =>
			validateReusableMetadata(
				{ ...metadata, validationStatus: 'failed' },
				identity
			),
		/valid snapshot/
	);
	assert.throws(
		() =>
			validateReusableMetadata(
				{ ...metadata, snapshotBytes: 104857601 },
				identity
			),
		/identity is invalid/
	);
} );

test( 'runtime identity accepts the stable fallback channel', () => {
	const metadata = published();
	const stable = {
		...metadata,
		resolvedWordPressBeta: {
			channel: 'stable',
			version: '7.1.2',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.1.2.zip',
		},
	};
	assert.equal( validateReusableMetadata( stable, identity ), stable );
	assert.throws(
		() =>
			validateReusableMetadata(
				{
					...stable,
					resolvedWordPressBeta: {
						...stable.resolvedWordPressBeta,
						channel: 'nightly',
					},
				},
				identity
			),
		/runtime identity/
	);
	assert.throws(
		() =>
			validateReusableMetadata(
				{
					...stable,
					resolvedWordPressBeta: {
						channel: 'stable',
						version: '7.2-beta1',
						downloadUrl:
							'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
					},
				},
				identity
			),
		/runtime identity/
	);
} );

test( 'same-SHA discovery verifies metadata and the proxied snapshot', async () => {
	const bytes = Buffer.from( 'snapshot' );
	const metadata = published( bytes );
	const metadataName = metadataAssetName( metadata.snapshotFilename );
	const assets = [
		{
			name: metadataName,
			created_at: '2026-08-09T13:00:00Z',
			browser_download_url: 'https://github.com/metadata.json',
		},
		{
			name: metadata.snapshotFilename,
			created_at: '2026-08-09T13:00:00Z',
			browser_download_url: 'https://github.com/snapshot.zip',
		},
	];
	const fetchImplementation = /** @param {string} url */ async ( url ) => {
		if ( url.includes( '/releases/tags/' ) ) {
			return { ok: true, status: 200, json: async () => ( { id: 9 } ) };
		}
		if ( url.includes( '/releases/9/assets' ) ) {
			return { ok: true, status: 200, json: async () => assets };
		}
		if ( url === 'https://github.com/metadata.json' ) {
			return { status: 200, json: async () => metadata };
		}
		assert.match( url, /wordpress-playground-cors-proxy/ );
		return {
			status: 200,
			headers: headers( {
				'x-playground-cors-proxy': 'true',
				'access-control-allow-origin': PLAYGROUND_ORIGIN,
			} ),
			arrayBuffer: async () => bytes,
		};
	};
	const handoff = await findReusablePreview( {
		...identity,
		token: 'read-token',
		fetchImplementation,
	} );
	assert.ok( handoff );
	assert.equal( handoff.handoffType, 'reuse' );
	assert.equal( handoff.workflowRunId, identity.workflowRunId );
	assert.equal( handoff.reusedSnapshot.assetName, metadata.snapshotFilename );
	assert.equal( handoff.reusedSnapshot.metadataAssetName, metadataName );
	assert.deepEqual(
		createReuseHandoff( metadata, identity ).reusedSnapshot,
		handoff.reusedSnapshot
	);
} );

test( 'the proxied snapshot read carries a deadline', async () => {
	const bytes = Buffer.from( 'snapshot' );
	const expected = published( bytes );
	/** @type {any} */
	let signal;
	await validatePublicSnapshot(
		releaseAssetUrl( identity.repository, expected.snapshotFilename ),
		{
			bytes: expected.snapshotBytes,
			sha256: expected.snapshotSha256,
			maximumBytes: identity.maximumBytes,
		},
		/** @param {string} _url @param {any} options */
		async ( _url, options ) => {
			signal = options.signal;
			return {
				status: 200,
				headers: headers( {
					'x-playground-cors-proxy': 'true',
					'access-control-allow-origin': PLAYGROUND_ORIGIN,
				} ),
				arrayBuffer: async () => bytes,
			};
		}
	);
	assert.ok( signal instanceof AbortSignal );
	assert.equal( signal.aborted, false );
} );

test( 'a release deleted mid-scan ends the reuse check without a crash', async () => {
	const fetchImplementation = /** @param {string} url */ async ( url ) => {
		if ( url.includes( '/releases/tags/' ) ) {
			return { ok: true, status: 200, json: async () => ( { id: 9 } ) };
		}
		assert.match( url, /\/releases\/9\/assets/ );
		return { ok: false, status: 404, json: async () => ( {} ) };
	};
	assert.equal(
		await findReusablePreview( {
			...identity,
			token: 'read-token',
			fetchImplementation,
		} ),
		null
	);
} );

test( 'reuse refuses to run without a usable size boundary', async () => {
	const fetchImplementation = async () => {
		assert.fail( 'No request may be made without a size boundary.' );
	};
	for ( const maximumBytes of [ undefined, NaN, 0, -1, 1.5, '100' ] ) {
		await assert.rejects(
			findReusablePreview( {
				...identity,
				maximumBytes,
				token: 'read-token',
				fetchImplementation,
			} ),
			/maximum snapshot size/
		);
	}
} );
