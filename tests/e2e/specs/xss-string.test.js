import {
	trashAllPosts,
	visitAdminPage,
} from '@wordpress/e2e-test-utils';
import { assert } from 'qunit';

const xss_title = "<svg onload=alert(XSS)>";

describe('XSS-String validation',() => {   
	beforeEach( async () => {
		await trashAllPosts();
	} );

    it( 'add xss-title', async () => {
		
		await visitAdminPage( '/' );
		const draftTitleField = await page.waitForSelector(
			'#quick-press #title'
		);
		await draftTitleField.focus();

		// Type xss text in the title.
		await page.keyboard.type( xss_title );

		// Navigate to content field and type in some content
		await page.keyboard.press( 'Tab' );
		await page.keyboard.type( 'Test Valid Draft Content for Xss String' );

		// Navigate to Save Draft button and press it.
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );

		// Check that new draft appears in Your Recent Drafts section
		const newDraft = await page.waitForSelector( '.drafts .draft-title' );
		
		expect(
			await newDraft.evaluate( ( element ) => element.innerText )
		).toContain( xss_title );

		// Check that new xss-string titled post is editable
		await page.click('div.draft-title a');
		const newPost = await page.waitForSelector('div.edit-post-visual-editor__post-title-wrapper h1');

		expect( 
			await newPost.evaluate( (element) => element.innerText )
		).toContain( xss_title )
	} );
	

})