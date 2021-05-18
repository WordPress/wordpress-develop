import { 
	visitAdminPage,
	pressKeyWithModifier
} from '@wordpress/e2e-test-utils';

async function deleteNonDefaultUsers() {
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
}

async function createBasicUser( username, email ) {
	/**
	 * Create a new basic user with username and password
	 */
	await visitAdminPage( 'user-new.php' );
	await page.focus( '#user_login' );
	await page.type( '#user_login', username );
	await page.focus( '#email' );
	await page.type( '#email', email );
	await page.click( "#createusersub" );
	await page.waitForNavigation();
}

describe( 'Users tests', () => {

	const firstname = "Hello";
	const lastname = "World";
	const username = "testuser";
	const email = "testuser@test.com";

	it( 'show the new added user', async () => {
		await deleteNonDefaultUsers();
		await createBasicUser( username, email );

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
		await pressKeyWithModifier( 'primary', 'a' );
		await page.type( '#user-search-input', "nonexistinguser" );
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

	/* it( 'allows to edit an existing user', async () => {
		await visitAdminPage( 'users.php' );
		await page.waitForSelector( '#the-list tr' );

		// Click on the new user name
		const [ editUserLink ] = await page.$x(
			`//td[@class='username']/a[contains( text(), "${ username }" )]`
		);
		await editUserLink.click();
		await page.waitForNavigation();

		await page.focus( '#first_name' );
		await page.type( '#first_name', firstname );

		await page.focus( '#last_name' );
		await page.type( '#last_name', lastname );

		await page.click( '#submit' );
		

		const newUserFullName = await page.$x(
			`//td/a[contains( text(), "${ firstname + " " + lastname }" )]`
		);
		expect( newUserFullName.length ).toBe( 1 );
	} ); */

	it( 'should not allows an admin to change their role', async () => {
		await visitAdminPage( 'profile.php' );
		
		// Expect the role changing role to not be present on the admin profile
		// page when logged in as admin
		const roleChangingRow = await page.$$( '#user-role-wrap' );
		expect( roleChangingRow.length ).toBe( 0 );
	} );

} );
