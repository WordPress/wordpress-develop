/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Command Palette', () => {
	test( 'is loaded on demand and opens with the keyboard shortcut on a classic screen', async ( {
		admin,
		page,
		pageUtils,
	} ) => {
		await admin.visitAdminPage( '/options-general.php' );

		await expect( page.locator( 'script#wp-core-commands-js' ) ).toHaveCount( 0 );
		await expect( page.locator( 'script#react-js' ) ).toHaveCount( 0 );

		await pageUtils.pressKeys( 'primary+k' );

		const palette = page.getByRole( 'dialog', { name: 'Command palette' } );
		await expect( palette ).toBeVisible();
		await expect( page.locator( 'script#wp-core-commands-js' ) ).toHaveCount( 1 );

		await palette.getByRole( 'combobox' ).fill( 'Permalinks' );
		await palette
			.getByRole( 'option', { name: /Settings > Permalinks/ } )
			.click();
		await expect( page ).toHaveURL( /\/wp-admin\/options-permalink\.php/ );
	} );

	test( 'opens again after it has been loaded', async ( {
		admin,
		page,
		pageUtils,
	} ) => {
		await admin.visitAdminPage( '/options-general.php' );

		const palette = page.getByRole( 'dialog', { name: 'Command palette' } );

		await pageUtils.pressKeys( 'primary+k' );
		await expect( palette ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( palette ).toBeHidden();

		await pageUtils.pressKeys( 'primary+k' );
		await expect( palette ).toBeVisible();
	} );

	test( 'opens from the toolbar button on a classic screen', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/edit.php' );

		await page.locator( '#wp-admin-bar-command-palette > .ab-item' ).click();

		await expect(
			page.getByRole( 'dialog', { name: 'Command palette' } )
		).toBeVisible();
	} );

	test( 'is loaded up front in the block editor', async ( {
		admin,
		page,
		pageUtils,
	} ) => {
		await admin.createNewPost();

		await expect( page.locator( 'script#wp-core-commands-js' ) ).toHaveCount( 1 );
		await expect( page.locator( '#wp-command-palette-assets' ) ).toHaveCount( 0 );

		await pageUtils.pressKeys( 'primary+k' );

		await expect(
			page.getByRole( 'dialog', { name: 'Command palette' } )
		).toBeVisible();
	} );
} );
