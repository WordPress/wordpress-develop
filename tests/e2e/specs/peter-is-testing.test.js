
/**
 * External dependencies
 */
import { join } from 'path';

const { PLAYWRIGHT_TEST_BASE_URL } = process.env;

/**
 * Creates new URL by parsing base URL, WPPath and query string.
 *
 * @param {string}  WPPath String to be serialized as pathname.
 * @param {?string} query  String to be serialized as query portion of URL.
 * @return {string} String which represents full URL.
 */
export function createURL( WPPath, query = '' ) {
	const url = new URL( PLAYWRIGHT_TEST_BASE_URL );

	url.pathname = join( url.pathname, WPPath );
	url.search = query;

	return url.href;
}

export function isCurrentURL( page, WPPath, query = '' ) {
	const currentURL = new URL( page.url() );

	currentURL.search = query;

	return createURL( WPPath, query ) === currentURL.href;
}

async function loginUser(
	page,
	username = 'admin',
	password = 'password'
) {
	if ( ! isCurrentURL( 'wp-login.php' ) ) {
		const waitForLoginPageNavigation = page.waitForNavigation();
		await page.goto( createURL( 'wp-login.php' ) );
		await waitForLoginPageNavigation;
	}

	await page.focus( '#user_login' );
	await page.type( '#user_login', username );
	await page.focus( '#user_pass' );
	await page.type( '#user_pass', password );

	await Promise.all( [
		page.click( '#wp-submit' ),
		page.waitForNavigation( { waitUntil: 'networkidle' } ),
	] );
}

async function visitAdminPage( page, adminPath, query ) {
	await page.goto( createURL( join( 'wp-admin', adminPath ), query ) );

	// Handle upgrade required screen.
	if ( isCurrentURL( 'wp-admin/upgrade.php' ) ) {
		// Click update.
		await page.click( '.button.button-large.button-primary' );
		// Click continue.
		await page.click( '.button.button-large' );
	}

	if ( isCurrentURL( 'wp-login.php' ) ) {
		await loginUser();
		await visitAdminPage( adminPath, query );
	}

	const error = await getPageError();
	if ( error ) {
		throw new Error( 'Unexpected error in page content: ' + error );
	}
}


/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
	test( 'Is it network related?', async ( { page, admin } ) => {
		await page.goto( '/wp-login.php?reauth=1', { waitUntil: 'networkidle' } );
		await admin.visitAdminPage( '/' );

		expect( isCurrentURL( page, '/wp-admin/' ) ).toBe( true );
		expect( page.getByText( 'Dashboard' ) ).toBeVisible();
	} );
