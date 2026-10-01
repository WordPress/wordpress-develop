import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

import { PLAYGROUND_ORIGIN, corsProxyUrl } from '../lib/publication.mjs';
import {
	TRUNK_POINTER_ASSET,
	TRUNK_POINTER_REF,
	assertLatestTrunkAuthorized,
	createTrunkBlueprint,
	createTrunkPublishedMetadata,
	inspectTrunkHandoff,
	trunkPlaygroundUrl,
	trunkBlueprintCommitUrl,
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
	/** @type {Record<string, string>} */
	const values = {
		'x-playground-cors-proxy': 'true',
		'access-control-allow-origin': PLAYGROUND_ORIGIN,
		...overrides,
	};
	return {
		get: /** @param {string} name */ ( name ) =>
			values[ name.toLowerCase() ] || null,
	};
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
		trunkBlueprintCommitUrl( repository, sha ),
		`https://raw.githubusercontent.com/${ repository }/${ sha }/${ TRUNK_POINTER_ASSET }`
	);
	assert.equal( TRUNK_POINTER_REF, 'heads/docs-preview-code-reference' );
	assert.throws(
		() => trunkSnapshotAssetName( { ...identity, sourceSha: 'short' } ),
		/full lowercase commit/
	);
} );

test( 'the stable trunk entry point uses the fixed public Blueprint asset', () => {
	const blueprintUrl = `https://raw.githubusercontent.com/${ repository }/docs-preview-code-reference/${ TRUNK_POINTER_ASSET }`;
	assert.equal( trunkStableBlueprintUrl( repository ), blueprintUrl );
	const launch = new URL( trunkPlaygroundUrl( repository ) );
	assert.equal( launch.origin, PLAYGROUND_ORIGIN );
	assert.equal(
		launch.searchParams.get( 'blueprint-url' ),
		corsProxyUrl( blueprintUrl )
	);
} );

test( 'the repository README links the stable trunk entry point', async () => {
	const readme = await readFile(
		new URL( '../../../../README.md', import.meta.url ),
		'utf8'
	);
	assert.match( readme, /latest successful Core Code Reference preview/ );
	assert.ok( readme.includes( trunkPlaygroundUrl( repository ) ) );
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
	assert.equal(
		assertLatestTrunkAuthorized( {
			...context(),
			trunkHeadSha: 'c'.repeat( 40 ),
		} ).sourceSha,
		sha
	);
	assert.throws(
		() =>
			assertLatestTrunkAuthorized( {
				...context(),
				latestRun: { ...context().run, id: 789 },
			} ),
		/superseded/
	);
	assert.throws(
		() =>
			assertLatestTrunkAuthorized( {
				...context(),
				latestRun: { ...context().run, run_attempt: 3 },
			} ),
		/superseded/
	);
} );

test( 'supersession races are marked distinctly from identity failures', () => {
	assert.throws(
		() =>
			validateTrunkPublicationContext(
				context( { triggerRunAttempt: 1 } )
			),
		/** @type {(error: any) => boolean} */ (
			( error ) =>
				error.trunkRunSuperseded === true &&
				/no longer current/.test( error.message )
		)
	);
	assert.throws(
		() =>
			validateTrunkPublicationContext( {
				...context(),
				run: { ...context().run, status: 'in_progress' },
			} ),
		/** @type {(error: any) => boolean} */ (
			( error ) =>
				error.trunkRunSuperseded === true &&
				/not terminal/.test( error.message )
		)
	);
	assert.throws(
		() =>
			validateTrunkPublicationContext( {
				...context(),
				run: { ...context().run, head_branch: 'main' },
			} ),
		/** @type {(error: any) => boolean} */ (
			( error ) =>
				error.trunkRunSuperseded === undefined &&
				/workflow identity/.test( error.message )
		)
	);
	assert.throws(
		() =>
			assertLatestTrunkAuthorized( {
				...context(),
				latestRun: { ...context().run, id: 999 },
			} ),
		/** @type {(error: any) => boolean} */ (
			( error ) => error.trunkRunSuperseded === true
		)
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
	/** @type {Array<[string, any, RegExp]>} */
	const invalidFields = [
		[ 'pullRequestNumber', 123, /trunk run/ ],
		[ 'sourceSha', 'c'.repeat( 40 ), /trunk run/ ],
		[ 'workflowRunAttempt', 1, /trunk run/ ],
		[ 'phpVersion', '8.5', /runtime identity/ ],
		[ 'dependencyManifestDigest', 'broken', /dependency identity/ ],
		[ 'snapshotFilename', 'snapshot.zip', /filename/ ],
		[ 'snapshotBytes', 104857601, /snapshot identity/ ],
	];
	for ( const [ name, value, error ] of invalidFields ) {
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
	const fetchImplementation =
		/** @param {string} url @param {Record<string, any>} options */ async (
			url,
			options
		) => {
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
