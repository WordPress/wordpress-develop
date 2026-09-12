/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import path from 'path';

const testImage = {
	name: "test'image.jpg",
	mimeType: 'image/jpeg',
	buffer: readFileSync(
		path.join( __dirname, '../../phpunit/data/images/test-image.jpg' )
	),
};

test.afterEach( async ( { requestUtils } ) => {
	await requestUtils.deleteAllMedia();
} );

test( 'Test dismissing failed upload works correctly', async ({ page, admin, requestUtils }) => {
	// Log in before visiting admin page.
	await requestUtils.login();
	await admin.visitAdminPage( '/media-new.php' );

	// It takes a moment for the multi-file uploader to become available.
	await page.waitForLoadState('load');

	const testImagePath = path.join(__dirname, '../assets/sample.svg');

	// Upload a file that will fail.
	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await input.setInputFiles( testImagePath );

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

test( 'uploads an image with an apostrophe in its filename', async ( {
	page,
	admin,
	requestUtils,
} ) => {
	await requestUtils.login();
	await requestUtils.deleteAllMedia();
	await admin.visitAdminPage( '/media-new.php' );
	await page.waitForLoadState( 'load' );

	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await input.setInputFiles( testImage );

	await expect(
		page.getByText( 'testimage.jpg', { exact: true } )
	).toBeVisible();
} );

test( 'uses the generic HTTP error for a rejected image upload', async ( {
	page,
	admin,
	requestUtils,
} ) => {
	await requestUtils.login();
	await page.route( '**/wp-admin/async-upload.php', ( route ) =>
		route.fulfill( {
			status: 403,
			contentType: 'text/plain',
			body: 'Forbidden',
		} )
	);
	await admin.visitAdminPage( '/media-new.php' );
	await page.waitForLoadState( 'load' );

	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await input.setInputFiles( testImage );

	await expect(
		page.getByText( 'Unexpected response from the server.' )
	).toBeVisible();
	await expect(
		page.getByText( 'Suggested maximum size is 2560 pixels.' )
	).not.toBeVisible();
} );

test( 'retains the image processing error for a server failure', async ( {
	page,
	admin,
	requestUtils,
} ) => {
	await requestUtils.login();
	await page.route( '**/wp-admin/async-upload.php', ( route ) =>
		route.fulfill( {
			status: 500,
			contentType: 'text/plain',
			body: 'Internal Server Error',
		} )
	);
	await admin.visitAdminPage( '/media-new.php' );
	await page.waitForLoadState( 'load' );

	const input = page.locator( '#plupload-upload-ui input[type="file"]' );
	await input.setInputFiles( testImage );

	await expect(
		page.getByText( 'The server cannot process the image.' )
	).toBeVisible();
	await expect(
		page.getByText( 'Suggested maximum size is 2560 pixels.' )
	).toBeVisible();
} );
