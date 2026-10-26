#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

import { GitHubApi } from './lib/github.mjs';
import {
	assertLatestAuthorized,
	createPublishedMetadata,
	inspectHandoff,
	readPreviewCommentSuccess,
	renderPreviewComment,
	validateCandidateFile,
	validatePublicationContext,
} from './lib/publisher.mjs';
import { metadataAssetName } from './lib/publication.mjs';
import { findPreviousPreview, loadPublishedPreview } from './lib/published.mjs';

class SupersededRun extends Error {}

/**
 * @param {unknown} error
 */
function errorMessage( error ) {
	return error instanceof Error ? error.message : String( error );
}

/**
 * @param {unknown} error
 * @param {string} property
 */
function errorFlag( error, property ) {
	return (
		error instanceof Error &&
		/** @type {any} */ ( error )[ property ] === true
	);
}

/**
 * @param {unknown} error
 * @returns {string}
 */
export function formatFailure( error ) {
	const description =
		error instanceof Error && error.stack ? error.stack : String( error );
	if ( ! ( error instanceof AggregateError ) ) {
		return description;
	}
	return [
		description,
		...error.errors.map(
			( cause, index ) =>
				`Cause ${ index + 1 }:\n${ formatFailure( cause ) }`
		),
	].join( '\n' );
}

/**
 * @param {string} directory
 */
async function readHandoff( directory ) {
	return JSON.parse(
		await readFile( path.join( directory, 'build.json' ), 'utf8' )
	);
}

/**
 * @param {Record<string, any>} options
 */
async function establishSession( options ) {
	const api =
		options.api ||
		new GitHubApi(
			options.repository,
			options.token,
			options.fetchImplementation
		);
	const run = await api.getRun( options.triggerRunId );
	let pullRequest;
	try {
		pullRequest = await api.findPullRequestForRun( run );
	} catch ( error ) {
		if ( run.head_repository?.owner?.login && run.head_branch ) {
			throw error;
		}
		throw new SupersededRun( errorMessage( error ) );
	}
	if ( ! pullRequest ) {
		return null;
	}
	const base = {
		repository: options.repository,
		stagingVariable: options.stagingVariable,
		triggerRunId: options.triggerRunId,
		triggerRunAttempt: options.triggerRunAttempt,
		run,
		pullRequest,
	};
	let context;
	try {
		context = validatePublicationContext( base );
	} catch ( error ) {
		throw new SupersededRun( errorMessage( error ) );
	}
	/** @type {(message: string) => unknown} */
	const warning =
		options.warning ||
		( ( message ) => process.stderr.write( `${ message }\n` ) );
	return {
		api,
		context,
		repository: options.repository,
		fetchImplementation: options.fetchImplementation || globalThis.fetch,
		warning,
		now: options.now || ( () => new Date().toISOString() ),
		handoffDirectory: path.resolve( options.handoffDirectory ),
		authorize: async () => {
			const currentRun = await api.getRun( options.triggerRunId );
			const currentPullRequest = await api.getPullRequest(
				context.pullRequestNumber
			);
			const latestRun = await api.latestPreviewRun( currentRun );
			try {
				return assertLatestAuthorized( {
					...base,
					run: currentRun,
					pullRequest: currentPullRequest,
					latestRun,
				} );
			} catch ( error ) {
				throw new SupersededRun( errorMessage( error ) );
			}
		},
	};
}

/**
 * @param {Record<string, any>} session
 */
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

/**
 * @param {Record<string, any>} session
 * @param {string} body
 */
async function upsertComment( session, body ) {
	const comment = await session.api.findPreviewComment(
		session.context.pullRequestNumber
	);
	await session.authorize();
	if ( comment ) {
		try {
			await session.api.updateComment( comment.id, body );
			return;
		} catch ( error ) {
			if (
				! errorFlag( error, 'status' ) &&
				! (
					error instanceof Error &&
					'status' in error &&
					error.status === 404
				)
			) {
				throw error;
			}
			session.warning(
				`Cannot update the deleted preview comment: ${ errorMessage(
					error
				) }`
			);
		}
	}
	await session.api.createComment( session.context.pullRequestNumber, body );
}

/**
 * @param {Record<string, any>} session
 */
async function removeRequestLabel( session ) {
	await session.authorize();
	await session.api.removeLabel( session.context.pullRequestNumber );
}

/**
 * @param {Record<string, any>} session
 * @param {any[]} assets
 * @param {Set<string>} keep
 */
async function cleanupOldAssets( session, assets, keep ) {
	const prefix = `code-reference-pr-${ session.context.pullRequestNumber }-`;
	for ( const asset of assets ) {
		if ( asset.name.startsWith( prefix ) && ! keep.has( asset.name ) ) {
			await session.authorize();
			await session.api.deleteReleaseAsset( asset.id );
		}
	}
}

