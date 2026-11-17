import { test, expect } from '../fixtures';
import { GENERAL_SETTINGS } from '../utils/locks';

test.describe( 'Settings -> General', { lock: GENERAL_SETTINGS }, () => {
	const invalidTimezones = [ '', '0', 'Barry/Gary' ];

	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( '/options-general.php' );
	} );

	for ( const invalidTimezone of invalidTimezones ) {
		test( `Does not allow saving an invalid timezone string with "${invalidTimezone}"`, async ( { admin, page, siteOptions } ) => {
			const originalOptions = await siteOptions.remember( [ 'timezone_string', 'gmt_offset' ] );

			const validTimezone = originalOptions.timezone_string === 'Europe/Lisbon' ? 'Africa/Libreville' : 'Europe/Lisbon';

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
