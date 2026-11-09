/**
 * External dependencies
 */
import fs from 'fs';
import path from 'path';

// A 640x480 image: it must be larger than at least one registered sub-size
// (thumbnail, medium) so the pipeline generates and sideloads thumbnails.
export const TEST_IMAGE_PATH = path.join(
	__dirname,
	'../assets/test-image.jpg'
);

// A short WAV file: audio stays on the classic upload path so it keeps the
// ID3-derived title and description that media_handle_upload() sets.
export const TEST_AUDIO_PATH = path.join(
	__dirname,
	'../assets/test-audio.wav'
);

// An SVG: like any file that is not an image the pipeline can process, it
// stays on the classic upload path.
export const TEST_SVG_PATH = path.join( __dirname, '../assets/sample.svg' );

// A REST error the pipeline surfaces as-is: its message matches none of the
// transient patterns @wordpress/upload-media retries on.
export const SIMULATED_ERROR = {
	code: 'rest_upload_simulated_failure',
	message: 'Simulated server failure for testing',
	data: { status: 500 },
};

// A well-formed HEIC, which the tests pair with withoutHeicDecoder() so the
// browser cannot decode it: whether the server can convert it is what
// decides its path. The container has to parse, otherwise the pipeline
// reports a damaged file rather than a missing codec.
export const UNDECODABLE_HEIC = {
	name: 'undecodable.heic',
	mimeType: 'image/heic',
	buffer: fs.readFileSync(
		path.join( __dirname, '../../phpunit/data/images/test-image.heic' )
	),
};

/**
 * Fails the request that creates the attachment, leaving sub-size and
 * finalize requests alone.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function failMediaCreate( page ) {
	await page.route(
		( url ) => {
			const decoded = decodeURIComponent( url.href );
			return (
				/\/wp\/v2\/media(?:[?&]|$)/.test( decoded ) &&
				! /\/(sideload|finalize)/.test( decoded )
			);
		},
		async ( route ) => {
			if ( route.request().method() !== 'POST' ) {
				await route.continue();
				return;
			}
			await route.fulfill( {
				status: 500,
				contentType: 'application/json',
				body: JSON.stringify( SIMULATED_ERROR ),
			} );
		}
	);
}

/**
 * Answers the classic upload with async-upload.php's error markup instead of
 * running it.
 *
 * The hand-off is what is under test, not whether this server's image editor
 * can convert HEIC, so the request is answered once it has been made.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function stubClassicUpload( page ) {
	await page.route( '**/wp-admin/async-upload.php', ( route ) =>
		route.fulfill( {
			status: 200,
			contentType: 'text/html',
			body: '<div class="error-div error">Upload stubbed by the test.</div>',
		} )
	);
}

/**
 * Takes away every HEIC decoder the browser offers, as on Linux or on
 * Windows without the HEVC extension, whatever the machine running the test.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function withoutHeicDecoder( page ) {
	await page.addInitScript( () => {
		const isHeic = ( source ) =>
			source instanceof Blob && /^image\/hei[cf]/i.test( source.type );

		const createImageBitmap = window.createImageBitmap;
		window.createImageBitmap = function ( source, ...args ) {
			if ( isHeic( source ) ) {
				return Promise.reject(
					new DOMException( 'Unsupported image', 'InvalidStateError' )
				);
			}
			return createImageBitmap.call( this, source, ...args );
		};

		if ( window.ImageDecoder ) {
			const isTypeSupported = window.ImageDecoder.isTypeSupported;
			window.ImageDecoder.isTypeSupported = ( type ) =>
				/^image\/hei[cf]/i.test( type )
					? Promise.resolve( false )
					: isTypeSupported.call( window.ImageDecoder, type );
		}

		if ( window.VideoDecoder ) {
			const isConfigSupported = window.VideoDecoder.isConfigSupported;
			window.VideoDecoder.isConfigSupported = ( config ) =>
				/^(hev1|hvc1)/.test( ( config && config.codec ) || '' )
					? Promise.resolve( { supported: false, config } )
					: isConfigSupported.call( window.VideoDecoder, config );
		}
	} );
}

/**
 * Counts the POST requests that upload a file, per transport.
 *
 * `rest` counts every REST media request; `create`, `sideload` and
 * `finalize` split them by step.
 *
 * The REST route may be a pretty permalink (/wp/v2/media) or the plain form
 * (index.php?rest_route=%2Fwp%2Fv2%2Fmedia), so match on the decoded URL.
 *
 * @param {import('@playwright/test').Page} page
 * @return {{ asyncUpload: number, rest: number, create: number, sideload: number, finalize: number }} Live counts.
 */
export function countUploadRequests( page ) {
	const counts = {
		asyncUpload: 0,
		rest: 0,
		create: 0,
		sideload: 0,
		finalize: 0,
	};
	page.on( 'request', ( request ) => {
		if ( request.method() !== 'POST' ) {
			return;
		}
		const url = request.url();
		if ( url.includes( '/async-upload.php' ) ) {
			counts.asyncUpload++;
			return;
		}
		const decoded = decodeURIComponent( url );
		if ( ! /\/wp\/v2\/media/.test( decoded ) ) {
			return;
		}
		counts.rest++;
		if ( /\/wp\/v2\/media\/\d+\/sideload/.test( decoded ) ) {
			counts.sideload++;
		} else if ( /\/wp\/v2\/media\/\d+\/finalize/.test( decoded ) ) {
			counts.finalize++;
		} else if ( /\/wp\/v2\/media(?:[?&]|$)/.test( decoded ) ) {
			counts.create++;
		}
	} );
	return counts;
}

/**
 * Finds a media grid tile by its accessible name. A tile is named after the
 * attachment title; placeholders have no title yet, so they are named
 * "uploading…" (see getAriaLabel() in src/js/media/views/attachment.js).
 *
 * @param {import('@playwright/test').Page|import('@playwright/test').Locator} scope
 * @param {string}                                                              name
 * @return {import('@playwright/test').Locator} The tile.
 */
export function tile( scope, name ) {
	return scope.getByRole( 'checkbox', { name, exact: true } );
}
