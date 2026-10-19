#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

import { isDeploymentEnabled } from './lib/config.mjs';
import { GitHubApi } from './lib/github.mjs';
import { findPreviousPreview } from './lib/published.mjs';
import {
	readPreviewCommentSuccess,
	readPreviewCommentSource,
	renderPreviewComment,
} from './lib/publisher.mjs';

const FULL_COMMIT = /^[0-9a-f]{40}$/;
const CACHE_PREFIX = 'docs-preview-base-';

class LifecycleSuperseded extends Error {}

/**
 * @param {unknown} body
 */
function isTerminalComment( body ) {
	return (
		typeof body === 'string' &&
		( body.includes( '\n\n**Status:** Ready\n\n' ) ||
			body.includes( '\n\n**Status:** Latest attempt failed\n\n' ) )
	);
}

/**
 * @param {unknown} value
 * @param {string} label
 */
function positiveInteger( value, label ) {
	const number = Number( value );
	if ( ! Number.isSafeInteger( number ) || number < 1 ) {
		throw new Error( `${ label } must be a positive integer.` );
	}
	return number;
}

/**
 * @param {Record<string, any>} options
 */
function lifecycleContext( options ) {
	if (
		! isDeploymentEnabled( options.repository, options.stagingVariable )
	) {
		throw new Error(
			'Docs preview lifecycle is disabled in this repository.'
		);
	}
	const action = options.event?.action;
	if ( action !== 'synchronize' && action !== 'closed' ) {
		throw new Error( `Unsupported pull request action ${ action }.` );
	}
	const pullRequest = options.event.pull_request;
	const pullRequestNumber = positiveInteger(
		pullRequest?.number,
		'Pull request number'
	);
	if (
		pullRequest.base?.ref !== 'trunk' ||
		! FULL_COMMIT.test( pullRequest.head?.sha || '' )
	) {
		throw new Error( 'Pull request lifecycle identity is invalid.' );
	}
	const sourceRepository = pullRequest.head.repo?.full_name || null;
	if ( action === 'synchronize' && ! sourceRepository ) {
		throw new Error( 'Open pull request head repository is missing.' );
	}
	return {
		action,
		pullRequestNumber,
		sourceRepository,
		sourceSha: pullRequest.head.sha,
	};
}

/**
 * @param {Record<string, any>} context
 * @param {Record<string, any>} pullRequest
 * @param {string} state
 * @param {boolean} labelMustBeAbsent
 */
function assertCurrent( context, pullRequest, state, labelMustBeAbsent ) {
	if (
		pullRequest.number !== context.pullRequestNumber ||
		pullRequest.state !== state ||
		pullRequest.base?.ref !== 'trunk' ||
		pullRequest.head?.sha !== context.sourceSha ||
		( context.sourceRepository &&
			pullRequest.head.repo?.full_name !== context.sourceRepository ) ||
		( labelMustBeAbsent &&
			pullRequest.labels?.some(
				/**
				 * @param {Record<string, any>} label
				 */
				( label ) => label.name === 'docs-preview'
			) )
	) {
		throw new Error( 'A newer pull request state superseded this event.' );
	}
	return pullRequest;
}

/**
 * @param {Record<string, any>} options
 */
function sessionFrom( options ) {
	const context = lifecycleContext( options );
	const api =
		options.api ||
		new GitHubApi(
			options.repository,
			options.token,
			options.fetchImplementation
		);
	/** @type {(message: string) => unknown} */
	const warning =
		options.warning ||
		( ( message ) => process.stderr.write( `${ message }\n` ) );
	const authorize = /** @param {string} state */ async (
		state,
		labelMustBeAbsent = false
	) => {
		const pullRequest = await api.getPullRequest(
			context.pullRequestNumber
		);
		try {
			return assertCurrent(
				context,
				pullRequest,
				state,
				labelMustBeAbsent
			);
		} catch ( error ) {
			throw new LifecycleSuperseded(
				error instanceof Error ? error.message : String( error )
			);
		}
	};
	return {
		api,
		context,
		repository: options.repository,
		fetchImplementation: options.fetchImplementation || globalThis.fetch,
		warning,
		authorize,
	};
}

/**
 * @param {Record<string, any>} session
 * @param {Record<string, any>} expectedComment
 */
async function authorizeStaleMutation( session, expectedComment ) {
	await session.authorize( 'open', true );
	const currentComment = await session.api.findPreviewComment(
		session.context.pullRequestNumber
	);
	if (
		currentComment?.id !== expectedComment.id ||
		currentComment.body !== expectedComment.body
	) {
		throw new LifecycleSuperseded(
			'A newer preview state superseded this stale event.'
		);
	}
}

/**
 * @param {Record<string, any>} session
 */
async function markStale( session ) {
	try {
		return await markStaleAuthorized( session );
	} catch ( error ) {
		if ( error instanceof LifecycleSuperseded ) {
			return { status: 'ignored' };
		}
		throw error;
	}
}

/**
 * @param {Record<string, any>} session
 */
