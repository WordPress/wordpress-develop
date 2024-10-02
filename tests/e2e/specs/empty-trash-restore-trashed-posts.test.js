/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const POST_TITLE = 'Test Title';

test.describe( 'Empty Trash', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	});

	test('Empty Trash', async ({ admin, editor, page }) => {
		await admin.createNewPost( { title: POST_TITLE } );
		await editor.publishPost();

		await admin.visitAdminPage( '/edit.php' );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Move post to trash
		await listTable.getByRole( 'link', { name: `“${ POST_TITLE }” (Edit)` } ).hover();
		await listTable.getByRole( 'link', { name: `Move “${POST_TITLE}” to the Trash` } ).click();

		// Empty trash
		await page.getByRole( 'link', { name: 'Trash' } ).click();
		await page.getByRole( 'button', { name: 'Empty Trash' } ).first().click();

		await expect( page.locator( '#message' ) ).toContainText( '1 post permanently deleted.' );
	} );

	test('Restore trash post', async ( { admin, editor, page }) => {
		await admin.createNewPost( { title: POST_TITLE } );
		await editor.publishPost();

		await admin.visitAdminPage( '/edit.php' );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Move post to trash
		await listTable.getByRole( 'link', { name: `“${ POST_TITLE }” (Edit)` } ).hover();
		await listTable.getByRole( 'link', { name: `Move “${POST_TITLE}” to the Trash` } ).click();

		await page.getByRole( 'link', { name: 'Trash' } ).click();

		// Remove post from trash.
		await listTable.getByRole( 'cell' ).filter( { hasText: POST_TITLE } ).hover();
		await listTable.getByRole( 'link', { name: `Restore “${POST_TITLE}” from the Trash` } ).click();

		// Expect for success message for restored post.
		await expect( page.locator( '#message' ) ).toContainText( '1 post restored from the Trash.' );
	} );
} );
