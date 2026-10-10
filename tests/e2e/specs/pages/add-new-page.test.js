/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Add New wp page', () => {
	test.beforeEach( async ( { admin, requestUtils } ) => {
		// delete all pages
		await requestUtils.deleteAllPages();
		// Open the new page editor
		await admin.createNewPost( { postType: 'page' } );
	} );

	test( 'Should create new page', async ( { page, admin } ) => {
		// Check if the template modal is visible and close it
		/* if ( page.locator( '.components-modal__content' ).isVisible() ) {
			await page.getByLabel( 'Close', { exact: true } ).click();
		} */

		// Fill the title of the page
		await page
			.frameLocator( 'iframe[name="editor-canvas"]' )
			.getByRole( 'textbox', { name: 'Add title' } )
			.fill( 'Test Page' );

		// Move to the next field means the description field
		await page.keyboard.press( 'ArrowDown' );

		// Add the description for the page
		await page.keyboard.type( 'Test page description' );

		//Click on publish button
		await page.click( '.editor-post-publish-panel__toggle' );

		//Double check, click again on publish button
		await page.click( '.editor-post-publish-button' );

		// A success notice should show up
		await expect( page.locator( '.components-snackbar' ) ).toBeVisible();

		// visit all pages page
		await admin.visitAdminPage( '/edit.php?post_type=page' );

		// validate that the created page is present
		await expect(
			page.getByRole( 'link', { name: '“Test Page” (Edit)' } )
		).toBeVisible();
	} );
} );
