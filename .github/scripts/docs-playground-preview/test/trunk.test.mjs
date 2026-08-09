import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

import {
	PLAYGROUND_ORIGIN,
	corsProxyUrl,
	releaseAssetUrl,
} from '../lib/publication.mjs';
import {
	TRUNK_POINTER_ASSET,
	assertLatestTrunkAuthorized,
	createTrunkBlueprint,
	createTrunkPublishedMetadata,
	inspectTrunkHandoff,
	trunkPlaygroundUrl,
	trunkPointerCandidateName,
	trunkSnapshotAssetName,
	trunkStableBlueprintUrl,
	validatePublicBlueprint,
	validatePublishedTrunkMetadata,
	validateTrunkPublicationContext,
} from '../lib/trunk.mjs';

const repository = 'WordPress/wordpress-develop';
const sha = 'a'.repeat( 40 );
const snapshot = Buffer.from( 'snapshot' );

function context( overrides = {} ) {
	const run = {
		id: 456,
		run_attempt: 2,
		name: 'Code Reference Playground Preview Build',
		event: 'push',
		status: 'completed',
		head_branch: 'trunk',
		head_sha: sha,
		head_repository: { full_name: repository },
	};
	return {
		repository,
		stagingVariable: '',
		triggerRunId: 456,
		triggerRunAttempt: 2,
		run,
		latestRun: run,
		trunkHeadSha: sha,
		...overrides,
	};
}

function metadata( overrides = {} ) {
	const identity = {
		sourceSha: sha,
		workflowRunId: 456,
		workflowRunAttempt: 2,
	};
	return {
		schemaVersion: 1,
		sourceRepository: repository,
		pullRequestNumber: null,
		sourceSha: sha,
		workflowRunId: '456',
		workflowRunAttempt: 2,
		runUrl: `https://github.com/${ repository }/actions/runs/456`,
		resolvedWordPressBeta: {
			channel: 'beta',
			version: '7.2-beta1',
			downloadUrl:
				'https://downloads.wordpress.org/release/wordpress-7.2-beta1.zip',
		},
		phpVersion: '8.4',
		dependencyManifestDigest: 'b'.repeat( 64 ),
		snapshotFilename: trunkSnapshotAssetName( identity ),
		snapshotBytes: snapshot.byteLength,
		snapshotSha256: createHash( 'sha256' )
			.update( snapshot )
			.digest( 'hex' ),
		buildStatus: 'success',
		validationStatus: 'passed',
		validationFailures: [],
		generationTimestamp: '2026-08-09T12:34:56.000Z',
		...overrides,
	};
}

function headers( overrides = {} ) {
	const values = {
		'x-playground-cors-proxy': 'true',
		'access-control-allow-origin': PLAYGROUND_ORIGIN,
		...overrides,
	};
	return { get: ( name ) => values[ name.toLowerCase() ] || null };
}

test( 'trunk assets bind the exact SHA, run, and attempt', () => {
	const identity = {
		sourceSha: sha,
		workflowRunId: 456,
		workflowRunAttempt: 2,
	};
	assert.equal(
		trunkSnapshotAssetName( identity ),
		`code-reference-trunk-${ sha }-456-2.zip`
	);
	assert.equal(
		trunkPointerCandidateName( identity ),
		`code-reference-trunk-pointer-${ sha }-456-2.json`
	);
	assert.throws(
		() => trunkSnapshotAssetName( { ...identity, sourceSha: 'short' } ),
		/full lowercase commit/
	);
} );

test( 'the stable trunk entry point uses the fixed public Blueprint asset', () => {
	const blueprintUrl = releaseAssetUrl( repository, TRUNK_POINTER_ASSET );
	assert.equal( trunkStableBlueprintUrl( repository ), blueprintUrl );
	const launch = new URL( trunkPlaygroundUrl( repository ) );
	assert.equal( launch.origin, PLAYGROUND_ORIGIN );
	assert.equal(
		launch.searchParams.get( 'blueprint-url' ),
		corsProxyUrl( blueprintUrl )
	);
} );

