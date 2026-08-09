#!/usr/bin/env node

import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

import { isDeploymentEnabled } from './lib/config.mjs';
import { GitHubApi } from './lib/github.mjs';
import { findPreviousPreview } from './lib/published.mjs';
import {
	readPreviewCommentSource,
	renderPreviewComment,
} from './lib/publisher.mjs';

const FULL_COMMIT = /^[0-9a-f]{40}$/;
const CACHE_PREFIX = 'docs-preview-base-';

class LifecycleSuperseded extends Error {}

function positiveInteger( value, label ) {
	const number = Number( value );
	if ( ! Number.isSafeInteger( number ) || number < 1 ) {
		throw new Error( `${ label } must be a positive integer.` );
	}
	return number;
}

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
				( label ) => label.name === 'docs-preview'
			) )
	) {
		throw new Error( 'A newer pull request state superseded this event.' );
	}
	return pullRequest;
}

function sessionFrom( options ) {
	const context = lifecycleContext( options );
	const api =
		options.api ||
		new GitHubApi(
			options.repository,
			options.token,
			options.fetchImplementation
		);
	const authorize = async ( state, labelMustBeAbsent = false ) => {
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
			throw new LifecycleSuperseded( error.message );
		}
	};
	return {
		api,
		context,
		repository: options.repository,
		fetchImplementation: options.fetchImplementation || globalThis.fetch,
		warning: options.warning || console.warn,
		authorize,
	};
}

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

async function markStale( session ) {
	try {
		await session.authorize( 'open', true );
	} catch ( error ) {
		if ( error instanceof LifecycleSuperseded ) {
			return { status: 'ignored' };
		}
		throw error;
	}
	const comment = await session.api.findPreviewComment(
		session.context.pullRequestNumber
	);
	if ( ! comment ) {
		return { status: 'ignored' };
	}
	const preview = await findPreviousPreview( session );
	if ( ! preview ) {
		const previous = readPreviewCommentSource( comment.body );
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

async function settle( operations ) {
	const errors = [];
	for ( const operation of operations ) {
		try {
			await operation();
		} catch ( error ) {
			errors.push( error );
		}
	}
	return errors;
}

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
				( asset ) => asset.name.startsWith( prefix )
		  )
		: [];
	const cacheRef = `refs/pull/${ session.context.pullRequestNumber }/merge`;
	const caches = ( await session.api.listActionCaches( cacheRef ) ).filter(
		( cache ) =>
			cache.ref === cacheRef && cache.key.startsWith( CACHE_PREFIX )
	);
	const comment = await session.api.findPreviewComment(
		session.context.pullRequestNumber
	);
	const assetErrors = await settle(
		assets.map( ( asset ) => async () => {
			await session.authorize( 'closed' );
			await session.api.deleteReleaseAsset( asset.id );
		} )
	);
	const cacheErrors = await settle(
		caches.map( ( cache ) => async () => {
			await session.authorize( 'closed' );
			await session.api.deleteActionCache( cache.id );
		} )
	);
	let commentErrors = [];
	if ( comment && assetErrors.length === 0 ) {
		commentErrors = await settle( [
			async () => {
				await session.authorize( 'closed' );
				await session.api.updateComment(
					comment.id,
					renderPreviewComment( { status: 'expired' } )
				);
			},
		] );
	}
	const errors = [ ...assetErrors, ...cacheErrors, ...commentErrors ];
	if ( errors.length > 0 ) {
		throw new AggregateError( errors, 'Pull request cleanup failed.' );
	}
	return {
		status: 'expired',
		deletedAssets: assets.length,
		deletedCaches: caches.length,
	};
}

export async function managePullRequest( options ) {
	const session = sessionFrom( options );
	return session.context.action === 'synchronize'
		? markStale( session )
		: expire( session );
}

async function main() {
	const event = JSON.parse( await readFile( process.env.GITHUB_EVENT_PATH ) );
	const result = await managePullRequest( {
		repository: process.env.GITHUB_REPOSITORY,
		stagingVariable: process.env.DOCS_PREVIEW_STAGING,
		token: process.env.GITHUB_TOKEN,
		event,
	} );
	console.log( `Code Reference lifecycle: ${ result.status }.` );
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	main().catch( ( error ) => {
		console.error( error );
		process.exitCode = 1;
	} );
}
