/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Create New Post', () => {
	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'creates new posts', async ( { admin, editor, page } ) => {
		const postTitle = 'Test Post to create post test';
		await admin.createNewPost( { title: postTitle } );
		await editor.publishPost();
		const successLocator = page.locator( '.components-snackbar' );

		await expect( successLocator ).toHaveText( 'Post published.View Post' );
	} );
} );
