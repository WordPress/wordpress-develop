/**
 * External dependencies
 */
import path from 'path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const SITE_ICON_SIZES = [
	'site_icon-32',
	'site_icon-64',
	'site_icon-180',
	'site_icon-192',
	'site_icon-270',
];

test.describe( 'Site Icon in General Settings', () => {
	const mediaIds = [];

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of mediaIds ) {
			await requestUtils.deleteMedia( id );
		}
	} );

	/**
	 * Uploads an image from the e2e assets folder.
	 *
	 * @param {Object} requestUtils The request utilities.
	 * @param {string} filename     The image file name.
	 * @return {Promise<number>} The attachment ID.
	 */
	async function uploadImage( requestUtils, filename ) {
		const media = await requestUtils.uploadMedia(
			path.join( __dirname, '../assets', filename )
		);
		mediaIds.push( media.id );
		return media.id;
	}

	/**
	 * Selects an image for the Site Icon in Settings > General.
	 *
	 * @param {Object} page    The page.
	 * @param {Object} admin   The admin utilities.
	 * @param {number} mediaId The attachment ID.
	 * @return {Promise<Object>} The media modal locator.
	 */
	async function selectSiteIcon( page, admin, mediaId ) {
		await admin.visitAdminPage( '/options-general.php' );
		await page.locator( '#choose-from-library-button' ).click();

		const modal = page.locator( '.media-modal' );
		await modal.locator( `li.attachment[data-id="${ mediaId }"]` ).click();
		await modal.locator( '.media-button-select' ).click();

		return modal;
	}

	/**
	 * Checks that the selected Site Icon is a new cropped copy with all site icon sizes.
	 *
	 * @param {Object} page         The page.
	 * @param {Object} requestUtils The request utilities.
	 * @param {number} originalId   The ID of the image that was selected.
	 */
	async function expectCroppedSiteIcon( page, requestUtils, originalId ) {
		const field = page.locator( '#site_icon_hidden_field' );
		await expect( field ).not.toHaveValue( '' );
		await expect( field ).not.toHaveValue( String( originalId ) );

		const croppedId = Number( await field.inputValue() );
		mediaIds.push( croppedId );

		const cropped = await requestUtils.rest( {
			path: `/wp/v2/media/${ croppedId }`,
			params: { context: 'edit' },
		} );

		expect( cropped.media_details.width ).toBe( 512 );
		expect( cropped.media_details.height ).toBe( 512 );
		expect( Object.keys( cropped.media_details.sizes ) ).toEqual(
			expect.arrayContaining( SITE_ICON_SIZES )
		);
	}

	test( 'creates the site icon sizes for an image that is exactly 512 by 512', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const mediaId = await uploadImage(
			requestUtils,
			'site-icon-512x512.png'
		);

		const modal = await selectSiteIcon( page, admin, mediaId );

		// The image does not need cropping, so the cropper is not shown.
		await expect( modal ).toBeHidden();
		await expectCroppedSiteIcon( page, requestUtils, mediaId );
	} );

	test( 'lets a larger square image skip cropping and creates the site icon sizes', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const mediaId = await uploadImage(
			requestUtils,
			'site-icon-1024x1024.png'
		);

		const modal = await selectSiteIcon( page, admin, mediaId );

		await expect(
			modal.getByRole( 'button', { name: 'Crop image' } )
		).toBeVisible();
		await modal.getByRole( 'button', { name: 'Skip cropping' } ).click();

		await expect( modal ).toBeHidden();
		await expectCroppedSiteIcon( page, requestUtils, mediaId );
	} );

	test( 'does not offer to skip cropping for an image that is not square', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		const mediaId = await uploadImage(
			requestUtils,
			'site-icon-800x600.png'
		);

		const modal = await selectSiteIcon( page, admin, mediaId );

		await expect(
			modal.getByRole( 'button', { name: 'Crop image' } )
		).toBeVisible();
		await expect(
			modal.getByRole( 'button', { name: 'Skip cropping' } )
		).toHaveCount( 0 );
	} );
} );
