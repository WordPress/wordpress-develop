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

// A short WAV file: audio stays on the classic upload path so it keeps the
// ID3-derived title and description that media_handle_upload() sets.
const TEST_AUDIO_PATH = path.join( __dirname, '../assets/test-audio.wav' );

// The plupload HTML5 runtime creates this hidden file input over the
// "Select Files" browse button; setting files on it triggers FilesAdded.
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

test.describe( 'Add New Media File client-side uploads', () => {
	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
		await requestUtils.deleteAllPosts();
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

	test( 'attaches the upload to the post named by post_id', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Published, so the attachment (post_status 'inherit') stays visible
		// in the media collection.
		const post = await requestUtils.createPost( {
			title: 'Client-side upload parent',
			status: 'publish',
		} );

		await admin.visitAdminPage( 'media-new.php', `post_id=${ post.id }` );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect(
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );

		// The classic flow posts `post_id` to async-upload.php; the pipeline
		// has to send the REST equivalent or the file lands unattached even
		// though the screen counts it against the post.
		const [ attachment ] = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { per_page: 1 },
		} );
		expect( attachment.post ).toBe( post.id );
	} );
	test( 'falls back to the classic uploader when the page is not isolated', async ( {
		page,
		admin,
	} ) => {
		// Blocking the Document-Isolation-Policy header reproduces every
		// browser that ignores it: the page is not isolated, the script
		// must no-op, and classic plupload must still upload the file.
		await page.route(
			( url ) => url.pathname.endsWith( '/wp-admin/media-new.php' ),
			async ( route ) => {
				const response = await route.fetch();
				const headers = { ...response.headers() };
				delete headers[ 'document-isolation-policy' ];
				await route.fulfill( { response, headers } );
			}
		);

		await admin.visitAdminPage( 'media-new.php' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		expect( isolated ).toBe( false );

		let asyncFileUploads = 0;
		let restUploadCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			if ( request.url().includes( '/async-upload.php' ) ) {
				// Ignore the markup fetch (fetch=3); count file uploads.
				if ( ! /(^|&)fetch=/.test( request.postData() || '' ) ) {
					asyncFileUploads++;
				}
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
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );

		expect( asyncFileUploads ).toBeGreaterThanOrEqual( 1 );
		expect( restUploadCount ).toBe( 0 );
	} );

	test( 'uploads several files at once, including duplicates', async ( {
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

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( [ TEST_IMAGE_PATH, TEST_IMAGE_PATH ] );

		// Each finished row carries the Edit link (the markup may repeat
		// the class inside a row, so count rows rather than links).
		await expect(
			page.locator( '#media-items .media-item:has(.edit-attachment)' )
		).toHaveCount( 2, { timeout: 90_000 } );
		expect( finalizeCount ).toBe( 2 );
	} );

	test( 'reports pipeline progress on the item', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'media-new.php' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

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

		// The screen's own progress markup (from fileQueued) reflects the
		// pipeline: past zero, not yet complete.
		const item = page.locator( '#media-items .media-item' ).first();
		await expect( item.locator( '.percent' ) ).toHaveText( /^[1-9]\d?%$/ );
		await expect( item.locator( '.bar' ) ).toHaveAttribute(
			'style',
			/width:\s*[1-9]/
		);

		holding = false;
		for ( const route of heldRoutes ) {
			await route.continue();
		}
		await expect(
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );
	} );

	test( 'surfaces a pipeline error with the server message', async ( {
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

		await failMediaCreate( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// itemAjaxError() renders the message inside the item, alongside
		// the file name, exactly as a classic upload error would.
		const item = page.locator( '#media-items .media-item' ).first();
		await expect( item.locator( '.error-div' ) ).toBeVisible( {
			timeout: 60_000,
		} );
		await expect( item.locator( '.error-div' ) ).toContainText(
			SIMULATED_ERROR.message
		);
		await expect( item.locator( '.error-div' ) ).toContainText(
			'test-image.jpg'
		);
	} );
	test( 'uploads unattached when post_id is not a valid post', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// media_upload_form() hands plupload the raw post_id, but the screen
		// validates it and prints the validated value; async-upload.php
		// silently attaches to nothing, so the pipeline must do the same
		// instead of sending the REST API a parent it will reject.
		await admin.visitAdminPage( 'media-new.php', 'post_id=999999999' );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect(
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );
		await expect( page.locator( '.media-item .error-div' ) ).toHaveCount(
			0
		);

		const [ attachment ] = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { per_page: 1 },
		} );
		expect( attachment.post ).toBeNull();
	} );

	test( 'forwards plupload multipart params to the REST upload', async ( {
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

		// A plugin adding a field through plupload_default_params or
		// uploader.settings.multipart_params reads it back from $_POST on
		// the classic path; the REST request must carry it the same way.
		// The browser does not expose multipart bodies that carry a file,
		// so record the FormData handed to fetch() from inside the page.
		await page.evaluate( () => {
			window.uploader.settings.multipart_params.e2e_custom_param =
				'forwarded';

			window.__e2eCreateBodies = [];
			const originalFetch = window.fetch;
			window.fetch = function ( input, init ) {
				const url = typeof input === 'string' ? input : input.url;
				if (
					init &&
					init.body instanceof FormData &&
					/\/wp\/v2\/media(?:[?&]|$)/.test( decodeURIComponent( url ) )
				) {
					const fields = {};
					init.body.forEach( ( value, key ) => {
						fields[ key ] =
							value instanceof Blob ? '[file]' : String( value );
					} );
					window.__e2eCreateBodies.push( fields );
				}
				return originalFetch.apply( this, arguments );
			};
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect(
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );

		const bodies = await page.evaluate( () => window.__e2eCreateBodies );
		expect( bodies.length ).toBeGreaterThanOrEqual( 1 );
		const fields = bodies[ 0 ];
		expect( fields.e2e_custom_param ).toBe( 'forwarded' );
		expect( fields.file ).toBe( '[file]' );
		// The classic transport's own fields stay behind.
		expect( fields ).not.toHaveProperty( '_wpnonce' );
		expect( fields ).not.toHaveProperty( 'action' );
	} );

	test( 'leaves audio files to the classic uploader', async ( {
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

		let asyncFileUploads = 0;
		let restUploadCount = 0;
		page.on( 'request', ( request ) => {
			if ( request.method() !== 'POST' ) {
				return;
			}
			if ( request.url().includes( '/async-upload.php' ) ) {
				if ( ! /(^|&)fetch=/.test( request.postData() || '' ) ) {
					asyncFileUploads++;
				}
			} else if (
				/\/wp\/v2\/media/.test( decodeURIComponent( request.url() ) )
			) {
				restUploadCount++;
			}
		} );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_AUDIO_PATH );

		await expect(
			page.locator( '#media-items .media-item .edit-attachment' ).first()
		).toBeVisible( { timeout: 60_000 } );

		expect( asyncFileUploads ).toBeGreaterThanOrEqual( 1 );
		expect( restUploadCount ).toBe( 0 );
	} );

	test( 'renders a failed upload with an accessible Dismiss button', async ( {
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

		await failMediaCreate( page );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		// Same markup as a server-side failure from async-upload.php: a
		// real button, described by the notice it dismisses.
		const notice = page.locator( '.media-item .error-div' ).first();
		await expect( notice ).toBeVisible( { timeout: 60_000 } );
		const dismiss = notice.getByRole( 'button', { name: 'Dismiss' } );
		await expect( dismiss ).toBeVisible();
		await expect( dismiss ).toHaveAttribute(
			'aria-describedby',
			await notice.getAttribute( 'id' )
		);
		await expect( notice ).toContainText(
			'“test-image.jpg” has failed to upload.'
		);

		// Dismissing removes the item and returns focus to the browse button.
		await dismiss.click();
		await expect( notice ).toHaveCount( 0 );
		await expect( page.locator( '#plupload-browse-button' ) ).toBeFocused();
	} );
} );
