/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const themeSlug = 'e2e-test-theme';
const themeName = 'E2E Test Theme';

async function deleteTheme( requestUtils ) {
	const themes = await requestUtils.rest( { path: '/wp/v2/themes' } );
	if ( ! themes.some( ( theme ) => theme.stylesheet === themeSlug ) ) {
		return;
	}
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

test.describe( 'Theme management', () => {
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
