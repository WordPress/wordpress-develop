import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtemp, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import {
	createPublishedMetadata,
	renderPreviewComment,
} from '../lib/publisher.mjs';
import {
	PLAYGROUND_ORIGIN,
	createReuseHandoff,
	metadataAssetName,
	releaseAssetUrl,
	snapshotAssetName,
} from '../lib/publication.mjs';
import {
	enforceValidationResult,
	formatFailure,
	publishPullRequest,
} from '../publish.mjs';

const repository = 'WordPress/wordpress-develop';
const sourceRepository = 'contributor/wordpress-develop';
const sha = 'a'.repeat( 40 );
const snapshotBytes = Buffer.from( 'snapshot' );

function run() {
	return {
		id: 456,
		run_attempt: 1,
		name: 'Code Reference Playground Preview Build',
		event: 'pull_request',
		status: 'completed',
		head_sha: sha,
		head_branch: 'feature',
		head_repository: {
			full_name: sourceRepository,
			owner: { login: 'contributor' },
		},
	};
}

function pullRequest() {
	return {
		number: 123,
		state: 'open',
		base: { ref: 'trunk' },
		head: {
			sha,
			ref: 'feature',
			repo: { full_name: sourceRepository },
		},
		labels: [ { name: 'docs-preview' } ],
	};
}

function buildMetadata( overrides = {} ) {
	const identity = {
		pullRequestNumber: 123,
		sourceSha: sha,
		workflowRunId: 456,
		workflowRunAttempt: 1,
	};
	return {
		schemaVersion: 1,
		sourceRepository,
		pullRequestNumber: 123,
		sourceSha: sha,
		workflowRunId: '456',
		workflowRunAttempt: 1,
		runUrl: `${
			releaseAssetUrl( repository, 'x' ).split( '/releases/' )[ 0 ]
		}/actions/runs/456`,
		resolvedWordPressBeta: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
		phpVersion: '8.4',
		dependencyManifestDigest: 'b'.repeat( 64 ),
		snapshotFilename: snapshotAssetName( identity ),
		snapshotBytes: snapshotBytes.byteLength,
		snapshotSha256: createHash( 'sha256' )
			.update( snapshotBytes )
			.digest( 'hex' ),
		buildStatus: 'success',
		validationStatus: 'passed',
		validationFailures: [],
		generationTimestamp: '2026-08-09T12:34:56.000Z',
		...overrides,
	};
}

/**
 * @param {number} id
 * @param {string} name
 */
function releaseAsset( id, name, createdAt = '2026-08-08T13:00:00.000Z' ) {
	return {
		id,
		name,
		created_at: createdAt,
		browser_download_url: releaseAssetUrl( repository, name ),
	};
}

class FakeApi {
	constructor() {
		/** @type {any} */
		this.currentRun = run();
		this.currentPullRequest = pullRequest();
		this.latestRun = this.currentRun;
		this.release = { id: 9 };
		/** @type {any[]} */
		this.assets = [];
		this.metadata = new Map();
		this.assetBytes = new Map();
		/** @type {any[]} */
		this.uploads = [];
		/** @type {any[]} */
		this.deleted = [];
		/** @type {any[]} */
		this.comments = [];
		this.labelRemovals = 0;
		this.runReads = 0;
		/** @type {any} */
		this.deleteError = null;
		/** @type {any} */
		this.uploadError = null;
	}

	async getRun() {
		this.runReads++;
		return this.currentRun;
	}

	async findPullRequestForRun() {
		return this.currentPullRequest;
	}

	async getPullRequest() {
		return this.currentPullRequest;
	}

	async latestPreviewRun() {
		return this.latestRun;
	}

	async isSkippedPreviewBuild() {
		return false;
	}

	async getRelease() {
		return this.release;
	}

	async createRelease() {
		this.release = { id: 9 };
		return this.release;
	}

	async listReleaseAssets() {
		return [ ...this.assets ];
	}

