import {
	createNewPost,
	pressKeyTimes,
	publishPost,
	trashAllPosts,
	visitAdminPage,
} from '@wordpress/e2e-test-utils';

describe( 'Edit Posts', () => {
	beforeEach( async () => {
		await trashAllPosts();
	} );

	it( 'displays a message in the posts table when no posts are present', async () => {
		await visitAdminPage( '/edit.php' );
		const noPostsMessage = await page.$x(
			'//td[text()="No posts found."]'
		);
		expect( noPostsMessage.length ).toBe( 1 );
	} );

	it( 'shows a single post after one is published with the correct title', async () => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

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

	it( 'allows an existing post to be edited using the Edit button', async () => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

		await page.waitForSelector( '#the-list .type-post' );

		// Click the post title (edit) link
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.click();

		// Edit the post.
		await page.waitForNavigation();

		// Wait for title field to render onscreen.
		await page.waitForSelector( '.editor-post-title__input' );

		// Expect to now be in the editor with the correct post title shown.
		const editorPostTitleInput = await page.$x(
			`//h1[contains(@class, "editor-post-title__input")][contains(text(), "${ title }")]`
		);
		expect( editorPostTitleInput.length ).toBe( 1 );
	} );

	it( 'allows an existing post to be quick edited using the Quick Edit button', async () => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

		await page.waitForSelector( '#the-list .type-post' );

		// Focus on the post title link.
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.focus();

		// Tab to the Quick Edit button and press Enter to quick edit.
		await pressKeyTimes( 'Tab', 2 );
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
	it( 'allows an existing post to be deleted using the Trash button', async () => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

		await page.waitForSelector( '#the-list .type-post' );

		// Focus on the post title link.
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.focus();

		// Tab to the Trash button and press Enter to delete the post.
		await pressKeyTimes( 'Tab', 3 );
		await page.keyboard.press( 'Enter' );

		const noPostsMessage = await page.waitForSelector(
			'#the-list .no-items td'
		);

		expect(
			await noPostsMessage.evaluate( ( element ) => element.innerText )
		).toBe( 'No posts found.' );
	} );
} );
