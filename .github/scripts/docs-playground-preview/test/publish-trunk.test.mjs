import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtemp, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

import {
	PLAYGROUND_ORIGIN,
	metadataAssetName,
	releaseAssetUrl,
} from '../lib/publication.mjs';
import {
	TRUNK_POINTER_REF,
	createTrunkBlueprint,
	createTrunkPublishedMetadata,
	trunkSnapshotAssetName,
} from '../lib/trunk.mjs';
import { publishTrunk } from '../publish-trunk.mjs';

const repository = 'WordPress/wordpress-develop';
const sha = 'a'.repeat( 40 );
const snapshotBytes = Buffer.from( 'snapshot' );

function run( overrides = {} ) {
	return {
		id: 456,
		run_attempt: 1,
		name: 'Code Reference Playground Preview Build',
		event: 'push',
		status: 'completed',
		head_branch: 'trunk',
		head_sha: sha,
		head_repository: { full_name: repository },
		...overrides,
	};
}

function buildMetadata( overrides = {} ) {
	const sourceSha = overrides.sourceSha || sha;
	const workflowRunId = overrides.workflowRunId || '456';
	const workflowRunAttempt = overrides.workflowRunAttempt || 1;
	const bytes = overrides.bytes || snapshotBytes;
	return {
		schemaVersion: 1,
		sourceRepository: repository,
		pullRequestNumber: null,
		sourceSha,
		workflowRunId,
		workflowRunAttempt,
		runUrl: `https://github.com/${ repository }/actions/runs/${ workflowRunId }`,
		resolvedWordPressBeta: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
		phpVersion: '8.4',
		dependencyManifestDigest: 'b'.repeat( 64 ),
		snapshotFilename: trunkSnapshotAssetName( {
			sourceSha,
			workflowRunId,
			workflowRunAttempt,
		} ),
		snapshotBytes: bytes.byteLength,
		snapshotSha256: createHash( 'sha256' ).update( bytes ).digest( 'hex' ),
		buildStatus: 'success',
		validationStatus: 'passed',
		validationFailures: [],
		generationTimestamp: '2026-08-09T12:34:56.000Z',
		...overrides,
	};
}

class FakeApi {
	constructor() {
		this.currentRun = run();
		this.latestRun = this.currentRun;
		this.trunkHeadSha = sha;
		this.release = { id: 9 };
		this.assets = [];
		this.assetBytes = new Map();
		this.deleted = [];
		this.events = [];
		this.nextId = 10;
		this.deleteErrorId = null;
		this.refSha = null;
		this.previousRefSha = null;
		this.commits = new Map();
		this.pendingBlueprint = null;
		this.refReads = 0;
		this.refUpdateFailure = null;
		this.failRefResolution = false;
		this.staleRefAfterMutation = false;
	}

	async getRun() {
		return this.currentRun;
	}

	async latestTrunkPreviewRun() {
		return this.latestRun;
	}

	async getTrunkHeadSha() {
		return this.trunkHeadSha;
	}

	async getRelease() {
		return this.release;
	}

	async createRelease() {
		this.release = { id: 9 };
		return this.release;
	}

	async listReleaseAssets() {
		return this.assets.map( ( asset ) => ( { ...asset } ) );
	}

	async uploadReleaseAsset( releaseId, name, bytes, contentType ) {
		assert.equal( releaseId, 9 );
		const asset = {
			id: this.nextId++,
			name,
			content_type: contentType,
			browser_download_url: releaseAssetUrl( repository, name ),
		};
		this.assets.push( asset );
		this.assetBytes.set( asset.id, Buffer.from( bytes ) );
		this.events.push( `upload:${ name }` );
		return { ...asset };
	}

	async getGitReference( reference ) {
		assert.equal( reference, TRUNK_POINTER_REF );
		this.refReads++;
		const visibleSha =
			this.staleRefAfterMutation && this.refReads > 1
				? this.previousRefSha
				: this.refSha;
		this.events.push( `git:read-ref:${ visibleSha }` );
		if ( this.failRefResolution && this.refReads > 1 ) {
			throw new Error( 'ref resolution failed' );
		}
		return visibleSha ? { object: { sha: visibleSha } } : null;
	}

