import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

import { managePullRequest } from '../lifecycle.mjs';
import {
	createPublishedMetadata,
	renderPreviewComment,
} from '../lib/publisher.mjs';
import {
	COMMENT_MARKER,
	PLAYGROUND_ORIGIN,
	metadataAssetName,
	releaseAssetUrl,
	snapshotAssetName,
} from '../lib/publication.mjs';

const repository = 'WordPress/wordpress-develop';
const sourceRepository = 'contributor/wordpress-develop';
const currentSha = 'a'.repeat( 40 );
const previewSha = 'c'.repeat( 40 );
const snapshotBytes = Buffer.from( 'snapshot' );

function pullRequest( state = 'open', labels = [] ) {
	return {
		number: 123,
		state,
		base: { ref: 'trunk' },
		head: {
			sha: currentSha,
			ref: 'feature',
			repo: { full_name: sourceRepository },
		},
		labels,
	};
}

function event( action, state = action === 'closed' ? 'closed' : 'open' ) {
	return { action, pull_request: pullRequest( state ) };
}

function previewMetadata() {
	const snapshotFilename = snapshotAssetName( {
		pullRequestNumber: 123,
		sourceSha: previewSha,
		workflowRunId: 300,
		workflowRunAttempt: 1,
	} );
	return createPublishedMetadata(
		{
			schemaVersion: 1,
			sourceRepository,
			pullRequestNumber: 123,
			sourceSha: previewSha,
			workflowRunId: '300',
			workflowRunAttempt: 1,
			runUrl: `https://github.com/${ repository }/actions/runs/300`,
			resolvedWordPressBeta: {
				channel: 'beta',
				version: '7.2-beta1',
				downloadUrl:
					'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
			},
			phpVersion: '8.4',
			dependencyManifestDigest: 'b'.repeat( 64 ),
			snapshotFilename,
			snapshotBytes: snapshotBytes.byteLength,
			snapshotSha256: createHash( 'sha256' )
				.update( snapshotBytes )
				.digest( 'hex' ),
			buildStatus: 'success',
			validationStatus: 'passed',
			generationTimestamp: '2026-08-08T12:00:00.000Z',
		},
		repository,
		'2026-08-08T13:00:00.000Z'
	);
}

function releaseAsset( id, name ) {
	return {
		id,
		name,
		created_at: '2026-08-08T13:00:00.000Z',
		browser_download_url: releaseAssetUrl( repository, name ),
	};
}

class FakeApi {
	constructor() {
		this.currentPullRequest = pullRequest();
		this.release = { id: 9 };
		this.assets = [];
		this.caches = [];
		this.metadata = new Map();
		this.assetBytes = new Map();
		this.comment = { id: 20, body: `${ COMMENT_MARKER }\nReady` };
		this.deletedAssets = [];
		this.deletedCaches = [];
		this.commentBodies = [];
		this.failedAsset = null;
		this.cacheRef = null;
		this.previewBuilds = [];
	}

	async getPullRequest() {
		return this.currentPullRequest;
	}

	async findLatestPreviewRun() {
		return this.previewBuilds.shift() || null;
	}

	async getRelease() {
		return this.release;
	}

	async listReleaseAssets() {
		return [ ...this.assets ];
	}

	async listActionCaches( ref ) {
		this.cacheRef = ref;
		return [ ...this.caches ];
	}

	async findPreviewComment() {
		return this.comment;
	}

	async updateComment( id, body ) {
		assert.equal( id, this.comment.id );
		this.commentBodies.push( body );
		this.comment = { id, body };
	}

	async deleteReleaseAsset( id ) {
		if ( id === this.failedAsset ) {
			throw new Error( 'release deletion failed' );
		}
		this.deletedAssets.push( id );
	}

	async deleteActionCache( id ) {
		this.deletedCaches.push( id );
	}
}

function installPreview( api ) {
	const metadata = previewMetadata();
	const metadataName = metadataAssetName( metadata.snapshotFilename );
	api.assets.push(
		releaseAsset( 1, metadata.snapshotFilename ),
		releaseAsset( 2, metadataName )
	);
	api.metadata.set( metadataName, metadata );
	api.assetBytes.set( metadata.snapshotFilename, snapshotBytes );
	return metadata;
}

function publicFetch( api ) {
	return async ( url ) => {
		if ( url.includes( 'wordpress-playground-cors-proxy.net' ) ) {
			const name = url.split( '/' ).at( -1 );
			return {
				status: 200,
				headers: {
					get: ( header ) =>
						( {
							'x-playground-cors-proxy': 'true',
							'access-control-allow-origin': PLAYGROUND_ORIGIN,
						} )[ header.toLowerCase() ] || null,
				},
				arrayBuffer: async () => api.assetBytes.get( name ),
			};
		}
		const name = url.split( '/' ).at( -1 );
		return {
			status: api.metadata.has( name ) ? 200 : 404,
			json: async () => api.metadata.get( name ),
		};
	};
}

