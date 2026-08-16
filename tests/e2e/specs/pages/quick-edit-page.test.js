/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Quick Edit Page', () => {
	test.beforeEach( async ( { requestUtils, admin, editor } ) => {
		await requestUtils.deleteAllPages();
		await admin.createNewPost( { title: 'QA Page', postType: 'page' } );
		await editor.publishPost();
	} );

	test( 'Should be able to quick edit a wp page', async ( {
		page,
		admin,
	} ) => {
		// navigate to All Pages
		await admin.visitAdminPage( '/edit.php?post_type=page' );

		// hover over the page created
		await page.hover( 'role=link[name= "“QA Page” (Edit)"i]' );

		// click on quick edit
		await page.getByRole( 'button', { name: 'Quick Edit' } ).click();

		// validate the title to check correct page is being edited
		await expect(
			page.getByRole( 'textbox', { name: 'Title', exact: true } )
		).toHaveValue( 'QA Page' );

		// Updated title of the page
		await page
			.getByRole( 'textbox', { name: 'Title' } )
			.fill( 'Edited from quick edit' );

		// click on the update page button
		await page.getByRole( 'button', { name: 'Update' } ).click();

		// Validate title after quick edit
		await expect(
			page.getByRole( 'link', { name: 'Edited from quick edit” (Edit)' } )
		).toContainText( 'Edited from quick edit' );
	} );
} );