	async createGitBlob( content ) {
		this.pendingBlueprint = content;
		this.events.push( 'git:blob' );
		return { sha: 'd'.repeat( 40 ) };
	}

	async createGitTree() {
		this.events.push( 'git:tree' );
		return { sha: 'e'.repeat( 40 ) };
	}

	async createGitCommit() {
		const commitSha = 'f'.repeat( 40 );
		this.commits.set( commitSha, Buffer.from( this.pendingBlueprint ) );
		this.events.push( 'git:commit' );
		return { sha: commitSha };
	}

	async createGitReference( reference, candidateSha ) {
		assert.equal( reference, TRUNK_POINTER_REF );
		this.refSha = candidateSha;
		this.events.push( `git:create-ref:${ candidateSha }` );
	}

	async updateGitReference( reference, candidateSha ) {
		assert.equal( reference, TRUNK_POINTER_REF );
		if ( this.refUpdateFailure === 'before' ) {
			throw new Error( 'ref update failed' );
		}
		this.refSha = candidateSha;
		this.events.push( `git:update-ref:${ candidateSha }` );
		if ( this.refUpdateFailure === 'after' ) {
			throw new Error( 'ref update response failed' );
		}
	}

	async deleteReleaseAsset( id ) {
		if ( id === this.deleteErrorId ) {
			throw new Error( 'cleanup failed' );
		}
		this.deleted.push( id );
		this.events.push( `delete:${ id }` );
		this.assets = this.assets.filter( ( asset ) => asset.id !== id );
		this.assetBytes.delete( id );
	}
}

function publicFetch( api, fetchOptions = {} ) {
	return async ( url ) => {
		const rawStart = url.indexOf( 'https://raw.githubusercontent.com/' );
		if ( rawStart !== -1 ) {
			const rawUrl = new URL( url.slice( rawStart ) );
			const parts = rawUrl.pathname.split( '/' ).filter( Boolean );
			const reference = parts[ 2 ];
			let commitSha = reference;
			if ( reference === 'docs-preview-code-reference' ) {
				commitSha = fetchOptions.staleStablePointer
					? api.previousRefSha
					: api.refSha;
			}
			api.events.push(
				`fetch:${
					reference === 'docs-preview-code-reference'
						? 'stable-pointer'
						: 'candidate-pointer'
				}`
			);
			const bytes = api.commits.get( commitSha );
			return {
				status: bytes ? 200 : 404,
				headers: {
					get: ( header ) =>
						( {
							'x-playground-cors-proxy': 'true',
							'access-control-allow-origin': PLAYGROUND_ORIGIN,
						} )[ header.toLowerCase() ] || null,
				},
				arrayBuffer: async () => bytes,
			};
		}
		const throughProxy = url.includes(
			'wordpress-playground-cors-proxy.net'
		);
		const name = new URL(
			throughProxy
				? url.slice( url.indexOf( 'https://github.com/' ) )
				: url
		).pathname
			.split( '/' )
			.at( -1 );
		const asset = api.assets.find(
			( candidate ) => candidate.name === name
		);
		api.events.push( `fetch:${ name }` );
		if ( ! asset ) {
			return { status: 404, headers: { get: () => null } };
		}
		let bytes = api.assetBytes.get( asset.id );
		if ( fetchOptions.corruptSnapshot && asset.name.endsWith( '.zip' ) ) {
			bytes = Buffer.from( 'corrupt' );
		}
		return {
			status: 200,
			headers: {
				get: ( header ) =>
					( {
						'x-playground-cors-proxy': 'true',
						'access-control-allow-origin': PLAYGROUND_ORIGIN,
					} )[ header.toLowerCase() ] || null,
			},
			arrayBuffer: async () => bytes,
			json: async () => JSON.parse( bytes.toString( 'utf8' ) ),
		};
	};
}

