/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Manage Themes', () => {
	const themeSlug = 'hello-elementor';
	const fallbackThemeSlug = 'twentytwentyone';

	test.afterAll( async ( { requestUtils } ) => {
		// Activate a fallback theme after all tests
		await requestUtils.activateTheme( fallbackThemeSlug );
	} );

	test( 'installs a theme', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'theme-install.php' ); // Navigate to theme install page
		await page.fill( '#wp-filter-search-input', themeSlug ); // Search for the theme
		await page.hover( `.theme[data-slug="${ themeSlug }"]` );
		await page.click(
			`.theme[data-slug="${ themeSlug }"] a.button-primary.theme-install`
		);
		await page.waitForSelector(
			`.theme[data-slug="${ themeSlug }"] a.button-primary.theme-install.updating-message`,
			{ state: 'detached' }
		);
		// Locate the notice element
		const successNotice = page.locator(
			`.theme[data-slug="${ themeSlug }"] .notice.notice-success.notice-alt p`
		);

		// Assert the text content is as expected
		await expect( successNotice ).toHaveText( 'Installed' );
	} );

	test( 'activates a theme', async ( { page, admin } ) => {
		await admin.visitAdminPage( 'themes.php' ); // Navigate to themes page
		await page.click( `.theme[data-slug="${ themeSlug }"] .activate` );
	} );

	test( 'deletes an inactive theme', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Activate a fallback theme before deleting the current one
		await requestUtils.activateTheme( fallbackThemeSlug );

		await admin.visitAdminPage( 'themes.php' ); // Navigate to themes page

		// Confirm deletion dialog
		page.on( 'dialog', async ( dialog ) => {
			await dialog.accept();
		} );

		// Open the theme details for the theme to delete
		await page.click( `.theme[data-slug="${ themeSlug }"]` );
		await page.click( '.delete-theme' );

		// Verify theme is deleted
		await expect(
			page.locator( `.theme[data-slug="${ themeSlug }"]` )
		).not.toBeVisible();
		console.log( `Theme "${ themeSlug }" deleted successfully.` );
	} );
} );
