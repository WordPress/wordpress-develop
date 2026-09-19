/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Bulk Delete wp pages', () => {
	test.beforeEach( async ( { page, admin, requestUtils, editor } ) => {
		// delete all pages
		await requestUtils.deleteAllPages();

		const pageTitles = ['Bulk edit page 1', 'Bulk edit page 2']

		// create pages
		for (const title of pageTitles) {
			await admin.createNewPost( {
				title: title,
				postType: 'page',
			} );
			await editor.publishPost();
			
		}

	} );

	test( 'Should able to delete the pages in bulk', async ( {
		page,
		admin,
	} ) => {

		// visit the page list
		await admin.visitAdminPage( '/edit.php?post_type=page' );

		await page.getByRole(' checkbox ', {name: 'Select All'}).first().check();

		await page.getByRole(' combobox ', {name: "action"}).first().selectOption('trash');

		await page.getByRole( 'button', {name: 'Apply'}).first().click();

		await expect(page.getByText(/moved to the Trash./)).toBeVisible();
	} );
} );
