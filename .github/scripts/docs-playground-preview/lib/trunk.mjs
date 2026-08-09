import { isDeepStrictEqual } from 'node:util';

import { isDeploymentEnabled } from './config.mjs';
import {
	PLAYGROUND_ORIGIN,
	SNAPSHOT_BYTES_LIMIT,
	corsProxyUrl,
	createLaunchBlueprint,
	playgroundUrl,
	releaseAssetUrl,
	validatePublishedSnapshotMetadata,
} from './publication.mjs';

export const TRUNK_BUILD_WORKFLOW_NAME =
	'Code Reference Playground Preview Build';
export const TRUNK_POINTER_ASSET = 'code-reference-trunk.json';
export const TRUNK_POINTER_REF = 'heads/docs-preview-code-reference';

const BLUEPRINT_SCHEMA =
	'https://playground.wordpress.net/blueprint-schema.json';
const FULL_COMMIT = /^[0-9a-f]{40}$/;
const MAXIMUM_BLUEPRINT_BYTES = 65536;

function positiveInteger( value, label ) {
	const number = Number( value );
	if ( ! Number.isSafeInteger( number ) || number < 1 ) {
		throw new Error( `${ label } must be a positive integer.` );
	}
	return number;
}

function timestamp( value, label ) {
	if (
		typeof value !== 'string' ||
		Number.isNaN( Date.parse( value ) ) ||
		! value.endsWith( 'Z' )
	) {
		throw new Error( `${ label } must be a UTC timestamp.` );
	}
	return value;
}

export function trunkSnapshotAssetName( identity ) {
	if ( ! FULL_COMMIT.test( identity.sourceSha || '' ) ) {
		throw new Error( 'sourceSha must be a full lowercase commit hash.' );
	}
	const runId = positiveInteger( identity.workflowRunId, 'workflowRunId' );
	const attempt = positiveInteger(
		identity.workflowRunAttempt,
		'workflowRunAttempt'
	);
	return `code-reference-trunk-${ identity.sourceSha }-${ runId }-${ attempt }.zip`;
}

export function trunkStableBlueprintUrl( repository ) {
	return `https://raw.githubusercontent.com/${ repository }/docs-preview-code-reference/${ TRUNK_POINTER_ASSET }`;
}

export function trunkBlueprintCommitUrl( repository, commitSha ) {
	if ( ! FULL_COMMIT.test( commitSha || '' ) ) {
		throw new Error( 'commitSha must be a full lowercase commit hash.' );
	}
	return `https://raw.githubusercontent.com/${ repository }/${ commitSha }/${ TRUNK_POINTER_ASSET }`;
}

export function trunkPlaygroundUrl( repository ) {
	return `${ PLAYGROUND_ORIGIN }/?blueprint-url=${ encodeURIComponent(
		corsProxyUrl( trunkStableBlueprintUrl( repository ) )
	) }`;
}

export function validateTrunkPublicationContext( context ) {
	if (
		! isDeploymentEnabled( context.repository, context.stagingVariable )
	) {
		throw new Error(
			'Docs preview publication is disabled in this repository.'
		);
	}
	const runId = positiveInteger( context.run?.id, 'Workflow run id' );
	const runAttempt = positiveInteger(
		context.run?.run_attempt,
		'Workflow run attempt'
	);
	if (
		runId !== positiveInteger( context.triggerRunId, 'Trigger run id' ) ||
		runAttempt !==
			positiveInteger( context.triggerRunAttempt, 'Trigger run attempt' )
	) {
		throw new Error(
			'The triggering workflow attempt is no longer current.'
		);
	}
	if (
		context.run.name !== TRUNK_BUILD_WORKFLOW_NAME ||
		context.run.event !== 'push' ||
		context.run.status !== 'completed' ||
		context.run.head_branch !== 'trunk'
	) {
		throw new Error( 'The trunk source workflow identity is invalid.' );
	}
	const sourceSha = context.run.head_sha;
	if (
		! FULL_COMMIT.test( sourceSha || '' ) ||
		context.run.head_repository?.full_name !== context.repository
	) {
		throw new Error( 'The trunk workflow source identity is invalid.' );
	}
	return {
		...context,
		sourceRepository: context.repository,
		sourceSha,
		workflowRunId: runId,
		workflowRunAttempt: runAttempt,
		runUrl: `https://github.com/${ context.repository }/actions/runs/${ runId }`,
	};
}

