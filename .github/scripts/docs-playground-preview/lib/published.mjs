import { REQUEST_TIMEOUT_MS } from './http.mjs';
import { SNAPSHOT_BYTES_LIMIT } from './publisher.mjs';
import {
	corsProxyUrl,
	metadataAssetName,
	playgroundUrl,
	releaseAssetUrl,
	validatePublicSnapshot,
	validateReusableMetadata,
} from './publication.mjs';

const BLUEPRINT_SCHEMA =
	'https://playground.wordpress.net/blueprint-schema.json';

/**
 * @param {Record<string, any>} asset
 * @param {(...args: any[]) => any} fetchImplementation
 */
async function fetchPublishedMetadata( asset, fetchImplementation ) {
	const response = await fetchImplementation( asset.browser_download_url, {
		signal: AbortSignal.timeout( REQUEST_TIMEOUT_MS ),
	} );
	if ( response.status !== 200 ) {
		throw new Error(
			`Public metadata returned HTTP ${ response.status }.`
		);
	}
	return response.json();
}

/**
 * @param {Record<string, any>} metadata
 * @param {string} repository
 * @param {Record<string, any>} snapshotAsset
 */
function validatePublishedFields( metadata, repository, snapshotAsset ) {
	const snapshotUrl = releaseAssetUrl( repository, snapshotAsset.name );
	if (
		snapshotAsset.browser_download_url !== snapshotUrl ||
		metadata.publication?.snapshotUrl !== snapshotUrl ||
		metadata.publication?.snapshotProxyUrl !==
			corsProxyUrl( snapshotUrl ) ||
		metadata.publication?.playgroundUrl !==
			playgroundUrl( snapshotUrl, {
				blueprintSchema: BLUEPRINT_SCHEMA,
				phpVersion: metadata.phpVersion,
				wordpressVersion: metadata.resolvedWordPressBeta.version,
			} ) ||
		typeof metadata.publication?.publishedAt !== 'string' ||
		! metadata.publication.publishedAt.endsWith( 'Z' ) ||
		Number.isNaN( Date.parse( metadata.publication.publishedAt ) )
	) {
		throw new Error( 'Published preview pointer metadata is invalid.' );
	}
}

/**
 * @param {string} repository
 * @param {number} pullRequestNumber
 * @param {Record<string, any>} metadataAsset
 * @param {Record<string, any>} snapshotAsset
 * @param {Record<string, any>} options
 */
export async function loadPublishedPreview(
	repository,
	pullRequestNumber,
	metadataAsset,
	snapshotAsset,
	options
) {
	const metadata = validateReusableMetadata(
		await fetchPublishedMetadata(
			metadataAsset,
			options.fetchImplementation
		),
		{
			sourceRepository: options.sourceRepository,
			pullRequestNumber,
			sourceSha: options.sourceSha,
			maximumBytes: SNAPSHOT_BYTES_LIMIT,
		}
	);
	if (
		metadataAsset.name !== metadataAssetName( metadata.snapshotFilename ) ||
		snapshotAsset.name !== metadata.snapshotFilename
	) {
		throw new Error( 'Published release asset names are invalid.' );
	}
	validatePublishedFields( metadata, repository, snapshotAsset );
	await validatePublicSnapshot(
		snapshotAsset.browser_download_url,
		{
			bytes: metadata.snapshotBytes,
			sha256: metadata.snapshotSha256,
			maximumBytes: SNAPSHOT_BYTES_LIMIT,
		},
		options.fetchImplementation
	);
	return metadata;
}

/**
 * @param {Record<string, any>} session
 */
export async function findPreviousPreview( session, excluded = new Set() ) {
	const release = await session.api.getRelease();
	if ( ! release ) {
		return null;
	}
	const assets = await session.api.listReleaseAssets( release.id );
	const prefix = `code-reference-pr-${ session.context.pullRequestNumber }-`;
	const candidates = assets
		.filter(
			/**
			 * @param {Record<string, any>} asset
			 */
			( asset ) =>
				asset.name.startsWith( prefix ) &&
				asset.name.endsWith( '.json' ) &&
				! excluded.has( asset.name )
		)
		.sort(
			/** @param {Record<string, any>} left @param {Record<string, any>} right */ (
				left,
				right
			) => right.created_at.localeCompare( left.created_at )
		);
	for ( const metadataAsset of candidates ) {
		try {
			const published = await fetchPublishedMetadata(
				metadataAsset,
				session.fetchImplementation
			);
			const snapshotAsset = assets.find(
				/**
				 * @param {Record<string, any>} asset
				 */
				( asset ) =>
					asset.name === published.snapshotFilename &&
					! excluded.has( asset.name )
			);
			if ( ! snapshotAsset ) {
				throw new Error( 'Published snapshot asset is missing.' );
			}
			return await loadPublishedPreview(
				session.repository,
				session.context.pullRequestNumber,
				metadataAsset,
				snapshotAsset,
				{
					sourceRepository: published.sourceRepository,
					sourceSha: published.sourceSha,
					fetchImplementation: session.fetchImplementation,
				}
			);
		} catch ( error ) {
			session.warning(
				`Cannot retain ${ metadataAsset.name }: ${
					error instanceof Error ? error.message : String( error )
				}`
			);
		}
	}
	return null;
}
