import { 
	visitAdminPage,
	pressKeyWithModifier
 } from '@wordpress/e2e-test-utils';

describe( 'Users tests', () => {
	const username = "testuser";
	const email = "testuser@test.com";
	const password = "password";

	it( 'show the new added user', async () => {
		await visitAdminPage( 'user-new.php' );

		await page.focus( '#user_login' );
		await page.type( '#user_login', username );
		await page.focus( '#email' );
		await page.type( '#email', email );
		await page.focus( '#pass1' );
		await pressKeyWithModifier( 'primary', 'a' );
		await page.type( '#pass1', password );
		await page.click( "#createusersub" );

		const nodes = await page.$x(
			'//h1[contains(text(), "Users")]'
		);
		expect( nodes.length ).toBe( 1 );
	} );
} );