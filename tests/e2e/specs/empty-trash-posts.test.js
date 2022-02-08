import {
    visitAdminPage,
    createNewPost,
    trashAllPosts,
    publishPost
} from '@wordpress/e2e-test-utils';

describe( 'Empty Trash', () => {
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

    it( 'Empty Trash', async () => {

        //Create a Post
        const title = 'Test Title';
        await createNewPost( { title } );
        await publishPost();

        await visitAdminPage( '/edit.php' );  

        // Move post to trash
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.row-title');
        await page.click("a[aria-label='Move “Test Title” to the Trash']");

        // Empty trash 
        await page.waitForSelector(".subsubsub");
        await page.click("a[href='edit.php?post_status=trash&post_type=post']");
        await page.waitForSelector( '#delete_all' );
        await page.click("#delete_all");
		await page.waitForSelector("#message");
		
    } );
} );
