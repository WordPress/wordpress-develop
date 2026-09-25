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

/**
 * @param {any[]} labels
 */
function pullRequest( state = 'open', labels = [], headSha = currentSha ) {
	return {
		number: 123,
		state,
		base: { ref: 'trunk' },
		head: {
			sha: headSha,
			ref: 'feature',
			repo: { full_name: sourceRepository },
		},
		labels,
	};
}

/**
 * @param {string} action
 */
function event(
	action,
	state = action === 'closed' ? 'closed' : 'open',
	headSha = currentSha
) {
	return { action, pull_request: pullRequest( state, [], headSha ) };
}

/** @returns {Record<string, any>} */
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

/**
 * @param {number} id
 * @param {string} name
 */
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
		/** @type {any[]} */
		this.assets = [];
		/** @type {any[]} */
		this.caches = [];
		this.metadata = new Map();
		this.assetBytes = new Map();
		this.comment = { id: 20, body: `${ COMMENT_MARKER }\nReady` };
		/** @type {any[]} */
		this.deletedAssets = [];
		/** @type {any[]} */
		this.deletedCaches = [];
		/** @type {any[]} */
		this.commentBodies = [];
		/** @type {any} */
		this.failedAsset = null;
		this.cacheRef = null;
		/** @type {any} */
		this.replacementComment = null;
		this.commentReads = 0;
		/** @type {any} */
		this.laterPullRequest = null;
		this.pullRequestReads = 0;
		this.reopenAfterAssetDeletion = false;
	}

	async getPullRequest() {
		this.pullRequestReads++;
		if ( this.pullRequestReads > 1 && this.laterPullRequest ) {
			return this.laterPullRequest;
		}
		return this.currentPullRequest;
	}

	async getRelease() {
		return this.release;
	}

	async listReleaseAssets() {
		return [ ...this.assets ];
	}

	/**
	 * @param {string} ref
	 */
	async listActionCaches( ref ) {
		this.cacheRef = ref;
		return [ ...this.caches ];
	}

	async findPreviewComment() {
		this.commentReads++;
		if ( this.commentReads > 1 && this.replacementComment ) {
			return this.replacementComment;
		}
		return this.comment;
	}

	/**
	 * @param {number} id
	 * @param {string} body
	 */
	async updateComment( id, body ) {
		assert.equal( id, this.comment.id );
		this.commentBodies.push( body );
		this.comment = { id, body };
	}

	/**
	 * @param {number} id
	 */
	async deleteReleaseAsset( id ) {
		if ( id === this.failedAsset ) {
			throw new Error( 'release deletion failed' );
		}
		this.deletedAssets.push( id );
		if ( this.reopenAfterAssetDeletion ) {
			this.currentPullRequest = pullRequest( 'open' );
		}
	}

	/**
	 * @param {number} id
	 */
	async deleteActionCache( id ) {
		this.deletedCaches.push( id );
	}
}

/**
 * @param {FakeApi} api
 */
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

/**
 * @param {FakeApi} api
 * @returns {(...args: any[]) => Promise<any>}
 */
