/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
import path from 'path';

test.describe( 'Filter wp-media-library by type test', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		// delete all media
		await requestUtils.deleteAllMedia();

		// upload files
		const files = [
			'tests/e2e/assets/test-data.jpg',
			'tests/e2e/assets/test-data1.jpg',
			'tests/e2e/assets/test-mp3.mp3'
		];

		for ( const file of files ) {
			await requestUtils.uploadMedia(
				path.resolve( process.cwd(), file )
			);
		}
	} );

	test( 'Should be able to filter the media based on media type in grid view', async ( {
		page,
		admin,
	} ) => {
		// navigate to url
		await admin.visitAdminPage( '/upload.php?mode=grid' );
		
		await expect(page.getByText('Showing 3 of 3 media items')).toBeVisible();

		// validate media by video
		await page
			.getByRole( 'combobox', { name: 'Filter by type' } )
			.selectOption( 'video' );

		// validate media does not exist
		await expect(page.getByText('No media items found.')).toBeVisible();

		// validate media by audio
		await page
			.getByRole( 'combobox', { name: 'Filter by type' } )
			.selectOption( 'audio' );

		// validate media count
		await expect(page.getByText('Showing 1 of 1 media items')).toBeVisible();

		// open the file
		await page.locator( '.thumbnail' ).click();

		// validate file type
		await expect(page.getByText('File type: audio/mpeg')).toBeVisible();

		// close the modal
		await page.getByRole('button', { name: ' Close dialog' }).click();

		// validate filter by image
		await page
			.getByRole( 'combobox', { name: 'Filter by type' } )
			.selectOption( 'image' );

		// validate media count
		await expect(page.getByText('Showing 2 of 2 media items')).toBeVisible();

		// open the image
		await page.getByLabel('test-data', { exact: true }).click();

		// validate file type
		await expect(page.getByText('File type: image/jpeg')).toBeVisible();

	} );

	test( 'Should be able to filter the media based on media type in list view', async ( {
		page,
		admin,
	} ) => {
		// navigate to url
		await admin.visitAdminPage( '/upload.php?mode=list' );
		
		await expect(page.getByText('3 items').first()).toBeVisible();

		// validate media by audio
		await page
			.getByRole( 'combobox', { name: 'Filter by type' } )
			.selectOption( 'Audio' );
		
		await page.getByRole( 'button', { name: 'Filter' } ).click();

		// validate media count
		await expect(page.getByText('1 item').first()).toBeVisible();

		await page.getByRole('link', { name: '“test-mp3” (Edit)'}).click();

		// validate file type
		await expect(page.getByText('File type: MP3 (audio/mpeg)')).toBeVisible();

		await admin.visitAdminPage( '/upload.php?mode=list' );

		// validate filter by image
		await page
			.getByRole( 'combobox', { name: 'Filter by type' } )
			.selectOption( 'Images' );

		await page.getByRole( 'button', { name: 'Filter' } ).click();

		// validate media count
		await expect(page.getByText('2 items').first()).toBeVisible();

		// open the image
		await page.locator( '.title a' ).first().click();

		// validate file type
		await expect(page.getByText('File type: JPG').first()).toBeVisible();
	} );
} );
