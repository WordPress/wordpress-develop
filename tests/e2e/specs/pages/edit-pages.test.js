/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Edit Pages', () => {
	let pageIds;
	let prefix;

	test.beforeEach( () => {
		pageIds = [];
		prefix = `e2e-page-${ randomUUID().slice( 0, 8 ) }`;
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

	test( 'lists a published page and opens it for editing', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const title = `${ prefix }-title`;
		const content = `${ prefix } content should appear on the front end.`;
		const created = await requestUtils.createPage( {
			title,
			content,
			status: 'publish',
		} );
		pageIds.push( created.id );

		await admin.visitAdminPage(
			'edit.php',
			`post_type=page&s=${ encodeURIComponent( prefix ) }`
		);

		const listTable = page.getByRole( 'table', {
			name: 'Table ordered by',
		} );
		await expect( listTable ).toBeVisible();

		const pageLink = listTable.getByRole( 'link', {
			name: title,
			exact: true,
		} );
		await expect( pageLink ).toBeVisible();

		await pageLink.hover();
		await listTable
			.getByRole( 'link', { name: `View “${ title }”`, exact: true } )
			.click();
		await expect(
			page.getByRole( 'heading', { name: title, exact: true } )
		).toBeVisible();
		await expect( page.getByText( content, { exact: true } ) ).toBeVisible();

		await admin.visitAdminPage(
			'edit.php',
			`post_type=page&s=${ encodeURIComponent( prefix ) }`
		);
		await listTable
			.getByRole( 'link', { name: title, exact: true } )
			.click();

		const editorPageTitle = editor.canvas.getByRole( 'textbox', {
			name: 'Add title',
		} );
		await expect( editorPageTitle ).toBeVisible( { timeout: 15_000 } );
		await expect( editorPageTitle ).toHaveText( title );
	} );

	test( 'allows an existing page to be deleted using the Trash button', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const title = `${ prefix }-trash`;
		const created = await requestUtils.createPage( {
			title,
			status: 'publish',
		} );
		pageIds.push( created.id );

		await admin.visitAdminPage(
			'edit.php',
			`post_type=page&s=${ encodeURIComponent( prefix ) }`
		);

		const row = page.locator( `#post-${ created.id }` );
		await expect( row ).toBeVisible();
		await row.getByRole( 'link', { name: title, exact: true } ).hover();
		await row
			.getByRole( 'button', {
				name: `Move “${ title }” to the Trash`,
				exact: true,
			} )
			.click();

		await expect( page.locator( '#message' ) ).toContainText( 'moved to the Trash' );
		await expect( row ).toBeHidden();
	} );
} );
