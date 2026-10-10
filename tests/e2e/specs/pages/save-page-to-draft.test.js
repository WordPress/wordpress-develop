/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Save Page', () => {
	test.beforeEach( async ( { admin, editor } ) => {
		// create Page to use in test
		const pageTitle = 'Test Page for draft';

		await admin.createNewPost( { title: pageTitle, postType: 'page' } );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPages();
	} );
    
	test( 'saves the page as drafts', async ( { page, admin } ) => {
		
		// Focus editor and type description
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.type( 'Test page description' );

		// Save the page as a draft
		await page.click( 'role=button[name="Save draft"i]' );

		// Wait for snackbar notification
		const snackbar = page.locator( '.components-snackbar__content' );
		await snackbar.waitFor( { state: 'visible', timeout: 10000 } );

		// Verify snackbar displays correct message
		await expect( snackbar ).toHaveText( /Draft saved./ );

		// Verify save button text changes to "Saved"
		const saveButton = page.locator( "button[aria-label='Saved']" );
		console.log('\x1b[32m%s\x1b[0m', '✅ Checking: Draft button label is changed to "Saved"');
        await expect(saveButton).toHaveText('Saved');
        
        console.log('\x1b[32m%s\x1b[0m', '✅ Checking: Saved button is disabled');
        await expect(saveButton).toBeDisabled();
	} );
} );