	/**
	 * @param {number} releaseId
	 * @param {string} name
	 * @param {Uint8Array} bytes
	 * @param {string} contentType
	 */
	async uploadReleaseAsset( releaseId, name, bytes, contentType ) {
		assert.equal( releaseId, 9 );
		if ( this.uploadError ) {
			throw this.uploadError;
		}
		const asset = {
			id: this.assets.length + 10,
			name,
			created_at: '2026-08-09T13:00:00.000Z',
			browser_download_url: releaseAssetUrl( repository, name ),
		};
		this.assets.push( asset );
		this.uploads.push( { name, contentType } );
		if ( contentType === 'application/json' ) {
			this.metadata.set( name, JSON.parse( bytes.toString() ) );
		} else {
			this.assetBytes.set( name, Buffer.from( bytes ) );
		}
		return asset;
	}

	/**
	 * @param {number} id
	 */
	async deleteReleaseAsset( id ) {
		if ( this.deleteError ) {
			throw this.deleteError;
		}
		this.deleted.push( id );
	}

	async findPreviewComment() {
		return this.comments[ 0 ] || null;
	}

	/**
	 * @param {number} number
	 * @param {string} body
	 */
	async createComment( number, body ) {
		this.comments = [ { id: 1, number, body } ];
	}

	/**
	 * @param {number} id
	 * @param {string} body
	 */
	async updateComment( id, body ) {
		this.comments = [ { id, body } ];
	}

	async removeLabel() {
		this.labelRemovals++;
	}
}

/**
 * @param {FakeApi} api
 */
function publicFetch( api, corrupt = new Set() ) {
	return /** @param {string} url */ async ( url ) => {
		if ( url.includes( 'wordpress-playground-cors-proxy.net' ) ) {
			const name = url.split( '/' ).at( -1 );
			const bytes = corrupt.has( name )
				? Buffer.from( 'corrupt!' )
				: api.assetBytes.get( name );
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
				arrayBuffer: async () => bytes,
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
 * @param {Record<string, any>} metadata
 */
async function handoffDirectory( metadata, includeSnapshot = true ) {
	const directory = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-publish-' )
	);
	await writeFile(
		path.join( directory, 'build.json' ),
		`${ JSON.stringify( metadata ) }\n`
	);
	if ( includeSnapshot ) {
		await writeFile(
			path.join( directory, metadata.snapshotFilename ),
			snapshotBytes
		);
	}
	return directory;
}

/**
 * @param {FakeApi} api
 * @param {string} directory
 */
function options( api, directory, extra = {} ) {
	return {
		repository,
		stagingVariable: '',
		triggerRunId: 456,
		triggerRunAttempt: 1,
		handoffDirectory: directory,
		artifactAvailable: true,
		api,
		fetchImplementation: publicFetch( api ),
		now: () => '2026-08-09T13:00:00.000Z',
		warning: () => {},
		...extra,
	};
}

/**
 * @param {FakeApi} api
 * @param {Record<string, any>} build
 */
function installPublishedPreview(
	api,
	build,
	content = snapshotBytes,
	publishedAt = '2026-08-08T13:00:00.000Z'
) {
	const published = createPublishedMetadata( build, repository, publishedAt );
	const metadataName = metadataAssetName( build.snapshotFilename );
	api.assets.push(
		releaseAsset( 1, build.snapshotFilename, publishedAt ),
		releaseAsset( 2, metadataName, publishedAt )
	);
	api.metadata.set( metadataName, published );
	api.assetBytes.set( build.snapshotFilename, content );
	return published;
}

test( 'a candidate is public before the comment moves and old assets delete', async () => {
	const api = new FakeApi();
	const metadata = buildMetadata();
	const directory = await handoffDirectory( metadata );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'ready' );
	assert.deepEqual(
		api.uploads.map( ( upload ) => upload.contentType ),
		[ 'application/zip', 'application/json' ]
	);
	assert.match( api.comments[ 0 ].body, /Status:\*\* Ready/ );
	assert.match( api.comments[ 0 ].body, /blueprint-url=/ );
	assert.equal( api.labelRemovals, 1 );
	assert.ok( api.runReads >= 5 );
} );

test( 'an advisory validation failure updates the comment but publishes nothing', async () => {
	const api = new FakeApi();
	const metadata = buildMetadata( { validationStatus: 'failed' } );
	const directory = await handoffDirectory( metadata, false );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'invalid' );
	assert.equal( api.uploads.length, 0 );
	assert.match( api.comments[ 0 ].body, /Latest attempt failed/ );
	assert.equal( api.labelRemovals, 1 );
} );

