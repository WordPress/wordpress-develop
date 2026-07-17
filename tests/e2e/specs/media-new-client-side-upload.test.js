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
		await expect(
			page
				.locator( '.media-item .error-div, .media-item.error' )
				.first()
		).toBeVisible( { timeout: 30_000 } );
	} );
} );
