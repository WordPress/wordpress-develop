import {
    visitAdminPage,
    createNewPost,
    trashAllPosts,
    publishPost
} from '@wordpress/e2e-test-utils';


const posttitle = 'Test Title';

describe( 'Empty Trash', () => {

    async function createPost(title) {
        // Create a Post     
        await createNewPost( {title} );
        await publishPost();

    }

    afterEach( async () => {
        await trashAllPosts();
    } );

    it( 'Empty Trash', async () => {

        await createPost(posttitle);
       
        await visitAdminPage( '/edit.php' );  

        // Move post to trash
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.row-title');
        await page.click("a[aria-label='Move “Test Title” to the Trash']")

        // Empty trash 
        const trashbutton = await page.$x("//li[@class='trash']");
        await trashbutton[0].click();   
        const deleteAllButton = await page.waitForSelector('input[value="Empty Trash"]');
        await deleteAllButton.click();
        await page.waitForSelector("#message");
        
    } );


    it( 'Restore trash post', async () => {

        await createPost(posttitle);
    
        await visitAdminPage( '/edit.php' );  

        // Move one post to trash
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.row-title');
        await page.click("a[aria-label='Move “Test Title” to the Trash']");

        // Remove post from trash 
        const trashbutton = await page.$x("//li[@class='trash']");
        await trashbutton[0].click();   
        
        await page.waitForSelector( '#the-list .type-post' );
        await page.hover('.page-title'); 
        await page.click(`[aria-label="Restore “${posttitle}” from the Trash"]`);

        // expect for sucess message for trashed post. 
        const messageElement = await page.waitForSelector('#message'); 

        expect( 
            await messageElement.evaluate( ( element ) => element.innerText ) 
        ).toContain( '1 post restored from the Trash.' )

    } );
   
} );
