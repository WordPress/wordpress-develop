/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * The classic menus admin screen (nav-menus.php). Relies on Twenty
 * Twenty-One being the active theme, which registers the "Primary menu"
 * and "Secondary menu" locations.
 */
test.describe( 'Classic Menus', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMenus();
	} );

	test( 'creates a menu, adds a custom link, and assigns it to a location', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( '/nav-menus.php' );

		// With no menus, the screen opens directly on the create form.
		const menuNameField = page.getByRole( 'textbox', {
			name: 'Menu Name',
		} );
		await menuNameField.fill( 'E2E Menu' );
		await page
			.getByRole( 'button', { name: 'Create Menu' } )
			.first()
			.click();

		// The screen reloads into the edit state for the new menu.
		await expect(
			page.getByRole( 'textbox', { name: 'Menu Name' } )
		).toHaveValue( 'E2E Menu' );

		// Add a custom link from the accordion.
		await page.getByRole( 'button', { name: 'Custom Links' } ).click();
		await page
			.getByRole( 'textbox', { name: 'URL' } )
			.fill( 'https://wordpress.org/' );
		await page
			.getByRole( 'textbox', { name: 'Link Text' } )
			.fill( 'E2E Custom Link' );
		// Each accordion panel has its own submit; scope to Custom Links.
		await page
			.locator( '#customlinkdiv' )
			.getByRole( 'button', { name: 'Add to Menu' } )
			.click();

		// The item lands in the menu structure.
		await expect(
			page
				.locator( '#menu-to-edit' )
				.getByText( 'E2E Custom Link', { exact: true } )
		).toBeVisible();

		// Assign the menu to the theme's primary location and save.
		await page.getByRole( 'checkbox', { name: 'Primary menu' } ).check();

		// Activate via keyboard: the sticky footer bar can shift on focus
		// and swallow the first pointer click at certain window heights.
		// See https://core.trac.wordpress.org/ticket/65684.
		await page.getByRole( 'button', { name: 'Save Menu' } ).first().focus();
		await page.keyboard.press( 'Enter' );

		// The location assignment persists.
		await expect
			.poll(
				async () => {
					const menus = await requestUtils.rest( {
						path: '/wp/v2/menus',
						params: { context: 'edit' },
					} );
					return menus.find( ( menu ) => menu.name === 'E2E Menu' )
						?.locations;
				},
				{
					message:
						'the menu should be assigned to the primary location',
				}
			)
			.toContain( 'primary' );

		// The menu renders in the site navigation on the front end.
		await page.goto( '/' );
		await expect(
			page
				.getByRole( 'navigation' )
				.getByRole( 'link', { name: 'E2E Custom Link' } )
		).toBeVisible();
	} );

	test( 'deletes a menu', async ( { admin, page, requestUtils } ) => {
		await requestUtils.createClassicMenu( 'Doomed Menu' );

		await admin.visitAdminPage( '/nav-menus.php' );

		// Deleting asks for confirmation.
		page.on( 'dialog', ( dialog ) => dialog.accept() );

		// Activate via keyboard: the sticky footer bar can shift on focus
		// and swallow the first pointer click at certain window heights.
		// See https://core.trac.wordpress.org/ticket/65684.
		await page.getByRole( 'link', { name: 'Delete Menu' } ).focus();
		await page.keyboard.press( 'Enter' );

		// With the only menu gone, the screen returns to the create form.
		await expect(
			page.getByRole( 'textbox', { name: 'Menu Name' } )
		).toHaveValue( '' );
		await expect
			.poll(
				async () => {
					const menus = await requestUtils.rest( {
						path: '/wp/v2/menus',
					} );
					return menus.length;
				},
				{ message: 'no menus should remain' }
			)
			.toBe( 0 );
	} );
} );
