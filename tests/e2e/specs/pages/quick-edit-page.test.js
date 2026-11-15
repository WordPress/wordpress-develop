/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';

test.describe( 'Quick Edit page', () => {
	test( 'saves a page parent using Quick Edit', async ( {
		page,
		admin,
		postFactory,
	} ) => {
		const parentPage = await postFactory.createPage( {
			title: 'Quick Edit parent',
			status: 'publish',
		} );
		const createdPage = await postFactory.createPage( {
			title: 'Quick Edit page',
			status: 'publish',
		} );
		const pageId = createdPage.id;

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
