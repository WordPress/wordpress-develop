/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Trash and Restore page', () => {

    let pageTitle;

	test.beforeEach( async ( { admin } ) => {
		// Generate a unique page title for each test
		pageTitle = `Test Page for Trash`;
		// Ensure we start on the admin "Pages" screen
		await admin.visitAdminPage( 'edit.php', 'post_type=page' );
	} );

    test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPages();
	} );

	test( 'trashes a page', async ( { page, admin, editor } ) => {
		await admin.createNewPost( { postType: 'page', title: pageTitle } );

		// Publish the page
        await editor.publishPost();

		// Verify success notice
		await expect(page.locator('.components-snackbar__content')).toHaveText('Page published.View Page');

		// Close the editor panel
		await page.click( '[aria-label="Close panel"]' );

		// Reload page to ensure the UI is updated
		await page.reload();

		// Move page to trash
		await page.click('button.editor-post-trash[aria-disabled="false"]');

        const modal = page.locator('div.components-modal__frame');
        await modal.locator('button:has-text("Move to trash")').click();    
        
        // Wait for the page to navigate or reload
        await page.waitForLoadState('networkidle');
       
		// Expect successful page deletion message
		await expect( page.locator( '#message p' ) ).toHaveText(
			'1 page moved to the Trash. Undo'
		);
	} );

	test( 'restores page from trash', async ( { page, admin } ) => {
		// Navigate to the Trash page
		const trashLink = page.locator('li.trash a:has-text("Trash")');
        await trashLink.click();

		// Restore trashed page
		await page.locator( '.column-primary.page-title' ).first().hover();
		await page.locator( '.untrash' ).first().click();

		// Expect restored page success message
		await expect( page.locator( '#message p' ) ).toHaveText(
			'1 page restored from the Trash. Edit Page'
		);
	} );
} );