test( 'the trusted publisher enforces invalid fork handoffs', () => {
	const invalid = { status: 'invalid' };
	assert.throws(
		() => enforceValidationResult( invalid, 'true' ),
		/DOCS_PREVIEW_ENFORCE/
	);
	assert.equal( enforceValidationResult( invalid, 'false' ), invalid );
	assert.equal( enforceValidationResult( invalid, 'TRUE' ), invalid );
	assert.equal(
		enforceValidationResult( { status: 'ready' }, 'true' ).status,
		'ready'
	);
} );

test( 'publisher failures retain every nested cause in the Actions log', () => {
	const failure = new AggregateError(
		[
			new Error( 'candidate upload failed' ),
			new AggregateError(
				[
					new Error( 'comment failed' ),
					new Error( 'label removal failed' ),
				],
				'failure reporting failed'
			),
		],
		'publication failed'
	);
	const formatted = formatFailure( failure );

	assert.match( formatted, /candidate upload failed/ );
	assert.match( formatted, /comment failed/ );
	assert.match( formatted, /label removal failed/ );
} );

test( 'a missing handoff always fails after its terminal comment', async () => {
	const api = new FakeApi();
	const directory = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-missing-' )
	);
	await assert.rejects(
		publishPullRequest(
			options( api, directory, { artifactAvailable: false } )
		),
		/artifact is unavailable/
	);
	assert.match( api.comments[ 0 ].body, /Latest attempt failed/ );
	assert.equal( api.labelRemovals, 1 );
} );

test( 'a superseded run makes no mutation', async () => {
	const api = new FakeApi();
	api.latestRun = { ...api.currentRun, id: 789 };
	const directory = await handoffDirectory( buildMetadata() );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'superseded' );
	assert.equal( api.uploads.length, 0 );
	assert.equal( api.comments.length, 0 );
	assert.equal( api.labelRemovals, 0 );
} );

test( 'a re-run source workflow supersedes instead of failing', async () => {
	const api = new FakeApi();
	api.currentRun = { ...run(), run_attempt: 2 };
	const directory = await handoffDirectory( buildMetadata() );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'superseded' );
	assert.equal( api.uploads.length, 0 );
	assert.equal( api.comments.length, 0 );
	assert.equal( api.labelRemovals, 0 );
} );

test( 'an in-progress source workflow supersedes instead of failing', async () => {
	const api = new FakeApi();
	api.currentRun = { ...run(), status: 'in_progress' };
	const directory = await handoffDirectory( buildMetadata() );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'superseded' );
	assert.equal( api.uploads.length, 0 );
	assert.equal( api.comments.length, 0 );
	assert.equal( api.labelRemovals, 0 );
} );

test( 'a fork deleted before publication supersedes instead of failing', async () => {
	const api = new FakeApi();
	api.currentRun = { ...run(), head_repository: null };
	api.findPullRequestForRun = async () => {
		throw new Error( 'Workflow run has no fork head identity.' );
	};
	const directory = await handoffDirectory( buildMetadata() );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'superseded' );
	assert.equal( api.uploads.length, 0 );
	assert.equal( api.comments.length, 0 );
	assert.equal( api.labelRemovals, 0 );
} );

test( 'pull request lookup outages remain visible failures', async () => {
	const api = new FakeApi();
	api.findPullRequestForRun = async () => {
		throw new Error( 'GitHub is unavailable' );
	};
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishPullRequest( options( api, directory ) ),
		/GitHub is unavailable/
	);
	assert.equal( api.uploads.length, 0 );
	assert.equal( api.comments.length, 0 );
} );