export function assertLatestTrunkAuthorized( context ) {
	const current = validateTrunkPublicationContext( context );
	if (
		context.latestRun?.id !== current.workflowRunId ||
		context.latestRun?.run_attempt !== current.workflowRunAttempt ||
		context.trunkHeadSha !== current.sourceSha
	) {
		throw new Error( 'A newer trunk build superseded this attempt.' );
	}
	return current;
}

function validateCommonHandoff( metadata, context ) {
	if (
		! metadata ||
		typeof metadata !== 'object' ||
		Array.isArray( metadata )
	) {
		throw new Error( 'Handoff metadata must be an object.' );
	}
	if (
		metadata.schemaVersion !== 1 ||
		metadata.sourceRepository !== context.sourceRepository ||
		metadata.pullRequestNumber !== null ||
		metadata.sourceSha !== context.sourceSha ||
		String( metadata.workflowRunId ) !== String( context.workflowRunId ) ||
		metadata.workflowRunAttempt !== context.workflowRunAttempt ||
		metadata.runUrl !== context.runUrl
	) {
		throw new Error( 'Handoff metadata does not match the trunk run.' );
	}
	timestamp( metadata.generationTimestamp, 'Generation timestamp' );
}

export function inspectTrunkHandoff( metadata, rawContext ) {
	const context = validateTrunkPublicationContext( rawContext );
	validateCommonHandoff( metadata, context );
	if ( metadata.buildStatus === 'failed' ) {
		return { kind: 'failed', metadata, context };
	}
	if ( metadata.buildStatus !== 'success' ) {
		throw new Error( 'Handoff build status is invalid.' );
	}
	if ( metadata.validationStatus === 'failed' ) {
		return { kind: 'invalid', metadata, context };
	}
	if ( metadata.validationStatus !== 'passed' ) {
		throw new Error( 'Handoff validation status is invalid.' );
	}
	validatePublishedSnapshotMetadata( metadata, {
		sourceRepository: context.sourceRepository,
		sourceSha: context.sourceSha,
		pullRequestNumber: null,
		maximumBytes: SNAPSHOT_BYTES_LIMIT,
	} );
	if (
		metadata.handoffType !== undefined ||
		metadata.snapshotFilename !== trunkSnapshotAssetName( context )
	) {
		throw new Error( 'Candidate trunk snapshot filename is invalid.' );
	}
	return { kind: 'candidate', metadata, context };
}

export function createTrunkPublishedMetadata(
	metadata,
	repository,
	publishedAt
) {
	timestamp( publishedAt, 'Publication timestamp' );
	const snapshotUrl = releaseAssetUrl(
		repository,
		metadata.snapshotFilename
	);
	return {
		...metadata,
		publication: {
			publishedAt,
			snapshotUrl,
			snapshotProxyUrl: corsProxyUrl( snapshotUrl ),
			playgroundUrl: playgroundUrl( snapshotUrl, {
				blueprintSchema: BLUEPRINT_SCHEMA,
				phpVersion: metadata.phpVersion,
				wordpressVersion: metadata.resolvedWordPressBeta.version,
			} ),
			stableBlueprintUrl: trunkStableBlueprintUrl( repository ),
			stablePlaygroundUrl: trunkPlaygroundUrl( repository ),
		},
	};
}

