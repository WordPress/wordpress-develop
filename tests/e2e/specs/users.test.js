import { 
	visitAdminPage,
	pressKeyWithModifier
 } from '@wordpress/e2e-test-utils';

describe( 'Users tests', () => {
	const username = "testuser";
	const email = "testuser@test.com";
	// const password = "password";

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
	} );

	it( 'show the new added user', async () => {
		await visitAdminPage( 'user-new.php' );

		await page.focus( '#user_login' );
		await page.type( '#user_login', username );
		await page.focus( '#email' );
		await page.type( '#email', email );
		await page.click( "#createusersub" );

		await page.waitForNavigation();

		// Expect the users table to contain two rows
		const usersRows = await page.$$( '#the-list tr' );
		expect ( usersRows.length ).toBe( 2 );
	} );
} );
