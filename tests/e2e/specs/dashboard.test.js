import {
	pressKeyTimes,
	trashAllPosts,
	visitAdminPage,
} from '@wordpress/e2e-test-utils';

describe( 'Quick Draft', () => {
	beforeEach( async () => {
		await trashAllPosts();
	} );

	it( 'Allows draft to be created with Title and Content', async () => {
		await visitAdminPage( '/' );

		// Wait for Quick Draft title field to appear and focus it
		const draftTitleField = await page.waitForSelector(
			'#quick-press #title'
		);
		await draftTitleField.focus();

		// Type in a title.
		await page.keyboard.type( 'Test Draft Title' );

		// Navigate to content field and type in some content
		await page.keyboard.press( 'Tab' );
		await page.keyboard.type( 'Test Draft Content' );

		// Navigate to Save Draft button and press it.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		// Check that new draft appears in Your Recent Drafts section
		const newDraft = await page.waitForSelector( '.drafts .draft-title' );

		expect(
			await newDraft.evaluate( ( element ) => element.innerText )
		).toContain( 'Test Draft Title' );

		// Check that new draft appears in Posts page
		await visitAdminPage( '/edit.php' );
		const postsListDraft = await page.waitForSelector(
			'.type-post.status-draft .title'
		);

		expect(
			await postsListDraft.evaluate( ( element ) => element.innerText )
		).toContain( 'Test Draft Title' );
	} );

	it( 'Allows draft to be created without Title or Content', async () => {
		await visitAdminPage( '/' );

		// Wait for Save Draft button to appear and click it
		const saveDraftButton = await page.waitForSelector(
			'#quick-press #save-post'
		);
		await saveDraftButton.click();

		// Check that new draft appears in Your Recent Drafts section
		const newDraft = await page.waitForSelector( '.drafts .draft-title a' );

		expect(
			await newDraft.evaluate( ( element ) => element.innerText )
		).toContain( '(no title)' );

		// Check that new draft appears in Posts page
		await visitAdminPage( '/edit.php' );
		const postsListDraft = await page.waitForSelector(
			'.type-post.status-draft .title a'
		);

		expect(
			await postsListDraft.evaluate( ( element ) => element.innerText )
		).toContain( '(no title)' );
	} );
} );
