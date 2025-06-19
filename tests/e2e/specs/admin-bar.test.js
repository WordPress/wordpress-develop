/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Admin Bar', () => {
	test( 'Should show admin bar when user logged in', async ( { admin, page } ) => {
		await admin.visitAdminPage( '/' );

		// Visit the front page while logged in.
		await page.goto( '/' );

		// Check that admin bar exists
		const adminBar = page.locator( '#wpadminbar' );
		await expect( adminBar ).toBeVisible();
	} );

	test( 'Should not show admin bar when logged out', async ( { admin, page } ) => {
		await admin.visitAdminPage( '/' );

		// Visit the front page while logged in.
		await page.goto( '/' );

		// Verify admin bar is visible before logout.
		const adminBarBeforeLogout = page.locator( '#wpadminbar' );
		await expect( adminBarBeforeLogout ).toBeVisible();

		// Logout.
		await page.hover( '#wp-admin-bar-my-account' );
		await page.locator( '#wp-admin-bar-logout' ).waitFor({ state: 'visible' });
		await page.locator( '#wp-admin-bar-logout a' ).click();

		// After logout, check that admin bar doesn't exist.
		const adminBar = page.locator( '#wpadminbar' );
		await expect( adminBar ).not.toBeVisible();
	} );
} );
