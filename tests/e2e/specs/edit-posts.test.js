/**
 * WordPress dependencies
 */
import { Editor, test, expect } from '@wordpress/e2e-test-utils-playwright';

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

	test( 'shows a single post after one is published with the correct title',async ( {
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

		// Expect there to be one row in the post list.
		const posts = listTable.locator( '.row-title' );
		await expect( posts ).toHaveCount( 1 );

		// Expect the title of the post to be correct.
		expect( posts.first() ).toHaveText( title );
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
		expect( posts.first() ).toHaveText( `${ title } Edited` );
	} );

	test( 'refreshes the post list when a post is updated in another window', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const originalTitle = 'Original title';
		const updatedTitle = 'Updated title';
		const post = await requestUtils.createPost( {
			title: originalTitle,
			status: 'publish',
		} );

		await admin.visitAdminPage( '/edit.php' );
		const rowTitle = page.locator( `#post-${ post.id } .row-title` );
		await expect( rowTitle ).toHaveText( originalTitle );

		const otherPage = await page.context().newPage();
		const otherEditor = new Editor( { page: otherPage } );
		await otherPage.goto( `/wp-admin/post.php?post=${ post.id }&action=edit` );

		const welcomeDialog = otherPage.getByRole( 'dialog', { name: 'Welcome to the editor' } );
		if ( await welcomeDialog.isVisible() ) {
			await welcomeDialog.getByRole( 'button', { name: 'Close' } ).click();
		}

		await otherEditor.canvas.getByRole( 'textbox', { name: 'Add title' } ).fill( updatedTitle );
		await otherPage.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect.poll( async () => {
			const updatedPost = await requestUtils.rest( {
				path: `/wp/v2/posts/${ post.id }`,
				params: { context: 'edit' },
			} );
			return updatedPost.title.raw;
		} ).toBe( updatedTitle );

		await otherPage.close();
		await page.bringToFront();
		await page.evaluate( () => window.wp.heartbeat.connectNow() );

		await expect( rowTitle ).toHaveText( updatedTitle );
	} );

	test( 'defers refreshing the post list while Quick Edit has unsaved changes', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Original title',
			status: 'publish',
		} );

		await admin.visitAdminPage( '/edit.php' );
		await page.locator( `#post-${ post.id } .editinline` ).evaluate( ( button ) => button.click() );

		const titleInput = page.locator( `#edit-${ post.id } input[name="post_title"]` );
		await titleInput.fill( 'Unsaved title' );
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/posts/${ post.id }`,
			data: { title: 'Updated title' },
		} );

		const heartbeatResponse = page.waitForResponse( ( response ) =>
			response.url().includes( 'admin-ajax.php' ) &&
			response.request().postData()?.includes( 'wp-check-post-list' )
		);
		await page.evaluate( () => window.wp.heartbeat.connectNow() );
		await heartbeatResponse;
		await expect( titleInput ).toHaveValue( 'Unsaved title' );

		await page.locator( `#edit-${ post.id } .cancel` ).click();
		await page.evaluate( () => window.wp.heartbeat.connectNow() );
		await expect( page.locator( `#post-${ post.id } .row-title` ) ).toHaveText( 'Updated title' );
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
