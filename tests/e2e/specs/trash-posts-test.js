import {
	loginUser,
	visitAdminPage,
} from '@wordpress/e2e-test-utils';

describe( 'Trash a single post', () => {

	it( 'Trash a single post', async () => {

		await visitAdminPage( '/edit.php' );    

        //wait for all posts
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.row-title');
        await page.click("a.submitdelete");

        //expect for sucess message for trashed post. 
        const noPostsMessage = await page.waitForSelector(
			"div[id='message'] p:nth-child(1)"
		);

		expect(
			await noPostsMessage.evaluate( ( element ) => element.innerText )
		).toBe( '1 post moved to the Trash. Undo' );
	} );
} );
