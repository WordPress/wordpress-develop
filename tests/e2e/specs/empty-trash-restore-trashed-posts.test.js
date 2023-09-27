/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const POST_TITLE = 'Test Title';

test.describe( 'Empty Trash', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	});

	test('Empty Trash', async ({ admin, editor, page }) => {
		await admin.createNewPost( { title: POST_TITLE } );
		await editor.publishPost();

		await admin.visitAdminPage( '/edit.php' );

		// Move post to trash
		await page.getByLabel( new RegExp( `^“${POST_TITLE}”` ) ).hover();
		await page.getByLabel( `Move “${POST_TITLE}” to the Trash` ).click();

		// Empty trash
		await page.getByRole( 'link', { name: 'Trash' } ).click();
		await page.getByRole( 'button', { name: 'Empty Trash' } ).click();

		const messageElement = await page.waitForSelector( '#message' );
		const message = await messageElement.evaluate( ( node ) => node.innerText );
		// Until we have `deleteAllPosts`, the number of posts being deleted could be dynamic.
		expect(message).toMatch(/\d+ posts? permanently deleted\./);
	} );

	test('Restore trash post', async ( { admin, editor, page }) => {
		await admin.createNewPost( { title: POST_TITLE } );
		await editor.publishPost();

		await admin.visitAdminPage( '/edit.php' );

		// Move post to trash.
		await page.getByLabel( new RegExp( `^“${POST_TITLE}”` ) ).hover();
		await page.getByLabel( `Move “${POST_TITLE}” to the Trash` ).click();

		// Remove post from trash.
		await page.getByRole( 'link', { name: 'Trash' } ).click();

		const postTitle = await page.getByText( new RegExp( `^${POST_TITLE}$` ) ).first();
		await postTitle.hover();
		await page.getByLabel( `Restore “${POST_TITLE}” from the Trash` ).click();

		// Expect for success message for trashed post.
		const messageElement = await page.waitForSelector( '#message' );
		const message = await messageElement.evaluate((element) => element.innerText);
		expect(message).toContain( '1 post restored from the Trash.' );
	} );
} );