async function handoffDirectory( metadata, bytes = snapshotBytes ) {
	const directory = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-trunk-publish-' )
	);
	await writeFile(
		path.join( directory, 'build.json' ),
		`${ JSON.stringify( metadata ) }\n`
	);
	if ( metadata.snapshotFilename ) {
		await writeFile(
			path.join( directory, metadata.snapshotFilename ),
			bytes
		);
	}
	return directory;
}

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

function addAsset( api, id, name, bytes ) {
	api.assets.push( {
		id,
		name,
		browser_download_url: releaseAssetUrl( repository, name ),
	} );
	api.assetBytes.set( id, Buffer.from( bytes ) );
}

function installPrevious( api ) {
	const oldSha = 'c'.repeat( 40 );
	const oldBytes = Buffer.from( 'old snapshot' );
	const build = buildMetadata( {
		sourceSha: oldSha,
		workflowRunId: '300',
		bytes: oldBytes,
		snapshotBytes: oldBytes.byteLength,
		snapshotSha256: createHash( 'sha256' )
			.update( oldBytes )
			.digest( 'hex' ),
	} );
	const published = createTrunkPublishedMetadata(
		build,
		repository,
		'2026-08-08T13:00:00.000Z'
	);
	addAsset( api, 1, build.snapshotFilename, oldBytes );
	addAsset(
		api,
		2,
		metadataAssetName( build.snapshotFilename ),
		`${ JSON.stringify( published ) }\n`
	);
	addAsset( api, 4, 'code-reference-pr-123-preview.zip', 'pr snapshot' );
	api.previousRefSha = '9'.repeat( 40 );
	api.refSha = api.previousRefSha;
	api.commits.set(
		api.refSha,
		Buffer.from(
			`${ JSON.stringify( createTrunkBlueprint( published ) ) }\n`
		)
	);
	return { build, published };
}

test( 'the stable pointer moves only after every candidate is public', async () => {
	const api = new FakeApi();
	installPrevious( api );
	const current = buildMetadata();
	const directory = await handoffDirectory( current );
	const result = await publishTrunk( options( api, directory ) );
	assert.equal( result.status, 'ready' );
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	const blueprint = JSON.parse(
		api.commits.get( api.refSha ).toString( 'utf8' )
	);
	assert.match( blueprint.meta.description, new RegExp( sha ) );
	assert.deepEqual( api.deleted.sort(), [ 1, 2 ] );
	assert.ok( api.assets.some( ( asset ) => asset.id === 4 ) );
	const candidateFetch = api.events.lastIndexOf( 'fetch:candidate-pointer' );
	const pointerMove = api.events.lastIndexOf(
		`git:update-ref:${ 'f'.repeat( 40 ) }`
	);
	const pointerRead = api.events.lastIndexOf(
		`git:read-ref:${ 'f'.repeat( 40 ) }`
	);
	const stableFetch = api.events.lastIndexOf( 'fetch:stable-pointer' );
	const firstDelete = api.events.findIndex( ( event ) =>
		event.startsWith( 'delete:' )
	);
	assert.ok(
		candidateFetch > -1 &&
			candidateFetch < pointerMove &&
			pointerMove < pointerRead &&
			pointerRead < stableFetch &&
			stableFetch < firstDelete
	);
} );

test( 'stale stable delivery retains both snapshot generations', async () => {
	const api = new FakeApi();
	installPrevious( api );
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk(
			options( api, directory, {
				fetchImplementation: publicFetch( api, {
					staleStablePointer: true,
				} ),
			} )
		),
		/does not identify the candidate/
	);
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 1 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 10 ) );
	assert.deepEqual( api.deleted, [] );
} );

