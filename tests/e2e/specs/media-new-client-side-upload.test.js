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
// "Select Files" browse button; setting files on it triggers FilesAdded.
const FILE_INPUT_SELECTOR = '.moxie-shim-html5 input[type="file"]';

test.describe( 'Add New Media File client-side uploads', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'sends the Document-Isolation-Policy header', async ( {
		page,
		admin,
	} ) => {
		const responsePromise = page.waitForResponse(
			( resp ) =>
				resp.url().includes( '/wp-admin/media-new.php' ) &&
				resp.request().resourceType() === 'document' &&
				resp.status() === 200
		);

		await admin.visitAdminPage( 'media-new.php' );

		const headers = ( await responsePromise ).headers();
		expect( headers[ 'document-isolation-policy' ] ).toBe(
			'isolate-and-credentialless'
		);
	} );

	test( 'uploads an image through the client-side pipeline', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'media-new.php' );

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
				// The pipeline still POSTs to async-upload.php once per
				// upload to fetch the finished item markup (fetch=3, no file
				// payload); only file uploads must not go through it.
				const postData = request.postData() || '';
				if ( ! /(^|&)fetch=/.test( postData ) ) {
					asyncUploads.push( url );
				}
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

		// The finished attachment row renders with the Edit link fetched
		// from the async-upload.php markup endpoint.
		await expect(
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );

		// The original upload and every sideload go through the REST API,
		// and the upload is finalized exactly once.
		expect( mediaCreateCount ).toBeGreaterThanOrEqual( 1 );
		expect( sideloadCount ).toBeGreaterThanOrEqual( 1 );
		expect( finalizeCount ).toBe( 1 );

		// No file upload goes through the classic async-upload.php endpoint.
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
		await admin.visitAdminPage( 'media-new.php' );

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
			page.locator( '#media-items .media-item .edit-attachment' ).first()
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
		await admin.visitAdminPage( 'media-new.php' );

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

		// The error surfaces either as a pipeline per-item error
		// (itemAjaxError renders .error-div inside the media item) or as a
		// plupload extension rejection (a .media-item.error element),
		// depending on which layer rejects the file first.
		const errorItem = page
			.locator( '.media-item .error-div, .media-item.error' )
			.first();
		await expect( errorItem ).toBeVisible( { timeout: 30_000 } );

		// The reason has to be readable: getErrorMessage() returns an object,
		// so handing it straight to the UI renders "[object Object]".
		const errorText = await errorItem.innerText();
		expect( errorText ).not.toContain( '[object Object]' );
	} );
} );
