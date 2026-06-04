/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Quick Draft', () => {
	test.beforeEach( async ({ requestUtils }) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'Allows draft to be created with Title and Content', async ( {
	   admin,
	   page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for Quick Draft title field to appear.
		const draftTitleField = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Title' } );

		await expect( draftTitleField ).toBeVisible();

		// Focus and fill in a title.
		await draftTitleField.fill( 'Test Draft Title' );

		// Navigate to content field and type in some content
		await page.keyboard.press( 'Tab' );
		await page.keyboard.type( 'Test Draft Content' );

		// Navigate to Save Draft button and press it.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		// Check that new draft appears in Your Recent Drafts section
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( 'Test Draft Title' );

		// Check that new draft appears in Posts page
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( 'Test Draft Title' );
	} );

	test( 'Allows draft to be created with content but without Title', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for Quick Draft content field to appear.
		const draftContentField = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Content' } );

		await expect( draftContentField ).toBeVisible();

		// Focus and fill in a title.
		await draftContentField.fill( 'Test Draft Content' );

		// Navigate to Save Draft button and press it.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		// Check that new draft appears in Your Recent Drafts section
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( 'Untitled' );

		// Check that new draft appears in Posts page
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( 'Untitled' );
	} );
} );