test( 'a skipped trigger run makes no mutation', async () => {
	const api = new FakeApi();
	api.isSkippedPreviewBuild = async () => true;
	const directory = await handoffDirectory( buildMetadata() );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'ignored' );
	assert.equal( api.uploads.length, 0 );
	assert.equal( api.comments.length, 0 );
	assert.equal( api.labelRemovals, 0 );
} );

test( 'public delivery failure keeps the previous healthy preview', async () => {
	const api = new FakeApi();
	const oldBytes = Buffer.from( 'old snapshot' );
	const oldBuild = buildMetadata( {
		sourceSha: 'c'.repeat( 40 ),
		workflowRunId: '300',
		snapshotFilename: snapshotAssetName( {
			pullRequestNumber: 123,
			sourceSha: 'c'.repeat( 40 ),
			workflowRunId: 300,
			workflowRunAttempt: 1,
		} ),
		snapshotBytes: oldBytes.byteLength,
		snapshotSha256: createHash( 'sha256' )
			.update( oldBytes )
			.digest( 'hex' ),
	} );
	installPublishedPreview( api, oldBuild, oldBytes );

	const current = buildMetadata();
	const directory = await handoffDirectory( current );
	await assert.rejects(
		publishPullRequest(
			options( api, directory, {
				fetchImplementation: publicFetch(
					api,
					new Set( [ current.snapshotFilename ] )
				),
			} )
		),
		/digest/
	);
	assert.deepEqual(
		api.deleted.sort( ( left, right ) => left - right ),
		[ 12, 13 ]
	);
	assert.match( api.comments[ 0 ].body, /Latest successful docs preview/ );
	assert.match( api.comments[ 0 ].body, new RegExp( 'c'.repeat( 40 ) ) );
} );

test( 'a proxy outage cannot remove the previous comment link', async () => {
	const api = new FakeApi();
	const oldBytes = Buffer.from( 'old snapshot' );
	const oldBuild = buildMetadata( {
		sourceSha: 'c'.repeat( 40 ),
		workflowRunId: '300',
		runUrl: `https://github.com/${ repository }/actions/runs/300`,
		snapshotFilename: snapshotAssetName( {
			pullRequestNumber: 123,
			sourceSha: 'c'.repeat( 40 ),
			workflowRunId: 300,
			workflowRunAttempt: 1,
		} ),
		snapshotBytes: oldBytes.byteLength,
		snapshotSha256: createHash( 'sha256' )
			.update( oldBytes )
			.digest( 'hex' ),
	} );
	const oldPreview = installPublishedPreview( api, oldBuild, oldBytes );
	api.comments = [
		{
			id: 1,
			body: renderPreviewComment( {
				status: 'ready',
				preview: oldPreview,
				runUrl: oldBuild.runUrl,
			} ),
		},
	];

	const current = buildMetadata();
	const directory = await handoffDirectory( current );
	await assert.rejects(
		publishPullRequest(
			options( api, directory, {
				fetchImplementation: publicFetch(
					api,
					new Set( [
						oldBuild.snapshotFilename,
						current.snapshotFilename,
					] )
				),
			} )
		),
		/digest/
	);
	assert.match( api.comments[ 0 ].body, /Latest attempt failed/ );
	assert.match( api.comments[ 0 ].body, /Latest successful docs preview/ );
	assert.match( api.comments[ 0 ].body, new RegExp( 'c'.repeat( 40 ) ) );
	assert.ok(
		api.comments[ 0 ].body.includes( oldPreview.publication.playgroundUrl )
	);
} );

test( 'a retry cannot delete a same-name preview it did not upload', async () => {
	const api = new FakeApi();
	const current = buildMetadata();
	installPublishedPreview( api, current );
	api.uploadError = new Error( 'already_exists' );
	const directory = await handoffDirectory( current );
	await assert.rejects(
		publishPullRequest( options( api, directory ) ),
		/already_exists/
	);
	assert.deepEqual( api.deleted, [] );
	assert.match( api.comments[ 0 ].body, /Latest successful docs preview/ );
} );

