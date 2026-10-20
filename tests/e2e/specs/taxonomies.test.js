/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

for ( const { taxonomy, endpoint, label } of [
	{ taxonomy: 'category', endpoint: 'categories', label: 'Category' },
	{ taxonomy: 'post_tag', endpoint: 'tags', label: 'Tag' },
] ) {
	test.describe( `${ label } management`, () => {
		let prefix;

		test.beforeEach( async () => {
			prefix = `e2e-term-${ randomUUID() }`;
		} );

		test.afterEach( async ( { requestUtils } ) => {
			// Find owned terms by name even if a regression changes their saved slug.
			const terms = await requestUtils.rest( {
				path: `/wp/v2/${ endpoint }`,
				params: {
					search: prefix,
					per_page: 100,
					orderby: 'id',
					order: 'desc',
				},
			} );
			for ( const term of terms ) {
				if ( term.name.startsWith( `${ prefix }-` ) ) {
					await requestUtils.rest( {
						method: 'DELETE',
						path: `/wp/v2/${ endpoint }/${ term.id }`,
						params: { force: true },
					} );
				}
			}
		} );

		/**
		 * Creates a term owned by the current test.
		 *
		 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils REST helpers.
		 * @param {string}                                                      suffix       Term name suffix.
		 * @return {Promise<Object>} Created term.
		 */
		async function createTerm( requestUtils, suffix ) {
			const slug = `${ prefix }-${ suffix }`;
			return requestUtils.rest( {
				method: 'POST',
				path: `/wp/v2/${ endpoint }`,
				data: { name: slug, slug },
			} );
		}

		test( 'adds a term', async ( { admin, page } ) => {
			const name = `${ prefix }-new`;
			await admin.visitAdminPage(
				'/edit-tags.php',
				`taxonomy=${ taxonomy }`
			);
			const form = page.locator( '#addtag' );
			await form
				.getByRole( 'textbox', { name: 'Name', exact: true } )
				.fill( name );
			await form
				.getByRole( 'textbox', { name: 'Slug', exact: true } )
				.fill( name );
			await form
				.getByRole( 'button', { name: `Add ${ label }`, exact: true } )
				.click();

			const link = page.getByRole( 'link', {
				name,
				exact: true,
			} );
			await expect( link ).toBeVisible();
			await admin.visitAdminPage(
				'/edit-tags.php',
				`taxonomy=${ taxonomy }&s=${ prefix }`
			);
			await expect( link ).toBeVisible();
			await expect(
				page.locator( 'tr', { has: link } ).locator( '.column-slug' )
			).toHaveText( name );
		} );

		test( 'quick edits a term', async ( { admin, page, requestUtils } ) => {
			const term = await createTerm( requestUtils, 'original' );
			await admin.visitAdminPage(
				'/edit-tags.php',
				`taxonomy=${ taxonomy }&s=${ prefix }`
			);
			const row = page.locator( `#tag-${ term.id }` );
			await row.hover();
			await row
				.getByRole( 'button', {
					name: `Quick edit “${ term.name }” inline`,
					exact: true,
				} )
				.click();
			const editor = page.getByRole( 'group', {
				name: 'Quick Edit',
				exact: true,
			} );
			await expect(
				editor.getByLabel( 'Name', { exact: true } )
			).toHaveValue( term.name );
			const updatedName = `${ prefix }-updated`;
			await editor
				.getByLabel( 'Name', { exact: true } )
				.fill( updatedName );
			await page
				.getByRole( 'button', {
					name: `Update ${ label }`,
					exact: true,
				} )
				.click();

			const link = row.getByRole( 'link', {
				name: updatedName,
				exact: true,
			} );
			await expect( link ).toBeVisible();
			await expect( editor ).toBeHidden();
			await page.reload();
			await expect( link ).toBeVisible();
		} );

		test( 'deletes a term', async ( { admin, page, requestUtils } ) => {
			const term = await createTerm( requestUtils, 'delete' );
			await admin.visitAdminPage(
				'/edit-tags.php',
				`taxonomy=${ taxonomy }&s=${ prefix }`
			);
			const row = page.locator( `#tag-${ term.id }` );
			await expect( row ).toBeVisible();
			await row.hover();
			await Promise.all( [
				page.waitForEvent( 'dialog' ).then( async ( dialog ) => {
					await dialog.accept();
					expect( dialog.type() ).toBe( 'confirm' );
				} ),
				row
					.getByRole( 'button', {
						name: `Delete “${ term.name }”`,
						exact: true,
					} )
					.click(),
			] );
			await expect( row ).toBeHidden();
			const terms = await requestUtils.rest( {
				path: `/wp/v2/${ endpoint }`,
				params: { slug: term.slug },
			} );
			expect( terms ).toEqual( [] );
		} );

		if ( taxonomy === 'category' ) {
			test( 'adds a category with a parent', async ( {
				admin,
				page,
				requestUtils,
			} ) => {
				const parent = await createTerm( requestUtils, 'parent' );
				const name = `${ prefix }-child`;
				await admin.visitAdminPage(
					'/edit-tags.php',
					'taxonomy=category'
				);
				const form = page.locator( '#addtag' );
				await form
					.getByRole( 'textbox', { name: 'Name', exact: true } )
					.fill( name );
				await form
					.getByRole( 'textbox', { name: 'Slug', exact: true } )
					.fill( name );
				await form
					.getByRole( 'combobox', {
						name: 'Parent Category',
						exact: true,
					} )
					.selectOption( String( parent.id ) );
				await form
					.getByRole( 'button', {
						name: 'Add Category',
						exact: true,
					} )
					.click();
				const link = page.getByRole( 'link', {
					name: new RegExp( `${ name }$` ),
				} );
				await expect( link ).toBeVisible();
				await admin.visitAdminPage(
					'/edit-tags.php',
					`taxonomy=category&s=${ prefix }`
				);
				await expect( link ).toBeVisible();
				const terms = await requestUtils.rest( {
					path: '/wp/v2/categories',
					params: { slug: name },
				} );
				expect( terms ).toHaveLength( 1 );
				expect( terms[ 0 ].parent ).toBe( parent.id );
			} );
		}
	} );
}