function publicFetch( api ) {
	return /** @param {string} url */ async ( url ) => {
		if ( url.includes( 'wordpress-playground-cors-proxy.net' ) ) {
			const name = url.split( '/' ).at( -1 );
			return {
				status: 200,
				headers: {
					get: /** @param {string} header */ ( header ) => {
						/** @type {Record<string, string>} */
						const values = {
							'x-playground-cors-proxy': 'true',
							'access-control-allow-origin': PLAYGROUND_ORIGIN,
						};
						return values[ header.toLowerCase() ] || null;
					},
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

/**
 * @param {FakeApi} api
 * @param {Record<string, any>} lifecycleEvent
 */
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

test( 'a proxy outage cannot remove the last successful stale link', async () => {
	const api = new FakeApi();
	const preview = installPreview( api );
	api.comment.body = renderPreviewComment( {
		status: 'ready',
		preview,
		runUrl: preview.runUrl,
	} );
	const lifecycleOptions = options( api, event( 'synchronize' ) );
	lifecycleOptions.fetchImplementation = async () => ( { status: 503 } );

	const result = await managePullRequest( lifecycleOptions );

	assert.equal( result.status, 'stale' );
	assert.match( api.comment.body, /Status:\*\* Stale/ );
	assert.match( api.comment.body, /Latest successful docs preview/ );
	assert.ok( api.comment.body.includes( preview.publication.playgroundUrl ) );
	assert.match( api.comment.body, new RegExp( previewSha ) );
	assert.match( api.comment.body, new RegExp( currentSha ) );
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

test( 'a newer terminal comment supersedes an older stale event', async () => {
	const api = new FakeApi();
	installPreview( api );
	api.replacementComment = {
		id: api.comment.id,
		body: renderPreviewComment( {
			status: 'failed',
			sourceRepository,
			sourceSha: currentSha,
			at: '2026-08-09T13:00:00.000Z',
			runUrl: `https://github.com/${ repository }/actions/runs/456`,
			previous: previewMetadata(),
		} ),
	};
	const result = await managePullRequest(
		options( api, event( 'synchronize' ) )
	);
	assert.equal( result.status, 'ignored' );
	assert.equal( api.commentBodies.length, 0 );
} );

test( 're-adding the label during a slow stale pass yields to the publisher', async () => {
	const api = new FakeApi();
	installPreview( api );
	api.laterPullRequest = pullRequest( 'open', [ { name: 'docs-preview' } ] );
	const result = await managePullRequest(
		options( api, event( 'synchronize' ) )
	);
	assert.equal( result.status, 'ignored' );
	assert.equal( api.commentBodies.length, 0 );
} );

test( 'a delayed stale event ignores the current terminal comment', async () => {
	const api = new FakeApi();
	api.comment.body = renderPreviewComment( {
		status: 'failed',
		sourceRepository,
		sourceSha: currentSha,
		at: '2026-08-09T13:00:00.000Z',
		runUrl: `https://github.com/${ repository }/actions/runs/456`,
		previous: previewMetadata(),
	} );
	const result = await managePullRequest(
		options( api, event( 'synchronize' ) )
	);
	assert.equal( result.status, 'ignored' );
	assert.equal( api.commentBodies.length, 0 );
} );

test( 'returning to a stale preview SHA refreshes obsolete visible state', async () => {
	const api = new FakeApi();
	installPreview( api );
	await managePullRequest( options( api, event( 'synchronize' ) ) );
	assert.match( api.comment.body, new RegExp( currentSha ) );

	api.currentPullRequest = pullRequest( 'open', [], previewSha );
	const result = await managePullRequest(
		options( api, event( 'synchronize', 'open', previewSha ) )
	);
	assert.equal( result.status, 'stale' );
	assert.doesNotMatch( api.comment.body, new RegExp( currentSha ) );
	assert.match( api.comment.body, new RegExp( previewSha ) );
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

test( 'a reopen during asset deletion stops cleanup without failing the job', async () => {
	const api = new FakeApi();
	api.currentPullRequest = pullRequest( 'closed' );
	installPreview( api );
	api.reopenAfterAssetDeletion = true;
	api.caches.push( {
		id: 10,
		ref: 'refs/pull/123/merge',
		key: `docs-preview-base-v1-${ 'd'.repeat( 64 ) }`,
	} );
	const result = await managePullRequest( options( api, event( 'closed' ) ) );
	assert.equal( result.status, 'superseded' );
	assert.deepEqual( api.deletedAssets, [ 1 ] );
	assert.deepEqual( api.deletedCaches, [] );
	assert.equal( api.commentBodies.length, 0 );
} );

test( 'a reopen mid-cleanup still surfaces genuine deletion failures', async () => {
	const api = new FakeApi();
	api.currentPullRequest = pullRequest( 'closed' );
	installPreview( api );
	api.failedAsset = 1;
	api.reopenAfterAssetDeletion = true;
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
	assert.deepEqual( api.deletedCaches, [] );
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
