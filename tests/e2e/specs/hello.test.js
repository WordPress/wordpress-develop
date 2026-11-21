/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Hello World', () => {
	test( 'Should load properly', async ( { admin, page }) => {
		await admin.visitAdminPage( '/' );
		await expect(
			page.getByRole('heading', { name: 'Welcome to WordPress', level: 2 })
		).toBeVisible();
	} );
} );
