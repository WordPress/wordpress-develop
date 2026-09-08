/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Undo trashed & delete posts permanantly from Trash', () => {
	const POST_TITLE = 'Test Title';

	test.beforeEach( async ( { requestUtils, editor, admin } ) => {
		await requestUtils.deleteAllPosts();
		await admin.createNewPost( { postType: 'post', title: POST_TITLE } );
		await editor.publishPost();
	} );

	test( 'Restore trash post', async ( { page, admin, editor } ) => {
		await admin.visitAdminPage( '/edit.php' );

		// Move one post to trash.
		await page.hover( `[aria-label^="“${ POST_TITLE }”"]` );
		await page
			.getByRole( `link`, {
				name: `Move “${ POST_TITLE }” to the Trash`,
			} )
			.first()
			.click();

		await expect( page.locator( '.no-items' ) ).toHaveText(
			'No posts found.'
		);
		// Remove post from trash.
		await page.getByRole( 'link', { name: 'Undo' } ).click();

		await expect( page.locator( '#message p' ) ).toHaveText(
			'1 post restored from the Trash.'
		);
	} );

	test( 'Empty Trash', async ( { page, admin, editor } ) => {
		await admin.visitAdminPage( '/edit.php' );

		// Move post to trash
		await page.hover( `[aria-label^="“${ POST_TITLE }”"]` );
		await page
			.getByRole( `link`, {
				name: `Move “${ POST_TITLE }” to the Trash`,
			} )
			.first()
			.click();

		await page.getByRole( 'link', { name: 'Trash' } ).click();

		// Delete all post.
		await page
			.getByRole( 'button', { name: 'Empty Trash' } )
			.first()
			.click();

		await expect( page.locator( '#message' ) ).toHaveText(
			/\d+ posts? permanently deleted\./
		);
	} );
} );
