/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
import path from 'path';

test.describe( 'Filter the wp-media-library by date', () => {
	test.beforeEach( async ( { requestUtils, page, admin } ) => {
		// delete all media files
		await requestUtils.deleteAllMedia();

		// upload media files
		const files = [
			'tests/e2e/assets/test-data.jpg',
			'tests/e2e/assets/test-data1.jpg',
		];

		for ( const file of files ) {
			await requestUtils.uploadMedia(
				path.resolve( process.cwd(), file )
			);
		}
	} );

	test( 'Should able to filter the media by date', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( '/upload.php?mode=grid' );

		// Select the current month for filter.
		await page
			.getByRole( 'combobox', { name: 'Filter by date' } )
			.selectOption( '0' );

		// open the first media file
		await page.locator( '.thumbnail' ).first().click();

		// Add date function to fetch the current date.
		const date = new Date();
		const day = date.getDate();
		const month = date.toLocaleString( 'default', { month: 'long' } );
		const year = date.getFullYear();

		// Validate the uploaded media date as current date.
		await expect(page.getByText('Uploaded on: ' + month + ' ' + day + ',' + ' ' + year)).toBeVisible();
	} );
} );