async function markStaleAuthorized( session ) {
	await session.authorize( 'open', true );
	const comment = await session.api.findPreviewComment(
		session.context.pullRequestNumber
	);
	if ( ! comment ) {
		return { status: 'ignored' };
	}
	const commentSource = readPreviewCommentSource( comment.body );
	if (
		isTerminalComment( comment.body ) &&
		commentSource?.repository === session.context.sourceRepository &&
		commentSource?.sha === session.context.sourceSha
	) {
		return { status: 'ignored' };
	}
	let preview = await findPreviousPreview( session );
	if ( ! preview ) {
		preview = readPreviewCommentSuccess( comment.body );
	}
	if ( ! preview ) {
		const previous = commentSource;
		if ( ! previous ) {
			return { status: 'unavailable' };
		}
		await authorizeStaleMutation( session, comment );
		await session.api.updateComment(
			comment.id,
			renderPreviewComment( {
				status: 'stale-unavailable',
				previousRepository: previous.repository,
				previousSha: previous.sha,
				currentRepository: session.context.sourceRepository,
				currentSha: session.context.sourceSha,
			} )
		);
		return { status: 'stale' };
	}
	await authorizeStaleMutation( session, comment );
	await session.api.updateComment(
		comment.id,
		renderPreviewComment( {
			status: 'stale',
			preview,
			currentRepository: session.context.sourceRepository,
			currentSha: session.context.sourceSha,
		} )
	);
	return { status: 'stale' };
}

/**
 * @param {any[]} operations
 */
async function settle( operations ) {
	const errors = [];
	for ( const operation of operations ) {
		try {
			await operation();
		} catch ( error ) {
			if ( error instanceof LifecycleSuperseded ) {
				return { errors, superseded: true };
			}
			errors.push( error );
		}
	}
	return { errors, superseded: false };
}

/**
 * @param {Record<string, any>} session
 */
async function expire( session ) {
	try {
		await session.authorize( 'closed' );
	} catch ( error ) {
		if ( error instanceof LifecycleSuperseded ) {
			return { status: 'superseded' };
		}
		throw error;
	}
	const release = await session.api.getRelease();
	const prefix = `code-reference-pr-${ session.context.pullRequestNumber }-`;
	const assets = release
		? ( await session.api.listReleaseAssets( release.id ) ).filter(
				/**
				 * @param {Record<string, any>} asset
				 */
				( asset ) => asset.name.startsWith( prefix )
		  )
		: [];
	const cacheRef = `refs/pull/${ session.context.pullRequestNumber }/merge`;
	const caches = ( await session.api.listActionCaches( cacheRef ) ).filter(
		/**
		 * @param {Record<string, any>} cache
		 */
		( cache ) =>
			cache.ref === cacheRef && cache.key.startsWith( CACHE_PREFIX )
	);
	const comment = await session.api.findPreviewComment(
		session.context.pullRequestNumber
	);
	const assetCleanup = await settle(
		assets.map(
			/** @param {Record<string, any>} asset */ ( asset ) => async () => {
				await session.authorize( 'closed' );
				await session.api.deleteReleaseAsset( asset.id );
			}
		)
	);
	const cacheCleanup = await settle(
		caches.map(
			/** @param {Record<string, any>} cache */ ( cache ) => async () => {
				await session.authorize( 'closed' );
				await session.api.deleteActionCache( cache.id );
			}
		)
	);
	/** @type {{errors: unknown[], superseded: boolean}} */
	let commentCleanup = { errors: [], superseded: false };
	if (
		comment &&
		assetCleanup.errors.length === 0 &&
		! assetCleanup.superseded
	) {
		commentCleanup = await settle( [
			async () => {
				await session.authorize( 'closed' );
				await session.api.updateComment(
					comment.id,
					renderPreviewComment( { status: 'expired' } )
				);
			},
		] );
	}
	const errors = [
		...assetCleanup.errors,
		...cacheCleanup.errors,
		...commentCleanup.errors,
	];
	if ( errors.length > 0 ) {
		throw new AggregateError( errors, 'Pull request cleanup failed.' );
	}
	if (
		assetCleanup.superseded ||
		cacheCleanup.superseded ||
		commentCleanup.superseded
	) {
		return { status: 'superseded' };
	}
	return {
		status: 'expired',
		deletedAssets: assets.length,
		deletedCaches: caches.length,
	};
}

/**
 * @param {Record<string, any>} options
 */
export async function managePullRequest( options ) {
	const session = sessionFrom( options );
	return session.context.action === 'synchronize'
		? markStale( session )
		: expire( session );
}

async function main() {
	const eventPath = process.env.GITHUB_EVENT_PATH;
	if ( ! eventPath ) {
		throw new Error( 'GITHUB_EVENT_PATH is required.' );
	}
	const event = JSON.parse( await readFile( eventPath, 'utf8' ) );
	const result = await managePullRequest( {
		repository: process.env.GITHUB_REPOSITORY,
		stagingVariable: process.env.DOCS_PREVIEW_STAGING,
		token: process.env.GITHUB_TOKEN,
		event,
	} );
	process.stdout.write( `Code Reference lifecycle: ${ result.status }.\n` );
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	main().catch( ( error ) => {
		process.stderr.write( `${ error.stack || error }\n` );
		process.exitCode = 1;
	} );
}