test( 'public candidate failure leaves the previous pointer and snapshot intact', async () => {
	const api = new FakeApi();
	installPrevious( api );
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk(
			options( api, directory, {
				fetchImplementation: publicFetch( api, {
					corruptSnapshot: true,
				} ),
			} )
		),
		/Public snapshot/
	);
	assert.equal( api.refSha, api.previousRefSha );
	assert.ok( api.assets.some( ( asset ) => asset.id === 1 ) );
	assert.deepEqual( api.deleted.sort(), [ 10, 11 ] );
} );

test( 'a failed ref update retains the previous and candidate assets', async () => {
	const api = new FakeApi();
	installPrevious( api );
	api.refUpdateFailure = 'before';
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk( options( api, directory ) ),
		/ref update failed/
	);
	assert.equal( api.refSha, api.previousRefSha );
	assert.ok( api.assets.some( ( asset ) => asset.id === 1 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 10 ) );
	assert.deepEqual( api.deleted, [] );
} );

test( 'an ambiguous ref response resolves the candidate and completes', async () => {
	const api = new FakeApi();
	installPrevious( api );
	api.refUpdateFailure = 'after';
	const directory = await handoffDirectory( buildMetadata() );
	assert.equal(
		( await publishTrunk( options( api, directory ) ) ).status,
		'ready'
	);
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	assert.deepEqual( api.deleted.sort(), [ 1, 2 ] );
} );

test( 'unresolved ref state retains both valid generations', async () => {
	const api = new FakeApi();
	installPrevious( api );
	api.refUpdateFailure = 'after';
	api.failRefResolution = true;
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk( options( api, directory ) ),
		/ref resolution failed/
	);
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 1 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 10 ) );
	assert.deepEqual( api.deleted, [] );
} );

test( 'a stale read after ref creation retains the candidate assets', async () => {
	const api = new FakeApi();
	api.staleRefAfterMutation = true;
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk( options( api, directory ) ),
		/pointer mutation has unknown state/
	);
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	assert.equal( api.assets.length, 2 );
	assert.deepEqual( api.deleted, [] );
} );

test( 'an update error and stale read retain both asset generations', async () => {
	const api = new FakeApi();
	installPrevious( api );
	api.refUpdateFailure = 'after';
	api.staleRefAfterMutation = true;
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk( options( api, directory ) ),
		/ref update response failed/
	);
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 1 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 10 ) );
	assert.deepEqual( api.deleted, [] );
} );

test( 'cleanup failure keeps the new validated pointer and both snapshots', async () => {
	const api = new FakeApi();
	installPrevious( api );
	api.deleteErrorId = 1;
	const directory = await handoffDirectory( buildMetadata() );
	await assert.rejects(
		publishTrunk( options( api, directory ) ),
		/cleanup failed/
	);
	assert.equal( api.refSha, 'f'.repeat( 40 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 1 ) );
	assert.ok( api.assets.some( ( asset ) => asset.id === 10 ) );
} );

test( 'invalid, missing, and superseded handoffs never move the pointer', async () => {
	const invalidApi = new FakeApi();
	installPrevious( invalidApi );
	const invalid = buildMetadata( { validationStatus: 'failed' } );
	assert.equal(
		(
			await publishTrunk(
				options( invalidApi, await handoffDirectory( invalid ) )
			)
		).status,
		'invalid'
	);
	assert.equal( invalidApi.assets.length, 3 );

	const missingApi = new FakeApi();
	installPrevious( missingApi );
	await assert.rejects(
		publishTrunk(
			options( missingApi, await handoffDirectory( buildMetadata() ), {
				artifactAvailable: false,
			} )
		),
		/handoff is unavailable/
	);
	assert.equal( missingApi.assets.length, 3 );

	const supersededApi = new FakeApi();
	installPrevious( supersededApi );
	supersededApi.latestRun = run( { id: 789 } );
	assert.equal(
		(
			await publishTrunk(
				options(
					supersededApi,
					await handoffDirectory( buildMetadata() )
				)
			)
		).status,
		'superseded'
	);
	assert.equal( supersededApi.assets.length, 3 );
} );
