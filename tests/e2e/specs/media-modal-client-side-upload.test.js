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

// The plupload HTML5 runtime creates this hidden file input over the modal's
// "Select Files" button; setting files on it triggers FilesAdded, the same
// event a file dropped on the modal fires.
const FILE_INPUT_SELECTOR = '.media-modal .moxie-shim-html5 input[type="file"]';

/**
 * Counts the POST requests that upload a file, per transport.
 *
 * The REST route may be a pretty permalink (/wp/v2/media) or the plain form
 * (index.php?rest_route=%2Fwp%2Fv2%2Fmedia), so match on the decoded URL.
 *
 * @param {import('@playwright/test').Page} page
 * @return {{ asyncUpload: number, create: number, sideload: number, finalize: number }} Live counts.
 */
function countUploadRequests( page ) {
	const counts = { asyncUpload: 0, create: 0, sideload: 0, finalize: 0 };
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
 * Opens the media modal from a new Image block and switches to its upload tab.
 *
 * @param {import('@playwright/test').Page}                        page
 * @param {import('@wordpress/e2e-test-utils-playwright').Editor} editor
 * @return {Promise<import('@playwright/test').Locator>} The modal.
 */
async function openModalFromImageBlock( page, editor ) {
	await editor.insertBlock( { name: 'core/image' } );
	await editor.canvas.getByRole( 'button', { name: 'Media Library' } ).click();

	const modal = page.locator( '.media-modal' );
	await expect( modal ).toBeVisible();
	await modal.locator( '.media-router' ).getByText( 'Upload files' ).click();

	return modal;
}

test.describe( 'Media modal client-side uploads', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'loads the media frame integration in the block editor', async ( {
		page,
	} ) => {
		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		test.skip(
			! isolated,
			'The client-side pipeline requires a cross-origin isolated context'
		);

		await expect(
			page.locator( 'script[src*="media-frame-upload"]' )
		).toHaveCount( 1 );
	} );

	test( 'uploads an image from the media modal through the client-side pipeline', async ( {
		page,
		editor,
		requestUtils,
	} ) => {
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

		const counts = countUploadRequests( page );
		const modal = await openModalFromImageBlock( page, editor );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached' } );

		// A settled tile is not on its own proof that this upload finished:
		// the modal leaves the upload tab for the library grid as soon as the
		// file is queued, so anything already in the library renders one
		// straight away. Wait for the pipeline's own last request instead.
		const finalized = page.waitForRequest(
			( request ) =>
				request.method() === 'POST' &&
				/\/wp\/v2\/media\/\d+\/finalize/.test(
					decodeURIComponent( request.url() )
				),
			{ timeout: 60_000 }
		);
		await fileInput.setInputFiles( TEST_IMAGE_PATH );
		await finalized;

		await expect( modal.locator( 'li.attachment.uploading' ) ).toHaveCount(
			0,
			{ timeout: 60_000 }
		);

		// The original upload and every sub-size go through the REST API,
		// the upload is finalized exactly once, and nothing goes through the
		// classic async-upload.php endpoint.
		expect( counts.create ).toBeGreaterThanOrEqual( 1 );
		expect( counts.sideload ).toBeGreaterThanOrEqual( 1 );
		expect( counts.finalize ).toBe( 1 );
		expect( counts.asyncUpload ).toBe( 0 );

		// The attachment carries the browser-generated sub-sizes.
		const [ attachment ] = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { per_page: 1 },
		} );
		expect( Object.keys( attachment.media_details.sizes || {} ) ).toEqual(
			expect.arrayContaining( [ 'thumbnail', 'medium' ] )
		);

		// The modal still hands the finished upload back to the block.
		await modal
			.getByRole( 'button', { name: 'Select', exact: true } )
			.click();
		await expect(
			editor.canvas.locator( 'figure.wp-block-image img' )
		).toHaveAttribute( 'src', attachment.source_url );
	} );

	test( 'falls back to the classic uploader when the editor is not isolated', async ( {
		page,
		editor,
		admin,
	} ) => {
		// Blocking the Document-Isolation-Policy header reproduces every
		// browser that ignores it: the page is not isolated, the script
		// must no-op, and classic plupload must still upload the file.
		await page.route(
			( url ) => url.pathname.endsWith( '/wp-admin/post-new.php' ),
			async ( route ) => {
				const response = await route.fetch();
				const headers = { ...response.headers() };
				delete headers[ 'document-isolation-policy' ];
				await route.fulfill( { response, headers } );
			}
		);
		await admin.createNewPost();

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		expect( isolated ).toBe( false );

		const counts = countUploadRequests( page );
		const modal = await openModalFromImageBlock( page, editor );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await fileInput.waitFor( { state: 'attached', timeout: 30_000 } );
		await fileInput.setInputFiles( TEST_IMAGE_PATH );

		await expect( modal.locator( 'li.attachment.uploading' ) ).toHaveCount(
			0,
			{ timeout: 60_000 }
		);
		await expect(
			modal.locator( 'li.attachment' ).first()
		).toBeVisible();

		// Classic plupload handled the upload end to end.
		expect( counts.asyncUpload ).toBeGreaterThanOrEqual( 1 );
		expect( counts.create + counts.sideload + counts.finalize ).toBe( 0 );
	} );
} );
