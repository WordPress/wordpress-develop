import {
    loginUser,
    visitAdminPage,
    createNewPost,
    publishPost
} from '@wordpress/e2e-test-utils';

describe( 'Restore trash post', () => {

    it( 'Restore trash post', async () => {
    
        //create a Post
        const title = 'Test Title';
        await createNewPost( { title } );
        await publishPost();

        await visitAdminPage( '/edit.php' );  

        // Move one post to trash
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.row-title');
        await page.click("a.submitdelete");


        // Remove post from trash
        await page.waitForSelector( '#the-list .type-post' );

        await page.click(".trash");
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.page-title');
        await page.click(".untrash"); 

        // expect for sucess message for trashed post. 
        const noPostsMessage = await page.waitForSelector(
            "div[id='message'] p:nth-child(1)"
        );

        expect(
            await noPostsMessage.evaluate( ( element ) => element.innerText )
        ).toBe( '1 post restored from the Trash. Edit Post' );
    } );
} );
