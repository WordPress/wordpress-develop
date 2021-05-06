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
 * Check if the new category appears in the category list
 */

describe( 'Add new category', () => {
	const query = addQueryArgs( '', {
		taxonomy: 'category',
	} );

	it( 'shows the new category in the category list after it is added', async () => {
		await visitAdminPage( 'edit-tags.php', query );

		/**
		 * Delete all categories before anything
		 * This is useful because after running more than one test
		 * there could be existing categories
		 */
		const bulkSelector = await page.$( '#bulk-action-selector-top' );

		await page.waitForSelector( '[id^=cb-select-all-]' );
		await page.click( '[id^=cb-select-all-]' );

		// Select the "bulk actions" > "delete" option.
		await page.select( '#bulk-action-selector-top', 'delete' );

		// Submit the form to delete all existing cateogies (except the default "Uncategorized")
		await page.click( '#doaction' );

		const title = 'New Category';

		await page.waitForSelector('#tag-name');
		await page.focus( '#tag-name' );
		await pressKeyWithModifier( 'primary', 'a' );
		await page.type( '#tag-name', title );

		await page.click( '#submit' );

		await page.reload();

		// Expect there to be one row in the post list.
		const categories = await page.$$( '#the-list tr' );
		expect( categories.length ).toBe( 2 )
	} );
} );
