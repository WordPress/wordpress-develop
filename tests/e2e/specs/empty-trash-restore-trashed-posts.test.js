/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';
import {
	adminNotice,
	bulkActionApply,
	bulkActionSelect,
	postRow,
} from '../utils/admin';

test( 'Undo restores a trashed post to its published status', async ( {
	page,
	admin,
	postFactory,
	requestUtils,
} ) => {
	const title = postFactory.uniqueTitle( 'Undo trash' );

	const post = await postFactory.create( {
		title,
		status: 'publish',
	} );
	const postId = post.id;
	await admin.visitAdminPage(
		'edit.php',
		new URLSearchParams( { s: title } ).toString()
	);
	const row = postRow( page, postId );
	await row.getByRole( 'link', { name: title, exact: true } ).hover();
	await row
		.getByRole( 'link', {
			name: `Move “${ title }” to the Trash`,
			exact: true,
		} )
		.click();
	await expect( adminNotice( page ) ).toContainText(
		'1 post moved to the Trash.'
	);
	await expect( row ).toHaveCount( 0 );
	const trashedPost = await requestUtils.rest( {
		path: `/wp/v2/posts/${ postId }`,
		params: { context: 'edit' },
	} );
	expect( trashedPost.status ).toBe( 'trash' );

	await page.getByRole( 'link', { name: 'Undo', exact: true } ).click();
	await expect( adminNotice( page ) ).toContainText(
		'1 post restored from the Trash.'
	);
	await admin.visitAdminPage(
		'edit.php',
		new URLSearchParams( { s: title } ).toString()
	);
	await expect(
		row.getByRole( 'link', { name: title, exact: true } )
	).toBeVisible();
	const restoredPost = await requestUtils.rest( {
		path: `/wp/v2/posts/${ postId }`,
		params: { context: 'edit' },
	} );
	expect( restoredPost.status ).toBe( 'publish' );
} );

test( 'bulk trashes only the selected posts', async ( {
	page,
	admin,
	postFactory,
	requestUtils,
} ) => {
	const posts = [];
	const titlePrefix = postFactory.uniqueTitle( 'Bulk trash' );

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
		await postRow( page, post.id ).getByRole( 'checkbox' ).check();
	}
	await bulkActionSelect( page ).selectOption( 'trash' );
	await bulkActionApply( page ).click();
	await expect( adminNotice( page ) ).toContainText(
		'2 posts moved to the Trash.'
	);

	for ( const post of posts.slice( 0, 2 ) ) {
		await expect( postRow( page, post.id ) ).toHaveCount( 0 );
		const savedPost = await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			params: { context: 'edit' },
		} );
		expect( savedPost.status ).toBe( 'trash' );
	}
	await expect( postRow( page, posts[ 2 ].id ) ).toBeVisible();
	const control = await requestUtils.rest( {
		path: `/wp/v2/posts/${ posts[ 2 ].id }`,
		params: { context: 'edit' },
	} );
	expect( control.status ).toBe( 'publish' );
} );

test( 'Restore trash post', async ( {
	page,
	admin,
	postFactory,
	requestUtils,
} ) => {
	const title = postFactory.uniqueTitle( 'Restore trash' );
	const post = await postFactory.create( { title, status: 'publish' } );
	await requestUtils.rest( {
		method: 'DELETE',
		path: `/wp/v2/posts/${ post.id }`,
	} );

	await admin.visitAdminPage(
		'edit.php',
		new URLSearchParams( { post_status: 'trash', s: title } ).toString()
	);
	const row = postRow( page, post.id );
	await row.getByRole( 'cell' ).filter( { hasText: title } ).hover();
	await row
		.getByRole( 'link', {
			name: `Restore “${ title }” from the Trash`,
			exact: true,
		} )
		.click();

	await expect( adminNotice( page ) ).toContainText(
		'1 post restored from the Trash.'
	);
	await expect( row ).toHaveCount( 0 );
	const restoredPost = await requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		params: { context: 'edit' },
	} );
	expect( restoredPost.status ).toBe( 'draft' );
} );
