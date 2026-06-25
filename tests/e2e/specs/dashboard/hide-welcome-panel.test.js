/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Dashboard Welcome Panel', () => {
	test.beforeEach( async ( { page, admin } ) => {
		await admin.visitAdminPage( '/index.php' );

		// Check if the Welcome Panel is visible
		const welcomePanel = page.locator( '#welcome-panel' );
		if ( ! ( await welcomePanel.isVisible() ) ) {
			// Open the "Screen Options" tab
			const screenOptionsTab = page.locator( '#show-settings-link' );
			await screenOptionsTab.click();

			// Check the "Welcome Panel" option if it exists
			const welcomePanelCheckbox = page.locator(
				'#wp_welcome_panel-hide'
			);
		
			if ( await welcomePanelCheckbox.isChecked() ) {
				// If already checked, just reload the page
				await page.reload();
			} else {
				// Check the box to make the Welcome Panel visible
				await welcomePanelCheckbox.check();
				// Close the "Screen Options" tab
				await screenOptionsTab.click();
				// Refresh the page to reflect changes
				await page.reload();
			}
		}
	} );

	test( 'hides the welcome panel using the dismiss button', async ( {
		page,
	} ) => {
		// Check if the Welcome Panel is visible
		const welcomePanel = page.locator( '#welcome-panel' );

		// Click the dismiss button on the Welcome Panel
		await page.locator( '#welcome-panel .welcome-panel-close' ).click();

		// Verify that the Welcome Panel is no longer visible
		await expect( welcomePanel ).not.toBeVisible();

	} );
} );
