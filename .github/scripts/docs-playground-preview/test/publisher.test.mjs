import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtemp, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import {
	assertLatestAuthorized,
	createPublishedMetadata,
	inspectHandoff,
	readPreviewCommentSuccess,
	readPreviewCommentSource,
	renderPreviewComment,
	validateCandidateFile,
	validatePublicationContext,
} from '../lib/publisher.mjs';
import {
	COMMENT_MARKER,
	metadataAssetName,
	snapshotAssetName,
} from '../lib/publication.mjs';

const sha = 'a'.repeat( 40 );
const bytes = Buffer.from( 'snapshot' );

function context() {
	return {
		repository: 'WordPress/wordpress-develop',
		stagingVariable: '',
		triggerRunId: 456,
		triggerRunAttempt: 1,
		latestRun: { id: 456, run_attempt: 1 },
		run: {
			id: 456,
			run_attempt: 1,
			name: 'Code Reference Playground Preview Build',
			event: 'pull_request',
			status: 'completed',
			head_sha: sha,
			head_repository: { full_name: 'contributor/wordpress-develop' },
			pull_requests: [],
		},
		pullRequest: {
			number: 123,
			state: 'open',
			base: { ref: 'trunk' },
			head: {
				sha,
				repo: { full_name: 'contributor/wordpress-develop' },
			},
			labels: [ { name: 'docs-preview' } ],
		},
	};
}

function metadata( overrides = {} ) {
	const identity = {
		pullRequestNumber: 123,
		sourceSha: sha,
		workflowRunId: 456,
		workflowRunAttempt: 1,
	};
	return {
		schemaVersion: 1,
		sourceRepository: 'contributor/wordpress-develop',
		pullRequestNumber: 123,
		sourceSha: sha,
		workflowRunId: '456',
		workflowRunAttempt: 1,
		runUrl: 'https://github.com/WordPress/wordpress-develop/actions/runs/456',
		resolvedWordPressBeta: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
		phpVersion: '8.4',
		dependencyManifestDigest: 'b'.repeat( 64 ),
		snapshotFilename: snapshotAssetName( identity ),
		snapshotBytes: bytes.byteLength,
		snapshotSha256: createHash( 'sha256' ).update( bytes ).digest( 'hex' ),
		buildStatus: 'success',
		validationStatus: 'passed',
		validationFailures: [],
		generationTimestamp: '2026-08-09T12:34:56.000Z',
		...overrides,
	};
}

test( 'the publisher binds the trigger to an enabled terminal PR run', () => {
	const checked = validatePublicationContext( context() );
	assert.equal( checked.sourceSha, sha );
	assert.equal( checked.pullRequestNumber, 123 );
	assert.equal(
		checked.runUrl,
		'https://github.com/WordPress/wordpress-develop/actions/runs/456'
	);
	assert.throws(
		() =>
			validatePublicationContext( {
				...context(),
				repository: 'attacker/wordpress-develop',
			} ),
		/publication is disabled/
	);
	assert.throws(
		() =>
			validatePublicationContext( {
				...context(),
				triggerRunAttempt: 2,
			} ),
		/no longer current/
	);
	assert.throws(
		() =>
			validatePublicationContext( {
				...context(),
				run: { ...context().run, head_sha: 'c'.repeat( 40 ) },
			} ),
		/does not match the pull request head/
	);
} );

test( 'latest-wins requires the label except for an explicit rerun', () => {
	assert.equal( assertLatestAuthorized( context() ).sourceSha, sha );
	const unlabeled = context();
	unlabeled.pullRequest.labels = [];
	assert.throws( () => assertLatestAuthorized( unlabeled ), /authorized/ );
	const rerun = context();
	rerun.triggerRunAttempt = 2;
	rerun.run.run_attempt = 2;
	rerun.latestRun.run_attempt = 2;
	rerun.pullRequest.labels = [];
	assert.equal( assertLatestAuthorized( rerun ).workflowRunAttempt, 2 );
	assert.throws(
		() =>
			assertLatestAuthorized( {
				...context(),
				latestRun: { id: 789, run_attempt: 1 },
			} ),
		/superseded/
	);
} );

test( 'a fresh candidate must pass every publisher identity field', () => {
	assert.equal( inspectHandoff( metadata(), context() ).kind, 'candidate' );
	/** @type {Array<[string, any, RegExp]>} */
	const invalidFields = [
		[ 'schemaVersion', 2, /schema/ ],
		[ 'sourceSha', 'c'.repeat( 40 ), /workflow run/ ],
		[ 'workflowRunAttempt', 2, /workflow run/ ],
		[ 'phpVersion', '8.5', /runtime identity/ ],
		[ 'dependencyManifestDigest', 'broken', /dependency identity/ ],
		[ 'handoffType', 'unknown', /Handoff type/ ],
		[ 'snapshotFilename', 'snapshot.zip', /filename/ ],
		[ 'snapshotBytes', 104857601, /100 MiB/ ],
		[ 'snapshotSha256', 'broken', /digest/ ],
	];
	for ( const [ name, value, error ] of invalidFields ) {
		assert.throws(
			() => inspectHandoff( metadata( { [ name ]: value } ), context() ),
			error
		);
	}
} );

