import { 
	visitAdminPage,
} from "@wordpress/e2e-test-utils";
import { addQueryArgs } from "@wordpress/url";

async function deleteAllCategories() {
	const categoriesPageQuery = addQueryArgs( '', {
		taxonomy: 'category',
	} );
	await visitAdminPage( 'edit-tags.php', categoriesPageQuery );

	// Wait for the categories rows to appear
	await page.waitForSelector( '#the-list tr' );

	const allCategoriesRows = await page.$$( '#the-list tr' );
	
	// If there is more than one category row, delete all the categories
	if( allCategoriesRows.length > 1 ) {
		await page.click( '[id^=cb-select-all-]' );
		await page.select( '#bulk-action-selector-top', 'delete' );
		await page.focus( '#doaction' );
		await page.keyboard.press( 'Enter' );
	}
}

async function createNewCategory() {
	const categoriesPageQuery = addQueryArgs( '', {
		taxonomy: 'category',
	} );
	await visitAdminPage( 'edit-tags.php', categoriesPageQuery );

	// Wait for the input for new category name to appear and focus it
	const newCategoryNameInput = await page.waitForSelector( 'input#tag-name' );
	await newCategoryNameInput.focus();

	// Type the new category name
	await page.keyboard.type( 'New category' );
	
	// Save the new category
	await page.keyboard.press( 'Enter' );

	// Wait for the two categories rows to be present
	await page.waitForSelector( '#the-list tr + tr' )
}

describe( 'Core Categories', () => {
	beforeEach( async () => {
		await deleteAllCategories();
	} );

	it( 'Shows correctly the Uncategorized category', async () => {
		// Wait for the categories rows to appear
		await page.waitForSelector( '#the-list tr' );

		// Check that there is only one category row
		const allCategoriesRows = await page.$$( '#the-list tr' );
		expect( allCategoriesRows.length ).toBe( 1 );

		// Check that the "Uncategorized" category is present
		const categoryTitle = await page.waitForSelector( '#the-list .row-title' );
		expect(
			await categoryTitle.evaluate( ( element ) => element.innerText )
		).toContain( 'Uncategorized' );
	} );

	it( 'Creates a new category and shows it correctly', async () => {
		await createNewCategory();

		// Wait for the new category row to appear
		await page.waitForSelector( '#the-list tr:first-child .row-title' );

		// Check that the new category is added and shows correctly
		const newCategoryLink = await page.$x(
			`//a[contains( @class, "row-title" )][contains( text(), "New category" )]`
		);
		expect( newCategoryLink.length ).toBe( 1 );
	} );

	it( 'Allows an existing category to be deleted using the delete link', async () => {
		await createNewCategory();

		// Wait for the new category row to appear
		await page.waitForSelector( '#the-list tr:first-child .row-title' );

		// Check that the new category is added and shows correctly
		const newCategoryLink = await page.waitForSelector( '#the-list tr:first-child .row-title' )
		
		// Focus on the new category link and move to the delete link
		newCategoryLink.focus();
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );

		// Click on the delete link
		await page.keyboard.press( 'Enter' );

		await page.reload();

		// Check that there is only one remaining row
		const allCategoriesRows = await page.$$( '#the-list tr' );
		expect( allCategoriesRows.length ).toBe( 1 );

		// Check that the remaining category is "Uncategorized"
		const categoryTitle = await page.waitForSelector( '#the-list .row-title' );
		expect(
			await categoryTitle.evaluate( ( element ) => element.innerText )
		).toContain( 'Uncategorized' );
	} );

	it( 'Returns the appropriate result when searching for an existing category', async () => {
		await createNewCategory();

		// Wait for the search field to appear and focus it
		const categorySearchInput = await page.waitForSelector( '#tag-search-input' );
		categorySearchInput.focus();

		// Type the new category title in the search input
		await page.keyboard.type( 'New category' );

		// Move to the search button and click on it
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );
		await page.waitForNavigation();

		// Check that there is only one category row
		const allCategoriesRows = await page.$$( '#the-list tr' );
		expect( allCategoriesRows.length ).toBe( 1 );
		
		// Check that the remaining category is "New Category"
		const categoryTitle = await page.waitForSelector( '#the-list .row-title' );
		expect(
			await categoryTitle.evaluate( ( element ) => element.innerText )
		).toContain( 'New category' );
	} );

	it( 'Should return No categories found. when searching for a category that does not exist', async () => {
		// Wait for the search field to appear and focus it
		const categorySearchInput = await page.waitForSelector( '#tag-search-input' );
		categorySearchInput.focus();

		// Type the new category title in the search input
		await page.keyboard.type( 'New category' );

		// Move to the search button and click on it
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );
		await page.waitForNavigation();

		// Check that there is only one category row
		const allCategoriesRows = await page.$$( '#the-list tr.no-items' );
		expect( allCategoriesRows.length ).toBe( 1 );

		// Check that the remaining category contains "No categories found."
		const categoryTitle = await page.waitForSelector( '#the-list tr.no-items' );
		expect(
			await categoryTitle.evaluate( ( element ) => element.innerText )
		).toContain( 'No categories found.' );
	} );
} );
