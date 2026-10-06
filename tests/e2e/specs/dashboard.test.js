/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Quick Draft', () => {
	test.beforeEach( async ({ requestUtils }) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'should allow Quick Draft to be created with Title and Content', async ( {
	   admin,
	   page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Quick Draft title field to appear.
		const draftTitleField = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Title' } );

		await expect( draftTitleField ).toBeVisible();

		// Focus and fill in a title.
		await draftTitleField.fill( 'Quick Draft test title' );

		// Wait for the Quick Draft content textarea to appear.
		const quickDraftContentTextarea = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Content' } );

		await expect( quickDraftContentTextarea ).toBeVisible();

		// Focus and fill in some content.
		await quickDraftContentTextarea.fill( 'Quick Draft test content' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that the new draft title appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( 'Quick Draft test title' );

		// Check that the new draft content appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-content' ).first()
		).toHaveText( 'Quick Draft test content' );

		// Check that the new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( 'Quick Draft test title' );
	} );

	test( 'should prevent Quick Draft from being created without Title and Content', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that an admin notice with ARIA role 'alert' appears.
		await expect(
			page.locator( '#quick-press' ).getByRole( 'alert' )
		).toHaveText( 'Cannot create a draft post with empty title and content.' );

		// Check that no new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '#the-list .no-items .colspanchange' )
		).toContainText( 'No posts found.' );
	} );

	test( 'should allow Quick Draft to be created with only the Title', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Quick Draft title field to appear.
		const quickDraftTitleField = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Title' } );

		await expect( quickDraftTitleField ).toBeVisible();

		// Focus and fill in a title.
		await quickDraftTitleField.fill( 'Quick Draft test title' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that the new draft title appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( 'Quick Draft test title' );

		// Check that the new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( 'Quick Draft test title' );
	} );

	test( 'should allow Quick Draft to be created with only the Content', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Quick Draft content textarea to appear.
		const quickDraftContentTextarea = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Content' } );

		await expect( quickDraftContentTextarea ).toBeVisible();

		// Focus and fill in some content.
		await quickDraftContentTextarea.fill( 'Quick Draft test content' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that the new draft title appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( '(no title)' );

		await expect(
			page.locator( '.drafts .draft-content' ).first()
		).toHaveText( 'Quick Draft test content' );

		// Check that the new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( '(no title)' );
	} );
} );
