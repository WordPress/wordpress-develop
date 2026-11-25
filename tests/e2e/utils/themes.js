/**
 * WordPress dependencies
 */
import { expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * The fixture theme in `tests/e2e/assets/e2e-test-theme*.zip`.
 */
export const themeSlug = 'e2e-test-theme';
export const themeName = 'E2E Test Theme';

export async function uploadTheme( admin, page, fixture ) {
	await admin.visitAdminPage( 'theme-install.php' );
	await page
		.getByRole( 'button', { name: 'Upload Theme', exact: true } )
		.click();
	await page
		.locator( '#themezip' )
		.setInputFiles( `tests/e2e/assets/${ fixture }` );
	await page
		.getByRole( 'button', { name: 'Install Now', exact: true } )
		.click();
}

export async function deleteTheme( requestUtils ) {
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
