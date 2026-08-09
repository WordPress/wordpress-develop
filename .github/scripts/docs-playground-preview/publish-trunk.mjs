#!/usr/bin/env node

import { isDeepStrictEqual } from 'node:util';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

import { GitHubApi } from './lib/github.mjs';
import {
	metadataAssetName,
	releaseAssetUrl,
	validatePublicSnapshot,
} from './lib/publication.mjs';
import { validateCandidateFile } from './lib/publisher.mjs';
import {
	TRUNK_POINTER_ASSET,
	TRUNK_POINTER_REF,
	assertLatestTrunkAuthorized,
	createTrunkBlueprint,
	createTrunkPublishedMetadata,
	inspectTrunkHandoff,
	trunkBlueprintCommitUrl,
	trunkStableBlueprintUrl,
	validatePublicBlueprint,
	validatePublishedTrunkMetadata,
	validateTrunkPublicationContext,
} from './lib/trunk.mjs';

class SupersededTrunkRun extends Error {}

async function readHandoff( directory ) {
	return JSON.parse(
		await readFile( path.join( directory, 'build.json' ), 'utf8' )
	);
}

async function establishSession( options ) {
	const api =
		options.api ||
		new GitHubApi(
			options.repository,
			options.token,
			options.fetchImplementation
		);
	const run = await api.getRun( options.triggerRunId );
	const base = {
		repository: options.repository,
		stagingVariable: options.stagingVariable,
		triggerRunId: options.triggerRunId,
		triggerRunAttempt: options.triggerRunAttempt,
		run,
	};
	const context = validateTrunkPublicationContext( base );
	return {
		api,
		context,
		repository: options.repository,
		fetchImplementation: options.fetchImplementation || globalThis.fetch,
		warning:
			options.warning ||
			( ( message ) => process.stderr.write( `${ message }\n` ) ),
		now: options.now || ( () => new Date().toISOString() ),
		handoffDirectory: path.resolve( options.handoffDirectory ),
		authorize: async () => {
			const currentRun = await api.getRun( options.triggerRunId );
			const latestRun = await api.latestTrunkPreviewRun();
			const trunkHeadSha = await api.getTrunkHeadSha();
			try {
				return assertLatestTrunkAuthorized( {
					...base,
					run: currentRun,
					latestRun,
					trunkHeadSha,
				} );
			} catch ( error ) {
				throw new SupersededTrunkRun( error.message );
			}
		},
	};
}

async function ensureRelease( session ) {
	let release = await session.api.getRelease();
	if ( release ) {
		return release;
	}
	await session.authorize();
	try {
		release = await session.api.createRelease();
	} catch ( error ) {
		release = await session.api.getRelease();
		if ( ! release ) {
			throw error;
		}
	}
	return release;
}

async function validatePublicMetadata( session, asset, expected ) {
	const response = await session.fetchImplementation(
		asset.browser_download_url
	);
	if ( response.status !== 200 ) {
		throw new Error(
			`Public trunk metadata returned HTTP ${ response.status }.`
		);
	}
	const metadata = validatePublishedTrunkMetadata(
		await response.json(),
		session.repository,
		session.context.sourceSha
	);
	if ( ! isDeepStrictEqual( metadata, expected ) ) {
		throw new Error(
			'Public trunk metadata does not match the candidate.'
		);
	}
	return metadata;
}

async function cleanupUploaded( session, uploaded ) {
	for ( const asset of uploaded ) {
		try {
			await session.api.deleteReleaseAsset( asset.id );
		} catch ( error ) {
			session.warning(
				`Cannot remove failed trunk candidate ${ asset.name }: ${ error.message }`
			);
		}
	}
}

function referenceSha( reference ) {
	return reference?.object?.sha || null;
}

async function createPointerCommit( session, previousRef, blueprint ) {
	const content = `${ JSON.stringify( blueprint, null, 2 ) }\n`;
	await session.authorize();
	const blob = await session.api.createGitBlob( content );
	await session.authorize();
	const tree = await session.api.createGitTree(
		TRUNK_POINTER_ASSET,
		blob.sha
	);
	await session.authorize();
	const commit = await session.api.createGitCommit(
		`Code Reference preview for ${ session.context.sourceSha }`,
		tree.sha,
		referenceSha( previousRef )
	);
	await validatePublicBlueprint(
		trunkBlueprintCommitUrl( session.repository, commit.sha ),
		blueprint,
		session.fetchImplementation
	);
	return commit;
}

async function resolvePointerMutation( session, previousSha, candidateSha ) {
	let current;
	try {
		current = referenceSha(
			await session.api.getGitReference( TRUNK_POINTER_REF )
		);
	} catch ( error ) {
		error.pointerStateUnknown = true;
		throw error;
	}
	if ( current === candidateSha ) {
		return true;
	}
	if ( current === previousSha ) {
		return false;
	}
	const error = new Error( 'The stable trunk pointer has unknown state.' );
	error.pointerStateUnknown = true;
	throw error;
}

