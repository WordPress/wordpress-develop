/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Profile update', () => {
	test( 'announces a successful update', async ( { admin, page } ) => {
		await admin.visitAdminPage( '/profile.php' );

		await page.getByRole( 'button', { name: 'Update Profile' } ).click();

		await expect( page.getByRole( 'status' ) ).toContainText(
			'Profile updated.'
		);
	} );
} );