test( 'failed and advisory handoffs are terminal but not candidates', () => {
	assert.equal(
		inspectHandoff(
			metadata( {
				buildStatus: 'failed',
				validationStatus: 'not-run',
			} ),
			context()
		).kind,
		'failed'
	);
	assert.equal(
		inspectHandoff( metadata( { validationStatus: 'failed' } ), context() )
			.kind,
		'invalid'
	);
} );

test( 'reuse identifies the exact existing snapshot and metadata assets', () => {
	const current = metadata();
	const reused = {
		...current,
		handoffType: 'reuse',
		reusedSnapshot: {
			assetName: current.snapshotFilename,
			metadataAssetName: metadataAssetName( current.snapshotFilename ),
		},
	};
	assert.equal( inspectHandoff( reused, context() ).kind, 'reuse' );
	assert.throws(
		() =>
			inspectHandoff(
				{
					...reused,
					reusedSnapshot: {
						...reused.reusedSnapshot,
						assetName: 'other.zip',
					},
				},
				context()
			),
		/Reusable asset identity/
	);
} );

test( 'candidate bytes and digest are checked without opening the snapshot', async () => {
	const temporary = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-publisher-' )
	);
	const current = metadata();
	const filename = path.join( temporary, current.snapshotFilename );
	await writeFile( filename, bytes );
	assert.deepEqual( await validateCandidateFile( current, filename ), {
		bytes: bytes.byteLength,
		sha256: current.snapshotSha256,
	} );
	await writeFile( filename, 'changed!' );
	await assert.rejects(
		validateCandidateFile( current, filename ),
		/digest/
	);
} );

test( 'published metadata contains the immutable public launch identity', () => {
	const published = createPublishedMetadata(
		metadata(),
		'WordPress/wordpress-develop',
		'2026-08-09T13:00:00.000Z'
	);
	assert.equal(
		published.publication.publishedAt,
		'2026-08-09T13:00:00.000Z'
	);
	assert.match( published.publication.snapshotUrl, /releases\/download/ );
	assert.match(
		published.publication.snapshotProxyUrl,
		/wordpress-playground-cors-proxy/
	);
	assert.match( published.publication.playgroundUrl, /blueprint-url=/ );
} );

test( 'the sticky comment has ready, failed, stale, and expired states', () => {
	const preview = createPublishedMetadata(
		metadata(),
		'WordPress/wordpress-develop',
		'2026-08-09T13:00:00.000Z'
	);
	const ready = renderPreviewComment( {
		status: 'ready',
		preview,
		runUrl: preview.runUrl,
	} );
	assert.match( ready, new RegExp( COMMENT_MARKER ) );
	assert.match( ready, /Status:\*\* Ready/ );
	assert.match( ready, new RegExp( sha ) );
	assert.deepEqual( readPreviewCommentSource( ready ), {
		repository: preview.sourceRepository,
		sha,
	} );
	assert.deepEqual( readPreviewCommentSuccess( ready ), {
		sourceRepository: preview.sourceRepository,
		sourceSha: sha,
		publication: { playgroundUrl: preview.publication.playgroundUrl },
	} );
	assert.equal( ready.match( /Open Code Reference preview/g )?.length, 1 );

	const failed = renderPreviewComment( {
		status: 'failed',
		sourceRepository: preview.sourceRepository,
		sourceSha: 'c'.repeat( 40 ),
		at: '2026-08-09T14:00:00.000Z',
		runUrl: preview.runUrl,
		previous: preview,
	} );
	assert.match( failed, /Latest attempt failed/ );
	assert.equal(
		failed.match( /Latest successful docs preview/g )?.length,
		1
	);
	assert.deepEqual( readPreviewCommentSuccess( failed ), {
		sourceRepository: preview.sourceRepository,
		sourceSha: sha,
		publication: { playgroundUrl: preview.publication.playgroundUrl },
	} );

	const stale = renderPreviewComment( {
		status: 'stale',
		preview,
		currentRepository: preview.sourceRepository,
		currentSha: 'c'.repeat( 40 ),
	} );
	assert.match( stale, /Status:\*\* Stale/ );
	assert.match( stale, /add the `docs-preview` label again/i );
	assert.match( stale, new RegExp( sha ) );
	assert.match( stale, new RegExp( 'c'.repeat( 40 ) ) );
	assert.deepEqual( readPreviewCommentSource( stale ), {
		repository: preview.sourceRepository,
		sha,
	} );
	const unavailable = renderPreviewComment( {
		status: 'stale-unavailable',
		previousRepository: preview.sourceRepository,
		previousSha: sha,
		currentRepository: preview.sourceRepository,
		currentSha: 'c'.repeat( 40 ),
	} );
	assert.match( unavailable, /no healthy docs preview is available/ );
	assert.match( unavailable, /add the `docs-preview` label again/i );
	assert.deepEqual( readPreviewCommentSource( unavailable ), {
		repository: preview.sourceRepository,
		sha,
	} );

	assert.match(
		renderPreviewComment( { status: 'expired' } ),
		/preview expired and is no longer live/
	);
} );
