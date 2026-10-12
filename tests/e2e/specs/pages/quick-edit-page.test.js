/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Quick Edit page', () => {
	let pageId;

	test.beforeEach( () => {
		pageId = undefined;
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( pageId ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ pageId }`,
				params: { force: true },
			} );
		}
	} );

	test( 'saves a page title using Quick Edit', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const createdPage = await requestUtils.createPage( {
			title: 'Quick Edit page',
			status: 'publish',
		} );
		pageId = createdPage.id;
		const updatedTitle = `Updated page ${ pageId }`;

		await admin.visitAdminPage( 'edit.php?post_type=page' );
		const row = page.locator( `#post-${ pageId }` );
		await row
			.getByRole( 'link', { name: 'Quick Edit page', exact: true } )
			.hover();
		await row
			.getByRole( 'button', {
				name: 'Quick edit “Quick Edit page” inline',
				exact: true,
			} )
			.click();

		const editRow = page.locator( `#edit-${ pageId }` );
		const title = editRow.getByRole( 'textbox', {
			name: 'Title',
			exact: true,
		} );
		await expect( title ).toHaveValue( 'Quick Edit page' );
		await title.fill( updatedTitle );
		await editRow
			.getByRole( 'button', { name: 'Update', exact: true } )
			.click();
		await expect(
			row.getByRole( 'link', { name: updatedTitle, exact: true } )
		).toBeVisible();

		await page.reload();
		await expect(
			row.getByRole( 'link', { name: updatedTitle, exact: true } )
		).toBeVisible();
	} );
} );
