import { stat } from 'node:fs/promises';
import path from 'node:path';

import { isDeploymentEnabled } from './config.mjs';
import { sha256File } from './files.mjs';
import {
	COMMENT_MARKER,
	corsProxyUrl,
	metadataAssetName,
	playgroundUrl,
	releaseAssetUrl,
	snapshotAssetName,
	validateReusableMetadata,
} from './publication.mjs';

export const BUILD_WORKFLOW_NAME = 'Code Reference Playground Preview Build';
export const SNAPSHOT_BYTES_LIMIT = 104857600;

const FULL_COMMIT = /^[0-9a-f]{40}$/;
const FULL_DIGEST = /^[0-9a-f]{64}$/;
const BLUEPRINT_SCHEMA =
	'https://playground.wordpress.net/blueprint-schema.json';

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

function headRepository( value ) {
	return value?.full_name || value?.repo?.full_name || null;
}

export function validatePublicationContext( context ) {
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
		context.run.name !== BUILD_WORKFLOW_NAME ||
		context.run.event !== 'pull_request'
	) {
		throw new Error( 'The source workflow identity is invalid.' );
	}
	if ( context.run.status !== 'completed' ) {
		throw new Error( 'The source workflow is not terminal.' );
	}
	const pullRequestNumber = positiveInteger(
		context.pullRequest?.number,
		'Pull request number'
	);
	if ( context.pullRequest.base?.ref !== 'trunk' ) {
		throw new Error( 'The pull request does not target trunk.' );
	}
	const sourceSha = context.pullRequest.head?.sha;
	if (
		! FULL_COMMIT.test( sourceSha || '' ) ||
		context.run.head_sha !== sourceSha
	) {
		throw new Error(
			'The workflow run does not match the pull request head.'
		);
	}
	const sourceRepository = headRepository( context.pullRequest.head );
	if (
		! sourceRepository ||
		headRepository( context.run.head_repository ) !== sourceRepository
	) {
		throw new Error(
			'The workflow run does not match the pull request repository.'
		);
	}
	return {
		...context,
		pullRequestNumber,
		sourceRepository,
		sourceSha,
		workflowRunId: runId,
		workflowRunAttempt: runAttempt,
		runUrl: `https://github.com/${ context.repository }/actions/runs/${ runId }`,
	};
}

export function assertLatestAuthorized( context ) {
	const current = validatePublicationContext( context );
	if (
		context.latestRun?.id !== current.workflowRunId ||
		context.latestRun?.run_attempt !== current.workflowRunAttempt
	) {
		throw new Error( 'A newer docs preview run superseded this attempt.' );
	}
	if ( current.pullRequest.state !== 'open' ) {
		throw new Error( 'The pull request is no longer open.' );
	}
	const hasLabel = current.pullRequest.labels?.some(
		( label ) => label.name === 'docs-preview'
	);
	if ( ! hasLabel && current.workflowRunAttempt === 1 ) {
		throw new Error( 'The docs-preview request is no longer authorized.' );
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
	if ( metadata.schemaVersion !== 1 ) {
		throw new Error( 'Handoff metadata schema is unsupported.' );
	}
	if (
		metadata.sourceRepository !== context.sourceRepository ||
		metadata.pullRequestNumber !== context.pullRequestNumber ||
		metadata.sourceSha !== context.sourceSha ||
		String( metadata.workflowRunId ) !== String( context.workflowRunId ) ||
		metadata.workflowRunAttempt !== context.workflowRunAttempt ||
		metadata.runUrl !== context.runUrl
	) {
		throw new Error( 'Handoff metadata does not match the workflow run.' );
	}
	timestamp( metadata.generationTimestamp, 'Generation timestamp' );
}

function validateSnapshotIdentity( metadata ) {
	if (
		! Number.isSafeInteger( metadata.snapshotBytes ) ||
		metadata.snapshotBytes < 1 ||
		metadata.snapshotBytes > SNAPSHOT_BYTES_LIMIT
	) {
		throw new Error( 'Snapshot size is outside the 100 MiB boundary.' );
	}
	if ( ! FULL_DIGEST.test( metadata.snapshotSha256 || '' ) ) {
		throw new Error( 'Snapshot digest is invalid.' );
	}
}

export function inspectHandoff( metadata, rawContext ) {
	const context = validatePublicationContext( rawContext );
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
	validateSnapshotIdentity( metadata );
	validateReusableMetadata( metadata, {
		sourceRepository: context.sourceRepository,
		pullRequestNumber: context.pullRequestNumber,
		sourceSha: context.sourceSha,
		maximumBytes: SNAPSHOT_BYTES_LIMIT,
	} );
	if ( metadata.handoffType === 'reuse' ) {
		if (
			metadata.reusedSnapshot?.assetName !== metadata.snapshotFilename ||
			metadata.reusedSnapshot.metadataAssetName !==
				metadataAssetName( metadata.snapshotFilename )
		) {
			throw new Error( 'Reusable asset identity is invalid.' );
		}
		return { kind: 'reuse', metadata, context };
	}
	if ( metadata.handoffType !== undefined ) {
		throw new Error( 'Handoff type is invalid.' );
	}
	const expectedFilename = snapshotAssetName( context );
	if ( metadata.snapshotFilename !== expectedFilename ) {
		throw new Error( 'Candidate snapshot filename is invalid.' );
	}
	return { kind: 'candidate', metadata, context };
}

export async function validateCandidateFile( metadata, filename ) {
	if ( path.basename( filename ) !== metadata.snapshotFilename ) {
		throw new Error(
			'Candidate file does not match its metadata filename.'
		);
	}
	const candidate = await stat( filename );
	if ( ! candidate.isFile() || candidate.size !== metadata.snapshotBytes ) {
		throw new Error( 'Candidate file size does not match its metadata.' );
	}
	const digest = await sha256File( filename );
	if ( digest !== metadata.snapshotSha256 ) {
		throw new Error( 'Candidate file digest does not match its metadata.' );
	}
	return { bytes: candidate.size, sha256: digest };
}

export function createPublishedMetadata( metadata, repository, publishedAt ) {
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
		},
	};
}

