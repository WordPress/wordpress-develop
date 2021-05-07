/**
 * WordPress dependencies
 */
import {
	visitAdminPage,
	pressKeyWithModifier,
	pressKeyTimes
} from '@wordpress/e2e-test-utils'
import { addQueryArgs } from '@wordpress/url';

describe( 'Add new category', () => {
	const title = 'New Category';
	const query = addQueryArgs( '', {
		taxonomy: 'category',
	} );

	beforeEach( async () => {
		/**
		 * Delete all categories before anything
		 * This is useful because after running more than one test
		 * there could be existing categories
		 */
		await visitAdminPage( 'edit-tags.php', query );
		await page.$( '#bulk-action-selector-top' );
		await page.waitForSelector( '[id^=cb-select-all-]' );
		await page.click( '[id^=cb-select-all-]' );
		await page.select( '#bulk-action-selector-top', 'delete' );
		await page.click( '#doaction' );

		/**
		 * Create a new category with the title 'New Category'
		 */
		await page.waitForSelector('#tag-name');
		await page.focus( '#tag-name' );
		await pressKeyWithModifier( 'primary', 'a' );
		await page.type( '#tag-name', title );
		await page.click( '#submit' );

		await page.reload();
	} );

	it( 'shows the new created category with the correct title', async () => {
		// Expect there to be two rows in the categories list.
		// The new created category and the default Uncategorized"
		await page.waitForSelector( '#the-list tr' );
		const categories = await page.$$( '#the-list tr' );
		expect( categories.length ).toBe( 2 );

		// Expect the new created category title to be correct.
		const firstRowCategoryTitle = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		expect( firstRowCategoryTitle.length ).toBe( 1 );
	} );

	it( 'allows an existing category to be edited using the Edit button', async () => {
		await page.waitForSelector( '#the-list tr' );
		// Click the first (new created) category title (edit) link
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.click();

		await page.waitForNavigation();

		await page.waitForSelector( '.term-name-wrap input#name' );
		// Expect to now be in the category editor with the correct category title shown.
		const editorCategoryTitleInput = await page.$x(
			`//input[contains(@id, "name")][@value = "${ title }"]`
		);
		expect( editorCategoryTitleInput.length ).toBe( 1 );
	} );

	it( 'allows an existing category to be quick edited using the Quick Edit button', async () => {
		await page.waitForSelector( '#the-list tr' );
		// Focus on the first (new created) category title (edit) link
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.focus();

		// Tab to the Quick Edit button and press Enter to quick edit.
		await pressKeyTimes( 'Tab', 2 );
		await page.keyboard.press( 'Enter' );

		// Type in the currently focused (title) field to modify the title, testing that focus is moved to the input.
		await page.keyboard.type( ' Edited' );

		// Update the category.
		await page.click( '.button.save' );

		// Wait for the quick edit button to reappear.
		await page.waitForSelector( 'button.editinline', { visible: true } );

		// Expect there to be two rows in the categories list.
		// The new created category and the default Uncategorized"
		const categories = await page.$$( '#the-list tr' );
		expect( categories.length ).toBe( 2 );

		// Expect the category title to be correct.
		const firstRowCategoryTitle = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title } Edited")]`
		);
		expect( firstRowCategoryTitle.length ).toBe( 1 );
	} );

	it( 'allows an existing category to be deleted using the Delete button', async () => {
		await page.waitForSelector( '#the-list tr' );

		// Focus on the first (new created) category title (edit) link
		const [ editLink ] = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		await editLink.focus();

		// Tab to the Delete button and press Enter to delete the category.
		await pressKeyTimes( 'Tab', 3 );
		await page.keyboard.press( 'Enter' );

		await page.reload();
		
		// Expect there to be only one row in the categories list.
		const categories = await page.$$( '#the-list tr' );
		expect( categories.length ).toBe( 1 );

		// Expect to remaining category to be the default "Uncategorized"
		const uncategorizedCategoryTitle = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "Uncategorized")]`
		);
		expect( uncategorizedCategoryTitle.length ).toBe( 1 );
	} );

	it( 'should return the appropriate results on a category search', async () => {
		const searchQuery = addQueryArgs( '', {
			taxonomy: 'category',
			s: title
		} );

		await visitAdminPage( 'edit-tags.php', searchQuery );
		await page.waitForSelector( '#the-list tr' );

		// Expect the new category created category to be the only one 
		// returned by the search
		const categories = await page.$$( '#the-list tr' );
		expect( categories.length ).toBe( 1 );

		// Expect the title of the category returned by the search to match
		// the new created category title
		const firstRowCategoryTitle = await page.$x(
			`//a[contains(@class, "row-title")][contains(text(), "${ title }")]`
		);
		expect( firstRowCategoryTitle.length ).toBe( 1 );
	} );

	it( 'should return "No categories found." if the searched category is not found', async () => {
		const searchQuery = addQueryArgs( '', {
			taxonomy: 'category',
			s: "Non existing category"
		} );

		await visitAdminPage( 'edit-tags.php', searchQuery );
		await page.waitForSelector( '#the-list tr' );

		// Expect the categories table to have only one row with the class "no-items"
		const notFoundRow = await page.$x(
			`//tr[contains(@class, "no-items")]`
		);
		expect( notFoundRow.length ).toBe( 1 );

		// Expect the row of the categories table to contain the text "No categories found."
		const notFoundText = await page.$x(
			`//td[contains(text(), "No categories found.")]`
		);
		expect( notFoundText.length ).toBe( 1 );
	} );
} );