test( 'a healthy same-SHA preview is reused without uploading it again', async () => {
	const api = new FakeApi();
	const oldBuild = buildMetadata( {
		workflowRunId: '300',
		workflowRunAttempt: 1,
		runUrl: `https://github.com/${ repository }/actions/runs/300`,
		snapshotFilename: snapshotAssetName( {
			pullRequestNumber: 123,
			sourceSha: sha,
			workflowRunId: 300,
			workflowRunAttempt: 1,
		} ),
	} );
	const oldPublished = installPublishedPreview( api, oldBuild );
	api.assets.push(
		releaseAsset(
			3,
			`code-reference-pr-123-${ 'c'.repeat( 40 ) }-200-1.zip`,
			'2026-08-07T13:00:00.000Z'
		)
	);
	const handoff = createReuseHandoff( oldPublished, {
		sourceRepository,
		pullRequestNumber: 123,
		sourceSha: sha,
		workflowRunId: 456,
		workflowRunAttempt: 1,
		runUrl: `https://github.com/${ repository }/actions/runs/456`,
		maximumBytes: 104857600,
	} );
	const directory = await handoffDirectory( handoff, false );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'reused' );
	assert.equal( api.uploads.length, 0 );
	assert.deepEqual( api.deleted, [ 3 ] );
	assert.match( api.comments[ 0 ].body, /Status:\*\* Ready/ );
	assert.equal( api.labelRemovals, 1 );
} );

test( 'a preview comment deleted mid-update is recreated', async () => {
	const api = new FakeApi();
	api.comments = [ { id: 7, body: 'about to be deleted' } ];
	api.updateComment = async () => {
		const error = Object.assign(
			new Error(
				`GitHub API returned HTTP 404 for /repos/${ repository }/issues/comments/7: Not Found`
			),
			{ status: 404 }
		);
		throw error;
	};
	const directory = await handoffDirectory( buildMetadata() );
	const result = await publishPullRequest( options( api, directory ) );
	assert.equal( result.status, 'ready' );
	assert.equal( api.comments[ 0 ].number, 123 );
	assert.match( api.comments[ 0 ].body, /Status:\*\* Ready/ );
	assert.deepEqual( api.deleted, [] );
	assert.equal( api.labelRemovals, 1 );
} );

test( 'a non-404 comment update failure still fails the publication', async () => {
	const api = new FakeApi();
	api.comments = [ { id: 7, body: 'existing preview comment' } ];
	api.updateComment = async () => {
		throw new Error(
			`GitHub API returned HTTP 500 for /repos/${ repository }/issues/comments/7: boom`
		);
	};
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishPullRequest( options( api, directory ) ),
		( error ) => {
			assert.ok( error instanceof AggregateError );
			assert.match( error.errors[ 0 ].message, /HTTP 500/ );
			return true;
		}
	);
	assert.equal( api.comments[ 0 ].id, 7 );
	assert.deepEqual(
		api.deleted.sort( ( left, right ) => left - right ),
		[ 10, 11 ]
	);
} );

test( 'cleanup failure cannot replace an already-ready comment', async () => {
	const api = new FakeApi();
	api.assets.push(
		releaseAsset(
			1,
			`code-reference-pr-123-${ 'c'.repeat( 40 ) }-300-1.zip`
		)
	);
	api.deleteError = new Error( 'cleanup failed' );
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishPullRequest( options( api, directory ) ),
		/cleanup failed/
	);
	assert.match( api.comments[ 0 ].body, /Status:\*\* Ready/ );
	assert.doesNotMatch( api.comments[ 0 ].body, /Latest attempt failed/ );
	assert.equal( api.labelRemovals, 1 );
} );

test( 'GitHub read failures remain visible publication failures', async () => {
	const api = new FakeApi();
	api.getPullRequest = async () => {
		throw new Error( 'GitHub is unavailable' );
	};
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishPullRequest( options( api, directory ) ),
		( error ) => {
			assert.ok( error instanceof AggregateError );
			assert.match( error.errors[ 0 ].message, /GitHub is unavailable/ );
			return true;
		}
	);
	assert.equal( api.comments.length, 0 );
} );
