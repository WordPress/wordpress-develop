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
import { UPGRADER } from '../utils/locks';
import {
	deleteTheme,
	themeName,
	themeSlug,
	uploadTheme,
} from '../utils/themes';

test.describe( 'Theme management', { lock: UPGRADER }, () => {
	let previousTheme;

	test.beforeEach( async ( { admin, page, requestUtils } ) => {
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

	test( 'deletes an inactive theme from its details', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( 'themes.php' );
		const details = page.getByRole( 'button', {
			name: `View Theme Details for ${ themeName }`,
			exact: true,
		} );
		await details.click();
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
		await expect( details ).toHaveCount( 0 );
		await page.reload();
		await expect( details ).toHaveCount( 0 );
		const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
		expect( themes.map( ( theme ) => theme.stylesheet ) ).not.toContain(
			themeSlug
		);
		expect(
			themes.find( ( theme ) => theme.status === 'active' ).stylesheet
		).toBe( previousTheme );
	} );

	test( 'replaces an installed theme with an uploaded version', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		expect(
			(
				await requestUtils.rest( {
					path: `/wp/v2/themes/${ themeSlug }`,
				} )
			).version
		).toBe( '1.0' );
		await uploadTheme( admin, page, 'e2e-test-theme-2.0.zip' );
		await expect(
			page.getByRole( 'heading', {
				name: 'This theme is already installed.',
				exact: true,
			} )
		).toBeVisible();
		await expect(
			page.getByRole( 'row', { name: 'Version 1.0 2.0', exact: true } )
		).toBeVisible();
		await page
			.getByRole( 'link', {
				name: 'Replace installed with uploaded',
				exact: true,
			} )
			.click();
		await expect(
			page.getByText( 'Theme updated successfully.', { exact: true } )
		).toBeVisible();
		expect(
			(
				await requestUtils.rest( {
					path: `/wp/v2/themes/${ themeSlug }`,
				} )
			).version
		).toBe( '2.0' );
	} );

	test( 'searches for an installed theme', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'themes.php' );
		const search = page.getByRole( 'searchbox', {
			name: 'Search installed themes',
			exact: true,
		} );
		await expect( search ).toBeVisible();
		const themeDetailsButtons = page.getByRole( 'button', {
			name: /View Theme Details for /,
		} );
		// Confirm search starts from a broader results set before filtering.
		await expect
			.poll( async () => themeDetailsButtons.count() )
			.toBeGreaterThan( 1 );
		await search.fill( themeName );

		// Wait for the debounced filter, then require a single matching result.
		await expect( themeDetailsButtons ).toHaveCount( 1 );
		await expect(
			page.getByRole( 'button', {
				name: `View Theme Details for ${ themeName }`,
				exact: true,
			} )
		).toBeVisible();
	} );

	test( 'shows an empty result when searching for a missing theme', async ( {
		admin,
		page,
	} ) => {
		// Fixture theme from beforeEach keeps the library non-empty so ignoring
		// search cannot vacuously pass.
		const missing = `e2e-missing-theme-${ randomUUID().slice( 0, 8 ) }`;
		await admin.visitAdminPage( 'themes.php' );
		const search = page.getByRole( 'searchbox', {
			name: 'Search installed themes',
			exact: true,
		} );
		await expect( search ).toBeVisible();
		await search.fill( missing );

		// Scope to the empty-state paragraph; the same copy is also spoken via
		// wp.a11y.speak() into a live region.
		const emptyState = page.locator( 'p.no-themes' );
		await expect( emptyState ).toBeVisible();
		await expect( emptyState ).toHaveText(
			'No themes found. Try a different search.'
		);
		await expect(
			page.getByRole( 'button', {
				name: /View Theme Details for /,
			} )
		).toHaveCount( 0 );
	} );
} );
