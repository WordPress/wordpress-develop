import {
	visitAdminPage,
	createNewPost,
	publishPost,
} from '@wordpress/e2e-test-utils';

describe( 'Trash Post', () => {

	beforeAll( async () => {
		await visitAdminPage( '/' );
	} );
	
	it( 'Trash a single post', async () => {

		await createNewPost({title: 'Test post to be trashed'});
		await publishPost();

		// Check that new post appears in Posts page
		await visitAdminPage( '/edit.php' );
		const postsListPublished = await page.waitForSelector(
			'.type-post.status-publish .title'
		);
		
		expect(
			await postsListPublished.evaluate( ( element ) => element.innerText )
		).toContain( 'Test post to be trashed' );

		await page.focus('tbody#the-list tr:nth-child(1) a.row-title');

		//Trash the post
		await page.click('tbody#the-list tr:nth-child(1) td div.row-actions a.submitdelete');

		// Check that post is trashed
		const trashMessage = await page.waitForSelector(
			`div#message`	
		);
		
		expect(
			await trashMessage.evaluate( ( element ) => element.innerText )
		).toContain( '1 post moved to the Trash. Undo' );

	} );

	

} );