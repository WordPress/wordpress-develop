/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Bulk @wp-post Edit', () => {
	test.beforeEach( async ( { admin, editor } ) => {
		// create Posts to use in test
		const postTitles = [
			'Test Post for bulk edit 1',
			'Test Post for bulk edit 2',
		];

		for ( const title of postTitles ) {
			await admin.createNewPost( { title: title } );
			await editor.publishPost();
		}
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'Should be able to edit the posts in bulk', async ( {
		page,
		admin,
	} ) => {
		// Navigate to posts page
		await admin.visitAdminPage( '/edit.php' );
		await expect( page.locator( '.wp-heading-inline' ) ).toHaveText(
			'Posts'
		);

		// click on select all checkbox
		await page.locator( '#cb-select-all-1' ).click();

		// select the edit option from the dropdown
		await page.selectOption( '#bulk-action-selector-top', 'edit' );

		// click on apply button
		await page.click( 'role=button[name="Apply"i] >> nth=0' );

		// select the draft option
		await page
			.getByRole( 'combobox', { name: 'Status' } )
			.selectOption( 'draft' );

		// click on the update button
		await page.getByRole( 'button', { name: 'Update' } ).click();

		await expect(
			page.locator( "div[id='message'] p" ).first()
		).toHaveText( /posts updated./ );

		const listTable = page.getByRole( 'table', {
			name: 'Table ordered by',
		} );
		const posts = listTable.locator( '.page-title  strong span' );

		// Validate that the page is in draft status
		await expect( posts.first() ).toHaveText( 'Draft' );
	} );
} );
