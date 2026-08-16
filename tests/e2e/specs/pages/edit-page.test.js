/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Edit a wp page', () => {
	// Create a new page before updating it
	test.beforeEach( async ( { admin, requestUtils, editor } ) => {
		await requestUtils.deleteAllPages();

		//Create a new page
		await admin.createNewPost( { postType: 'page', title: 'Test page' } );

		// publish page
		await editor.publishPost();
	} );

	test( 'should be able to edit page', async ( { page, admin } ) => {
		// navigate to all pages
		await admin.visitAdminPage( '/edit.php?post_type=page' );

		// Click on the edit link
		await page.hover( 'role=link[name="“Test page” (Edit)"i]' );
		await page
			.getByRole( 'link', { name: 'Edit “Test page”' } )
			.first()
			.click();

		// Update the exiting page title
		await page
			.frameLocator( 'iframe[name="editor-canvas"]' )
			.getByRole( 'textbox', { name: 'Add title' } )
			.fill( 'Test Page - Edited' );

		// Click on save button
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();

		// A success notice should show up
		await expect( page.getByTestId( 'snackbar' ) ).toBeVisible();
		await admin.visitAdminPage( '/edit.php?post_type=page' );
		expect(
			page.getByRole( 'link', { name: 'Test Page – Edited” (Edit)' } )
		).toBeVisible();
	} );
} );
