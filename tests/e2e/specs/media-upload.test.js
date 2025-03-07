/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import path from 'path';

test( 'Test dismissing failed upload works correctly', async ({ page, admin }) => {
	await admin.visitAdminPage( '/media-new.php' );

	await page.waitForLoadState('load');

	const testImagePath = path.join(__dirname, '../assets/sample.svg');
	console.log( 'testImagePath', testImagePath );

	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await input.setInputFiles( testImagePath );

	// await page.waitForEvent('networkidle');

	// await dragAndDropFile(page, "#plupload-upload-ui", testImagePath, "sample.svg");

	await expect(
		page.getByText('“sample.svg” has failed to upload.')
	).toBeVisible();


	await page.getByRole('button', { name: 'Dismiss' }).click();

	await expect(
		page.getByText('“sample.svg” has failed to upload.')
	).not.toBeVisible();
} );
