/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import path from 'path';

test( 'Test dismissing failed upload works correctly', async ({ page, admin, requestUtils }) => {
	// Log in before visiting admin page.
	await requestUtils.login();
	await admin.visitAdminPage( '/media-new.php' );

	// It takes a moment for the multi-file uploader to become available.
	await page.waitForLoadState('load');

	const testImagePath = path.join(__dirname, '../assets/sample.svg');

	// Upload a file that will fail.
	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await input.setInputFiles( testImagePath );

	// Ensure the error message is visible.
	await expect(
		page.getByText('“sample.svg” has failed to upload.')
	).toBeVisible();

	// Ensure the error message is dismissed.
	await page.getByRole('button', { name: 'Dismiss' }).click();
	await expect(
		page.getByText('“sample.svg” has failed to upload.')
	).not.toBeVisible();
} );
