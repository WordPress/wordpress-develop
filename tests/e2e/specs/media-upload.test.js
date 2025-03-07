/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test('Test dismissing failed upload works correctly', async ({ page }) => {
	await admin.visitAdminPage( '/media-new.php' );
	await page.getByRole('link', { name: 'Add New Media File' }).click();
	await page.getByRole('button', { name: 'Select Files' }).click();
	await page.getByRole('button', { name: 'Select Files' }).setInputFiles('assets/sample.jfif');
	await expect(
		await page.getByText('”Dismiss sample.jfif” has failed to upload.Sorry, you are not allowed')
	);
	await page.getByRole('button', { name: 'Dismiss' }).click();
	await page.locator('#wpbody-content').click();
	expect(await page.getByText('”Dismiss sample.jfif” has failed to upload.Sorry, you are not allowed').count()).toEqual(0);
});

