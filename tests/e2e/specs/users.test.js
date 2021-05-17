import { 
	visitAdminPage,
	pressKeyWithModifier
 } from '@wordpress/e2e-test-utils';

describe( 'Users tests', () => {
	const username = "testuser";
	const email = "testuser@test.com";

	beforeEach( async () => {
		/**
		 * If there is more than one user delete all of them
		 */
		await visitAdminPage( 'users.php' );
		const usersRows = await page.$$( '#the-list tr' );
		if( usersRows.length > 1 ) {
			await page.click( '[id^=cb-select-all-]' );
			await page.select( '#bulk-action-selector-top', 'delete' );

			// Do not delete the defaut admin user
			await page.click( '[id^=user_1]' );

			await page.click( '#doaction' );
			await page.waitForSelector( '#submit' );
			await page.click( '#submit' );
		}

		/**
		 * Create a new default user with username and password
		 */
		await visitAdminPage( 'user-new.php' );
		await page.focus( '#user_login' );
		await page.type( '#user_login', username );
		await page.focus( '#email' );
		await page.type( '#email', email );
		await page.click( "#createusersub" );
		await page.waitForNavigation();
	} );

	it( 'show the new added user', async () => {
		// Expect the users table to contain two rows
		const usersRows = await page.$$( '#the-list tr' );
		expect ( usersRows.length ).toBe( 2 );

		// Expect the new created username to be correct
		const newUserName = await page.$x(
			`//td/a[contains( text(), "${ username }" )]`
		);
		expect( newUserName.length ).toBe( 1 );
	} );

	it( 'should return the appropriate results on a username search', async () => {
		await page.waitForSelector( '#user-search-input' )
		await page.focus( '#user-search-input' );
		await page.type( '#user-search-input', username );
		await page.click( '#search-submit' );
	
		// Expect the title of the user returned by the search to match
		// the new created user title
		const newUserName = await page.$x(
			`//td/a[contains( text(), "${ username }" )]`
		);
		expect( newUserName.length ).toBe( 1 );
	
	} );

	it( 'should return a row with the class name "no-items', async () => {
		await page.waitForSelector( '#user-search-input' )
		await page.focus( '#user-search-input' );
		await page.type( '#user-search-input', "Non existing user" );
		await page.click( '#search-submit' );

		// Expect the users table to have only one row with the class "no-items"
		const notFoundRow = await page.$x(
			`//tr[contains( @class, "no-items" )]`
		);
		expect( notFoundRow.length ).toBe( 1 );

		// Expect the row of the users table to contain the text "No users found."
		const notFoundText = await page.$x(
			`//td[contains( text(), "No users found." )]`
		);
		expect( notFoundText.length ).toBe( 1 );
	} );
} );