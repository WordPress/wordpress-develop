/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const IMPORTER_SLUG = 'wordpress-importer';
const IMPORTER_NAME = 'WordPress';

/**
 * Deletes the WordPress importer plugin if it is installed.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils REST helpers.
 */
async function deleteWordPressImporter( requestUtils ) {
	const plugins = await requestUtils.rest( { path: '/wp/v2/plugins' } );
	const installed = plugins.find( ( item ) =>
		item.plugin.startsWith( `${ IMPORTER_SLUG }/` )
	);
	if ( ! installed ) {
		return;
	}
	if ( installed.status !== 'inactive' ) {
		await requestUtils.rest( {
			method: 'PUT',
			path: `/wp/v2/plugins/${ installed.plugin }`,
			data: { status: 'inactive' },
		} );
	}
	await requestUtils.rest( {
		method: 'DELETE',
		path: `/wp/v2/plugins/${ installed.plugin }`,
	} );
}

test.describe( 'Import', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await deleteWordPressImporter( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await deleteWordPressImporter( requestUtils );
	} );

	test( 'lists popular importers with Install Now actions', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'import.php' );

		await expect(
			page.getByRole( 'heading', { name: 'Import', exact: true } )
		).toBeVisible();
		await expect( page.locator( 'table.importers' ) ).toBeVisible();
		await expect(
			page.locator( '.importer-title', { hasText: IMPORTER_NAME } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', {
				name: `Install ${ IMPORTER_NAME } now`,
				exact: true,
			} )
		).toBeVisible();
	} );

	test( 'installs the WordPress importer plugin from the Import screen', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Installing from wordpress.org can take longer than the default timeout.
		test.setTimeout( 300_000 );

		await admin.visitAdminPage( 'import.php' );
		await page
			.getByRole( 'link', {
				name: `Install ${ IMPORTER_NAME } now`,
				exact: true,
			} )
			.click();

		await expect(
			page.getByRole( 'link', {
				name: `Run ${ IMPORTER_NAME }`,
				exact: true,
			} )
		).toBeVisible( { timeout: 180_000 } );

		const plugins = await requestUtils.rest( { path: '/wp/v2/plugins' } );
		expect(
			plugins.some( ( item ) =>
				item.plugin.startsWith( `${ IMPORTER_SLUG }/` )
			)
		).toBe( true );
	} );
} );
