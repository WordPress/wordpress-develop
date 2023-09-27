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
		const noPostsMessage = await page.$x(
			'//td[text()="No posts found."]'
		);
		expect( noPostsMessage.length ).toBe( 1 );
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

		await page.waitForSelector( '#the-list .type-post' );

		// Expect there to be one row in the post list.
		const posts = await page.$$( '#the-list .type-post' );
		expect( posts.length ).toBe( 1 );

		const [ firstPost ] = posts;

		// Expect the title of the post to be correct.
		const postTitle = await firstPost.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		expect( postTitle.length ).toBe( 1 );
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

		await page.waitForSelector( '#the-list .type-post' );

		// Click the post title (edit) link
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.click();

		// Wait for the editor iframe to load, and switch to it as the active content frame.
		const editorFrame = await page.waitForSelector( 'iframe[name="editor-canvas"]' );

		const innerFrame = await editorFrame.contentFrame();

		// Wait for title field to render onscreen.
		await innerFrame.waitForSelector( '.editor-post-title__input' );

		// Expect to now be in the editor with the correct post title shown.
		const editorPostTitleInput = await innerFrame.$x(
			`//h1[contains(@class, "editor-post-title__input")][contains(text(), "${ title }")]`
		);
		expect( editorPostTitleInput.length ).toBe( 1 );
	} );

	test( 'allows an existing post to be quick edited using the Quick Edit button', async ( {
		admin,
		editor,
		page
	} ) => {
		const title = 'Test Title';
		await admin.createNewPost( { title } );
		await editor.publishPost();
		await admin.visitAdminPage( '/edit.php' );

		await page.waitForSelector( '#the-list .type-post' );

		// Focus on the post title link.
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.focus();

		// Tab to the Quick Edit button and press Enter to quick edit.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		// Type in the currently focused (title) field to modify the title, testing that focus is moved to the input.
		await page.keyboard.type( ' Edited' );

		// Update the post.
		await page.click( '.button.save' );

		// Wait for the quick edit button to reappear.
		await page.waitForSelector( 'button.editinline', { visible: true } );

		// Expect there to be one row in the post list.
		const posts = await page.$$( '#the-list tr.type-post' );
		expect( posts.length ).toBe( 1 );

		const [ firstPost ] = posts;

		// Expect the title of the post to be correct.
		const postTitle = await firstPost.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title } Edited")]`
		);
		expect( postTitle.length ).toBe( 1 );
	} );

	test( 'allows an existing post to be deleted using the Trash button', async ( {
		admin,
		editor,
		page
	} ) => {
		const title = 'Test Title';
		await admin.createNewPost( { title } );
		await editor.publishPost();
		await admin.visitAdminPage( '/edit.php' );

		await page.waitForSelector( '#the-list .type-post' );

		// Focus on the post title link.
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.focus();

		// Tab to the Trash button and press Enter to delete the post.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		const noPostsMessage = await page.waitForSelector(
			'#the-list .no-items td'
		);

		expect(
			await noPostsMessage.evaluate( ( element ) => element.innerText )
		).toBe( 'No posts found.' );
	} );
} );
