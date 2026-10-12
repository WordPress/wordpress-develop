/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'General Settings', () => {
	let originalTimezone;

	test.beforeEach( async ( { admin, page } ) => {
		originalTimezone = undefined;
		await admin.visitAdminPage( 'options-general.php' );
		originalTimezone = await page
			.getByLabel( 'Timezone', { exact: true } )
			.inputValue();
	} );

	test.afterEach( async ( { admin, page } ) => {
		if ( originalTimezone === undefined ) {
			return;
		}

		// Start from a fresh form, even if the test failed on another screen.
		await admin.visitAdminPage( 'options-general.php' );
		await page
			.getByLabel( 'Timezone', { exact: true } )
			.selectOption( originalTimezone );
		await page
			.getByRole( 'button', { name: 'Save Changes', exact: true } )
			.click();
		await expect(
			page.locator( '#setting-error-settings_updated' )
		).toContainText( 'Settings saved.' );

		// Restoring through the form also preserves UTC-offset selections.
		await admin.visitAdminPage( 'options-general.php' );
		await expect(
			page.getByLabel( 'Timezone', { exact: true } )
		).toHaveValue( originalTimezone );
	} );

	test( 'changes timezone and preserves it after reloading', async ( {
		admin,
		page,
	} ) => {
		const targetTimezone =
			originalTimezone === 'Africa/Libreville'
				? 'Europe/Lisbon'
				: 'Africa/Libreville';
		expect( targetTimezone ).not.toBe( originalTimezone );

		await page
			.getByLabel( 'Timezone', { exact: true } )
			.selectOption( targetTimezone );
		await page
			.getByRole( 'button', { name: 'Save Changes', exact: true } )
			.click();
		await expect(
			page.locator( '#setting-error-settings_updated' )
		).toContainText( 'Settings saved.' );

		await admin.visitAdminPage( 'options-general.php' );
		await expect(
			page.getByLabel( 'Timezone', { exact: true } )
		).toHaveValue( targetTimezone );
	} );
} );
