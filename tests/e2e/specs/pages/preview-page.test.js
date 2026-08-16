/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Preview page', () => {
	test.beforeEach( async ( { admin, editor, requestUtils } ) => {
		requestUtils.deleteAllPages();

		await admin.createNewPost( { postType: 'page', title: 'qa page' } );

		// publish post
		await editor.publishPost();
	} );

	test( 'Should be able to preview page in different viewport', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( '/edit.php?post_type=page' );

		await page.getByRole( 'link', { name: '“qa page” (Edit)' } ).click();

		// Click Preview button
		await page.getByRole( 'button', { name: 'View', exact: true } ).click();

		//Check for tablet preview
		await page.getByRole( 'menuitemradio', { name: 'Tablet' } ).click();
		await expect( page.isVisible( '.is-tablet-preview' ) ).toBeTruthy();

		//Check for Mobile preview
		await page.getByRole( 'menuitemradio', { name: 'Mobile' } ).click();
		await expect( page.isVisible( '.is-mobile-preview' ) ).toBeTruthy();

		// Check for Desktop preview
		await page.getByRole( 'menuitemradio', { name: 'Desktop' } ).click();
		await expect( page.isVisible( '.is-mobile-preview' ) ).toBeTruthy();

		// click on the preview in new tab link and wait for new tab to be triggered
		const [ newPage ] = await Promise.all( [
			page.waitForEvent( 'popup' ),
			page.locator( 'text=Preview in new tab' ).click(),
		] );

		// wait for the new page to load and validate the URL
		await newPage.waitForLoadState();
		await expect( newPage ).toHaveURL( /preview=true/ );
	} );
} );