/**
 * @param {Record<string, any>} session
 * @param {any[]} assets
 */
async function cleanupFailedCandidate( session, assets ) {
	for ( const asset of assets ) {
		await session.authorize();
		await session.api.deleteReleaseAsset( asset.id );
	}
}

/**
 * @param {any[]} operations
 * @param {string} message
 */
async function runIndependently( operations, message ) {
	const errors = [];
	for ( const operation of operations ) {
		try {
			await operation();
		} catch ( error ) {
			errors.push( error );
		}
	}
	if (
		errors.length === 1 ||
		( errors.length > 1 &&
			errors.every( ( error ) => error instanceof SupersededRun ) )
	) {
		throw errors[ 0 ];
	}
	if ( errors.length > 1 ) {
		throw new AggregateError( errors, message );
	}
}

/**
 * @param {Record<string, any>} session
 */
async function reportFailure( session, excluded = new Set() ) {
	let previous = await findPreviousPreview( session, excluded );
	if ( ! previous ) {
		const comment = await session.api.findPreviewComment(
			session.context.pullRequestNumber
		);
		previous = readPreviewCommentSuccess( comment?.body );
	}
	const body = renderPreviewComment( {
		status: 'failed',
		sourceRepository: session.context.sourceRepository,
		sourceSha: session.context.sourceSha,
		at: session.now(),
		runUrl: session.context.runUrl,
		previous,
	} );
	await runIndependently(
		[
			() => upsertComment( session, body ),
			() => removeRequestLabel( session ),
		],
		'Failure comment and label removal both failed.'
	);
}

/**
 * @param {Record<string, any>} session
 * @param {any[]} assets
 * @param {Set<string>} keep
 */
async function finishReady( session, assets, keep ) {
	try {
		await runIndependently(
			[
				() => cleanupOldAssets( session, assets, keep ),
				() => removeRequestLabel( session ),
			],
			'Old-asset cleanup and label removal both failed.'
		);
	} catch ( error ) {
		throw Object.assign(
			error instanceof Error ? error : new Error( String( error ) ),
			{ preserveReadyComment: true }
		);
	}
}

/**
 * @param {Record<string, any>} session
 * @param {Record<string, any>} metadata
 * @param {any[]} uploadedAssets
 */
async function publishCandidate( session, metadata, uploadedAssets ) {
	const candidateFile = path.join(
		session.handoffDirectory,
		metadata.snapshotFilename
	);
	await validateCandidateFile( metadata, candidateFile );
	const release = await ensureRelease( session );
	const oldAssets = await session.api.listReleaseAssets( release.id );
	const snapshotBytes = await readFile( candidateFile );
	await session.authorize();
	const snapshotAsset = await session.api.uploadReleaseAsset(
		release.id,
		metadata.snapshotFilename,
		snapshotBytes,
		'application/zip'
	);
	uploadedAssets.push( snapshotAsset );
	const published = createPublishedMetadata(
		metadata,
		session.repository,
		session.now()
	);
	const publishedBytes = Buffer.from(
		`${ JSON.stringify( published, null, 2 ) }\n`
	);
	await session.authorize();
	const metadataAsset = await session.api.uploadReleaseAsset(
		release.id,
		metadataAssetName( metadata.snapshotFilename ),
		publishedBytes,
		'application/json'
	);
	uploadedAssets.push( metadataAsset );
	const preview = await loadPublishedPreview(
		session.repository,
		session.context.pullRequestNumber,
		metadataAsset,
		snapshotAsset,
		{
			sourceRepository: session.context.sourceRepository,
			sourceSha: session.context.sourceSha,
			fetchImplementation: session.fetchImplementation,
		}
	);
	await upsertComment(
		session,
		renderPreviewComment( {
			status: 'ready',
			preview,
			runUrl: session.context.runUrl,
		} )
	);
	const keep = new Set( [ snapshotAsset.name, metadataAsset.name ] );
	await finishReady( session, oldAssets, keep );
	return { status: 'ready', preview };
}

/**
 * @param {Record<string, any>} session
 * @param {Record<string, any>} metadata
 */
