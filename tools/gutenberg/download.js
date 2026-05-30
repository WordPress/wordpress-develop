#!/usr/bin/env node

/**
 * Download Gutenberg Repository Script.
 *
 * This script downloads a pre-built Gutenberg tar.gz artifact from the GitHub
 * Container Registry and extracts it into the ./gutenberg directory. Any
 * existing gutenberg directory is removed before extraction.
 *
 * The artifact is identified by the "gutenberg.sha" value in the root
 * package.json, which is used as the OCI tag for the gutenberg-wp-develop-build
 * package on GitHub Container Registry. The value is normally a Git SHA, but
 * may also be a mutable tag (e.g. "trunk", "pr-12345") in a pull request that
 * wants to track the latest build of a stream. When the ref is a mutable tag,
 * the script resolves it to the immutable SHA tag for the actual blob fetch
 * and falls back to the mutable tag's manifest when the immutable tag is
 * unavailable.
 *
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const fs = require( 'fs' );
const { Readable } = require( 'stream' );
const { pipeline } = require( 'stream/promises' );
const zlib = require( 'zlib' );
const {
	gutenbergDir,
	readGutenbergConfig,
	fetchGhcrToken,
	fetchManifest,
} = require( './utils' );

/**
 * Resolve the manifest to use for downloading.
 *
 * For immutable refs (SHA values), the ref is used directly.
 *
 * For mutable refs, the mutable tag's manifest is fetched first and the
 * `image.revision` annotation is read. The corresponding immutable SHA tag is
 * then preferred. If the immutable SHA tag is unavailable, fall back to the
 * manifest already fetched via the mutable tag.
 *
 * @param {{ ref: string, ghcrRepo: string, isMutable: boolean }} config
 * @param {string} token
 * @return {Promise<{ manifest: Record<string, any>, resolvedRef: string }>}
 */
async function resolveDownloadManifest( config, token ) {
	const { ref, ghcrRepo, isMutable } = config;

	const initialManifest = await fetchManifest( ref, ghcrRepo, token );

	if ( ! isMutable ) {
		return { manifest: initialManifest, resolvedRef: ref };
	}

	const revision =
		initialManifest?.annotations?.[ 'org.opencontainers.image.revision' ];
	if ( ! revision ) {
		console.log(
			`ℹ️  No image.revision annotation on "${ ref }"; using mutable tag for download.`
		);
		return { manifest: initialManifest, resolvedRef: ref };
	}

	try {
		const immutableManifest = await fetchManifest( revision, ghcrRepo, token );
		return { manifest: immutableManifest, resolvedRef: revision };
	} catch ( error ) {
		if ( /** @type {{ status?: number }} */ ( error ).status === 404 ) {
			console.log(
				`ℹ️  Immutable SHA tag ${ revision } unavailable; falling back to mutable tag "${ ref }".`
			);
			return { manifest: initialManifest, resolvedRef: ref };
		}
		throw error;
	}
}

/**
 * Main execution function.
 */
async function main() {
	console.log( '🔍 Checking Gutenberg configuration...' );

	/*
	 * Read Gutenberg configuration from package.json.
	 *
	 * Note: ghcr stands for GitHub Container Registry where wordpress-develop ready builds of the Gutenberg plugin
	 * are published by the Gutenberg build-plugin-zip workflow.
	 */
	let config;
	try {
		config = readGutenbergConfig();
		console.log(
			`   Ref: ${ config.ref }${
				config.isMutable ? ' (mutable tag)' : ''
			}`
		);
		console.log( `   GHCR repository: ${ config.ghcrRepo }` );
	} catch ( error ) {
		console.error( '❌ Error reading package.json:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	// Step 1: Get an anonymous GHCR token for pulling.
	console.log( '\n🔑 Fetching GHCR token...' );
	let token;
	try {
		token = await fetchGhcrToken( config.ghcrRepo );
		console.log( '✅ Token acquired' );
	} catch ( error ) {
		console.error( '❌ Failed to fetch token:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	// Step 2: Resolve the manifest to use for download.
	console.log( `\n📋 Fetching manifest for ${ config.ref }...` );
	let manifest, resolvedRef;
	try {
		( { manifest, resolvedRef } = await resolveDownloadManifest(
			config,
			token
		) );
		if ( resolvedRef !== config.ref ) {
			console.log( `   Resolved to immutable SHA tag: ${ resolvedRef }` );
		}
	} catch ( error ) {
		console.error( '❌ Failed to fetch manifest:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	const digest = manifest?.layers?.[ 0 ]?.digest;
	if ( ! digest ) {
		console.error( '❌ No layer digest found in manifest' );
		process.exit( 1 );
	}
	console.log( `✅ Blob digest: ${ digest }` );

	// Remove existing gutenberg directory so the extraction is clean.
	if ( fs.existsSync( gutenbergDir ) ) {
		console.log( '\n🗑️  Removing existing gutenberg directory...' );
		fs.rmSync( gutenbergDir, { recursive: true, force: true } );
	}

	fs.mkdirSync( gutenbergDir, { recursive: true } );

	/*
	 * Step 3: Stream the blob directly through gunzip into tar, writing
	 * into ./gutenberg with no temporary file on disk.
	 */
	console.log( `\n📥 Downloading and extracting artifact...` );
	try {
		const response = await fetch( `https://ghcr.io/v2/${ config.ghcrRepo }/blobs/${ digest }`, {
			headers: {
				Authorization: `Bearer ${ token }`,
			},
		} );
		if ( ! response.ok ) {
			throw new Error( `Failed to download blob: ${ response.status } ${ response.statusText }` );
		}
		if ( ! response.body ) {
			throw new Error( 'Blob response has no body' );
		}

		/*
		 * Spawn tar to read from stdin and extract into gutenbergDir.
		 * `tar` is available on macOS, Linux, and Windows 10+.
		 */
		const tar = spawn( 'tar', [ '-x', '-C', gutenbergDir ], {
			stdio: [ 'pipe', 'inherit', 'inherit' ],
		} );

		/** @type {Promise<void>} */
		const tarDone = new Promise( ( resolve, reject ) => {
			tar.on( 'close', ( code ) => {
				if ( code !== 0 ) {
					reject( new Error( `tar exited with code ${ code }` ) );
				} else {
					resolve();
				}
			} );
			tar.on( 'error', reject );
		} );

		/*
		 * Pipe: fetch body → gunzip → tar stdin.
		 * Decompressing in Node keeps the pipeline error handling
		 * consistent and means tar only sees plain tar data on stdin.
		 */
		await pipeline(
			Readable.fromWeb(
				/** @type {import('stream/web').ReadableStream} */ ( response.body )
			),
			zlib.createGunzip(),
			tar.stdin,
		);

		await tarDone;

		console.log( '✅ Download and extraction complete' );
	} catch ( error ) {
		console.error( '❌ Download/extraction failed:', /** @type {Error} */ ( error ).message );
		process.exit( 1 );
	}

	console.log( '\n✅ Gutenberg download complete!' );
}

// Run main function.
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
