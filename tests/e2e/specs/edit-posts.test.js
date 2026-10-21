/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Edit Posts', () => {
	test.beforeEach( async ( { requestUtils }) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'displays a message in the posts table when no posts are present',async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/edit.php' );
		await expect(
			page.getByRole( 'cell', { name: 'No posts found.' } )
		).toBeVisible();
	} );

	test( 'lists a published post and renders its title and content on the front end', async ( {
		admin,
		editor,
		page,
	} ) => {
		const title = 'Test Title';
		const content = 'This content should be visible on the published post.';
		await admin.createNewPost( { title, content } );
		await editor.publishPost();
		await admin.visitAdminPage( '/edit.php' );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Expect there to be one row in the post list.
		const posts = listTable.locator( '.row-title' );
		await expect( posts ).toHaveCount( 1 );

		// Expect the title of the post to be correct.
		await expect( posts.first() ).toHaveText( title );

		await posts.first().hover();
		await listTable.getByRole( 'link', { name: `View “${ title }”`, exact: true } ).click();
		await expect( page.getByRole( 'heading', { name: title, exact: true } ) ).toBeVisible();
		await expect( page.getByText( content, { exact: true } ) ).toBeVisible();
	} );

	test( 'allows an existing post to be edited using the Edit button', async ( {
		admin,
		editor,
		page,
	} ) => {
		const title = 'Test Title';
		await admin.createNewPost( { title } );
		await editor.publishPost();
		await admin.visitAdminPage( '/edit.php' );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Click the post title (edit) link
		await listTable.getByRole( 'link', { name: title, exact: true } ).click();

		// Wait for the editor iframe to load, and switch to it as the active content frame.
		await page
				.frameLocator( '[name=editor-canvas]' )
				.locator( 'body > *' )
				.first()
				.waitFor();

		const editorPostTitle = editor.canvas.getByRole( 'textbox', { name: 'Add title' } );

		// Expect title field to be in the editor with correct title shown.
		await expect( editorPostTitle ).toBeVisible();
		await expect( editorPostTitle ).toHaveText( title );
	} );

	test( 'allows an existing post to be quick edited using the Quick Edit button', async ( {
		admin,
		editor,
		page,
		pageUtils
	} ) => {
		const title = 'Test Title';
		await admin.createNewPost( { title } );
		await editor.publishPost();
		await admin.visitAdminPage( '/edit.php' );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// // Focus on the post title link.
		await listTable.getByRole( 'link', { name: title, exact: true } ).focus();

		// Tab to the Quick Edit button and press Enter to quick edit.
		await pageUtils.pressKeys( 'Tab', { times: 2 } )
		await page.keyboard.press( 'Enter' );

		// Type in the currently focused (title) field to modify the title, testing that focus is moved to the input.
		await page.keyboard.type( ' Edited' );

		// Update the post.
		await page.getByRole( 'button', { name: 'Update' } ).click();

		// Wait for the quick edit button to reappear.
		await expect( page.getByRole( 'button', { name: 'Quick Edit' } ) ).toBeVisible();

		// Expect there to be one row in the post list.
		const posts = listTable.locator( '.row-title' );
		await expect( posts ).toHaveCount( 1 );

		// Expect the title of the post to be correct.
		await expect( posts.first() ).toHaveText( `${ title } Edited` );
	} );

	test( 'allows an existing post to be deleted using the Trash button', async ( {
		admin,
		editor,
		page,
		pageUtils
	} ) => {
		const title = 'Test Title';
		await admin.createNewPost( { title } );
		await editor.publishPost();
		await admin.visitAdminPage( '/edit.php' );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Focus on the post title link.
		await listTable.getByRole( 'link', { name: title, exact: true } ).focus();

		// Tab to the Trash button and press Enter to delete the post.
		await pageUtils.pressKeys( 'Tab', { times: 3 } )
		await page.keyboard.press( 'Enter' );

		await expect(
			page.getByRole( 'cell', { name: 'No posts found.' } )
		).toBeVisible();
	} );
} );

test( 'bulk edits only the selected posts', async ( {
	page,
	admin,
	requestUtils,
} ) => {
	const posts = [];
	const titlePrefix = `Bulk edit ${ Date.now() }`;

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
			.getByRole( 'combobox', {
				name: 'Select bulk action',
				exact: true,
			} )
			.first()
			.selectOption( 'edit' );
		await page
			.getByRole( 'button', { name: 'Apply', exact: true } )
			.first()
			.click();
		const bulkEdit = page.getByRole( 'region', {
			name: 'Bulk Edit',
			exact: true,
		} );
		await bulkEdit
			.getByRole( 'combobox', { name: 'Status', exact: true } )
			.selectOption( 'draft' );
		await bulkEdit
			.getByRole( 'button', { name: 'Update', exact: true } )
			.click();
		await expect( page.locator( '#message' ) ).toContainText(
			'2 posts updated.'
		);

		await page.reload();
		for ( const post of posts.slice( 0, 2 ) ) {
			await expect(
				page
					.locator( `#post-${ post.id }` )
					.getByText( 'Draft', { exact: true } )
			).toBeVisible();
			const savedPost = await requestUtils.rest( {
				path: `/wp/v2/posts/${ post.id }`,
				params: { context: 'edit' },
			} );
			expect( savedPost.status ).toBe( 'draft' );
		}
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
