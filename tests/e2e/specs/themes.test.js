/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const themeSlug = 'e2e-test-theme';
const themeName = 'E2E Test Theme';

test.describe( 'Theme management', () => {
	let previousTheme;
	let ownsTheme;

	test.beforeEach( async ( { admin, page, requestUtils } ) => {
		// The fixture slug requires an environment not shared with another test run.
		ownsTheme = false;
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		expect( themes.map( ( theme ) => theme.stylesheet ) ).not.toContain(
			themeSlug
		);
		previousTheme = themes.find(
			( theme ) => theme.status === 'active'
		).stylesheet;
		ownsTheme = true;
		await admin.visitAdminPage( 'theme-install.php' );
		await page
			.getByRole( 'button', { name: 'Upload Theme', exact: true } )
			.click();
		await page
			.locator( '#themezip' )
			.setInputFiles( 'tests/e2e/assets/e2e-test-theme.zip' );
		await page
			.getByRole( 'button', { name: 'Install Now', exact: true } )
			.click();
		await expect(
			page.getByText( 'Theme installed successfully.', { exact: true } )
		).toBeVisible();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! ownsTheme ) {
			return;
		}
		await requestUtils.activateTheme( previousTheme );
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		if ( themes.some( ( theme ) => theme.stylesheet === themeSlug ) ) {
			const themesResponse = await requestUtils.request.get(
				'wp-admin/themes.php'
			);
			const html = await themesResponse.text();
			await themesResponse.dispose();
			const deleteQuery = html.match(
				`action=delete&amp;stylesheet=${ themeSlug }&amp;_wpnonce=[a-z0-9]+`
			);
			expect( deleteQuery ).not.toBeNull();
			const response = await requestUtils.request.get(
				`wp-admin/themes.php?${ deleteQuery[ 0 ].replace( /&amp;/g, '&' ) }`
			);
			expect( response.ok() ).toBe( true );
			await response.dispose();
		}
		const remainingThemes = await requestUtils.rest( {
			path: '/wp/v2/themes',
		} );
		expect(
			remainingThemes.map( ( theme ) => theme.stylesheet )
		).not.toContain( themeSlug );
		expect(
			remainingThemes.find( ( theme ) => theme.status === 'active' )
				.stylesheet
		).toBe( previousTheme );
	} );

	test( 'activates an installed theme from its card', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'themes.php' );
		const card = page.locator( `.theme[data-slug="${ themeSlug }"]` );
		await card.hover();
		await card
			.getByRole( 'link', {
				name: `Activate ${ themeName }`,
				exact: true,
			} )
			.click();
		await expect(
			card.getByRole( 'heading', {
				name: `Active: ${ themeName }`,
				exact: true,
			} )
		).toBeVisible();
		await page.reload();
		await expect(
			card.getByRole( 'heading', {
				name: `Active: ${ themeName }`,
				exact: true,
			} )
		).toBeVisible();
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		expect(
			themes.find( ( theme ) => theme.status === 'active' ).stylesheet
		).toBe( themeSlug );
	} );

	test( 'deletes an inactive theme from its details', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'themes.php' );
		const card = page.locator( `.theme[data-slug="${ themeSlug }"]` );
		await card.click();
		await Promise.all( [
			page.waitForEvent( 'dialog' ).then( async ( dialog ) => {
				expect( dialog.type() ).toBe( 'confirm' );
				await dialog.accept();
			} ),
			page
				.getByRole( 'link', {
					name: `Delete ${ themeName }`,
					exact: true,
				} )
				.click(),
		] );
		await expect( card ).toHaveCount( 0 );
		await page.reload();
		await expect( card ).toHaveCount( 0 );
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		expect( themes.map( ( theme ) => theme.stylesheet ) ).not.toContain(
			themeSlug
		);
		expect(
			themes.find( ( theme ) => theme.status === 'active' ).stylesheet
		).toBe( previousTheme );
	} );
} );
