/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Validate @wp-theme editor test', () => {
	test( 'Should able to validate the theme editor', async ( {
		page,
		admin,
	} ) => {
		// variables to store the status of the Editor and Customize.
		let statusOfEditor = false;
		let statusOfCustomize = false;

		// Check if the Editor is available and validate the iframe.
		await admin.visitAdminPage( '/themes.php' ); // Visit the Theme page.

		const editorSelector = page.locator( '#menu-appearance' ).getByRole( 'link', { name: 'Editor', exact: true } );

		// Check if the Editor is available and validate the iframe.
		if ( await editorSelector.isVisible() ) {
			await editorSelector.click(); // Click on Editor
			await expect( page.locator( 'iframe' ) ).toBeVisible();
			statusOfEditor = true;
		}

		// Check if the Customize is available and validate the iframe.
		await admin.visitAdminPage( '/themes.php' ); // Visit the Theme page.

		const customizeSelector = page.locator( '#menu-appearance' ).getByRole( 'link', { name: 'Customize', exact: true } );

		// Check if the Customize is available and validate the iframe.
		if ( await customizeSelector.isVisible() ) {
			await customizeSelector.click(); // Click on Customize
			await expect( page.locator( 'iframe' ) ).toBeVisible();
			statusOfCustomize = true;
		}

		// Check if the Editor or Customize is present.
		if ( statusOfEditor === true || statusOfCustomize === true ) {
			expect( true ).toBe( true ); // the test will pass if the Editor or Customize is available.
		} else {
			expect( true ).toBeFalsy(); // the test will fail if the Editor or Customize is not available.
		}
	} );
} );
