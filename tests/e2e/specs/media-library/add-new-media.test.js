/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const path = require( 'path' );

test.describe( 'Add new Media', () => {
	const imgname = `test-data`;
	const filePath = path.resolve( __dirname, `../../assets/${imgname}.jpg` );

	test.beforeEach( async ( { admin } ) => {
		// Ensure test starts on the Media Upload page
		await admin.visitAdminPage( 'media-new.php' );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		// Cleanup uploaded media
		await requestUtils.deleteAllMedia();
	} );

	test( 'Should be able to add new media', async ( { page } ) => {

		// Verify navigation to Media Upload page
		await expect(
			page.locator( "div[class='wrap'] h1" ),
			'Failed to navigate to the Media Upload page'
		).toHaveText( 'Upload New Media' );

		// Handle file upload via file chooser
		const [ fileChooser ] = await Promise.all( [
			page.waitForEvent( 'filechooser' ),
			page.getByRole('button', { name: 'Select Files' }).click(),
		] );
		await fileChooser.setFiles( [ filePath ] );

		await page.waitForSelector('.media-list-title', { state: 'visible' });

		// Validate the uploaded media
		await expect(
			page.locator( '.media-list-title' ),
			'Uploaded media item title does not match'
		).toContainText( imgname );

		// Ensure uploaded media item is visible
		await expect(
			page.locator( '.media-item' ),
			'Uploaded media item is not visible'
		).toBeVisible();
	} );
} );
