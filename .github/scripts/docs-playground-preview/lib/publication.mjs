import { createHash } from 'node:crypto';

export const RELEASE_TAG = 'code-reference-playground-preview';
export const COMMENT_MARKER = '<!-- code-reference-docs-preview -->';
export const PLAYGROUND_ORIGIN = 'https://playground.wordpress.net';
export const CORS_PROXY = 'https://wordpress-playground-cors-proxy.net/';
export const SNAPSHOT_BYTES_LIMIT = 104857600;

const FULL_COMMIT = /^[0-9a-f]{40}$/;
const FULL_DIGEST = /^[0-9a-f]{64}$/;
const REPOSITORY = /^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/;

function positiveInteger( value, label ) {
	const number = Number( value );
	if ( ! Number.isSafeInteger( number ) || number < 1 ) {
		throw new Error( `${ label } must be a positive integer.` );
	}
	return number;
}

function assertRepository( repository, label ) {
	if ( typeof repository !== 'string' || ! REPOSITORY.test( repository ) ) {
		throw new Error( `${ label } must be an owner/repository.` );
	}
}

function assertCommit( sha, label ) {
	if ( typeof sha !== 'string' || ! FULL_COMMIT.test( sha ) ) {
		throw new Error( `${ label } must be a full lowercase commit hash.` );
	}
}

export function snapshotAssetName( identity ) {
	const pullRequest = positiveInteger(
		identity.pullRequestNumber,
		'pullRequestNumber'
	);
	const runId = positiveInteger( identity.workflowRunId, 'workflowRunId' );
	const attempt = positiveInteger(
		identity.workflowRunAttempt,
		'workflowRunAttempt'
	);
	assertCommit( identity.sourceSha, 'sourceSha' );
	return `code-reference-pr-${ pullRequest }-${ identity.sourceSha }-${ runId }-${ attempt }.zip`;
}

export function metadataAssetName( snapshotName ) {
	if (
		typeof snapshotName !== 'string' ||
		! snapshotName.endsWith( '.zip' )
	) {
		throw new Error( 'Snapshot asset name must end in .zip.' );
	}
	return `${ snapshotName.slice( 0, -4 ) }.json`;
}

export function releaseAssetUrl( repository, assetName ) {
	assertRepository( repository, 'repository' );
	if (
		typeof assetName !== 'string' ||
		! /^[A-Za-z0-9_.-]+$/.test( assetName )
	) {
		throw new Error( 'Release asset name is invalid.' );
	}
	return `https://github.com/${ repository }/releases/download/${ RELEASE_TAG }/${ assetName }`;
}

export function corsProxyUrl( publicUrl ) {
	const parsed = new URL( publicUrl );
	if ( parsed.protocol !== 'https:' || parsed.hostname !== 'github.com' ) {
		throw new Error( 'Only public GitHub asset URLs may use the proxy.' );
	}
	return `${ CORS_PROXY }${ publicUrl }`;
}

export function createLaunchBlueprint( snapshotUrl, runtime ) {
	return {
		$schema: runtime.blueprintSchema,
		meta: {
			title: 'WordPress Core Code Reference preview',
			author: 'WordPress',
			description:
				'Complete Core Code Reference generated from an exact commit.',
		},
		preferredVersions: {
			php: runtime.phpVersion,
			wp: runtime.wordpressVersion,
		},
		landingPage: '/reference/',
		login: false,
		features: { networking: false },
		steps: [
			{
				step: 'unzip',
				zipFile: { resource: 'url', url: corsProxyUrl( snapshotUrl ) },
				extractToPath: '/',
			},
		],
	};
}

export function playgroundUrl( snapshotUrl, runtime ) {
	const blueprint = JSON.stringify(
		createLaunchBlueprint( snapshotUrl, runtime )
	);
	return `${ PLAYGROUND_ORIGIN }/?blueprint-url=data:application/json,${ encodeURIComponent(
		blueprint
	) }`;
}

export async function validatePublicSnapshot(
	publicUrl,
	expected,
	fetchImplementation = globalThis.fetch
) {
	if (
		! Number.isSafeInteger( expected.bytes ) ||
		expected.bytes < 1 ||
		expected.bytes > expected.maximumBytes
	) {
		throw new Error( 'Published snapshot size is outside its boundary.' );
	}
	if ( ! FULL_DIGEST.test( expected.sha256 || '' ) ) {
		throw new Error( 'Published snapshot digest is invalid.' );
	}
	const response = await fetchImplementation( corsProxyUrl( publicUrl ), {
		headers: { Origin: PLAYGROUND_ORIGIN },
	} );
	if ( response.status !== 200 ) {
		throw new Error(
			`Public snapshot returned HTTP ${ response.status } through the proxy.`
		);
	}
	if ( response.headers.get( 'x-playground-cors-proxy' ) !== 'true' ) {
		throw new Error(
			'Public snapshot response did not come through the proxy.'
		);
	}
	const allowedOrigin = response.headers.get( 'access-control-allow-origin' );
	if ( allowedOrigin !== PLAYGROUND_ORIGIN && allowedOrigin !== '*' ) {
		throw new Error(
			'Public snapshot response does not allow Playground.'
		);
	}
	const bytes = Buffer.from( await response.arrayBuffer() );
	if ( bytes.byteLength !== expected.bytes ) {
		throw new Error(
			`Public snapshot has ${ bytes.byteLength } bytes; expected ${ expected.bytes }.`
		);
	}
	const digest = createHash( 'sha256' ).update( bytes ).digest( 'hex' );
	if ( digest !== expected.sha256 ) {
		throw new Error(
			'Public snapshot digest does not match its metadata.'
		);
	}
	return { bytes: bytes.byteLength, sha256: digest };
}

