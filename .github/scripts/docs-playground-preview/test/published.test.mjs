import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

import {
	findPreviousPreview,
	loadPublishedPreview,
} from '../lib/published.mjs';
import {
	PLAYGROUND_ORIGIN,
	corsProxyUrl,
	metadataAssetName,
	playgroundUrl,
	releaseAssetUrl,
	snapshotAssetName,
} from '../lib/publication.mjs';

const repository = 'WordPress/wordpress-develop';
const sourceRepository = 'contributor/wordpress-develop';
const pullRequestNumber = 123;
const sourceSha = 'a'.repeat( 40 );
const bytes = Buffer.from( 'snapshot' );

function published() {
	const snapshotFilename = snapshotAssetName( {
		pullRequestNumber,
		sourceSha,
		workflowRunId: 456,
		workflowRunAttempt: 1,
	} );
	const snapshotUrl = releaseAssetUrl( repository, snapshotFilename );
	return {
		schemaVersion: 1,
		sourceRepository,
		pullRequestNumber,
		sourceSha,
		workflowRunId: '456',
		workflowRunAttempt: 1,
		runUrl: `https://github.com/${ repository }/actions/runs/456`,
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
		generationTimestamp: '2026-08-09T12:34:56.000Z',
		publication: {
			snapshotUrl,
			snapshotProxyUrl: corsProxyUrl( snapshotUrl ),
			playgroundUrl: playgroundUrl( snapshotUrl, {
				blueprintSchema:
					'https://playground.wordpress.net/blueprint-schema.json',
				phpVersion: '8.4',
				wordpressVersion: '7.2-beta1',
			} ),
			publishedAt: '2026-08-09T12:35:00.000Z',
		},
	};
}

/**
 * @param {Record<string, any>} metadata
 */
function assets( metadata ) {
	return {
		metadataAsset: {
			name: metadataAssetName( metadata.snapshotFilename ),
			browser_download_url: `https://github.com/metadata/${ metadata.snapshotFilename }.json`,
			created_at: '2026-08-09T12:35:00Z',
		},
		snapshotAsset: {
			name: metadata.snapshotFilename,
			browser_download_url: releaseAssetUrl(
				repository,
				metadata.snapshotFilename
			),
			created_at: '2026-08-09T12:34:59Z',
		},
	};
}

function headers() {
	/** @type {Record<string, string>} */
	const values = {
		'x-playground-cors-proxy': 'true',
		'access-control-allow-origin': PLAYGROUND_ORIGIN,
	};
	return {
		get: /** @param {string} name */ ( name ) =>
			values[ name.toLowerCase() ] || null,
	};
}

/**
 * @param {Record<string, any>} metadata
 * @param {Record<string, any>} metadataAsset
 */
function publicFetch( metadata, metadataAsset ) {
	return /** @param {string} url */ async ( url ) => {
		if ( url === metadataAsset.browser_download_url ) {
			return { status: 200, json: async () => metadata };
		}
		assert.equal( url, metadata.publication.snapshotProxyUrl );
		return {
			status: 200,
			headers: headers(),
			arrayBuffer: async () => bytes,
		};
	};
}

test( 'a published preview is accepted only through its public delivery path', async () => {
	const metadata = published();
	const { metadataAsset, snapshotAsset } = assets( metadata );
	assert.equal(
		await loadPublishedPreview(
			repository,
			pullRequestNumber,
			metadataAsset,
			snapshotAsset,
			{
				sourceRepository,
				sourceSha,
				fetchImplementation: publicFetch( metadata, metadataAsset ),
			}
		),
		metadata
	);
} );

test( 'a published preview rejects invalid pointer identity and proxy bytes', async () => {
	const metadata = published();
	const { metadataAsset, snapshotAsset } = assets( metadata );
	await assert.rejects(
		loadPublishedPreview(
			repository,
			pullRequestNumber,
			metadataAsset,
			snapshotAsset,
			{
				sourceRepository,
				sourceSha,
				fetchImplementation: publicFetch(
					{
						...metadata,
						publication: {
							...metadata.publication,
							publishedAt: '2026-08-09T12:35:00+04:00',
						},
					},
					metadataAsset
				),
			}
		),
		/pointer metadata/
	);

	await assert.rejects(
		loadPublishedPreview(
			repository,
			pullRequestNumber,
			metadataAsset,
			snapshotAsset,
			{
				sourceRepository,
				sourceSha,
				fetchImplementation: /** @param {string} url */ async (
					url
				) => {
					if ( url === metadataAsset.browser_download_url ) {
						return { status: 200, json: async () => metadata };
					}
					return {
						status: 200,
						headers: headers(),
						arrayBuffer: async () => Buffer.from( 'changed' ),
					};
				},
			}
		),
		/bytes/
	);
} );

test( 'previous-preview discovery skips broken and excluded assets', async () => {
	const metadata = published();
	const { metadataAsset, snapshotAsset } = assets( metadata );
	const broken = {
		name: `code-reference-pr-${ pullRequestNumber }-${ 'c'.repeat(
			40
		) }-999-1.json`,
		browser_download_url: 'https://github.com/metadata/broken.json',
		created_at: '2026-08-09T12:36:00Z',
	};
	/** @type {any[]} */
	const warnings = [];
	const session = {
		repository,
		context: { pullRequestNumber },
		warning: /** @param {string} message */ ( message ) =>
			warnings.push( message ),
		fetchImplementation: /** @param {string} url */ async ( url ) => {
			if ( url === broken.browser_download_url ) {
				return { status: 404 };
			}
			return publicFetch( metadata, metadataAsset )( url );
		},
		api: {
			getRelease: async () => ( { id: 1 } ),
			listReleaseAssets: async () => [
				broken,
				metadataAsset,
				snapshotAsset,
			],
		},
	};
	assert.equal( await findPreviousPreview( session ), metadata );
	assert.equal( warnings.length, 1 );
	assert.equal(
		await findPreviousPreview(
			session,
			new Set( [ metadataAsset.name, snapshotAsset.name ] )
		),
		null
	);
} );
