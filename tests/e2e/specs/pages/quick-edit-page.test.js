/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Quick Edit page', () => {
	let pageIds;

	test.beforeEach( () => {
		pageIds = [];
	} );

	test.afterEach( async ( { requestUtils } ) => {
		for ( const pageId of pageIds.reverse() ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ pageId }`,
				params: { force: true },
			} );
		}
	} );

	test( 'saves a page parent using Quick Edit', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const parentPage = await requestUtils.createPage( {
			title: 'Quick Edit parent',
			status: 'publish',
		} );
		pageIds.push( parentPage.id );
		const createdPage = await requestUtils.createPage( {
			title: 'Quick Edit page',
			status: 'publish',
		} );
		const pageId = createdPage.id;
		pageIds.push( pageId );

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
		const parent = editRow.getByRole( 'combobox', {
			name: 'Parent',
			exact: true,
		} );
		await expect( parent ).toHaveValue( '0' );
		await expect(
			parent.locator( `option[value="${ pageId }"]` )
		).toHaveCount( 0 );
		await parent.selectOption( String( parentPage.id ) );
		await editRow
			.getByRole( 'button', { name: 'Update', exact: true } )
			.click();
		await expect( editRow ).toBeHidden();

		await page.reload();
		await row
			.getByRole( 'link', { name: 'Quick Edit page', exact: true } )
			.hover();
		await row
			.getByRole( 'button', {
				name: 'Quick edit “Quick Edit page” inline',
				exact: true,
			} )
			.click();
		await expect( parent ).toHaveValue( String( parentPage.id ) );
	} );
} );
