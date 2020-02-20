import {
	createNewPost,
	pressKeyTimes,
	switchUserToAdmin,
	switchUserToTest,
	visitAdminPage,
} from '@wordpress/e2e-test-utils';

/**
 * Trash any posts on the edit posts screen.
 */
async function trashExistingPosts() {
	await switchUserToAdmin();
	// Visit `/wp-admin/edit.php` so we can see a list of posts and delete them.
	await visitAdminPage( 'edit.php' );

	// If this selector doesn't exist there are no posts for us to delete.
	const bulkSelector = await page.$( '#bulk-action-selector-top' );
	if ( ! bulkSelector ) {
		return;
	}

	// Select all posts.
	await page.waitForSelector( '[id^=cb-select-all-]' );
	await page.click( '[id^=cb-select-all-]' );
	// Select the "bulk actions" > "trash" option.
	await page.select( '#bulk-action-selector-top', 'trash' );
	// Submit the form to send all draft/scheduled/published posts to the trash.
	await page.click( '#doaction' );
	await page.waitForXPath(
		'//*[contains(@class, "updated notice")]/p[contains(text(), "moved to the Trash.")]'
	);
	await switchUserToTest();
}

export async function publishPost() {
	await page.waitForSelector(
		'.editor-post-publish-panel__toggle:not([aria-disabled="true"])'
	);
	await page.click( '.editor-post-publish-panel__toggle' );
	await page.waitForSelector( '.editor-post-publish-button' );
	await page.keyboard.press( 'Enter' );
}

describe( 'Edit Posts', () => {
	beforeEach( trashExistingPosts );

	it( 'displays a message in the posts table when no posts are present', async () => {
		await visitAdminPage( '/edit.php' );
		const noPostsMessage = await page.$x( '//td[text()="No posts found."]' );
		expect( noPostsMessage.length ).toBe( 1 );
	} );

	it( 'shows a single post after one is published with the correct title', async () => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

		// Expect there to be one row in the post list.
		const posts = await page.$$( '#the-list tr.type-post' );
		expect( posts.length ).toBe( 1 );

		const [ firstPost ] = posts;

		// Expect the title of the post to be correct.
		const postTitle = await firstPost.$x( `//a[contains(@class, "row-title")][contains(text(), "${ title }")]` );
		expect( postTitle.length ).toBe( 1 );
	} );

	it( 'allows an existing post to be edited using the Edit button', async() => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

		// Skip to the main content using the shortcut.
		await pressKeyTimes( 'Tab', 1 );
		await page.keyboard.press( 'Enter' );

		// Tab to the Edit Post link
		await pressKeyTimes( 'Tab', 20 );

		// Edit the post.
		await page.keyboard.press( 'Enter' );
		await page.waitForNavigation();

		// Expect to now be in the editor with the correct post title shown.
		const editorPostTitleInput = await page.$x( `//textarea[contains(@class, "editor-post-title__input")][contains(text(), "${ title }")]` );
		expect( editorPostTitleInput.length ).toBe( 1 );
	} );

	it( 'allows an existing post to be quick edited using the Quick Edit button', async() => {
		const title = 'Test Title';
		await createNewPost( { title } );
		await publishPost();
		await visitAdminPage( '/edit.php' );

		// Skip to the main content using the shortcut.
		await pressKeyTimes( 'Tab', 1 );
		await page.keyboard.press( 'Enter' );

		// Tab to the Quick Edit button and press Enter to quick edit.
		await pressKeyTimes( 'Tab', 21 );
		await page.keyboard.press( 'Enter' );

		// Type in the currently focused (title) field to modify the title, testing that focus is moved to the input.
		await page.keyboard.type( ' Edited' );

		// Update the post.
		await pressKeyTimes( 'Tab', 17 );
		await page.keyboard.press( 'Enter' );

		// Wait for the quick edit button to reappear.
		await page.waitForSelector( 'button.editinline', { visible: true } );

		// Expect there to be one row in the post list.
		const posts = await page.$$( '#the-list tr.type-post' );
		expect( posts.length ).toBe( 1 );

		const [ firstPost ] = posts;

		// Expect the title of the post to be correct.
		const postTitle = await firstPost.$x( `//a[contains(@class, "row-title")][contains(text(), "${ title } Edited")]` );
		expect( postTitle.length ).toBe( 1 );
	} );
} );
