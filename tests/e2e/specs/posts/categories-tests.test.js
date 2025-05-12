/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Category Tests', () => {
	async function deleteAllCategories( { page, admin } ) {
		await admin.visitAdminPage( '/edit-tags.php?taxonomy=category' );

		//delete all categories
		await page
			.getByRole( 'checkbox', { name: 'Select All' } )
			.first()
			.click();
		await page
			.getByRole( 'combobox', { name: 'action' } )
			.first()
			.selectOption( 'Delete' );
		await page.getByRole( 'button', { name: 'Apply' } ).first().click();
	}

	// create new category
	async function setupCategory( {
		page,
		admin,
		categoryName = 'Test Category',
		parent = null,
	} ) {
		await admin.visitAdminPage( '/edit-tags.php?taxonomy=category' );
		
		await page
			.getByRole( 'textbox', { name: 'Name' } )
			.fill( categoryName );
		// add parent category
		if ( parent ) {
			await page
				.getByRole( 'combobox', { name: 'Parent' } )
				.selectOption( parent );
		}

		await page
			.getByRole( 'combobox', { name: 'Parent' } )
			.selectOption( parent );
		await page.getByRole( 'button', { name: 'Add Category' } ).click();
	}

	// delete all posts and categories before each test
	test.beforeEach( async ( { requestUtils, admin, page } ) => {
		await requestUtils.deleteAllPosts();
		await deleteAllCategories( { page, admin } );
		await setupCategory( { page, admin } );
	} );

	test( 'Should be able to create a new category', async ( {
		page,
	} ) => {
		await expect(page.getByText('Category added.').first()).toBeVisible({ timeout: 20000 });
	} );

	test( 'Should be able to create a new category with parent category', async ( {
		page,
		admin,
	} ) => {

		//setup category with parent
		await setupCategory( {
			page,
			admin,
			categoryName: 'Child Category',
			parent: 'Test Category',
		} );

		// validate category is created
		await expect(page.getByText('Category added.').first()).toBeVisible({ timeout: 20000 });
	} );

	test( 'Should be able to quick edit a Category', async ( {
		page
	} ) => {

		// hover and quick edit a category
		await page.hover( 'role=link[name= "“Test Category” (Edit)"i]' );
		await page
			.getByRole( 'button', { name: 'Quick Edit' } )
			.first()
			.click();
		
		// update category name
		await page
			.getByRole( 'group', { name: 'Quick Edit' } )
			.getByLabel( 'Name' )
			.fill( 'Updated Category' );
		await page.getByRole( 'button', { name: 'Update Category' } ).click();
		await expect(
			page.getByRole( 'link', { name: 'Updated Category” (Edit)' } )
		).toContainText( 'Updated Category' );
	} );

	test( 'Should be able to delete a Category', async ( {
		page
	} ) => {

		await page.hover( 'role=link[name= "“Test Category” (Edit)"i]' );

		// handle confirm modal and accept delete of category
		page.on( 'dialog', async ( dialog ) => {
			expect( dialog.type() ).toContain( 'confirm' );
			expect( dialog.message() ).toContain(
				'You are about to permanently delete these items from your site.'
			);
			await dialog.accept();
		} );

		// click on the delete button
		await page.getByRole( 'button', { name: 'Delete' } ).first().click();

		// validate category is deleted
		await expect(
			page.getByRole( 'link', { name: 'Test Category” (Edit)' } )
		).not.toBeVisible();
	} );

	test( 'Should be able to sort categories as per name', async ( {
		page
	} ) => {
		await page.getByRole( 'link', { name: 'Name' } ).first().click();
		
		// validate page url and category order
		await expect(page.url()).toContain('?taxonomy=category&orderby=name&order=desc');
		await expect( page.locator( '.row-title' ).first() ).not.toContainText(
			'Test Category'
		);
		await page.getByRole( 'link', { name: 'Name' } ).first().click();
		await expect(page.url()).toContain('?taxonomy=category&orderby=name&order=asc');
		await expect( page.locator( '.row-title' ).first() ).toContainText(
			'Test Category'
		);
	} );

	test( 'Should be able to sort categories as per count', async ( {
		page,
		admin,
		editor,
	} ) => {
		// create new post
		await admin.createNewPost( { postType: 'post', title: 'Test Post' } );

		// assign newly created category
		await page
			.getByRole( 'button' )
			.and( page.getByText( 'Categories' ) )
			.click();
		await page.getByLabel( 'Test Category' ).check();

		// publish post
		await editor.publishPost();

		// check category count
		await admin.visitAdminPage( '/edit-tags.php?taxonomy=category' );

		await page.getByRole( 'link', { name: 'Count' } ).first().click();
		await expect(page.url()).toContain('?taxonomy=category&orderby=count&order=asc');
		await expect(
			page.locator( '.posts.column-posts' ).first()
		).toContainText( '0' );
		await page.getByRole( 'link', { name: 'Count' } ).first().click();
		await expect(page.url()).toContain('?taxonomy=category&orderby=count&order=desc');
		await expect(
			page.locator( '.posts.column-posts' ).first()
		).toContainText( '1' );
	} );

	test( 'Should be able to assign category to a post', async ( {
		page,
		admin,
		editor,
	} ) => {
		await setupCategory( { page, admin } );
		// create new post
		await admin.createNewPost( { postType: 'post', title: 'Test Post' } );

		// assign newly created category
		await page
			.getByRole( 'button' )
			.and( page.getByText( 'Categories' ) )
			.click();
		await page.getByLabel( 'Test Category' ).check();

		// publish post
		await editor.publishPost();

		// check category count
		await admin.visitAdminPage( '/edit-tags.php?taxonomy=category' );
		await expect( page.locator( '.row-title' ).first() ).toContainText(
			'Test Category'
		);

		await expect(
			page.locator( '.posts.column-posts' ).first()
		).toContainText( '1' );
	} );
} );