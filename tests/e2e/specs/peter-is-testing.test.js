
/**
 * External dependencies
 */
import { join } from 'path';

const { PLAYWRIGHT_TEST_BASE_URL } = process.env;


/**
 * Regular expression matching a displayed PHP error within a markup string.
 *
 * @see https://github.com/php/php-src/blob/598175e/main/main.c#L1257-L1297
 *
 * @type {RegExp}
 */
const REGEXP_PHP_ERROR =
	/(<b>)?(Fatal error|Recoverable fatal error|Warning|Parse error|Notice|Strict Standards|Deprecated|Unknown error)(<\/b>)?: (.*?) in (.*?) on line (<b>)?\d+(<\/b>)?/;

/**
 * Returns a promise resolving to one of either a string or null. A string will
 * be resolved if an error message is present in the contents of the page. If no
 * error is present, a null value will be resolved instead. This requires the
 * environment be configured to display errors.
 *
 * @see http://php.net/manual/en/function.error-reporting.php
 *
 * @return {Promise<?string>} Promise resolving to a string or null, depending
 *                            whether a page error is present.
 */
export async function getPageError( page ) {
	const content = await page.content();
	const match = content.match( REGEXP_PHP_ERROR );
	return match ? match[ 0 ] : null;
}

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
	if ( ! isCurrentURL( page, 'wp-login.php' ) ) {
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
		page.waitForNavigation( { waitUntil: 'networkidle0' } ),
	] );
}

async function visitAdminPage( page, adminPath, query ) {
	await page.goto( createURL( join( 'wp-admin', adminPath ), query ) );

	// Handle upgrade required screen.
	if ( isCurrentURL( page, 'wp-admin/upgrade.php' ) ) {
		// Click update.
		await page.click( '.button.button-large.button-primary' );
		// Click continue.
		await page.click( '.button.button-large' );
	}

	if ( isCurrentURL( page, 'wp-login.php' ) ) {
		await loginUser( page );
		await visitAdminPage( page, adminPath, query );
	}

	const error = await getPageError( page );
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
		await visitAdminPage( page, '/' );

		expect( isCurrentURL( page, '/wp-admin/' ) ).toBe( true );
		expect(page.locator('body')).toContainText('Dashboard');
	} );
