/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Create User', () => {
	let username, email;

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllUsers();
	} );

	test( 'creates new user successfully', async ( { admin, page, requestUtils } ) => {

		// Navigate to the Add New User page
		await admin.visitAdminPage( 'user-new.php' );

		// Generate random username and email
		username = 'TestUser' + Date.now();
		email = `user${ Date.now() }@domain.tld`;

		// Wait until network is idle
		await page.waitForLoadState( 'networkidle' );

		// Fill in the username and email
		await page.locator( '#user_login' ).fill( username );
		await page.locator( '#email' ).fill( email );

		const passwordText = await page.locator( '#pass1' ).textContent();

		// Click the "Add New User" button
		await page.click( 'role=button[name="Add New User"i]' );

		// Verify success message
		await expect( page.locator( '#message p' ) ).toHaveText(
			'New user created. Edit user'
		);

		await requestUtils.login( username, passwordText );

		// Verify if the expected element is visible
		const isLoggedIn = await page
			.locator( '.wp-heading-inline' )
			.isVisible();
		if ( isLoggedIn ) {
			console.log( 'Logged in successfuly with newly created user' );
		} else {
			console.error( 'Login failed' );
		}
	} );

	test( 'deletes new user successfully', async ( { admin, page } ) => {
		// Navigate to the users page
		await admin.visitAdminPage( 'users.php' );
		
		// Filter by "Subscriber" role if required
		await page.locator( 'role=link[name= /Subscriber/i]' ).click();
		await page.waitForLoadState( 'networkidle' ); // Ensure all elements are loaded
	
		// Hover over the user row and click the delete link
		const userRowSelector = '.has-row-actions.column-primary';
		await page.locator( userRowSelector ).first().hover();
		await page.locator( 'role=link[name="Delete"i]' ).first().click();
	
		// Confirm deletion
		const confirmButtonSelector = 'role=button[name="Confirm Deletion"i]';
		await page.locator( confirmButtonSelector ).click();
	
		// Verify success message
		const successMessageSelector = '#message > p';
		await expect( page.locator( successMessageSelector ) ).toHaveText(
			/User deleted\./i);
	
	} );

	test( 'edits user role successfully', async ( { admin, page, requestUtils } ) => {

		await requestUtils.createUser({
			username: 'testuser',
			email: 'testuser@domain.tld',
			roles: 'subscriber',
			password: '34hhasjdhkahsdh'
		});
		// Navigate to Users page
		await admin.visitAdminPage( 'users.php' );
	
		// Wait for the users table to load
		await page.waitForSelector( '#the-list', { visible: true } );
	
		// Click on the test user's link
		const userLinkSelector = 'role=link[name="testuser"i]';
		await page.locator( userLinkSelector ).click();
	
		// Wait for the user profile page to load
		await page.waitForSelector( '#first_name', { visible: true } );
	
		// Edit the user's first name
		await page.fill( '#first_name', 'Test' );
	
		// Change the user's role
		const roleSelector = '#role';
		await page.selectOption( roleSelector, 'editor' );
	
		// Click the "Update user" button
		const updateButtonSelector = 'role=button[name="Update user"i]';
		await page.locator( updateButtonSelector ).click();
	
		// Verify the success message
		const successMessageSelector = "div[id='message'] p strong";
		await expect( page.locator( successMessageSelector ) ).toHaveText(
			'User updated.'
		);
	
	} );
	
	
} );
