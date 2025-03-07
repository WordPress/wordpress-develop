
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

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { env } from 'process';

	test( 'currenet url should work', async ( { page, admin } ) => {
		await page.goto( '/wp-login.php?reauth=1' );

		await page.goto( '/wp-admin/' );

		console.log( page.url() );
		expect( isCurrentURL( page, '/wp-login.php' ) ).toBe( true );
	} );
