/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	deleteTheme,
	themeName,
	themeSlug,
	uploadTheme,
} from '../utils/themes';

test.describe( 'Theme activation', () => {
	let previousTheme;

	test.beforeEach( async ( { admin, page, requestUtils } ) => {
		previousTheme = undefined;
		// The fixture slug requires an environment not shared with another test run.
		// An interrupted run can leave the fixture theme installed.
		await deleteTheme( requestUtils );
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		expect( themes.map( ( theme ) => theme.stylesheet ) ).not.toContain(
			themeSlug
		);
		previousTheme = themes.find(
			( theme ) => theme.status === 'active'
		).stylesheet;
		await uploadTheme( admin, page, 'e2e-test-theme.zip' );
		await expect(
			page.getByText( 'Theme installed successfully.', { exact: true } )
		).toBeVisible();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! previousTheme ) {
			return;
		}
		await requestUtils.activateTheme( previousTheme );
		await deleteTheme( requestUtils );
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
		await page
			.getByRole( 'button', {
				name: `View Theme Details for ${ themeName }`,
				exact: true,
			} )
			.hover();
		await page
			.getByRole( 'link', {
				name: `Activate ${ themeName }`,
				exact: true,
			} )
			.click();
		await expect(
			page.getByRole( 'heading', {
				name: `Active: ${ themeName }`,
				exact: true,
			} )
		).toBeVisible();
		await page.reload();
		await expect(
			page.getByRole( 'heading', {
				name: `Active: ${ themeName }`,
				exact: true,
			} )
		).toBeVisible();
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		expect(
			themes.find( ( theme ) => theme.status === 'active' ).stylesheet
		).toBe( themeSlug );
	} );
} );