function commitLink( repository, sha ) {
	return `[${ sha }](https://github.com/${ repository }/commit/${ sha })`;
}

function previousLink( previous ) {
	if ( ! previous ) {
		return '';
	}
	return `\n\n[Latest successful docs preview](${
		previous.publication.playgroundUrl
	}) — built from ${ commitLink(
		previous.sourceRepository,
		previous.sourceSha
	) }.`;
}

function commentHeader( source ) {
	const identity = source
		? `\n<!-- code-reference-docs-preview-source: ${ source.repository }@${ source.sha } -->`
		: '';
	return `${ COMMENT_MARKER }${ identity }\n## Code Reference documentation preview`;
}

export function readPreviewCommentSource( body ) {
	if ( typeof body !== 'string' ) {
		return null;
	}
	const match = body.match(
		/<!-- code-reference-docs-preview-source: ([A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+)@([0-9a-f]{40}) -->/
	);
	return match ? { repository: match[ 1 ], sha: match[ 2 ] } : null;
}

export function renderPreviewComment( state ) {
	if ( state.status === 'ready' ) {
		const header = commentHeader( {
			repository: state.preview.sourceRepository,
			sha: state.preview.sourceSha,
		} );
		return `${ header }\n\n**Status:** Ready\n\n[Open Code Reference preview](${
			state.preview.publication.playgroundUrl
		})\n\nSource: ${ commitLink(
			state.preview.sourceRepository,
			state.preview.sourceSha
		) }  \nPublished: ${
			state.preview.publication.publishedAt
		}  \n[GitHub Actions run](${ state.runUrl })`;
	}
	if ( state.status === 'failed' ) {
		const header = commentHeader( {
			repository: state.sourceRepository,
			sha: state.sourceSha,
		} );
		return `${ header }\n\n**Status:** Latest attempt failed\n\nThe latest attempt for ${ commitLink(
			state.sourceRepository,
			state.sourceSha
		) } failed at ${ state.at }. [View the GitHub Actions run](${
			state.runUrl
		}).${ previousLink( state.previous ) }`;
	}
	if ( state.status === 'stale' ) {
		const header = commentHeader( {
			repository: state.currentRepository,
			sha: state.currentSha,
		} );
		return `${ header }\n\n**Status:** Stale\n\nThe latest successful preview was built from ${ commitLink(
			state.preview.sourceRepository,
			state.preview.sourceSha
		) }, but the pull request is now at ${ commitLink(
			state.currentRepository,
			state.currentSha
		) }.\n\n[Latest successful docs preview](${
			state.preview.publication.playgroundUrl
		})\n\nAdd the \`docs-preview\` label again to build the current commit.`;
	}
	if ( state.status === 'stale-unavailable' ) {
		const header = commentHeader( {
			repository: state.previousRepository,
			sha: state.previousSha,
		} );
		return `${ header }\n\n**Status:** Stale\n\nThe latest preview attempt was for ${ commitLink(
			state.previousRepository,
			state.previousSha
		) }, but no healthy docs preview is available. The pull request is now at ${ commitLink(
			state.currentRepository,
			state.currentSha
		) }.\n\nAdd the \`docs-preview\` label again to build the current commit.`;
	}
	if ( state.status === 'expired' ) {
		const header = commentHeader();
		return `${ header }\n\n**Status:** Expired\n\nThis pull request is closed or merged. Its Code Reference preview expired and is no longer live.`;
	}
	throw new Error( `Unknown preview comment state ${ state.status }.` );
}
