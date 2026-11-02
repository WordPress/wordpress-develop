import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

function timezoneOptions( baseURL, values ) {
	const restore = values === undefined ? '' : `
		$values = json_decode( base64_decode( '${ Buffer.from( JSON.stringify( values ) ).toString( 'base64' ) }' ), true );
		foreach ( $values as $option => $value ) {
			update_option( $option, $value );
		}
	`;

	return JSON.parse( execFileSync( process.execPath, [
		join( process.cwd(), 'tools/local-env/scripts/docker.js' ),
		'exec', '--user', 'wp_php', 'cli', 'wp', `--url=${ baseURL }`, 'eval',
		`
			// Read and restore the stored offset, not the value calculated for a named timezone.
			remove_filter( 'pre_option_gmt_offset', 'wp_timezone_override_offset' );
			${ restore }
			echo wp_json_encode( array(
				'timezone_string' => get_option( 'timezone_string' ),
				'gmt_offset' => get_option( 'gmt_offset' ),
			) );
		`,
	], { encoding: 'utf-8', env: { ...process.env, DOTENV_CONFIG_QUIET: 'true' } } ) );
}

test.describe( 'Settings -> General', () => {
	const invalidTimezones = [ '', '0', 'Barry/Gary' ];
	let originalOptions;

	test.beforeEach( async ( { admin, baseURL } ) => {
		originalOptions = undefined;
		originalOptions = timezoneOptions( baseURL );
		await admin.visitAdminPage( '/options-general.php' );
	} );

	test.afterEach( async ( { baseURL } ) => {
		if ( originalOptions === undefined ) {
			return;
		}

		// The settings form can normalize stored UTC offsets, so restore the options directly.
		expect( timezoneOptions( baseURL, originalOptions ) ).toEqual( originalOptions );
	} );

	for ( const invalidTimezone of invalidTimezones ) {
		test( `Does not allow saving an invalid timezone string with "${invalidTimezone}"`, async ( { admin, page } ) => {
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
