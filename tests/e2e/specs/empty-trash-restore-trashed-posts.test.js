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

test( 'bulk trashes only the selected posts', async ( {
	page,
	admin,
	requestUtils,
} ) => {
	const posts = [];
	const titlePrefix = `Bulk trash ${ Date.now() }`;

	try {
		for ( const suffix of [ 'first', 'second', 'control' ] ) {
			posts.push(
				await requestUtils.createPost( {
					title: `${ titlePrefix } ${ suffix }`,
					status: 'publish',
				} )
			);
		}

		await admin.visitAdminPage(
			'edit.php',
			new URLSearchParams( { s: titlePrefix } ).toString()
		);
		for ( const post of posts.slice( 0, 2 ) ) {
			await page
				.locator( `#post-${ post.id }` )
				.getByRole( 'checkbox' )
				.check();
		}
		await page
			.locator( '#bulk-action-selector-top' )
			.selectOption( 'trash' );
		await page.locator( '#doaction' ).click();
		await expect( page.locator( '#message' ) ).toContainText(
			'2 posts moved to the Trash.'
		);

		for ( const post of posts.slice( 0, 2 ) ) {
			await expect( page.locator( `#post-${ post.id }` ) ).toHaveCount(
				0
			);
			const savedPost = await requestUtils.rest( {
				path: `/wp/v2/posts/${ post.id }`,
				params: { context: 'edit' },
			} );
			expect( savedPost.status ).toBe( 'trash' );
		}
		await expect(
			page.locator( `#post-${ posts[ 2 ].id }` )
		).toBeVisible();
		const control = await requestUtils.rest( {
			path: `/wp/v2/posts/${ posts[ 2 ].id }`,
			params: { context: 'edit' },
		} );
		expect( control.status ).toBe( 'publish' );
	} finally {
		for ( const post of posts ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/posts/${ post.id }`,
				params: { force: true },
			} );
		}
	}
} );
