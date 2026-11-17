import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { GENERAL_SETTINGS } from '../utils/locks';

test.describe( 'Settings -> General', { lock: GENERAL_SETTINGS }, () => {
	test( 'Saving General Settings preserves the active theme options', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'options.php' );
		const stylesheet = await page.locator( '#stylesheet' ).inputValue();
		const template = await page.locator( '#template' ).inputValue();
		expect( stylesheet ).not.toBe( '' );
		expect( template ).not.toBe( '' );

		await admin.visitAdminPage( 'options-general.php' );
		const tagline = page.getByLabel( 'Tagline', { exact: true } );
		const originalTagline = await tagline.inputValue();
		const updatedTagline = (
			originalTagline + ' Updated in General Settings.'
		).trimStart();

		try {
			await tagline.fill( updatedTagline );
			await page.getByRole( 'button', { name: 'Save Changes' } ).click();
			await expect(
				page.locator( '#setting-error-settings_updated' )
			).toContainText( 'Settings saved.' );
			await expect( tagline ).toHaveValue( updatedTagline );

			await admin.visitAdminPage( 'options.php' );
			await expect( page.locator( '#stylesheet' ) ).toHaveValue( stylesheet );
			await expect( page.locator( '#template' ) ).toHaveValue( template );
		} finally {
			await requestUtils.updateSiteSettings( {
				description: originalTagline,
			} );
		}
	} );
} );

