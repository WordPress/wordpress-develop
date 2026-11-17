/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { NAV_MENU_LOCATIONS } from '../utils/locks';

/**
 * The classic menus admin screen (nav-menus.php). Relies on Twenty
 * Twenty-One being the active theme, which registers the "Primary menu"
 * and "Secondary menu" locations.
 *
 * Assigning a menu to a location and deleting any menu both write the
 * theme's menu locations, so every test holds the lock.
 */
test.describe( 'Classic Menus', { lock: NAV_MENU_LOCATIONS }, () => {
	let prefix;

	test.beforeEach( async () => {
		prefix = `e2e-menu-${ randomUUID() }`;
	} );

	// Deleting a menu also removes it from the theme's menu locations.
	test.afterEach( async ( { requestUtils } ) => {
		const menus = await requestUtils.rest( {
			path: '/wp/v2/menus',
			params: { search: prefix, per_page: 100 },
		} );
		for ( const menu of menus ) {
			if ( menu.name.startsWith( `${ prefix }-` ) ) {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `/wp/v2/menus/${ menu.id }`,
					params: { force: true },
				} );
			}
		}
	} );

	test( 'creates a menu, adds a custom link, and assigns it to a location', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const menuName = `${ prefix }-menu`;
		const linkText = `${ prefix }-link`;

		await admin.visitAdminPage( '/nav-menus.php', 'action=edit&menu=0' );

		const menuNameField = page.getByRole( 'textbox', {
			name: 'Menu Name',
		} );
		await menuNameField.fill( menuName );
		await page.locator( '#save_menu_footer' ).click();

		// The screen reloads into the edit state for the new menu.
		await expect( page ).toHaveURL( /[?&]menu=[1-9]\d*/ );
		await expect(
			page.getByRole( 'textbox', { name: 'Menu Name' } )
		).toHaveValue( menuName );

		// Add a custom link from the accordion.
		await page.getByRole( 'button', { name: 'Custom Links' } ).click();
		await page
			.getByRole( 'textbox', { name: 'URL' } )
			.fill( 'https://wordpress.org/' );
		await page
			.getByRole( 'textbox', { name: 'Link Text' } )
			.fill( linkText );
		// Each accordion panel has its own submit; scope to Custom Links.
		await page
			.locator( '#customlinkdiv' )
			.getByRole( 'button', { name: 'Add to Menu' } )
			.click();

		// The item lands in the menu structure.
		await expect(
			page
				.locator( '#menu-to-edit' )
				.getByText( linkText, { exact: true } )
		).toBeVisible();

		// Assign the menu to the theme's primary location and save.
		const primaryMenu = page.getByRole( 'checkbox', {
			name: 'Primary menu',
		} );
		// Use the keyboard to avoid the focus-induced scroll described in #65684.
		await primaryMenu.focus();
		await page.keyboard.press( 'Space' );
		await expect( primaryMenu ).toBeChecked();

		// Activate via keyboard: the sticky footer bar can shift on focus
		// and swallow the first pointer click at certain window heights.
		// See https://core.trac.wordpress.org/ticket/65684.
		await page.locator( '#save_menu_footer' ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '#message' ) ).toContainText(
			`${ menuName } has been updated.`
		);

		// The location assignment persists.
		await expect
			.poll(
				async () => {
					const menus = await requestUtils.rest( {
						path: '/wp/v2/menus',
						params: { context: 'edit', search: prefix },
					} );
					return menus.find( ( menu ) => menu.name === menuName )
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
				.getByRole( 'link', { name: linkText } )
		).toBeVisible();
	} );

	test( 'deletes a menu', async ( { admin, page, requestUtils } ) => {
		const menu = await requestUtils.createClassicMenu( `${ prefix }-menu` );

		await admin.visitAdminPage(
			'/nav-menus.php',
			`action=edit&menu=${ menu.id }`
		);

		// Deleting asks for confirmation.
		page.on( 'dialog', ( dialog ) => dialog.accept() );

		// Activate via keyboard: the sticky footer bar can shift on focus
		// and swallow the first pointer click at certain window heights.
		// See https://core.trac.wordpress.org/ticket/65684.
		await page.getByRole( 'link', { name: 'Delete Menu' } ).focus();
		await page.keyboard.press( 'Enter' );

		// The screen opens any other menu on the site or, with no menus left,
		// the create form, so check only that it no longer shows this menu.
		await expect(
			page.getByRole( 'textbox', { name: 'Menu Name' } )
		).not.toHaveValue( menu.name );
		await expect
			.poll(
				async () => {
					const menus = await requestUtils.rest( {
						path: '/wp/v2/menus',
						params: { search: prefix },
					} );
					return menus.length;
				},
				{ message: 'the menu should be gone' }
			)
			.toBe( 0 );
	} );
} );
