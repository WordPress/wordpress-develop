/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Admin Bar', () => {
	test( 'Should show admin bar when user logged in', async ( { page } ) => {
		await page.goto( '/' );

		await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	} );

	test.describe( 'Logged out', () => {
		// Start without cookies; logging out via the UI would destroy the shared admin session.
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'Should not show admin bar when logged out', async ( {
			page,
		} ) => {
			await page.goto( '/' );

			await expect( page.locator( '#wpadminbar' ) ).toHaveCount( 0 );
		} );
	} );
} );