test( 'trunk publication binds the exact terminal push and latest head', () => {
	const checked = validateTrunkPublicationContext( context() );
	assert.equal( checked.sourceSha, sha );
	assert.equal( checked.sourceRepository, repository );
	assert.equal( checked.workflowRunAttempt, 2 );
	assert.equal( assertLatestTrunkAuthorized( context() ).workflowRunId, 456 );
	assert.throws(
		() =>
			validateTrunkPublicationContext(
				context( { repository: 'attacker/wordpress-develop' } )
			),
		/publication is disabled/
	);
	assert.throws(
		() =>
			validateTrunkPublicationContext( {
				...context(),
				run: { ...context().run, event: 'pull_request' },
			} ),
		/source workflow identity/
	);
	assert.throws(
		() =>
			assertLatestTrunkAuthorized( {
				...context(),
				trunkHeadSha: 'c'.repeat( 40 ),
			} ),
		/superseded/
	);
} );

test( 'the trunk handoff accepts only a passed exact-run candidate', () => {
	assert.equal(
		inspectTrunkHandoff( metadata(), context() ).kind,
		'candidate'
	);
	assert.equal(
		inspectTrunkHandoff(
			metadata( { validationStatus: 'failed' } ),
			context()
		).kind,
		'invalid'
	);
	assert.equal(
		inspectTrunkHandoff(
			metadata( {
				buildStatus: 'failed',
				validationStatus: 'not-run',
			} ),
			context()
		).kind,
		'failed'
	);
	for ( const [ name, value, error ] of [
		[ 'pullRequestNumber', 123, /trunk run/ ],
		[ 'sourceSha', 'c'.repeat( 40 ), /trunk run/ ],
		[ 'workflowRunAttempt', 1, /trunk run/ ],
		[ 'phpVersion', '8.5', /runtime identity/ ],
		[ 'dependencyManifestDigest', 'broken', /dependency identity/ ],
		[ 'snapshotFilename', 'snapshot.zip', /filename/ ],
		[ 'snapshotBytes', 104857601, /snapshot identity/ ],
	] ) {
		assert.throws(
			() =>
				inspectTrunkHandoff(
					metadata( { [ name ]: value } ),
					context()
				),
			error
		);
	}
} );

test( 'published trunk metadata produces an exact immutable Blueprint', () => {
	const published = createTrunkPublishedMetadata(
		metadata(),
		repository,
		'2026-08-09T13:00:00.000Z'
	);
	assert.equal(
		validatePublishedTrunkMetadata( published, repository, sha ),
		published
	);
	const blueprint = createTrunkBlueprint( published );
	assert.equal( blueprint.landingPage, '/reference/' );
	assert.equal( blueprint.login, false );
	assert.equal( blueprint.features.networking, false );
	assert.equal(
		blueprint.steps[ 0 ].zipFile.url,
		published.publication.snapshotProxyUrl
	);
	assert.match( blueprint.meta.description, new RegExp( sha ) );
	assert.match( blueprint.meta.description, /actions\/runs\/456/ );
	const broken = structuredClone( published );
	broken.publication.stableBlueprintUrl = 'https://example.test/broken';
	assert.throws(
		() => validatePublishedTrunkMetadata( broken, repository, sha ),
		/pointer metadata/
	);
} );

test( 'the public Blueprint must arrive intact through the Playground proxy', async () => {
	const expected = createTrunkBlueprint(
		createTrunkPublishedMetadata(
			metadata(),
			repository,
			'2026-08-09T13:00:00.000Z'
		)
	);
	const publicUrl = trunkStableBlueprintUrl( repository );
	const fetchImplementation = async ( url, options ) => {
		assert.equal( url, corsProxyUrl( publicUrl ) );
		assert.equal( options.headers.Origin, PLAYGROUND_ORIGIN );
		return {
			status: 200,
			headers: headers(),
			arrayBuffer: async () =>
				Buffer.from( `${ JSON.stringify( expected ) }\n` ),
		};
	};
	assert.deepEqual(
		await validatePublicBlueprint(
			publicUrl,
			expected,
			fetchImplementation
		),
		expected
	);
	await assert.rejects(
		validatePublicBlueprint( publicUrl, expected, async () => ( {
			status: 200,
			headers: headers(),
			arrayBuffer: async () => Buffer.from( '{}' ),
		} ) ),
		/does not identify/
	);
	await assert.rejects(
		validatePublicBlueprint( publicUrl, expected, async () => ( {
			status: 200,
			headers: headers( { 'x-playground-cors-proxy': 'false' } ),
			arrayBuffer: async () => Buffer.from( '{}' ),
		} ) ),
		/did not come through/
	);
} );
