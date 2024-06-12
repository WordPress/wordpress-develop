import { visitAdminPage } from '@wordpress/e2e-test-utils';

// See https://github.com/puppeteer/puppeteer/blob/main/docs/api.md#pagescreenshotoptions for more available options.
const screenshotOptions = {
	fullPage: true,
};

async function hideElementVisibility( elements ) {
	for ( let i = 0; i < elements.length; i++ ) {
		const elementOnPage = await page.$( elements[ i ] );
		if ( elementOnPage ) {
			await elementOnPage.evaluate( ( el ) => {
				el.style.visibility = 'hidden';
			} );
		}
	}
	await page.waitFor( 1000 );
}

async function removeElementFromLayout( elements ) {
	for ( let i = 0; i < elements.length; i++ ) {
		const elementOnPage = await page.$( elements[ i ] );
		if ( elementOnPage ) {
			await elementOnPage.evaluate( ( el ) => {
				el.style.visibility = 'hidden';
			} );
		}
	}
	await page.waitFor( 1000 );
}

const elementsToHide = [ '#footer-upgrade', '#wp-admin-bar-root-default' ];

const elementsToRemove = [ '#toplevel_page_gutenberg' ];

describe( 'Admin Visual Snapshots', () => {
	beforeAll( async () => {
		await page.setViewport( {
			width: 1000,
			height: 750,
		} );
	} );

	it( 'All Posts', async () => {
		await visitAdminPage( '/edit.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Categories', async () => {
		await visitAdminPage( '/edit-tags.php', 'taxonomy=category' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Tags', async () => {
		await visitAdminPage( '/edit-tags.php', 'taxonomy=post_tag' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Media Library', async () => {
		await visitAdminPage( '/upload.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Add New Media', async () => {
		await visitAdminPage( '/media-new.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'All Pages', async () => {
		await visitAdminPage( '/edit.php', 'post_type=page' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Comments', async () => {
		await visitAdminPage( '/edit-comments.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Widgets', async () => {
		await visitAdminPage( '/widgets.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Menus', async () => {
		await visitAdminPage( '/nav-menus.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Plugins', async () => {
		await visitAdminPage( '/plugins.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'All Users', async () => {
		await visitAdminPage( '/users.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Add New User', async () => {
		await visitAdminPage( '/user-new.php' );
		await hideElementVisibility( [
			...elementsToHide,
			'.password-input-wrapper',
		] );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Your Profile', async () => {
		await visitAdminPage( '/profile.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Available Tools', async () => {
		await visitAdminPage( '/tools.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Import', async () => {
		await visitAdminPage( '/import.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Export', async () => {
		await visitAdminPage( '/export.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Export Personal Data', async () => {
		await visitAdminPage( '/export-personal-data.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Erase Personal Data', async () => {
		await visitAdminPage( '/erase-personal-data.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Reading Settings', async () => {
		await visitAdminPage( '/options-reading.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Discussion Settings', async () => {
		await visitAdminPage( '/options-discussion.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Media Settings', async () => {
		await visitAdminPage( '/options-media.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );

	it( 'Privacy Settings', async () => {
		await visitAdminPage( '/options-privacy.php' );
		await hideElementVisibility( elementsToHide );
		await removeElementFromLayout( elementsToRemove );
		const image = await page.screenshot( screenshotOptions );
		expect( image ).toMatchImageSnapshot();
	} );
} );
