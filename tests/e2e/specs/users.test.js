import { 
	visitAdminPage,
} from "@wordpress/e2e-test-utils";

async function deleteNonDefaultUsers() {
	await visitAdminPage( 'users.php' );

	// Wait for the users rows to appear
	await page.waitForSelector( '#the-list tr' );

	const allUsersRows = await page.$$( '#the-list tr' );
	if( allUsersRows.length > 1 ) {
		await page.click( '[id^=cb-select-all-]' );
		await page.select( '#bulk-action-selector-top', 'delete' );

		// Do not delete the defaut admin user
		await page.click( '[id^=user_1]' );

		await page.click( '#doaction' );

		await page.waitForSelector( 'input#submit' );
		await page.click( 'input#submit' );

		await page.waitForNavigation();
	}
}

async function createBasicUser() {
	await visitAdminPage( 'user-new.php' );

	// Wait for the username field to appear and focus it
	const newUsernameField = await page.waitForSelector( 'input#user_login' );
	await newUsernameField.focus();

	// Type the user name and user email
	await page.keyboard.type( 'testuser' );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.type( 'testuser@test.com' );

	// Add the user
	await page.click( 'input#createusersub' );
}

describe( 'Core Users', () => {
	beforeEach( async () => {
		await deleteNonDefaultUsers();
		await createBasicUser();
	} );

	it( 'Correctly shows a new added user', async () => {
		// Wait for two users rows to appear
		await page.waitForSelector( '#the-list tr + tr' );

		// Check that the new user is added and shows correctly
		const newUserLink = await page.$x(
			`//td[contains( @class, "column-username" )]//a[contains( text(), "testuser" )]`
		);
		expect( newUserLink.length ).toBe( 1 );
	} );

	it( 'Returns the appropriate result when searching for an existing user', async () => {
		// Wait for the search field to appear and focus it
		const userSearchInput = await page.waitForSelector( '#user-search-input' );
		userSearchInput.focus();

		// Type the new username in the search input
		await page.keyboard.type( 'testuser' );

		// Move to the search button and click on it
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );
		await page.waitForNavigation();

		// Check that there is only one user row
		const allUsersRows = await page.$$( '#the-list tr' );
		expect( allUsersRows.length ).toBe( 1 );
		
		// Check that the remaining user is "testuser"
		const foundUserRow = await page.waitForSelector( '#the-list td.column-username a' );
		expect(
			await foundUserRow.evaluate( ( element ) => element.innerText )
		).toContain( 'testuser' );
	} );

	it( 'Should return No users found. when searching for a user that does not exist', async () => {
		// Wait for the search field to appear and focus it
		const userSearchInput = await page.waitForSelector( '#user-search-input' );
		userSearchInput.focus();

		// Type the new username in the search input
		await page.keyboard.type( 'nonexistinguser' );

		// Move to the search button and click on it
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Enter' );
		await page.waitForNavigation();

		// Check that there is only one user row
		const allUsersRows = await page.$$( '#the-list tr.no-items' );
		expect( allUsersRows.length ).toBe( 1 );
		
		// Check that the remaining row contains "No users found."
		const notFoundUserRow = await page.waitForSelector( '#the-list tr.no-items' );
		expect(
			await notFoundUserRow.evaluate( ( element ) => element.innerText )
		).toContain( 'No users found.' );
	} );
} );
