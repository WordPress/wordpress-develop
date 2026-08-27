/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import path from 'path';

const TEST_IMAGE = path.join(
	__dirname,
	'..',
	'..',
	'phpunit',
	'data',
	'images',
	'test-image-1-100x100.jpg'
);

/**
 * @see https://core.trac.wordpress.org/ticket/65513
 */
test.describe( 'Featured image modal media count and deletion', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		// Start from an empty Media Library so the count is deterministic.
		await requestUtils.deleteAllMedia();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'reports the correct count on first upload and removes a deleted item from the grid', async ( {
		page,
		admin,
	} ) => {
		await admin.createNewPost( { showWelcomeGuide: false } );

		// `wp.media` is enqueued on the post editing screen.
		await page.waitForFunction(
			() => window.wp && window.wp.media && window.wp.media.featuredImage
		);

		// The Featured Image modal opens on the Upload tab.
		await page.evaluate( () => window.wp.media.featuredImage.frame().open() );

		const modal = page.locator( '.media-modal' );
		await expect( modal ).toBeVisible();

		await modal
			.locator( 'input[type="file"]' )
			.setInputFiles( TEST_IMAGE );

		await page.evaluate( () => window.wp.media.frame.content.mode( 'browse' ) );

		await expect(
			modal.locator( '.attachments .attachment' )
		).toHaveCount( 1 );

		// The uploaded image must be counted once, not once per collection it
		// belongs to (mirrored query + auto-selected selection).
		await expect( modal.locator( '.load-more-count' ) ).toHaveText(
			'Showing 1 of 1 media items'
		);

		page.on( 'dialog', ( dialog ) => dialog.accept() );

		// The uploaded image is auto-selected, so its details are already shown.
		await modal
			.locator( '.attachment-details .delete-attachment' )
			.click();

		// The deleted attachment must not linger in the grid.
		await expect(
			modal.locator( '.attachments .attachment' )
		).toHaveCount( 0 );
	} );
} );
