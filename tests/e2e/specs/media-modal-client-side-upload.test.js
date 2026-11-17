/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';
import {
	TEST_IMAGE_PATH,
	SIMULATED_ERROR,
	countUploadRequests,
	expectCrossOriginIsolated,
	failMediaCreate,
	supportsDocumentIsolation,
	tile,
	titleOf,
	trackUploads,
	uniqueFile,
} from '../utils/media-pipeline';

// The plupload HTML5 runtime creates this hidden file input over the modal's
// "Select Files" button; setting files on it triggers FilesAdded, the same
// event a file dropped on the modal fires.
const FILE_INPUT_SELECTOR = '.media-modal .moxie-shim-html5 input[type="file"]';

/**
 * Opens the media modal from a new Image block and switches to its upload tab.
 *
 * @param {import('@playwright/test').Page}                        page
 * @param {import('@wordpress/e2e-test-utils-playwright').Editor} editor
 * @return {Promise<import('@playwright/test').Locator>} The modal.
 */
async function openModalFromImageBlock( page, editor ) {
	// Blocks inserted before the editor sets up the post are reset away.
	await expect(
		editor.canvas.getByRole( 'textbox', { name: 'Add title' } )
	).toBeVisible();
	await editor.insertBlock( { name: 'core/image' } );
	await editor.canvas.getByRole( 'button', { name: 'Media Library' } ).click();

	const modal = page.getByRole( 'dialog', {
		name: 'Select or Upload Media',
		exact: true,
	} );
	await expect( modal ).toBeVisible();
	await modal
		.getByRole( 'tab', { name: 'Upload files', exact: true } )
		.click();

	return modal;
}

test.describe( 'Media modal client-side uploads', () => {
	test.skip(
		( { browserName, browser } ) =>
			! supportsDocumentIsolation( browserName, browser ),
		'This browser does not support Document-Isolation-Policy'
	);

	test.beforeEach( async ( { admin, postFactory } ) => {
		const post = await postFactory.create( {
			title: postFactory.uniqueTitle( 'Media modal' ),
		} );
		await admin.editPost( post.id );
	} );

	test( 'loads the media frame integration in the block editor', async ( {
		page,
	} ) => {
		await expectCrossOriginIsolated( page );

		await expect(
			page.locator( 'script[src*="media-frame-upload"]' )
		).toHaveCount( 1 );
	} );

	test( 'leaves the upload-media store configured by the block editor', async ( {
		page,
	} ) => {
		await expectCrossOriginIsolated( page );

		// The editor's provider passes its own mediaUpload setting; a second
		// provider from the media frame script would pass the raw media-utils one.
		await expect
			.poll( () =>
				page.evaluate( () => {
					const settings = window.wp.data
						.select( window.wp.uploadMedia.store )
						.getSettings();
					return Boolean( settings.mediaFinalize ) &&
						settings.mediaUpload !== window.wp.mediaUtils.uploadMedia;
				} )
			)
			.toBe( true );

		// Give a late provider render the chance to commit.
		await page.evaluate(
			() => new Promise( ( resolve ) => setTimeout( resolve, 500 ) )
		);
		expect(
			await page.evaluate(
				() =>
					window.wp.data
						.select( window.wp.uploadMedia.store )
						.getSettings().mediaUpload !==
					window.wp.mediaUtils.uploadMedia
			)
		).toBe( true );
	} );

	test( 'uploads an image from the media modal through the client-side pipeline', async ( {
		page,
		editor,
		attachments,
		requestUtils,
	} ) => {
		await expectCrossOriginIsolated( page );

		const counts = countUploadRequests( page );
		const ids = trackUploads( page, attachments );
		const modal = await openModalFromImageBlock( page, editor );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 10_000 } );

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
		await fileInput.setInputFiles( uniqueFile( TEST_IMAGE_PATH ) );
		await finalized;

		await expect( tile( modal, 'uploading…' ) ).toHaveCount( 0, {
			timeout: 60_000,
		} );

		// The original upload and every sub-size go through the REST API,
		// the upload is finalized exactly once, and nothing goes through the
		// classic async-upload.php endpoint.
		expect( counts.create ).toBeGreaterThanOrEqual( 1 );
		expect( counts.sideload ).toBeGreaterThanOrEqual( 1 );
		expect( counts.finalize ).toBe( 1 );
		expect( counts.asyncUpload ).toBe( 0 );

		// The attachment carries the browser-generated sub-sizes.
		const attachment = await requestUtils.rest( {
			path: `/wp/v2/media/${ ids[ 0 ] }`,
		} );
		expect( Object.keys( attachment.media_details.sizes || {} ) ).toEqual(
			expect.arrayContaining( [ 'thumbnail', 'medium' ] )
		);

		// The modal still hands the finished upload back to the block.
		await modal
			.getByRole( 'button', { name: 'Select', exact: true } )
			.click();
		await expect(
			editor.canvas
				.getByRole( 'document', { name: 'Block: Image' } )
				.locator( 'img' )
		).toHaveAttribute( 'src', attachment.source_url );
	} );

	test( 'surfaces a pipeline error in the modal', async ( {
		page,
		editor,
	} ) => {
		await expectCrossOriginIsolated( page );

		await failMediaCreate( page );
		const counts = countUploadRequests( page );
		const modal = await openModalFromImageBlock( page, editor );

		const file = uniqueFile( TEST_IMAGE_PATH );
		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 10_000 } );
		await fileInput.setInputFiles( file );

		// The modal's uploader status lists the error the same way it lists
		// a classic upload error: file name and the server's message.
		const error = modal
			.locator( '.upload-error' )
			.filter( { hasText: file.name } );
		await expect( error ).toBeVisible( { timeout: 60_000 } );
		await expect(
			error.getByText( file.name, { exact: true } )
		).toBeVisible();
		await expect(
			error.getByText( SIMULATED_ERROR.message, { exact: true } )
		).toBeVisible();

		// The placeholder tile is removed rather than left spinning, and the
		// failure did not retry through async-upload.php.
		await expect( tile( modal, 'uploading…' ) ).toHaveCount( 0 );
		expect( counts.asyncUpload ).toBe( 0 );
	} );

	test( 'leaves an uploader that does not post attachments to plupload', async ( {
		page,
		editor,
		attachments,
	} ) => {
		await expectCrossOriginIsolated( page );

		// A plugin uploader with its own action: the pipeline must not take
		// its files, even though it is a wp.Uploader on an isolated screen.
		await page.evaluate( () => {
			window.wp.Uploader.defaults.multipart_params.action =
				'e2e-plugin-upload';
		} );

		const counts = countUploadRequests( page );
		trackUploads( page, attachments );
		await openModalFromImageBlock( page, editor );

		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 10_000 } );
		const classicUpload = page.waitForResponse(
			( response ) =>
				response.request().method() === 'POST' &&
				response.url().includes( '/async-upload.php' ),
			{ timeout: 30_000 }
		);
		await fileInput.setInputFiles( uniqueFile( TEST_IMAGE_PATH ) );
		await classicUpload;

		expect( counts.create + counts.sideload + counts.finalize ).toBe( 0 );
	} );
} );

