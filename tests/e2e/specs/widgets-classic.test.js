/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Classic widgets screen', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		const pluginsMap = await requestUtils.getPluginsMap();
		if ( ! pluginsMap[ 'classic-widgets' ] ) {
			await requestUtils.rest( {
				method: 'POST',
				path: 'wp/v2/plugins?slug=classic-widgets',
			} );
			await requestUtils.getPluginsMap( /* forceRefetch */ true );
		}
		await requestUtils.activatePlugin( 'classic-widgets' );
		await requestUtils.deleteAllWidgets();
		const created = await requestUtils.rest( {
			method: 'POST',
			path: 'wp/v2/widgets',
			data: {
				id_base: 'search',
				sidebar: 'sidebar-1',
				instance: { raw: {} },
			},
		} );
		await requestUtils.rest( {
			method: 'POST',
			path: 'wp/v2/sidebars/sidebar-1',
			data: { widgets: [ created.id ] },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllWidgets();
		await requestUtils.deactivatePlugin( 'classic-widgets' );
	} );

	for ( const [ column, label ] of [
		[ '#widgets-right', '.edit' ],
		[ '#available-widgets', '.add' ],
	] ) {
		test( `${ column } link is only shown on focus`, async ( {
			admin,
			page,
		} ) => {
			await admin.visitAdminPage( 'widgets.php' );

			const link = page
				.locator( `${ column } .widget-control-edit` )
				.first();

			await expect( link ).toHaveClass( /screen-reader-text/ );
			await expect( link ).toHaveAttribute( 'href', /editwidget=/ );
			// boundingBox ignores clip-path, so check the clipping itself.
			await expect( link ).toHaveCSS( 'clip-path', 'inset(50%)' );

			await link.focus();

			await expect( link ).toHaveCSS( 'clip-path', 'none' );
			await expect( link ).not.toHaveCSS( 'box-shadow', 'none' );
			expect( ( await link.boundingBox() ).width ).toBeGreaterThan( 20 );
			expect(
				( await link.locator( label ).boundingBox() ).width
			).toBeGreaterThan( 10 );
		} );
	}

	test( 'links follow a widget added without a reload', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'widgets.php' );
		await page
			.locator( '#available-widgets .widget-top' )
			.filter( { hasText: 'Archives' } )
			.click();
		await page.locator( '.widgets-chooser-add' ).click();

		const added = page.locator( '#widgets-right .widget' ).last();
		await expect( added.locator( 'input.widget-id' ) ).toHaveValue(
			/^archives-\d+$/
		);
		const addedId = await added.locator( 'input.widget-id' ).inputValue();

		const addedLink = added.locator( '.widget-control-edit' );
		await addedLink.focus();
		await expect( addedLink ).toHaveAttribute(
			'href',
			new RegExp( `editwidget=${ addedId }&sidebar=sidebar-1&key=1$` )
		);

		const paletteWidget = page
			.locator( '#available-widgets .widget' )
			.filter( { hasText: 'Archives' } );
		const nextNumber = await paletteWidget
			.locator( 'input.multi_number' )
			.inputValue();
		await paletteWidget.locator( '.widget-control-edit' ).focus();
		await expect(
			paletteWidget.locator( '.widget-control-edit' )
		).toHaveAttribute( 'href', new RegExp( `num=${ nextNumber }&` ) );
	} );
} );
