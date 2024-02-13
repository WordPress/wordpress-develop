import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Settings -> General', () => {
	const invalidTimezones = [ '', '0', 'Barry/Gary' ];

	for ( const invalidTimezone of invalidTimezones ) {
		test( `Does not allow saving an invalid timezone string with "${invalidTimezone}"`, async ( { admin, page } ) => {
			await admin.visitAdminPage( '/options-general.php' );

			// Set the timezone to a valid value.
			await page.locator( '#timezone_string' ).evaluate( timezone => timezone.value = 'Europe/Lisbon' );

			// Save changes.
			await page.locator( '#submit' ).click();

			// Set the timezone to an invalid value.
			await page.locator( '#timezone_string' ).evaluate( ( timezone, invalidTimezone ) => {
				timezone.options[0].value = invalidTimezone;
				timezone.value = invalidTimezone;
			}, invalidTimezone );

			// Save changes.
			await page.locator( '#submit' ).click();

			await expect( page.locator( '#timezone_string' ) ).toHaveValue( 'Europe/Lisbon' );
		} );
	}
} );