async function reuseCandidate( session, metadata ) {
	const release = await session.api.getRelease();
	if ( ! release ) {
		throw new Error( 'Reusable release is missing.' );
	}
	const assets = await session.api.listReleaseAssets( release.id );
	const snapshotAsset = assets.find(
		/**
		 * @param {Record<string, any>} asset
		 */
		( asset ) => asset.name === metadata.reusedSnapshot.assetName
	);
	const metadataAsset = assets.find(
		/**
		 * @param {Record<string, any>} asset
		 */
		( asset ) => asset.name === metadata.reusedSnapshot.metadataAssetName
	);
	if ( ! snapshotAsset || ! metadataAsset ) {
		throw new Error( 'Reusable release assets are missing.' );
	}
	const preview = await loadPublishedPreview(
		session.repository,
		session.context.pullRequestNumber,
		metadataAsset,
		snapshotAsset,
		{
			sourceRepository: session.context.sourceRepository,
			sourceSha: session.context.sourceSha,
			fetchImplementation: session.fetchImplementation,
		}
	);
	for ( const name of [
		'snapshotFilename',
		'snapshotBytes',
		'snapshotSha256',
		'generationTimestamp',
		'dependencyManifestDigest',
	] ) {
		if ( preview[ name ] !== metadata[ name ] ) {
			throw new Error(
				`Reusable ${ name } does not match public metadata.`
			);
		}
	}
	await upsertComment(
		session,
		renderPreviewComment( {
			status: 'ready',
			preview,
			runUrl: session.context.runUrl,
		} )
	);
	await finishReady(
		session,
		assets,
		new Set( [ snapshotAsset.name, metadataAsset.name ] )
	);
	return { status: 'reused', preview };
}

/**
 * @param {Record<string, any>} options
 */
export async function publishPullRequest( options ) {
	let session;
	try {
		session = await establishSession( options );
	} catch ( error ) {
		if ( error instanceof SupersededRun ) {
			return { status: 'superseded' };
		}
		throw error;
	}
	if ( ! session ) {
		return { status: 'superseded' };
	}
	if ( await session.api.isSkippedPreviewBuild( session.context.run ) ) {
		return { status: 'ignored' };
	}
	/** @type {any[]} */
	const uploadedAssets = [];
	try {
		await session.authorize();
		if ( options.artifactAvailable === false ) {
			throw new Error( 'The publisher handoff artifact is unavailable.' );
		}
		const metadata = await readHandoff( session.handoffDirectory );
		const handoff = inspectHandoff( metadata, {
			...session.context,
			latestRun: session.context.run,
		} );
		if ( handoff.kind === 'failed' ) {
			throw new Error( metadata.buildError || 'Preview build failed.' );
		}
		if ( handoff.kind === 'invalid' ) {
			await reportFailure( session );
			return { status: 'invalid' };
		}
		if ( handoff.kind === 'reuse' ) {
			return await reuseCandidate( session, metadata );
		}
		return await publishCandidate( session, metadata, uploadedAssets );
	} catch ( error ) {
		if ( error instanceof SupersededRun ) {
			return { status: 'superseded' };
		}
		if ( errorFlag( error, 'preserveReadyComment' ) ) {
			throw error;
		}
		const excluded = new Set(
			uploadedAssets.map( ( asset ) => asset.name )
		);
		try {
			await cleanupFailedCandidate( session, uploadedAssets );
		} catch ( cleanupError ) {
			session.warning(
				`Cannot remove failed candidate: ${ errorMessage(
					cleanupError
				) }`
			);
		}
		try {
			await reportFailure( session, excluded );
		} catch ( reportError ) {
			if ( reportError instanceof SupersededRun ) {
				return { status: 'superseded' };
			}
			throw new AggregateError(
				[ error, reportError ],
				'Publication and failure reporting both failed.'
			);
		}
		throw error;
	}
}

/**
 * @param {Record<string, any>} result
 * @param {string | undefined} enforcementVariable
 */
export function enforceValidationResult( result, enforcementVariable ) {
	if ( result.status === 'invalid' && enforcementVariable === 'true' ) {
		throw new Error(
			'Code Reference validation failed with DOCS_PREVIEW_ENFORCE enabled.'
		);
	}
	return result;
}

async function main() {
	const eventPath = process.env.GITHUB_EVENT_PATH;
	if ( ! eventPath ) {
		throw new Error( 'GITHUB_EVENT_PATH is required.' );
	}
	const event = JSON.parse( await readFile( eventPath, 'utf8' ) );
	const result = enforceValidationResult(
		await publishPullRequest( {
			repository: process.env.GITHUB_REPOSITORY,
			stagingVariable: process.env.DOCS_PREVIEW_STAGING,
			triggerRunId: event.workflow_run.id,
			triggerRunAttempt: event.workflow_run.run_attempt,
			handoffDirectory: process.argv[ 2 ] || 'handoff',
			artifactAvailable:
				process.env.HANDOFF_DOWNLOAD_RESULT === 'success',
			token: process.env.GITHUB_TOKEN,
		} ),
		process.env.DOCS_PREVIEW_ENFORCE
	);
	process.stdout.write( `Code Reference publisher: ${ result.status }.\n` );
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	main().catch( ( error ) => {
		process.stderr.write( `${ formatFailure( error ) }\n` );
		process.exitCode = 1;
	} );
}
