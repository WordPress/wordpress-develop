/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Updates', () => {
	test( 'shows the current version and plugin and theme sections', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'update-core.php' );

		await expect(
			page.getByRole( 'heading', {
				name: 'WordPress Updates',
				exact: true,
			} )
		).toBeVisible();

		const currentVersion = page.locator( '.wp-current-version' );
		await expect( currentVersion ).toContainText( 'Current version:' );
		await expect( currentVersion ).toHaveText(
			/Current version:\s*\d+\.\d+/
		);

		// These headings remain when everything is up to date.
		await expect(
			page.getByRole( 'heading', { name: /^Plugins/ } )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: /^Themes/ } )
		).toBeVisible();
	} );
} );
