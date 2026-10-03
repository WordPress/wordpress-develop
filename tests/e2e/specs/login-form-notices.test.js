/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/*
 * login_header() displays a separate notice per error severity and can display
 * both at once. Every notice displayed must be referenced by the login fields.
 */
test.describe( 'Login form notices', () => {
	// The login form is only displayed to logged out users.
	test.use( { storageState: { cookies: [], origins: [] } } );

	async function submitInvalidCredentials( page ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'incorrect-password' );
		await page.locator( '#wp-submit' ).click();
	}

	test( 'should reference both notices when an error and a message are displayed', async ( {
		page,
	} ) => {
		// Redirecting back to the About page after an update adds a message.
		await page.goto(
			'/wp-login.php?redirect_to=' +
				encodeURIComponent( '/wp-admin/about.php?updated' )
		);
		await submitInvalidCredentials( page );

		await expect( page.locator( '#login_error' ) ).toBeVisible();
		await expect( page.locator( '#login-message' ) ).toBeVisible();

		await expect( page.locator( '#user_login' ) ).toHaveAttribute(
			'aria-describedby',
			'login_error login-message'
		);
		await expect( page.locator( '#user_pass' ) ).toHaveAttribute(
			'aria-describedby',
			'login_error login-message'
		);
	} );

	test( 'should reference the error notice when only an error is displayed', async ( {
		page,
	} ) => {
		await page.goto( '/wp-login.php' );
		await submitInvalidCredentials( page );

		await expect( page.locator( '#user_login' ) ).toHaveAttribute(
			'aria-describedby',
			'login_error'
		);
	} );

	test( 'should reference the message notice when only a message is displayed', async ( {
		page,
	} ) => {
		await page.goto( '/wp-login.php?loggedout=true' );

		await expect( page.locator( '#user_login' ) ).toHaveAttribute(
			'aria-describedby',
			'login-message'
		);
	} );

	test( 'should not reference a notice when none is displayed', async ( {
		page,
	} ) => {
		await page.goto( '/wp-login.php' );

		expect(
			await page
				.locator( '#user_login' )
				.getAttribute( 'aria-describedby' )
		).toBeNull();
	} );
} );
