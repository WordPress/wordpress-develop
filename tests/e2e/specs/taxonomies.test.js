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
			const slug = `${ name }-slug`;
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
				.fill( slug );
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
				page
					.getByRole( 'row' )
					.filter( { has: link } )
					.getByRole( 'cell', { name: slug, exact: true } )
			).toBeVisible();
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

		test( 'shows an empty result when searching for a missing term', async ( {
			admin,
			page,
			requestUtils,
		} ) => {
			// Ensure at least one non-matching term exists so ignoring `s` cannot
			// vacuously pass with an empty taxonomy table.
			await createTerm( requestUtils, 'present' );
			const suffix = randomUUID().slice( 0, 8 );
			const missing = `${ prefix }-missing-${ suffix }`;
			await admin.visitAdminPage(
				'/edit-tags.php',
				`taxonomy=${ taxonomy }&s=${ encodeURIComponent( missing ) }`
			);
			await expect(
				page.getByRole( 'cell', {
					name:
						taxonomy === 'category'
							? 'No categories found.'
							: 'No tags found.',
					exact: true,
				} )
			).toBeVisible();
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

			test( 'bulk deletes selected categories; the default category cannot be selected', async ( {
				admin,
				page,
				requestUtils,
			} ) => {
				const first = await createTerm( requestUtils, 'bulk-one' );
				const second = await createTerm( requestUtils, 'bulk-two' );
				const defaults = await requestUtils.rest( {
					path: '/wp/v2/categories',
					params: { slug: 'uncategorized' },
				} );
				expect( defaults ).toHaveLength( 1 );
				const uncategorizedId = defaults[ 0 ].id;

				await admin.visitAdminPage(
					'/edit-tags.php',
					'taxonomy=category'
				);
				const uncategorizedRow = page.locator(
					`#tag-${ uncategorizedId }`
				);
				await expect( uncategorizedRow ).toBeVisible();
				await expect(
					uncategorizedRow.getByRole( 'checkbox' )
				).toHaveCount( 0 );

				await admin.visitAdminPage(
					'/edit-tags.php',
					`taxonomy=category&s=${ prefix }`
				);
				await page
					.locator( `#tag-${ first.id }` )
					.getByRole( 'checkbox' )
					.check();
				await page
					.locator( `#tag-${ second.id }` )
					.getByRole( 'checkbox' )
					.check();
				await page
					.locator( '#bulk-action-selector-top' )
					.selectOption( 'delete' );
				await page.locator( '#doaction' ).click();

				await expect(
					page.locator( `#tag-${ first.id }` )
				).toBeHidden();
				await expect(
					page.locator( `#tag-${ second.id }` )
				).toBeHidden();

				const remaining = await requestUtils.rest( {
					path: '/wp/v2/categories',
					params: {
						include: [ first.id, second.id ],
					},
				} );
				expect( remaining ).toEqual( [] );
			} );
		}
	} );
}
