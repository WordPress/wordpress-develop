/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Tag Tests', () => {
	const pageLink = `/edit-tags.php?taxonomy=post_tag`;
	
	//delete all categories
	async function deleteAllTags( { page, admin } ) { 
		await admin.visitAdminPage( pageLink );
		
		// validate if tags exist
		const tagsExist = await page
			.locator( '#bulk-action-selector-top' )
			.isVisible();
		
		// delete tags if exist
		if ( tagsExist ) {
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
	}
	
	// create new Tag
	async function createTag( { page, admin, tagName = 'Test Tag' } ) {
		await admin.visitAdminPage( pageLink );
		await page.getByRole( 'textbox', { name: 'Name' } ).fill( tagName );
		await page.getByRole( 'button', { name: 'Add Tag' } ).click();
		
	}

	async function publishPostWithTag( { page, admin, editor, tagName = 'Test Tag' } ) {
		// add a post 
		await admin.createNewPost( { postType: 'post', title: 'Test Post' } );
		
		// assign newly created tag
		const tagInputFieldVisible = await page.locator( '.components-panel__body input' ).isVisible();
		
		// if tag input field is not visible, click on Tags button

		if (await ! tagInputFieldVisible) {
			await page.locator('.components-panel__body').last().click();
		}

		await page
			.locator( '.components-panel__body input' )
			.fill( tagName );
		await page.locator( '.components-form-token-field__suggestion-match' ).click();

		// publish post
		await editor.publishPost();
		
	}


	test.beforeEach( async ( { page, admin, requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await deleteAllTags( { page, admin } );
		await createTag( { page, admin } );
	} );

	test( 'Should be able to create a new tag', async ( { page } ) => {
		await expect(
			page.getByText( 'Tag added.' ).first()
		).toBeVisible();
	} );

	test( 'Should be able to quick edit a Tag', async ( {
		page,
		admin,
	} ) => {
		// hover over tag titlee and click quick edit
		await page.hover( 'role=link[name= "“Test Tag” (Edit)"i]' );
		await page
			.getByRole( 'button', { name: 'Quick Edit' } )
			.first()
			.click();

		// update tag name
		await page
			.getByRole( 'group', { name: 'Quick Edit' } )
			.getByLabel( 'Name' )
			.fill( 'Updated Tag' );
		await page.getByRole( 'button', { name: 'Update Tag' } ).click();
		await expect(
			page.getByRole( 'link', { name: 'Updated Tag” (Edit)' } )
		).toBeVisible();
	} );

	test( 'Should be able to delete a Tag', async ( {
		page,
	} ) => {
		// handle confirm modal and accept delete of tag
		page.on( 'dialog', async ( dialog ) => {
			expect( dialog.type() ).toContain( 'confirm' );
			expect( dialog.message() ).toContain(
				'You are about to permanently delete these items from your site.'
			);
			await dialog.accept();
		} );

		// hover over tag title and click delete
		await page.hover( 'role=link[name= "“Test Tag” (Edit)"i]' );
		await page.getByRole( 'button', { name: 'Delete' } ).first().click();
		await expect(
			page.getByRole( 'link', { name: 'Test Tag” (Edit)' } )
		).not.toBeVisible();
	} );

	test( 'Should be able to sort tags as per name', async ( { 
		admin,
		page,
	} ) => {
		// add an additional tag to check sorting
		await createTag( { page, admin, tagName: 'Sample Tag to validate sorting' } );
		await page.getByRole( 'link', { name: 'Name' } ).first().click();
		
		// validate page url and tag order
		await expect(page.url()).toContain('?taxonomy=post_tag&orderby=name&order=desc');
		await expect( page.locator( '.row-title' ).first() ).toContainText(
			'Test Tag'
		);
		await page.getByRole( 'link', { name: 'Name' } ).first().click();
		await expect(page.url()).toContain('?taxonomy=post_tag&orderby=name&order=asc');
		await expect( page.locator( '.row-title' ).first() ).toContainText(
			'Sample Tag to validate sorting'
		);

	});

	test( 'Should be able to sort tags as per count', async ( { 
		admin,
		page,
		editor,
	} ) => {
		// add an additional tag to check sorting with count
		await createTag( { page, admin, tagName: 'Sample Tag to validate sorting' } );

		// create a post and assign tag to it
		await publishPostWithTag( { page, admin, editor } );

		// visit tags page
		await admin.visitAdminPage('/edit-tags.php?taxonomy=post_tag')

		// validate page url and tag order
		await page.getByRole( 'link', { name: 'Count' } ).first().click();
		
		await expect(page.url()).toContain('?taxonomy=post_tag&orderby=count&order=asc');
		await expect( page.locator( '.posts.column-posts' ).first() ).toContainText(
			'0'
		);
		await page.getByRole( 'link', { name: 'Count' } ).first().click();
		await expect(page.url()).toContain('?taxonomy=post_tag&orderby=count&order=desc');
		await expect( page.locator( '.posts.column-posts' ).first() ).toContainText(
			'1'
		);

	});

	test( 'Should create new post and add tag', async ( {
		page,
		admin,
		editor,
	} ) => {

		// create a new post and add tag
		await publishPostWithTag( { page, admin, editor } );

		// check category count
		await admin.visitAdminPage( '/edit-tags.php?taxonomy=post_tag' );
		await expect( page.locator( '.row-title' ).first() ).toContainText(
			'Test Tag'
		);
		await expect(
			page.locator( '.posts.column-posts' ).first()
		).toContainText( '1' );
	} );
} );
