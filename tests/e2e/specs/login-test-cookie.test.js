/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/*
 * The login screen no longer sets a test cookie to check whether
 * cookies are supported by the browser.
 *
 * See https://core.trac.wordpress.org/ticket/66237
 */
test.describe( 'Login test cookie', () => {
	const username = process.env.WP_USERNAME ?? 'admin';
	const password = process.env.WP_PASSWORD ?? 'password';

	test( 'should not set a test cookie on the login screen', async ( {
		browser,
	} ) => {
		// Use a new browser context to simulate a logged-out visitor.
		const context = await browser.newContext();
		const loggedOutPage = await context.newPage();

		await loggedOutPage.goto( '/wp-login.php' );
		await loggedOutPage.waitForSelector( '#loginform' );

		const cookieNames = ( await context.cookies() ).map(
			( cookie ) => cookie.name
		);

		await expect(
			loggedOutPage.locator( '#loginform input[name="testcookie"]' )
		).toHaveCount( 0 );

		await context.close();

		expect( cookieNames ).not.toContain( 'wordpress_test_cookie' );
	} );

	test( 'should log in with valid credentials', async ( { browser } ) => {
		const context = await browser.newContext();
		const loggedOutPage = await context.newPage();

		await loggedOutPage.goto( '/wp-login.php' );
		await loggedOutPage.fill( '#user_login', username );
		await loggedOutPage.fill( '#user_pass', password );
		await Promise.all( [
			loggedOutPage.waitForURL( /\/wp-admin\/?/ ),
			loggedOutPage.click( '#wp-submit' ),
		] );

		const cookieNames = ( await context.cookies() ).map(
			( cookie ) => cookie.name
		);

		await expect( loggedOutPage.locator( '#login_error' ) ).toHaveCount( 0 );

		await context.close();

		expect(
			cookieNames.some( ( name ) =>
				name.startsWith( 'wordpress_logged_in_' )
			)
		).toBe( true );
		expect( cookieNames ).not.toContain( 'wordpress_test_cookie' );
	} );

	test( 'should log in when a login form still submits the legacy testcookie field', async ( {
		browser,
	} ) => {
		const context = await browser.newContext();
		const loggedOutPage = await context.newPage();

		await loggedOutPage.goto( '/wp-login.php' );

		/*
		 * Cached or custom login forms may still post `testcookie`. Without a
		 * test cookie in the browser, this used to fail with a
		 * "Cookies are blocked or not supported" error.
		 */
		await loggedOutPage.evaluate( () => {
			const input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = 'testcookie';
			input.value = '1';
			document.getElementById( 'loginform' ).appendChild( input );
		} );

		await loggedOutPage.fill( '#user_login', username );
		await loggedOutPage.fill( '#user_pass', password );
		await Promise.all( [
			loggedOutPage.waitForURL( /\/wp-admin\/?/ ),
			loggedOutPage.click( '#wp-submit' ),
		] );

		await expect( loggedOutPage.locator( '#login_error' ) ).toHaveCount( 0 );

		await context.close();
	} );
} );