export function validatePublishedSnapshotMetadata( metadata, expected ) {
	if (
		! metadata ||
		typeof metadata !== 'object' ||
		Array.isArray( metadata )
	) {
		throw new Error( 'Published metadata must be an object.' );
	}
	if ( metadata.schemaVersion !== 1 ) {
		throw new Error( 'Published metadata schema is unsupported.' );
	}
	assertRepository( metadata.sourceRepository, 'sourceRepository' );
	assertRepository( expected.sourceRepository, 'expected sourceRepository' );
	assertCommit( metadata.sourceSha, 'sourceSha' );
	assertCommit( expected.sourceSha, 'expected sourceSha' );
	if (
		metadata.sourceRepository !== expected.sourceRepository ||
		metadata.sourceSha !== expected.sourceSha ||
		metadata.pullRequestNumber !== expected.pullRequestNumber
	) {
		throw new Error( 'Published metadata does not match its source.' );
	}
	if (
		metadata.buildStatus !== 'success' ||
		metadata.validationStatus !== 'passed'
	) {
		throw new Error(
			'Published metadata does not describe a valid snapshot.'
		);
	}
	if (
		! Number.isSafeInteger( metadata.snapshotBytes ) ||
		metadata.snapshotBytes < 1 ||
		metadata.snapshotBytes > expected.maximumBytes ||
		! FULL_DIGEST.test( metadata.snapshotSha256 || '' )
	) {
		throw new Error( 'Published snapshot identity is invalid.' );
	}
	if (
		metadata.phpVersion !== '8.4' ||
		metadata.resolvedWordPressBeta?.channel !== 'beta' ||
		! /^\d+\.\d+(?:\.\d+)?-(?:beta\d+|RC\d+)$/.test(
			metadata.resolvedWordPressBeta.version || ''
		) ||
		metadata.resolvedWordPressBeta.downloadUrl !==
			`https://downloads.wordpress.org/release/wordpress-${ metadata.resolvedWordPressBeta.version }.zip`
	) {
		throw new Error( 'Published runtime identity is invalid.' );
	}
	if ( ! FULL_DIGEST.test( metadata.dependencyManifestDigest || '' ) ) {
		throw new Error( 'Published dependency identity is invalid.' );
	}
	if (
		typeof metadata.generationTimestamp !== 'string' ||
		Number.isNaN( Date.parse( metadata.generationTimestamp ) )
	) {
		throw new Error( 'Published generation timestamp is invalid.' );
	}
	return metadata;
}

export function validateReusableMetadata( metadata, expected ) {
	validatePublishedSnapshotMetadata( metadata, {
		...expected,
		pullRequestNumber: positiveInteger(
			expected.pullRequestNumber,
			'pullRequestNumber'
		),
	} );
	const prefix = `code-reference-pr-${ metadata.pullRequestNumber }-${ metadata.sourceSha }-`;
	if (
		typeof metadata.snapshotFilename !== 'string' ||
		! metadata.snapshotFilename.startsWith( prefix ) ||
		! /^code-reference-pr-\d+-[0-9a-f]{40}-\d+-\d+\.zip$/.test(
			metadata.snapshotFilename
		)
	) {
		throw new Error( 'Published snapshot filename is invalid.' );
	}
	return metadata;
}

export function createReuseHandoff( published, identity ) {
	validateReusableMetadata( published, {
		...identity,
		maximumBytes: identity.maximumBytes,
	} );
	return {
		...published,
		handoffType: 'reuse',
		sourceRepository: identity.sourceRepository,
		pullRequestNumber: positiveInteger(
			identity.pullRequestNumber,
			'pullRequestNumber'
		),
		sourceSha: identity.sourceSha,
		workflowRunId: String(
			positiveInteger( identity.workflowRunId, 'workflowRunId' )
		),
		workflowRunAttempt: positiveInteger(
			identity.workflowRunAttempt,
			'workflowRunAttempt'
		),
		runUrl: identity.runUrl,
		reusedSnapshot: {
			assetName: published.snapshotFilename,
			metadataAssetName: metadataAssetName( published.snapshotFilename ),
		},
	};
}
