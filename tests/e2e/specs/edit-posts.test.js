/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

test.describe( 'Edit Posts', () => {
	async function visitPostList( admin, title ) {
		await admin.visitAdminPage(
			'edit.php',
			new URLSearchParams( { s: title } ).toString()
		);
	}

	test( 'displays a message in the posts table when a search finds no posts',async ( {
		admin,
		page,
	} ) => {
		await visitPostList( admin, randomUUID() );
		await expect(
			page.getByRole( 'cell', { name: 'No posts found.' } )
		).toBeVisible();
	} );

	test( 'lists a published post and renders its title and content on the front end', async ( {
		admin,
		page,
		postFactory,
	} ) => {
		const title = postFactory.uniqueTitle( 'Edit Posts list' );
		const content = 'This content should be visible on the published post.';
		const post = await postFactory.create( { title, content, status: 'publish' } );
		await visitPostList( admin, title );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Expect there to be one row in the post list.
		await expect(
			listTable.locator( '#the-list' ).getByRole( 'row' )
		).toHaveCount( 1 );
		const row = listTable.locator( `#post-${ post.id }` );

		// Expect the title of the post to be correct.
		const postLink = row.getByRole( 'link', {
			name: title,
			exact: true,
		} );
		await expect( postLink ).toBeVisible();

		await postLink.hover();
		await row.getByRole( 'link', { name: `View “${ title }”`, exact: true } ).click();
		await expect( page.getByRole( 'heading', { name: title, exact: true } ) ).toBeVisible();
		await expect( page.getByText( content, { exact: true } ) ).toBeVisible();
	} );

	test( 'allows an existing post to be edited using the Edit button', async ( {
		admin,
		editor,
		page,
		postFactory,
	} ) => {
		const title = postFactory.uniqueTitle( 'Edit Posts edit' );
		const post = await postFactory.create( { title, status: 'publish' } );
		await visitPostList( admin, title );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();

		// Click the post title (edit) link
		await listTable
			.locator( `#post-${ post.id }` )
			.getByRole( 'link', { name: title, exact: true } )
			.click();

		const editorPostTitle = editor.canvas.getByRole( 'textbox', { name: 'Add title' } );

		// Expect title field to be in the editor with correct title shown.
		// The editor iframe can take a while to load.
		await expect( editorPostTitle ).toBeVisible( { timeout: 15_000 } );
		await expect( editorPostTitle ).toHaveText( title );
	} );

	test( 'allows an existing post to be quick edited using the Quick Edit button', async ( {
		admin,
		page,
		pageUtils,
		postFactory,
	} ) => {
		const title = postFactory.uniqueTitle( 'Edit Posts quick edit' );
		const post = await postFactory.create( { title, status: 'publish' } );
		await visitPostList( admin, title );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();
		const row = listTable.locator( `#post-${ post.id }` );

		// // Focus on the post title link.
		await row.getByRole( 'link', { name: title, exact: true } ).focus();

		// Tab to the Quick Edit button and press Enter to quick edit.
		await pageUtils.pressKeys( 'Tab', { times: 2 } );
		await expect(
			page.getByRole( 'button', {
				name: `Quick edit “${ title }” inline`,
				exact: true,
			} )
		).toBeFocused();
		await page.keyboard.press( 'Enter' );

		// Type in the currently focused (title) field to modify the title, testing that focus is moved to the input.
		await page.keyboard.type( ' Edited' );

		// Update the post.
		await page.getByRole( 'button', { name: 'Update' } ).click();

		// Wait for the quick edit button to reappear.
		await expect( row.getByRole( 'button', { name: 'Quick Edit' } ) ).toBeVisible();

		// Expect there to be one row in the post list.
		await expect(
			listTable.locator( '#the-list' ).getByRole( 'row' )
		).toHaveCount( 1 );

		// Expect the title of the post to be correct.
		await expect(
			row.getByRole( 'link', {
				name: `${ title } Edited`,
				exact: true,
			} )
		).toBeVisible();
	} );

	test( 'allows an existing post to be deleted using the Trash button', async ( {
		admin,
		page,
		postFactory,
	} ) => {
		const title = postFactory.uniqueTitle( 'Edit Posts trash' );
		const post = await postFactory.create( { title, status: 'publish' } );
		await visitPostList( admin, title );

		const listTable = page.getByRole( 'table', { name: 'Table ordered by' } );
		await expect( listTable ).toBeVisible();
		const row = listTable.locator( `#post-${ post.id }` );

		await row.getByRole( 'link', { name: title, exact: true } ).hover();
		await row
			.getByRole( 'link', {
				name: `Move “${ title }” to the Trash`,
				exact: true,
			} )
			.click();

		await expect(
			page.getByRole( 'cell', { name: 'No posts found.' } )
		).toBeVisible();
	} );
} );

test( 'bulk edits only the selected posts', async ( {
	page,
	admin,
	postFactory,
	requestUtils,
} ) => {
	const posts = [];
	const titlePrefix = postFactory.uniqueTitle( 'Bulk edit' );

	for ( const suffix of [ 'first', 'second', 'control' ] ) {
		posts.push(
			await postFactory.create( {
				title: `${ titlePrefix } ${ suffix }`,
				status: 'publish',
			} )
		);
	}

	await admin.visitAdminPage(
		'edit.php',
		new URLSearchParams( { s: titlePrefix } ).toString()
	);
	for ( const post of posts.slice( 0, 2 ) ) {
		await page
			.locator( `#post-${ post.id }` )
			.getByRole( 'checkbox' )
			.check();
	}
	await page.locator( '#bulk-action-selector-top' ).selectOption( 'edit' );
	await page.locator( '#doaction' ).click();
	const bulkEdit = page.getByRole( 'region', {
		name: 'Bulk Edit',
		exact: true,
	} );
	await bulkEdit
		.getByRole( 'combobox', { name: 'Status', exact: true } )
		.selectOption( 'draft' );
	await bulkEdit
		.getByRole( 'button', { name: 'Update', exact: true } )
		.click();
	await expect( page.locator( '#message' ) ).toContainText(
		'2 posts updated.'
	);

	await page.reload();
	for ( const post of posts.slice( 0, 2 ) ) {
		await expect(
			page
				.locator( `#post-${ post.id }` )
				.getByText( 'Draft', { exact: true } )
		).toBeVisible();
		const savedPost = await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			params: { context: 'edit' },
		} );
		expect( savedPost.status ).toBe( 'draft' );
	}
	const control = await requestUtils.rest( {
		path: `/wp/v2/posts/${ posts[ 2 ].id }`,
		params: { context: 'edit' },
	} );
	expect( control.status ).toBe( 'publish' );
} );