test.describe( 'Media modal uploads without cross-origin isolation', () => {
	test( 'falls back to the classic uploader when the editor is not isolated', async ( {
		page,
		editor,
		admin,
		attachments,
		postFactory,
	} ) => {
		// Blocking the Document-Isolation-Policy header reproduces every
		// browser that ignores it: the page is not isolated, the script
		// must no-op, and classic plupload must still upload the file.
		await page.route(
			( url ) => url.pathname.endsWith( '/wp-admin/post.php' ),
			async ( route ) => {
				const response = await route.fetch();
				const headers = { ...response.headers() };
				delete headers[ 'document-isolation-policy' ];
				await route.fulfill( { response, headers } );
			}
		);
		const post = await postFactory.create( {
			title: postFactory.uniqueTitle( 'Media modal' ),
		} );
		await admin.editPost( post.id );

		const isolated = await page.evaluate( () =>
			Boolean( window.crossOriginIsolated )
		);
		expect( isolated ).toBe( false );

		const counts = countUploadRequests( page );
		trackUploads( page, attachments );
		const modal = await openModalFromImageBlock( page, editor );

		const file = uniqueFile( TEST_IMAGE_PATH );
		const fileInput = page.locator( FILE_INPUT_SELECTOR ).first();
		await expect( fileInput ).toBeAttached( { timeout: 30_000 } );
		await fileInput.setInputFiles( file );

		await expect( tile( modal, 'uploading…' ) ).toHaveCount( 0, {
			timeout: 60_000,
		} );
		// The frame selects the file it just uploaded.
		await expect(
			modal.getByRole( 'checkbox', {
				name: titleOf( file ),
				checked: true,
				exact: true,
			} )
		).toBeVisible();

		// Classic plupload handled the upload end to end.
		expect( counts.asyncUpload ).toBeGreaterThanOrEqual( 1 );
		expect( counts.create + counts.sideload + counts.finalize ).toBe( 0 );
	} );
} );
