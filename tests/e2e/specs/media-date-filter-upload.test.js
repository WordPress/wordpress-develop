/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';
import fs from 'node:fs';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { TEST_IMAGE_PATH, tile } from '../utils/media-pipeline';

// The backdated attachment puts this month in the date filter.
const OLD_DATE = '2020-01-15T12:00:00';
const OLD_MONTH = 'January 2020';

/*
 * Uploads take the client-side pipeline when the screen is cross-origin
 * isolated, and classic plupload otherwise. Removing the
 * Document-Isolation-Policy header reproduces a browser without isolation.
 */
const UPLOAD_PATHS = [ 'client-side', 'classic' ];

/**
 * Removes the Document-Isolation-Policy header from a screen for classic uploads.
 *
 * @param {import('@playwright/test').Page} page       The page.
 * @param {string}                          uploadPath 'client-side' or 'classic'.
 * @param {string}                          screen     The admin screen, e.g. '/wp-admin/upload.php'.
 */
async function useUploadPath( page, uploadPath, screen ) {
	if ( 'classic' !== uploadPath ) {
		return;
	}
	await page.route(
		( url ) => url.pathname.endsWith( screen ),
		async ( route ) => {
			const response = await route.fetch();
			const headers = { ...response.headers() };
			delete headers[ 'document-isolation-policy' ];
			await route.fulfill( { response, headers } );
		}
	);
}

/**
 * Checks the loaded screen uses the upload path the test asked for.
 *
 * @param {import('@playwright/test').Page} page       The page.
 * @param {string}                          uploadPath 'client-side' or 'classic'.
 */
async function expectUploadPath( page, uploadPath ) {
	const isolated = await page.evaluate( () =>
		Boolean( window.crossOriginIsolated )
	);
	if ( 'classic' === uploadPath ) {
		expect( isolated ).toBe( false );
		return;
	}
	test.skip(
		! isolated,
		'The client-side pipeline requires a cross-origin isolated context'
	);
}

const dateFilter = ( scope ) =>
	scope.getByRole( 'combobox', { name: 'Filter by date', exact: true } );

test.describe( 'Media uploads with the date filter set', () => {
	let prefix;

	test.beforeEach( async ( { requestUtils } ) => {
		prefix = `e2e-54839-${ randomUUID() }`;

		const old = await requestUtils.uploadMedia( TEST_IMAGE_PATH );
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/media/${ old.id }`,
			data: { title: `${ prefix }-old`, date: OLD_DATE },
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		const items = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { search: prefix, per_page: 100 },
		} );
		for ( const item of items ) {
			await requestUtils.deleteMedia( item.id );
		}
	} );

	/**
	 * Uploads a uniquely named copy of the test image and waits for the
	 * upload queue to be reset, which both upload paths do once every
	 * queued file has finished.
	 *
	 * @param {import('@playwright/test').Page} page     The page.
	 * @param {string}                          selector The plupload file input.
	 */
	async function upload( page, selector ) {
		await page.evaluate( () => {
			window.e2eUploadDone = new Promise( ( resolve ) =>
				window.wp.Uploader.queue.once( 'reset', () => resolve() )
			);
		} );
		await page.locator( selector ).first().setInputFiles( {
			name: `${ prefix }-new.jpg`,
			mimeType: 'image/jpeg',
			buffer: fs.readFileSync( TEST_IMAGE_PATH ),
		} );
		await page.evaluate( () => window.e2eUploadDone );
	}

	for ( const uploadPath of UPLOAD_PATHS ) {
		test.describe( `${ uploadPath } uploads`, () => {
			test.describe( 'Media Library grid', () => {
				const FILE_INPUT = '.moxie-shim-html5 input[type="file"]';

				test.beforeEach( async ( { admin, page } ) => {
					await useUploadPath( page, uploadPath, '/wp-admin/upload.php' );
					await admin.visitAdminPage( 'upload.php', 'mode=grid' );
					await expectUploadPath( page, uploadPath );
				} );

				test( 'shows the upload when the date filter is untouched', async ( {
					page,
				} ) => {
					await upload( page, FILE_INPUT );

					await expect( tile( page, `${ prefix }-new` ) ).toBeVisible();
				} );

				test( 'shows the upload after going back to All dates', async ( {
					page,
				} ) => {
					await dateFilter( page ).selectOption( { label: OLD_MONTH } );
					await dateFilter( page ).selectOption( { label: 'All dates' } );
					await expect( tile( page, `${ prefix }-old` ) ).toBeVisible();

					await upload( page, FILE_INPUT );

					await expect( tile( page, `${ prefix }-new` ) ).toBeVisible();
				} );
			} );

			test.describe( 'Media modal', () => {
				// The plupload HTML5 runtime creates this hidden file input
				// over the modal's "Select Files" button.
				const FILE_INPUT =
					'.media-modal .moxie-shim-html5 input[type="file"]';

				let modal;
				const selectButton = () =>
					modal.getByRole( 'button', { name: 'Select', exact: true } );

				test.beforeEach( async ( { admin, editor, page } ) => {
					await useUploadPath( page, uploadPath, '/wp-admin/post-new.php' );
					await admin.createNewPost();
					await expectUploadPath( page, uploadPath );

					// Blocks inserted before the editor sets up the post are reset away.
					await expect(
						editor.canvas.getByRole( 'textbox', { name: 'Add title' } )
					).toBeVisible();
					await editor.insertBlock( { name: 'core/image' } );
					await editor.canvas
						.getByRole( 'button', { name: 'Media Library' } )
						.click();

					modal = page.getByRole( 'dialog', {
						name: 'Select or Upload Media',
						exact: true,
					} );
					await modal
						.getByRole( 'tab', { name: 'Media Library', exact: true } )
						.click();
				} );

				test( 'shows and allows selecting the upload when the date filter is untouched', async ( {
					page,
				} ) => {
					await upload( page, FILE_INPUT );

					await expect( tile( modal, `${ prefix }-new` ) ).toBeVisible();
					await expect( selectButton() ).toBeEnabled();
				} );

				test( 'shows and allows selecting the upload after going back to All dates', async ( {
					page,
				} ) => {
					await dateFilter( modal ).selectOption( { label: OLD_MONTH } );
					await dateFilter( modal ).selectOption( { label: 'All dates' } );
					await expect( tile( modal, `${ prefix }-old` ) ).toBeVisible();

					await upload( page, FILE_INPUT );

					await expect( tile( modal, `${ prefix }-new` ) ).toBeVisible();
					await expect( selectButton() ).toBeEnabled();
				} );

				test( 'allows selecting the upload while an older month is selected', async ( {
					page,
					editor,
				} ) => {
					await dateFilter( modal ).selectOption( { label: OLD_MONTH } );
					await expect( tile( modal, `${ prefix }-old` ) ).toBeVisible();

					await upload( page, FILE_INPUT );

					// Whether the grid should show an upload outside the selected
					// month is not settled (#54839); only the selection is checked.
					await expect( selectButton() ).toBeEnabled();
					await selectButton().click();
					await expect(
						editor.canvas
							.getByRole( 'document', { name: 'Block: Image' } )
							.locator( 'img' )
					).toHaveAttribute( 'src', new RegExp( `${ prefix }-new` ) );
				} );
			} );
		} );
	}
} );
