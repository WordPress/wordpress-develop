/**
 * WordPress dependencies
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe('Save post to draft', () => {

  test.beforeEach( async ( { requestUtils }) => {
		await requestUtils.deleteAllPosts();
	} );
  
  test('Should able to save post to draft', async ({
    page,
    admin,
  }) => {
    const title = "Test Post - Saved to Draft"
    await admin.createNewPost({title: title});
  
    // Click on the save draft button
    await page.getByRole('button', { name: 'Save draft' }).click();

    // wait for confirmation message
    await page.locator('.components-snackbar__content').waitFor({
        state: 'visible'
    });

    //assert confirmation message
    await expect(page.locator('.components-snackbar__content')).toHaveText(
      /Draft saved./
    );
    await expect(page.locator(".editor-post-saved-state")).toHaveText('Saved');
    
    await admin.visitAdminPage( '/edit.php' );

    const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

    const posts = listTable.locator( '.row-title' );
		await expect( posts ).toHaveCount( 1 );

		// Expect the title of the post to be correct.
		expect( posts.first() ).toHaveText( "Test Post – Saved to Draft" );
    expect(page.locator('.post-state')).toHaveText('Draft')
  });

}); 