/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import path from 'path';

// A 640x480 image: it must be larger than at least one registered sub-size
// (thumbnail, medium) so the pipeline generates and sideloads thumbnails.
const TEST_IMAGE_PATH = path.join( __dirname, '../assets/test-image.jpg' );

// The plupload HTML5 runtime creates this hidden file input over the
// "Add New" browse button; setting files on it triggers FilesAdded.
const FILE_INPUT_SELECTOR = '.moxie-shim-html5 input[type="file"]';

// A REST error the pipeline surfaces as-is: its message matches none of the
// transient patterns @wordpress/upload-media retries on.
const SIMULATED_ERROR = {
	code: 'rest_upload_simulated_failure',
	message: 'Simulated server failure for testing',
	data: { status: 500 },
};

/**
 * Fails every REST media create request (not sideload/finalize).
 *
 * @param {import('@playwright/test').Page} page
 */
async function failMediaCreate( page ) {
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

test.describe( 'Media Library grid client-side uploads', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'sends the Document-Isolation-Policy header on the grid', async ( {
		page,
		admin,
	} ) => {
		const responsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( '/wp-admin/upload.php' ) &&
				resp.request().resourceType() === 'document' &&
				resp.status() === 200
		);

		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const headers = ( await responsePromise ).headers();
		expect( headers[ 'document-isolation-policy' ] ).toBe(
			'isolate-and-credentialless'
		);
	} );

	test( 'does not send the Document-Isolation-Policy header in list mode', async ( {
		page,
		admin,
	} ) => {
		const responsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( '/wp-admin/upload.php' ) &&
				resp.request().resourceType() === 'document' &&
				resp.status() === 200
		);

		await admin.visitAdminPage( 'upload.php', 'mode=list' );

		const headers = ( await responsePromise ).headers();
		expect( headers[ 'document-isolation-policy' ] ).toBeUndefined();

		// List mode uploads via media-new.php, so the grid integration
		// script has no business on this screen either.
		await expect(
			page.locator( 'script[src*="media-library-upload"]' )
		).toHaveCount( 0 );

		// Visiting with ?mode= persists the user's preference; restore it.
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );
	} );

	test( 'uploads an image through the client-side pipeline', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		// In Chromium builds without Document-Isolation-Policy support,
		// isolation is legitimately unavailable and the pipeline falls back
		// to classic uploads. Only assert where isolation is real.
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// The REST route may be a pretty permalink (/wp/v2/media) or the
		// plain form (index.php?rest_route=%2Fwp%2Fv2%2Fmedia), so match on
		// the decoded URL.
		let mediaCreateCount = 0;
		let sideloadCount = 0;
		let finalizeCount = 0;
		const asyncUploads = [];
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			const url = request.url();
			if ( url.includes( '/async-upload.php' ) ) {
				asyncUploads.push( url );
				return;
			}
			const decoded = decodeURIComponent( url );
			if ( /\/wp\/v2\/media\/\d+\/sideload/.test( decoded ) ) {
				sideloadCount++;
			} else if ( /\/wp\/v2\/media\/\d+\/finalize/.test( decoded ) ) {
				finalizeCount++;
			} else if ( /\/wp\/v2\/media(?:[?&]|$)/.test( decoded ) ) {
				mediaCreateCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// The finalized attachment resolves to a normal (non-uploading) tile.
		await expect(
			page.locator( 'li.attachment:not(.uploading)' ).first()
		).toBeVisible( { timeout: 60_000 } );

		// The original upload and every sideload go through the REST API,
		// and the upload is finalized exactly once.
		expect( mediaCreateCount ).toBeGreaterThanOrEqual( 1 );
		expect( sideloadCount ).toBeGreaterThanOrEqual( 1 );
		expect( finalizeCount ).toBe( 1 );

		// Nothing goes through the classic async-upload.php endpoint.
		expect( asyncUploads ).toEqual( [] );

		// The finalized attachment carries the browser-generated sub-sizes
		// in its metadata (the 640x480 source is larger than thumbnail and
		// medium), and the sideloaded thumbnail file really exists.
		const [ attachment ] = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { per_page: 1 },
		} );
		const sizes = attachment.media_details.sizes || {};
		expect( Object.keys( sizes ) ).toEqual(
			expect.arrayContaining( [ 'thumbnail', 'medium' ] )
		);

		const thumbnailResponse = await page.request.get(
			sizes.thumbnail.source_url
		);
		expect( thumbnailResponse.status() ).toBe( 200 );
	} );

	test( 'warns before leaving while a pipeline upload is in flight', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// Hold sideload requests so the upload stays in flight at a
		// deterministic point.
		const heldRoutes = [];
		let holding = true;
		await page.route(
			( url ) => decodeURIComponent( url.href ).includes( '/sideload' ),
			async ( route ) => {
				if ( holding ) {
					heldRoutes.push( route );
					return;
				}
				await route.continue();
			}
		);
		const sideloadRequested = page.waitForRequest(
			( request ) =>
				decodeURIComponent( request.url() ).includes( '/sideload' ),
			{ timeout: 60_000 }
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );
		await sideloadRequested;

		// A synthetic cancelable event exercises the guard's listener
		// without triggering the real (untestable) browser prompt.
		const preventedWhileUploading = await page.evaluate( () => {
			const event = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( event );
			return event.defaultPrevented;
		} );
		expect( preventedWhileUploading ).toBe( true );

		// Release the held requests, let the upload finish, and verify the
		// guard disengages once nothing is in flight anymore.
		holding = false;
		for ( const route of heldRoutes ) {
			await route.continue();
		}
		await expect(
			page.locator( 'li.attachment:not(.uploading)' ).first()
		).toBeVisible( { timeout: 60_000 } );

		const preventedAfterUpload = await page.evaluate( () => {
			const event = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( event );
			return event.defaultPrevented;
		} );
		expect( preventedAfterUpload ).toBe( false );
	} );

	test( 'shows an error for a disallowed file type', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( {
			name: 'disallowed.xyz',
			mimeType: 'application/octet-stream',
			buffer: Buffer.from( 'not an allowed file type' ),
		} );

		// The Manage frame renders rejected uploads in the error sidebar.
		const errorNotice = page
			.locator( '.upload-error, .upload-errors' )
			.first();
		await expect( errorNotice ).toBeVisible( { timeout: 30_000 } );

		// The sidebar names the file in its own span and keeps the reason
		// in the message span; the reason has to be readable text, not a
		// stringified object.
		await expect(
			errorNotice.locator( '.upload-error-filename' )
		).toHaveText( 'disallowed.xyz' );
		const message = await errorNotice
			.locator( '.upload-error-message' )
			.innerText();
		expect( message ).not.toContain( '[object Object]' );
		expect( message.trim() ).not.toBe( '' );
	} );
	test( 'falls back to the classic uploader when the page is not isolated', async ( {
		page,
		admin,
	} ) => {
		// Blocking the Document-Isolation-Policy header reproduces every
		// browser that ignores it: the page is not isolated, the script
		// must no-op, and classic plupload must still upload the file.
		await page.route(
			( url ) => url.pathname.endsWith( '/wp-admin/upload.php' ),
			async ( route ) => {
				const response = await route.fetch();
				const headers = { ...response.headers() };
				delete headers[ 'document-isolation-policy' ];
				await route.fulfill( { response, headers } );
			}
		);

		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		expect( isolated ).toBe( false );

		let asyncUploadCount = 0;
		let restUploadCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			if ( request.url().includes( '/async-upload.php' ) ) {
				asyncUploadCount++;
			} else if (
				/\/wp\/v2\/media/.test( decodeURIComponent( request.url() ) )
			) {
				restUploadCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect(
			page.locator( 'li.attachment:not(.uploading)' ).first()
		).toBeVisible( { timeout: 60_000 } );

		// Classic plupload handled the upload end to end.
		expect( asyncUploadCount ).toBeGreaterThanOrEqual( 1 );
		expect( restUploadCount ).toBe( 0 );
	} );

	test( 'uploads several files at once, including duplicates', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		let finalizeCount = 0;
		page.on( 'request', ( request ) => {
			if (
				request.method() === 'POST' &&
				/\/wp\/v2\/media\/\d+\/finalize/.test(
					decodeURIComponent( request.url() )
				)
			) {
				finalizeCount++;
			}
		} );

		// The same file twice exercises the shared-identity path: both
		// tiles must track progress and resolve independently.
		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( [ TEST_IMAGE_PATH, TEST_IMAGE_PATH ] );

		await expect( page.locator( 'li.attachment.uploading' ) ).toHaveCount(
			0,
			{ timeout: 90_000 }
		);
		await expect(
			page.locator( 'li.attachment:not(.uploading)' )
		).toHaveCount( 2 );
		expect( finalizeCount ).toBe( 2 );
	} );

	test( 'reports pipeline progress on the tile', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		// Hold sideloads so the tile is observed mid-pipeline, after the
		// original has been uploaded but before thumbnails have landed.
		const heldRoutes = [];
		let holding = true;
		await page.route(
			( url ) => decodeURIComponent( url.href ).includes( '/sideload' ),
			async ( route ) => {
				if ( holding ) {
					heldRoutes.push( route );
					return;
				}
				await route.continue();
			}
		);
		const sideloadRequested = page.waitForRequest(
			( request ) =>
				decodeURIComponent( request.url() ).includes( '/sideload' ),
			{ timeout: 60_000 }
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );
		await sideloadRequested;

		// The placeholder tile's progress bar has advanced past zero but
		// is not reported complete while work remains.
		const bar = page.locator(
			'li.attachment.uploading .media-progress-bar div'
		);
		await expect( bar ).toHaveAttribute( 'style', /width:\s*[1-9]/ );
		await expect( bar ).not.toHaveAttribute( 'style', /width:\s*100%/ );

		holding = false;
		for ( const route of heldRoutes ) {
			await route.continue();
		}
		await expect(
			page.locator( 'li.attachment:not(.uploading)' ).first()
		).toBeVisible( { timeout: 60_000 } );
	} );

	test( 'surfaces a pipeline error with its message and file name', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php', 'mode=grid' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		await failMediaCreate( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// The error lands in the grid's error sidebar exactly like a classic
		// upload error: the file name and the server's message.
		const error = page.locator( '.upload-error' ).first();
		await expect( error ).toBeVisible( { timeout: 60_000 } );
		await expect( error.locator( '.upload-error-filename' ) ).toHaveText(
			'test-image.jpg'
		);
		await expect( error.locator( '.upload-error-message' ) ).toHaveText(
			SIMULATED_ERROR.message
		);

		// The placeholder tile is removed rather than left spinning.
		await expect( page.locator( 'li.attachment.uploading' ) ).toHaveCount(
			0
		);
	} );
} );
