#!/usr/bin/env node

import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

import { REQUEST_TIMEOUT_MS } from './lib/http.mjs';
import {
	RELEASE_TAG,
	createReuseHandoff,
	metadataAssetName,
	validatePublicSnapshot,
	validateReusableMetadata,
} from './lib/publication.mjs';

/**
 * @param {string[]} values
 */
function argumentsFrom( values ) {
	/** @type {Record<string, string>} */
	const options = {};
	for ( let index = 0; index < values.length; index += 2 ) {
		const name = values[ index ];
		const value = values[ index + 1 ];
		if ( ! name?.startsWith( '--' ) || ! value ) {
			throw new Error( `Invalid argument ${ name || '(missing)' }.` );
		}
		options[
			name
				.slice( 2 )
				.replace(
					/-([a-z])/g,
					/** @param {string} _ @param {string} letter */ (
						_,
						letter
					) => letter.toUpperCase()
				)
		] = value;
	}
	return options;
}

/**
 * @param {string} url
 * @param {string} token
 * @param {(...args: any[]) => any} fetchImplementation
 */
async function apiJson( url, token, fetchImplementation ) {
	const response = await fetchImplementation( url, {
		headers: {
			Accept: 'application/vnd.github+json',
			Authorization: `Bearer ${ token }`,
			'X-GitHub-Api-Version': '2022-11-28',
		},
		signal: AbortSignal.timeout( REQUEST_TIMEOUT_MS ),
	} );
	if ( response.status === 404 ) {
		return null;
	}
	if ( ! response.ok ) {
		throw new Error(
			`GitHub returned HTTP ${ response.status } for ${ url }.`
		);
	}
	return response.json();
}

/**
 * @param {string} repository
 * @param {number} releaseId
 * @param {string} token
 * @param {(...args: any[]) => any} fetchImplementation
 */
async function releaseAssets(
	repository,
	releaseId,
	token,
	fetchImplementation
) {
	const assets = [];
	for ( let page = 1; ; page++ ) {
		const batch = await apiJson(
			`https://api.github.com/repos/${ repository }/releases/${ releaseId }/assets?per_page=100&page=${ page }`,
			token,
			fetchImplementation
		);
		// A release deleted mid-scan reports no assets rather than crashing
		// the reuse check with an unrelated error.
		if ( ! batch ) {
			return assets;
		}
		assets.push( ...batch );
		if ( batch.length < 100 ) {
			return assets;
		}
	}
}

/**
 * @param {Record<string, any>} options
 */
export async function findReusablePreview( options ) {
	// Every size guard compares with `>`, which a missing or malformed
	// boundary would pass as NaN, so reuse is refused instead.
	if (
		! Number.isSafeInteger( options.maximumBytes ) ||
		options.maximumBytes < 1
	) {
		throw new Error(
			'The maximum snapshot size must be a positive integer.'
		);
	}
	const fetchImplementation = options.fetchImplementation || globalThis.fetch;
	const release = await apiJson(
		`https://api.github.com/repos/${ options.repository }/releases/tags/${ RELEASE_TAG }`,
		options.token,
		fetchImplementation
	);
	if ( ! release ) {
		return null;
	}
	const assets = await releaseAssets(
		options.repository,
		release.id,
		options.token,
		fetchImplementation
	);
	const prefix = `code-reference-pr-${ options.pullRequestNumber }-${ options.sourceSha }-`;
	const candidates = assets
		.filter(
			( asset ) =>
				asset.name.startsWith( prefix ) &&
				asset.name.endsWith( '.json' )
		)
		.sort( ( left, right ) =>
			right.created_at.localeCompare( left.created_at )
		);
	for ( const candidate of candidates ) {
		try {
			const metadataResponse = await fetchImplementation(
				candidate.browser_download_url,
				{ signal: AbortSignal.timeout( REQUEST_TIMEOUT_MS ) }
			);
			if ( metadataResponse.status !== 200 ) {
				throw new Error(
					`Published metadata returned HTTP ${ metadataResponse.status }.`
				);
			}
			const published = validateReusableMetadata(
				await metadataResponse.json(),
				options
			);
			if (
				candidate.name !==
				metadataAssetName( published.snapshotFilename )
			) {
				throw new Error(
					'Published metadata asset name does not match.'
				);
			}
			const snapshot = assets.find(
				( asset ) => asset.name === published.snapshotFilename
			);
			if ( ! snapshot ) {
				throw new Error( 'Published snapshot asset is missing.' );
			}
			await validatePublicSnapshot(
				snapshot.browser_download_url,
				{
					bytes: published.snapshotBytes,
					sha256: published.snapshotSha256,
					maximumBytes: options.maximumBytes,
				},
				fetchImplementation
			);
			return createReuseHandoff( published, options );
		} catch ( error ) {
			options.warning?.(
				`Cannot reuse ${ candidate.name }: ${
					error instanceof Error ? error.message : String( error )
				}`
			);
		}
	}
	return null;
}

async function main() {
	const options = argumentsFrom( process.argv.slice( 2 ) );
	const handoff = await findReusablePreview( {
		...options,
		maximumBytes: Number( options.maximumBytes ),
		token: process.env.GITHUB_TOKEN,
		warning: /** @param {string} message */ ( message ) =>
			process.stderr.write( `::warning::${ message }\n` ),
	} );
	if ( handoff ) {
		await mkdir( path.dirname( options.output ), { recursive: true } );
		await writeFile(
			options.output,
			`${ JSON.stringify( handoff, null, 2 ) }\n`
		);
	}
	if ( process.env.GITHUB_OUTPUT ) {
		await writeFile(
			process.env.GITHUB_OUTPUT,
			`reused=${ handoff ? 'true' : 'false' }\n`,
			{ flag: 'a' }
		);
	}
}

if ( import.meta.url === pathToFileURL( process.argv[ 1 ] ).href ) {
	main().catch( async ( error ) => {
		process.stderr.write(
			`::warning::Same-SHA reuse check failed: ${ error.message }\n`
		);
		if ( process.env.GITHUB_OUTPUT ) {
			await writeFile( process.env.GITHUB_OUTPUT, 'reused=false\n', {
				flag: 'a',
			} ).catch( () => {} );
		}
	} );
}
