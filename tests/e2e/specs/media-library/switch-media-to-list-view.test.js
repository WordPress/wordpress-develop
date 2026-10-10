/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
import path from 'path';

test.describe( 'Switch wp-media-library to list view', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		// delete all media
		await requestUtils.deleteAllMedia();

		// upload media files
		const files = [ 'tests/e2e/assets/test-data.jpg', 'tests/e2e/assets/test-data1.jpg' ];

		for ( const file of files ) {
			await requestUtils.uploadMedia(
				path.resolve( process.cwd(), file )
			);
		}
	} );

	test( 'Should be able to switch the media view to list view.', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'upload.php' );

		// Switch media view to list view.
		await page.getByRole( 'link', { name: 'List View' } ).click();

		// validate the list view.
		await expect(
			page.getByRole( 'table' ).locator( '.title.column-title' )
		).toHaveCount( 2 );
	} );
} );
