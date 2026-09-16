/**
 * E2E tests for the "(Language default)" label on Settings > General.
 *
 * @ticket 64102
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Settings -> General: Date/Time format language default label', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( '/options-general.php' );
	} );

	test( 'Shows "(Language default)" label next to exactly one date format option', async ( { page } ) => {
		const labels = page.locator( 'input[name="date_format"] ~ .description' );
		await expect( labels.filter( { hasText: 'Language default' } ) ).toHaveCount( 1 );
	} );

	test( 'Shows "(Language default)" label next to exactly one time format option', async ( { page } ) => {
		const labels = page.locator( 'input[name="time_format"] ~ .description' );
		await expect( labels.filter( { hasText: 'Language default' } ) ).toHaveCount( 1 );
	} );

	test( 'Date format preview text is not affected by the language default label', async ( { page } ) => {
		// Click the first (non-custom) date format radio.
		await page.locator( 'input[name="date_format"]' ).first().click();
		// The .example span should not include the label text.
		await expect( page.locator( 'fieldset:has(input[name="date_format"]) .example' ) ).not.toContainText( 'Language default' );
	} );

	test( 'Time format preview text is not affected by the language default label', async ( { page } ) => {
		await page.locator( 'input[name="time_format"]' ).first().click();
		await expect( page.locator( 'fieldset:has(input[name="time_format"]) .example' ) ).not.toContainText( 'Language default' );
	} );

	test( 'Language default label is not inside the .format-i18n span', async ( { page } ) => {
		const formatI18nSpans = page.locator( '.format-i18n' );
		const count = await formatI18nSpans.count();
		for ( let i = 0; i < count; i++ ) {
			await expect( formatI18nSpans.nth( i ) ).not.toContainText( 'Language default' );
		}
	} );
} );