export function validatePublishedTrunkMetadata(
	metadata,
	repository,
	sourceSha
) {
	validatePublishedSnapshotMetadata( metadata, {
		sourceRepository: repository,
		sourceSha,
		pullRequestNumber: null,
		maximumBytes: SNAPSHOT_BYTES_LIMIT,
	} );
	if (
		metadata.snapshotFilename !==
		trunkSnapshotAssetName( {
			sourceSha,
			workflowRunId: metadata.workflowRunId,
			workflowRunAttempt: metadata.workflowRunAttempt,
		} )
	) {
		throw new Error( 'Published trunk snapshot filename is invalid.' );
	}
	const snapshotUrl = releaseAssetUrl(
		repository,
		metadata.snapshotFilename
	);
	if (
		metadata.publication?.snapshotUrl !== snapshotUrl ||
		metadata.publication?.snapshotProxyUrl !==
			corsProxyUrl( snapshotUrl ) ||
		metadata.publication?.playgroundUrl !==
			playgroundUrl( snapshotUrl, {
				blueprintSchema: BLUEPRINT_SCHEMA,
				phpVersion: metadata.phpVersion,
				wordpressVersion: metadata.resolvedWordPressBeta.version,
			} ) ||
		metadata.publication?.stableBlueprintUrl !==
			trunkStableBlueprintUrl( repository ) ||
		metadata.publication?.stablePlaygroundUrl !==
			trunkPlaygroundUrl( repository )
	) {
		throw new Error( 'Published trunk pointer metadata is invalid.' );
	}
	timestamp( metadata.publication?.publishedAt, 'Publication timestamp' );
	return metadata;
}

export function createTrunkBlueprint( metadata ) {
	const blueprint = createLaunchBlueprint( metadata.publication.snapshotUrl, {
		blueprintSchema: BLUEPRINT_SCHEMA,
		phpVersion: metadata.phpVersion,
		wordpressVersion: metadata.resolvedWordPressBeta.version,
	} );
	blueprint.meta.description = `Latest successful Core Code Reference from ${ metadata.sourceRepository }@${ metadata.sourceSha }. Generated ${ metadata.generationTimestamp }. ${ metadata.runUrl }`;
	return blueprint;
}

export async function readPublicBlueprint(
	publicUrl,
	fetchImplementation = globalThis.fetch
) {
	const response = await fetchImplementation( corsProxyUrl( publicUrl ), {
		headers: { Origin: PLAYGROUND_ORIGIN },
	} );
	if ( response.status !== 200 ) {
		throw new Error(
			`Public Blueprint returned HTTP ${ response.status } through the proxy.`
		);
	}
	if ( response.headers.get( 'x-playground-cors-proxy' ) !== 'true' ) {
		throw new Error( 'Public Blueprint did not come through the proxy.' );
	}
	const allowedOrigin = response.headers.get( 'access-control-allow-origin' );
	if ( allowedOrigin !== PLAYGROUND_ORIGIN && allowedOrigin !== '*' ) {
		throw new Error( 'Public Blueprint does not allow Playground.' );
	}
	const bytes = Buffer.from( await response.arrayBuffer() );
	if ( bytes.byteLength < 1 || bytes.byteLength > MAXIMUM_BLUEPRINT_BYTES ) {
		throw new Error( 'Public Blueprint size is outside its boundary.' );
	}
	let blueprint;
	try {
		blueprint = JSON.parse( bytes.toString( 'utf8' ) );
	} catch {
		throw new Error( 'Public Blueprint is not JSON.' );
	}
	return blueprint;
}

export async function validatePublicBlueprint(
	publicUrl,
	expected,
	fetchImplementation = globalThis.fetch
) {
	const blueprint = await readPublicBlueprint(
		publicUrl,
		fetchImplementation
	);
	if ( ! isDeepStrictEqual( blueprint, expected ) ) {
		throw new Error( 'Public Blueprint does not identify the candidate.' );
	}
	return blueprint;
}
