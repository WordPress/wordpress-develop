/**
 * External dependencies
 */
import path from 'path';

/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';
import { TEST_SVG_PATH, trackUploads } from '../utils/media-pipeline';

test( 'Test dismissing failed upload works correctly', async ({ page, admin }) => {
	await admin.visitAdminPage( '/media-new.php' );

	// It takes a moment for the multi-file uploader to become available.
	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await expect( input ).toBeAttached( { timeout: 10_000 } );

	// Upload a file that will fail.
	await input.setInputFiles( TEST_SVG_PATH );

	// Ensure the error message is visible.
	await expect(
		page.getByText('“sample.svg” has failed to upload.')
	).toBeVisible();

	// Ensure the error message is dismissed.
	await page.getByRole('button', { name: 'Dismiss' }).click();
	await expect(
		page.getByText('“sample.svg” has failed to upload.')
	).not.toBeVisible();
} );

test( 'uploads a JPEG and shows the completed attachment', async ( {
	page,
	admin,
	attachments,
} ) => {
	trackUploads( page, attachments );
	await admin.visitAdminPage( '/media-new.php' );
	const fileChooserPromise = page.waitForEvent( 'filechooser' );
	await page
		.getByRole( 'button', { name: 'Select Files', exact: true } )
		.click();
	const fileChooser = await fileChooserPromise;
	const uploadResponsePromise = page.waitForResponse( ( response ) => {
		if ( response.request().method() !== 'POST' ) {
			return false;
		}

		const url = new URL( response.url() );

		// Client-side processing creates attachments through the REST API.
		if (
			url.pathname.endsWith( '/wp/v2/media' ) ||
			url.searchParams.get( 'rest_route' ) === '/wp/v2/media'
		) {
			return true;
		}

		// Ignore the separate request that fetches completed attachment markup.
		return (
			url.pathname.endsWith( '/async-upload.php' ) &&
			response
				.request()
				.headers()
				[ 'content-type' ]?.startsWith( 'multipart/form-data' )
		);
	} );
	await fileChooser.setFiles(
		path.join( __dirname, '../../phpunit/data/images/test-image.jpg' )
	);
	const uploadResponse = await uploadResponsePromise;
	let attachmentId = Number(
		uploadResponse.headers()[ 'x-wp-upload-attachment-id' ]
	);
	if ( ! attachmentId ) {
		attachmentId = Number(
			uploadResponse.headers()[ 'content-type' ]?.includes(
				'application/json'
			)
				? ( await uploadResponse.json() ).id
				: await uploadResponse.text()
		);
	}
	expect( uploadResponse.ok() ).toBe( true );
	expect( attachmentId ).toBeGreaterThan( 0 );
	attachments.track( attachmentId );

	await expect(
		page.getByText( 'test-image', { exact: true } )
	).toBeVisible();
	const editLink = page.getByRole( 'link', {
		name: 'Edit',
		exact: true,
	} );
	await expect( editLink ).toBeVisible();
	await expect( editLink ).toHaveAttribute(
		'href',
		new RegExp( `[?&]post=${ attachmentId }&` )
	);
} );
