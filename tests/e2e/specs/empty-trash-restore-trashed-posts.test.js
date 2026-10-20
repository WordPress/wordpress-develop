/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const POST_TITLE = 'Test Title';

test( 'Undo restores a trashed post to its published status', async ( {
	page,
	admin,
	requestUtils,
} ) => {
	let postId;
	const title = `Undo trash ${ Date.now() }`;

	try {
		const post = await requestUtils.createPost( {
			title,
			status: 'publish',
		} );
		postId = post.id;
		await admin.visitAdminPage(
			'edit.php',
			new URLSearchParams( { s: title } ).toString()
		);
		const row = page.locator( `#post-${ postId }` );
		await row.getByRole( 'link', { name: title, exact: true } ).hover();
		await row
			.getByRole( 'link', {
				name: `Move “${ title }” to the Trash`,
				exact: true,
			} )
			.click();
		await expect( page.locator( '#message' ) ).toContainText(
			'1 post moved to the Trash.'
		);
		await expect( row ).toHaveCount( 0 );
		const trashedPost = await requestUtils.rest( {
			path: `/wp/v2/posts/${ postId }`,
			params: { context: 'edit' },
		} );
		expect( trashedPost.status ).toBe( 'trash' );

		await page.getByRole( 'link', { name: 'Undo', exact: true } ).click();
		await expect( page.locator( '#message' ) ).toContainText(
			'1 post restored from the Trash.'
		);
		await admin.visitAdminPage(
			'edit.php',
			new URLSearchParams( { s: title } ).toString()
		);
		await expect(
			row.getByRole( 'link', { name: title, exact: true } )
		).toBeVisible();
		const restoredPost = await requestUtils.rest( {
			path: `/wp/v2/posts/${ postId }`,
			params: { context: 'edit' },
		} );
		expect( restoredPost.status ).toBe( 'publish' );
	} finally {
		if ( postId ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/posts/${ postId }`,
				params: { force: true },
			} );
		}
	}
} );

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
		await listTable.getByRole( 'link', { name: POST_TITLE, exact: true } ).hover();
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
		await listTable.getByRole( 'link', { name: POST_TITLE, exact: true } ).hover();
		await listTable.getByRole( 'link', { name: `Move “${POST_TITLE}” to the Trash` } ).click();

		await page.getByRole( 'link', { name: 'Trash' } ).click();

		// Remove post from trash.
		await listTable.getByRole( 'cell' ).filter( { hasText: POST_TITLE } ).hover();
		await listTable.getByRole( 'link', { name: `Restore “${POST_TITLE}” from the Trash` } ).click();

		// Expect for success message for restored post.
		await expect( page.locator( '#message' ) ).toContainText( '1 post restored from the Trash.' );
	} );
} );
