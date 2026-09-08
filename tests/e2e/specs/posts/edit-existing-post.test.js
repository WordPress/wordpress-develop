/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Edit Existing Post', () => {
	test.beforeEach( async ( { requestUtils }, testInfo ) => {
		const postTitle = 'test post';
		testInfo.data = { postTitle };
		await requestUtils.createPost( {
			title: postTitle,
			status: 'publish',
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'edits post', async ( { page, admin, editor }, testInfo ) => {
		const { postTitle } = testInfo.data;

		// Navigate to Posts and open the post
		await admin.visitAdminPage( '/' );
		await page.getByRole( 'link', { name: 'Posts', exact: true } ).click();
		await page.locator( `text="${ postTitle }"` ).first().click();

		await page.waitForLoadState( 'domcontentloaded' );
		await editor.setPreferences( 'core/edit-post', {
			welcomeGuide: false,
			fullscreenMode: false,
		} );

		// Locate the iframe
		const iframe = page.frameLocator( 'iframe[name="editor-canvas"]' );

		// Locate the H1 inside the iframe and edit its text
		const h1Locator = iframe.locator(
			'h1[role="textbox"][aria-label="Add title"]'
		);
		await h1Locator.fill( `${ postTitle } edit` );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();

		// Assertions
		await expect(
			page.locator( '.components-snackbar__content' )
		).toHaveText( /Post updated/ );
	} );
} );
