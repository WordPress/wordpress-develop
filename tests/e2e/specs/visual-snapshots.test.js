import { visitAdminPage } from '@wordpress/e2e-test-utils';

const screenshotOptions = {
	fullPage: true,
}

describe( 'Admin Visual Snapshots', () => {

	it( 'All Posts', async () => {
		await visitAdminPage( '/edit.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Categories', async () => {
		await visitAdminPage( '/edit-tags.php','taxonomy=category' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Tags', async () => {
		await visitAdminPage( '/edit-tags.php','taxonomy=post_tag' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Media Library', async () => {
		await visitAdminPage( '/upload.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Add New Media', async () => {
		await visitAdminPage( '/media-new.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'All Pages', async () => {
		await visitAdminPage( '/edit.php','post_type=page' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Comments', async () => {
		await visitAdminPage( '/edit-comments.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Widgets', async () => {
		await visitAdminPage( '/widgets.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Menus', async () => {
		await visitAdminPage( '/nav-menus.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Plugins', async () => {
		await visitAdminPage( '/plugins.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'All Users', async () => {
		await visitAdminPage( '/users.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Add New User', async () => {
		await visitAdminPage( '/user-new.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Your Profile', async () => {
		await visitAdminPage( '/profile.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Available Tools', async () => {
		await visitAdminPage( '/tools.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Import', async () => {
		await visitAdminPage( '/import.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Export', async () => {
		await visitAdminPage( '/export.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Export Personal Data', async () => {
		await visitAdminPage( '/export-personal-data.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Erase Personal Data', async () => {
		await visitAdminPage( '/erase-personal-data.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Reading Settings', async () => {
		await visitAdminPage( '/options-reading.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Discussion Settings', async () => {
		await visitAdminPage( '/options-discussion.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Media Settings', async () => {
		await visitAdminPage( '/options-media.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );

	it( 'Privacy Settings', async () => {
		await visitAdminPage( '/options-privacy.php' );
		const image = await page.screenshot(screenshotOptions);
    	expect(image).toMatchImageSnapshot();
	} );
} );