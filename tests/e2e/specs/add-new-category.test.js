import {
	visitAdminPage,
	pressKeyWithModifier
} from '@wordpress/e2e-test-utils'

/* Test scenario
 * Go to the page /edit-tags.php?taxonomy=category
 * Add a category name
 * Click on the "Add New Category" button
 * Check if the new category appears in the category list
 */

describe( 'Add new category', () => {
	it( 'shows the new category in the category list after it is added', async () => {
		const title = 'New Category';
		
		await visitAdminPage( '/edit-tags.php?taxonomy=category' );

		await page.waitForSelector('#tag-name');
		await page.focus( '#tag-name' );
		await pressKeyWithModifier( 'primary', 'a' );
		await page.type( '#tag-name', title );

		await page.click( '#submit' );

		// Expect one category and only one to have the "tag-2" id
		// When a new category is created WordPress add the "tag-x"
		// id to the category line in the list.
		// The first one being the "Uncategorized" category
		const newCategory = await page.$$( '#the-list #tag-2' );
		expect( newCategory.length ).toBe( 1 );

		// Expect the title of the new created category to be correct
		const categoryTitle = await newCategory.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		expect( categoryTitle.length ).toBe( 1 );
	} );
} );