async function moveStablePointer( session, previousRef, commit, transaction ) {
	const previousSha = referenceSha( previousRef );
	let mutationError = null;
	try {
		await session.authorize();
		if ( previousSha ) {
			await session.api.updateGitReference(
				TRUNK_POINTER_REF,
				commit.sha
			);
		} else {
			await session.api.createGitReference(
				TRUNK_POINTER_REF,
				commit.sha
			);
		}
	} catch ( error ) {
		mutationError = error;
	}
	const moved = await resolvePointerMutation(
		session,
		previousSha,
		commit.sha
	);
	if ( ! moved ) {
		const error =
			mutationError ||
			new Error( 'The stable trunk pointer mutation has unknown state.' );
		error.pointerStateUnknown = true;
		throw error;
	}
	transaction.pointerPublished = true;
}

async function cleanupOldTrunkAssets( session, release, keepIds ) {
	const assets = await session.api.listReleaseAssets( release.id );
	for ( const asset of assets ) {
		if (
			asset.name.startsWith( 'code-reference-trunk-' ) &&
			! keepIds.has( asset.id )
		) {
			await session.authorize();
			await session.api.deleteReleaseAsset( asset.id );
		}
	}
}

async function publishCandidate( session, metadata, uploaded, transaction ) {
	const candidateFile = path.join(
		session.handoffDirectory,
		metadata.snapshotFilename
	);
	await validateCandidateFile( metadata, candidateFile );
	const release = await ensureRelease( session );
	const previousRef = await session.api.getGitReference( TRUNK_POINTER_REF );

	const snapshotBytes = await readFile( candidateFile );
	await session.authorize();
	const snapshotAsset = await session.api.uploadReleaseAsset(
		release.id,
		metadata.snapshotFilename,
		snapshotBytes,
		'application/zip'
	);
	uploaded.push( snapshotAsset );

	const published = createTrunkPublishedMetadata(
		metadata,
		session.repository,
		session.now()
	);
	const metadataBytes = Buffer.from(
		`${ JSON.stringify( published, null, 2 ) }\n`
	);
	await session.authorize();
	const metadataAsset = await session.api.uploadReleaseAsset(
		release.id,
		metadataAssetName( metadata.snapshotFilename ),
		metadataBytes,
		'application/json'
	);
	uploaded.push( metadataAsset );

	const blueprint = createTrunkBlueprint( published );
	await validatePublicSnapshot(
		releaseAssetUrl( session.repository, snapshotAsset.name ),
		{
			bytes: metadata.snapshotBytes,
			sha256: metadata.snapshotSha256,
			maximumBytes: 104857600,
		},
		session.fetchImplementation
	);
	await validatePublicMetadata( session, metadataAsset, published );
	const pointerCommit = await createPointerCommit(
		session,
		previousRef,
		blueprint
	);
	await moveStablePointer( session, previousRef, pointerCommit, transaction );
	await validatePublicBlueprint(
		trunkStableBlueprintUrl( session.repository ),
		blueprint,
		session.fetchImplementation
	);
	await cleanupOldTrunkAssets(
		session,
		release,
		new Set( [ snapshotAsset.id, metadataAsset.id ] )
	);
	return { status: 'ready', published, pointerCommit: pointerCommit.sha };
}

export async function publishTrunk( options ) {
	const session = await establishSession( options );
	const uploaded = [];
	const transaction = { pointerPublished: false };
	try {
		await session.authorize();
		if ( options.artifactAvailable === false ) {
			throw new Error( 'The trunk publisher handoff is unavailable.' );
		}
		const metadata = await readHandoff( session.handoffDirectory );
		const handoff = inspectTrunkHandoff( metadata, session.context );
		if ( handoff.kind === 'failed' ) {
			throw new Error(
				metadata.buildError || 'Trunk preview build failed.'
			);
		}
		if ( handoff.kind === 'invalid' ) {
			return { status: 'invalid' };
		}
		return await publishCandidate(
			session,
			metadata,
			uploaded,
			transaction
		);
	} catch ( error ) {
		if ( ! transaction.pointerPublished && ! error.pointerStateUnknown ) {
			await cleanupUploaded( session, uploaded );
		}
		if ( error instanceof SupersededTrunkRun ) {
			return { status: 'superseded' };
		}
		throw error;
	}
}

async function main() {
	const event = JSON.parse( await readFile( process.env.GITHUB_EVENT_PATH ) );
	const result = await publishTrunk( {
		repository: process.env.GITHUB_REPOSITORY,
		stagingVariable: process.env.DOCS_PREVIEW_STAGING,
		triggerRunId: event.workflow_run.id,
		triggerRunAttempt: event.workflow_run.run_attempt,
		handoffDirectory: process.argv[ 2 ] || 'handoff',
		artifactAvailable: process.env.HANDOFF_DOWNLOAD_RESULT === 'success',
		token: process.env.GITHUB_TOKEN,
	} );
	process.stdout.write(
		`Code Reference trunk publisher: ${ result.status }.\n`
	);
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	main().catch( ( error ) => {
		process.stderr.write( `${ error.stack || error }\n` );
		process.exitCode = 1;
	} );
}
