import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Settings -> General', () => {
	const invalidTimezones = [ '', '0', 'Barry/Gary' ];
	let originalTimezone;

	test.beforeEach( async ( { admin, page } ) => {
		originalTimezone = undefined;
		await admin.visitAdminPage( '/options-general.php' );
		originalTimezone = await page.locator( '#timezone_string' ).inputValue();
	} );

	test.afterEach( async ( { admin, page } ) => {
		if ( originalTimezone === undefined ) {
			return;
		}

		// Restore through a fresh form, including numeric UTC-offset selections.
		await admin.visitAdminPage( '/options-general.php' );
		await page.locator( '#timezone_string' ).selectOption( originalTimezone );
		await page.locator( '#submit' ).click();
		await expect( page.locator( '#setting-error-settings_updated' ) ).toContainText( 'Settings saved.' );

		await admin.visitAdminPage( '/options-general.php' );
		await expect( page.locator( '#timezone_string' ) ).toHaveValue( originalTimezone );
	} );

	for ( const invalidTimezone of invalidTimezones ) {
		test( `Does not allow saving an invalid timezone string with "${invalidTimezone}"`, async ( { admin, page } ) => {
			const validTimezone = originalTimezone === 'Europe/Lisbon' ? 'Africa/Libreville' : 'Europe/Lisbon';

			// Set the timezone to a different valid value.
			await page.locator( '#timezone_string' ).selectOption( validTimezone );

			// Save changes.
			await page.locator( '#submit' ).click();
			await expect( page.locator( '#setting-error-settings_updated' ) ).toContainText( 'Settings saved.' );

			// Set the timezone to an invalid value.
			await page.locator( '#timezone_string' ).evaluate( ( timezone, invalidTimezone ) => {
				timezone.options[0].value = invalidTimezone;
				timezone.value = invalidTimezone;
			}, invalidTimezone );

			// Save changes.
			await page.locator( '#submit' ).click();

			await expect( page.locator( '#setting-error-settings_updated' ) ).toContainText( 'The timezone you have entered is not valid. Please select a valid timezone.' );
			await expect( page.locator( '#timezone_string' ) ).toHaveValue( validTimezone );

			// Verify the saved value on a fresh page, not just the submit response.
			await admin.visitAdminPage( '/options-general.php' );
			await expect( page.locator( '#timezone_string' ) ).toHaveValue( validTimezone );
		} );
	}
} );
