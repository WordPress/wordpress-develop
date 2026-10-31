/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';
import path from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Media library', () => {
	let ids;
	let prefix;

	test.beforeEach( async () => {
		ids = [];
		prefix = `e2e-media-${ randomUUID() }`;
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! ids.length ) {
			return;
		}
		const remaining = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: {
				include: ids,
				per_page: ids.length,
				status: [ 'inherit', 'private', 'trash' ],
			},
		} );
		for ( const item of remaining ) {
			await requestUtils.deleteMedia( item.id );
		}
	} );

	async function upload(
		requestUtils,
		suffix,
		file = 'images/test-image.jpg'
	) {
		const item = await requestUtils.uploadMedia(
			path.resolve( 'tests/phpunit/data', file )
		);
		ids.push( item.id );
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/media/${ item.id }`,
			data: { title: `${ prefix }-${ suffix }` },
		} );
		return item.id;
	}

	async function confirmDelete( page, button ) {
		await Promise.all( [
			page.waitForEvent( 'dialog' ).then( async ( dialog ) => {
				await dialog.accept();
				expect( dialog.type() ).toBe( 'confirm' );
			} ),
			button.click(),
		] );
	}

	test( 'permanently deletes one attachment', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const id = await upload( requestUtils, 'delete' );
		await admin.visitAdminPage( '/upload.php', `mode=list&s=${ prefix }` );
		const row = page.locator( `#post-${ id }` );
		await expect( row ).toBeVisible();
		await row.hover();
		await confirmDelete(
			page,
			row.getByRole( 'button', {
				name: `Delete “${ prefix }-delete” permanently`,
				exact: true,
			} )
		);
		await expect( page.locator( '#message' ) ).toContainText(
			'Media file permanently deleted.'
		);
		await expect( row ).toBeHidden();
		const remaining = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: {
				include: [ id ],
				status: [ 'inherit', 'private', 'trash' ],
			},
		} );
		expect( remaining ).toEqual( [] );
	} );

	test( 'bulk deletes selected attachments and keeps the unselected attachment', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const first = await upload( requestUtils, 'first' );
		const second = await upload( requestUtils, 'second' );
		const control = await upload( requestUtils, 'control' );
		await admin.visitAdminPage( '/upload.php', `mode=list&s=${ prefix }` );
		const firstRow = page.locator( `#post-${ first }` );
		const secondRow = page.locator( `#post-${ second }` );
		const controlRow = page.locator( `#post-${ control }` );
		await firstRow.getByRole( 'checkbox' ).check();
		await secondRow.getByRole( 'checkbox' ).check();
		await page
			.locator( '#bulk-action-selector-top' )
			.selectOption( 'delete' );
		await confirmDelete( page, page.locator( '#doaction' ) );
		await expect( page.locator( '#message' ) ).toContainText(
			'2 media files permanently deleted.'
		);
		await expect( firstRow ).toBeHidden();
		await expect( secondRow ).toBeHidden();
		await expect( controlRow ).toBeVisible();
		const remaining = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: {
				include: [ first, second, control ],
				status: [ 'inherit', 'private', 'trash' ],
			},
		} );
		expect( remaining.map( ( item ) => item.id ) ).toEqual( [ control ] );
	} );

	test( 'filters the grid by media type', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const image = await upload( requestUtils, 'image' );
		const audio = await upload(
			requestUtils,
			'audio',
			'uploads/small-audio.mp3'
		);
		await admin.visitAdminPage( '/upload.php', 'mode=grid' );
		await page
			.getByRole( 'searchbox', { name: 'Search media', exact: true } )
			.fill( prefix );
		const imageTile = page.locator( `.attachments [data-id="${ image }"]` );
		const audioTile = page.locator( `.attachments [data-id="${ audio }"]` );
		await expect( imageTile ).toBeVisible();
		await expect( audioTile ).toBeVisible();
		await page
			.getByRole( 'combobox', { name: 'Filter by type', exact: true } )
			.selectOption( 'audio' );
		await expect( audioTile ).toBeVisible();
		await expect( imageTile ).toBeHidden();
		await page
			.getByRole( 'combobox', { name: 'Filter by type', exact: true } )
			.selectOption( 'image' );
		await expect( imageTile ).toBeVisible();
		await expect( audioTile ).toBeHidden();
	} );
} );
