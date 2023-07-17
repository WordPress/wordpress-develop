import {
	visitAdminPage,
} from '@wordpress/e2e-test-utils';

describe( 'Settings -> General', () => {
	const invalidTimezones = [ '', '0', 'Barry/Gary' ];

	invalidTimezones.forEach( invalidTimezone => {
		it( `Does not allow saving an invalid timezone string with "${invalidTimezone}"`, async () => {
			await visitAdminPage( '/options-general.php' );

			// Set the timezone to a valid value.
			let timezoneField = await page.waitForSelector( '#timezone_string' );
			await timezoneField.evaluate( timezone => timezone.value = 'Europe/Lisbon' );

			// Save changes.
			let submitButton = await page.waitForSelector( '#submit' );
			await Promise.all([
				submitButton.click(),
				page.waitForNavigation(),
			]);

			// Set the timezone to an invalid value.
			timezoneField = await page.waitForSelector( '#timezone_string' );
			await page.evaluate( ( timezone, invalidTimezone ) => {
					timezone.options[0].value = invalidTimezone;
					timezone.value = invalidTimezone;
				},
				timezoneField,
				invalidTimezone
			);

			// Save changes.
			submitButton = await page.waitForSelector( '#submit' );
			await Promise.all([
				submitButton.click(),
				page.waitForNavigation(),
			]);

			timezoneField = await page.waitForSelector( '#timezone_string' );
			expect( await timezoneField.evaluate( timezone => timezone.value ) ).toBe( 'Europe/Lisbon' );
		} );
	} );
} );
