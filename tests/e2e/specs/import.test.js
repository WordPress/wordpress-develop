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

	test( 'lists popular importers with Install Now actions', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'import.php' );

		await expect(
			page.getByRole( 'heading', { name: 'Import', exact: true } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', {
				name: `Install ${ IMPORTER_NAME } now`,
				exact: true,
			} )
		).toBeVisible();
	} );
} );
