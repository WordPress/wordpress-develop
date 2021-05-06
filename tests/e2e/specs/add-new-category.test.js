/**
 * WordPress dependencies
 */
import {
	visitAdminPage,
	pressKeyWithModifier
} from '@wordpress/e2e-test-utils'
import { addQueryArgs } from '@wordpress/url';


/** Test scenario
 * Go to the page /edit-tags.php?taxonomy=category
 * If there is any other existing categorie apart from "Uncategorized", delete it
 * Add a category name
 * Click on the "Add New Category" button
 * Check if there are now two categories: the new created and "Uncategorized"
 * Check if the new category title is correct
 */

describe( 'Add new category', () => {
	const query = addQueryArgs( '', {
		taxonomy: 'category',
	} );

	it( 'shows the new created category with the correct title', async () => {
		await visitAdminPage( 'edit-tags.php', query );

		/**
		 * Delete all categories before anything
		 * This is useful because after running more than one test
		 * there could be existing categories
		 */
		const bulkSelector = await page.$( '#bulk-action-selector-top' );
		await page.waitForSelector( '[id^=cb-select-all-]' );
		await page.click( '[id^=cb-select-all-]' );
		await page.select( '#bulk-action-selector-top', 'delete' );
		await page.click( '#doaction' );

		/**
		 * Create a new category
		 */
		const title = 'New Category';
		await page.waitForSelector('#tag-name');
		await page.focus( '#tag-name' );
		await pressKeyWithModifier( 'primary', 'a' );
		await page.type( '#tag-name', title );
		await page.click( '#submit' );

		await page.reload();

		// Expect there to be two rows in the categories list.
		const categories = await page.$$( '#the-list tr' );
		expect( categories.length ).toBe( 2 )

		// Expect the new created category title to be correct.
		const newCategory = categories[0];
		const newCategoryTitle = await newCategory.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		expect( newCategoryTitle.length ).toBe( 1 );
	} );
} );