function options( api, lifecycleEvent ) {
	return {
		repository,
		stagingVariable: '',
		event: lifecycleEvent,
		api,
		fetchImplementation: publicFetch( api ),
		warning: () => {},
	};
}

test( 'a later unlabeled commit marks the healthy preview stale', async () => {
	const api = new FakeApi();
	installPreview( api );
	const result = await managePullRequest(
		options( api, event( 'synchronize' ) )
	);
	assert.equal( result.status, 'stale' );
	assert.match( api.comment.body, /Status:\*\* Stale/ );
	assert.match( api.comment.body, new RegExp( previewSha ) );
	assert.match( api.comment.body, new RegExp( currentSha ) );
	assert.match( api.comment.body, /add the `docs-preview` label again/i );
} );

test( 'a labeled synchronize event leaves the publisher in charge', async () => {
	const api = new FakeApi();
	api.currentPullRequest = pullRequest( 'open', [
		{ name: 'docs-preview' },
	] );
	const result = await managePullRequest(
		options( api, event( 'synchronize' ) )
	);
	assert.equal( result.status, 'ignored' );
	assert.equal( api.commentBodies.length, 0 );
} );

test( 'a completed current-SHA build supersedes an older stale event', async () => {
	const api = new FakeApi();
	installPreview( api );
	api.previewBuilds.push( null, { id: 456 } );
	await assert.rejects(
		managePullRequest( options( api, event( 'synchronize' ) ) ),
		/superseded/
	);
	assert.equal( api.commentBodies.length, 0 );
} );

test( 'a stale failed attempt still tells the maintainer how to rebuild', async () => {
	const api = new FakeApi();
	api.comment.body = renderPreviewComment( {
		status: 'failed',
		sourceRepository,
		sourceSha: previewSha,
		at: '2026-08-08T13:00:00.000Z',
		runUrl: `https://github.com/${ repository }/actions/runs/300`,
		previous: null,
	} );
	const result = await managePullRequest(
		options( api, event( 'synchronize' ) )
	);
	assert.equal( result.status, 'stale' );
	assert.match( api.comment.body, /no healthy docs preview is available/ );
	assert.match( api.comment.body, new RegExp( previewSha ) );
	assert.match( api.comment.body, new RegExp( currentSha ) );
	assert.match( api.comment.body, /add the `docs-preview` label again/i );
} );

test( 'closing a PR deletes only its preview assets and scoped docs caches', async () => {
	const api = new FakeApi();
	api.currentPullRequest = pullRequest( 'closed' );
	installPreview( api );
	api.assets.push(
		releaseAsset( 3, `code-reference-pr-124-${ previewSha }-300-1.zip` ),
		releaseAsset( 4, 'code-reference-trunk-snapshot.zip' )
	);
	api.caches.push(
		{
			id: 10,
			ref: 'refs/pull/123/merge',
			key: `docs-preview-base-v1-${ 'd'.repeat( 64 ) }`,
		},
		{
			id: 11,
			ref: 'refs/pull/123/merge',
			key: 'unrelated-cache',
		},
		{
			id: 12,
			ref: 'refs/heads/trunk',
			key: `docs-preview-base-v1-${ 'e'.repeat( 64 ) }`,
		}
	);
	const result = await managePullRequest( options( api, event( 'closed' ) ) );
	assert.equal( result.status, 'expired' );
	assert.deepEqual( api.deletedAssets, [ 1, 2 ] );
	assert.deepEqual( api.deletedCaches, [ 10 ] );
	assert.equal( api.cacheRef, 'refs/pull/123/merge' );
	assert.match( api.comment.body, /Status:\*\* Expired/ );
} );

test( 'asset cleanup failure preserves a comment that still links live assets', async () => {
	const api = new FakeApi();
	api.currentPullRequest = pullRequest( 'closed' );
	installPreview( api );
	api.failedAsset = 1;
	api.caches.push( {
		id: 10,
		ref: 'refs/pull/123/merge',
		key: `docs-preview-base-v1-${ 'd'.repeat( 64 ) }`,
	} );
	await assert.rejects(
		managePullRequest( options( api, event( 'closed' ) ) ),
		/Pull request cleanup failed/
	);
	assert.deepEqual( api.deletedAssets, [ 2 ] );
	assert.deepEqual( api.deletedCaches, [ 10 ] );
	assert.equal( api.commentBodies.length, 0 );
} );

test( 'a reopened PR supersedes close cleanup before any deletion', async () => {
	const api = new FakeApi();
	installPreview( api );
	const result = await managePullRequest( options( api, event( 'closed' ) ) );
	assert.equal( result.status, 'superseded' );
	assert.deepEqual( api.deletedAssets, [] );
	assert.deepEqual( api.deletedCaches, [] );
} );
