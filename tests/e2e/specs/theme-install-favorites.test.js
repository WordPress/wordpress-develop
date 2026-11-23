/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const ALICE_THEME_NAME = 'Alice Favorite Theme';

const aliceTheme = {
	name: ALICE_THEME_NAME,
	slug: 'alice-favorite-theme',
	version: '1.0.0',
	preview_url: 'https://wp-themes.com/alice-favorite-theme/',
	author: { user_nicename: 'alice', display_name: 'Alice' },
	screenshot_url: '//ts.w.org/wp-content/themes/alice-favorite-theme/screenshot.png',
	rating: 100,
	num_ratings: 10,
	homepage: 'https://wordpress.org/themes/alice-favorite-theme/',
	description: 'A theme favorited by Alice.',
	installed: false,
	compatible_wp: true,
	compatible_php: true,
	requires: false,
	requires_php: false,
};

function queryThemesResponse( themes ) {
	return {
		success: true,
		data: {
			info: { page: 1, pages: 1, results: themes.length },
			themes,
		},
	};
}

// Alice is the saved username, so a favorites request without `user` is hers too.
function respondToThemesQuery( params ) {
	const isFavorites = params.get( 'request[browse]' ) === 'favorites';
	const user = params.get( 'request[user]' );

	if ( isFavorites && ( user === null || user === 'alice' ) ) {
		return queryThemesResponse( [ aliceTheme ] );
	}

	return queryThemesResponse( [] );
}

test.describe( 'Add Themes: Favorites tab', () => {
	test.beforeEach( async ( { page } ) => {
		await page.route( '**/wp-admin/admin-ajax.php', async ( route ) => {
			const params = new URLSearchParams( route.request().postData() );
			const action = params.get( 'action' );

			if ( action === 'query-themes' ) {
				return route.fulfill( { json: respondToThemesQuery( params ) } );
			}

			if ( action === 'save-wporg-username' ) {
				return route.fulfill( { json: { success: true, data: true } } );
			}

			return route.continue();
		} );
	} );

	async function submitUsername( page, username ) {
		await page.getByLabel( 'Your WordPress.org username:' ).fill( username );
		await page.getByRole( 'button', { name: 'Get Favorites' } ).click();
	}

	async function switchToBlockThemesAndBack( page ) {
		await page.getByRole( 'link', { name: 'Block Themes' } ).click();
		await page.getByRole( 'link', { name: 'Favorites' } ).click();
	}

	test( "shows the current username's favorites after switching tabs", async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'theme-install.php', 'browse=favorites' );
		await expect( page.getByText( ALICE_THEME_NAME ).first() ).toBeVisible();

		await submitUsername( page, 'bob' );
		await expect( page.locator( 'p.no-themes' ) ).toBeVisible();

		await switchToBlockThemesAndBack( page );

		await expect( page.getByText( ALICE_THEME_NAME ) ).toHaveCount( 0 );
		await expect( page.locator( 'p.no-themes' ) ).toBeVisible();
	} );

	test( "shows an earlier username's favorites again after re-submitting it", async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'theme-install.php', 'browse=favorites' );
		await expect( page.getByText( ALICE_THEME_NAME ).first() ).toBeVisible();

		await submitUsername( page, 'bob' );
		await expect( page.locator( 'p.no-themes' ) ).toBeVisible();

		await submitUsername( page, 'alice' );
		await switchToBlockThemesAndBack( page );

		await expect( page.getByText( ALICE_THEME_NAME ).first() ).toBeVisible();
	} );
} );
