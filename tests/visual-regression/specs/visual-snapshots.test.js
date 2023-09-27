import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const elementsToHide = [
	'#footer-upgrade',
	'#wp-admin-bar-root-default',
	'#toplevel_page_gutenberg'
];

test.describe( 'Admin Visual Snapshots', () => {
	test( 'All Posts', async ({ admin, page }) => {
		await admin.visitAdminPage( '/edit.php' );
		await expect( page ).toHaveScreenshot( 'All Posts.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Categories', async ({ admin, page }) => {
		await admin.visitAdminPage( '/edit-tags.php', 'taxonomy=category' );
		await expect( page ).toHaveScreenshot( 'Categories.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Tags', async ({ admin, page }) => {
		await admin.visitAdminPage( '/edit-tags.php', 'taxonomy=post_tag' );
		await expect( page ).toHaveScreenshot( 'Tags.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Media Library', async ({ admin, page }) => {
		await admin.visitAdminPage( '/upload.php' );
		await expect( page ).toHaveScreenshot( 'Media Library.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Add New Media', async ({ admin, page }) => {
		await admin.visitAdminPage( '/media-new.php' );
		await expect( page ).toHaveScreenshot( 'Add New Media.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'All Pages', async ({ admin, page }) => {
		await admin.visitAdminPage( '/edit.php', 'post_type=page' );
		await expect( page ).toHaveScreenshot( 'All Pages.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Comments', async ({ admin, page }) => {
		await admin.visitAdminPage( '/edit-comments.php' );
		await expect( page ).toHaveScreenshot( 'Comments.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Widgets', async ({ admin, page }) => {
		await admin.visitAdminPage( '/widgets.php' );
		await expect( page ).toHaveScreenshot( 'Widgets.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Menus', async ({ admin, page }) => {
		await admin.visitAdminPage( '/nav-menus.php' );
		await expect( page ).toHaveScreenshot( 'Menus.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Plugins', async ({ admin, page }) => {
		await admin.visitAdminPage( '/plugins.php' );
		await expect( page ).toHaveScreenshot( 'Plugins.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'All Users', async ({ admin, page }) => {
		await admin.visitAdminPage( '/users.php' );
		await expect( page ).toHaveScreenshot( 'All Users.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Add New User', async ({ admin, page }) => {
		await admin.visitAdminPage( '/user-new.php' );
		await expect( page ).toHaveScreenshot( 'Add New User.png', {
			mask: [
					...elementsToHide,
					'.password-input-wrapper'
			].map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Your Profile', async ({ admin, page }) => {
		await admin.visitAdminPage( '/profile.php' );
		await expect( page ).toHaveScreenshot( 'Your Profile.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Available Tools', async ({ admin, page }) => {
		await admin.visitAdminPage( '/tools.php' );
		await expect( page ).toHaveScreenshot( 'Available Tools.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Import', async ({ admin, page }) => {
		await admin.visitAdminPage( '/import.php' );
		await expect( page ).toHaveScreenshot( 'Import.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Export', async ({ admin, page }) => {
		await admin.visitAdminPage( '/export.php' );
		await expect( page ).toHaveScreenshot( 'Export.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Export Personal Data', async ({ admin, page }) => {
		await admin.visitAdminPage( '/export-personal-data.php' );
		await expect( page ).toHaveScreenshot( 'Export Personal Data.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Erase Personal Data', async ({ admin, page }) => {
		await admin.visitAdminPage( '/erase-personal-data.php' );
		await expect( page ).toHaveScreenshot( 'Erase Personal Data.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Reading Settings', async ({ admin, page }) => {
		await admin.visitAdminPage( '/options-reading.php' );
		await expect( page ).toHaveScreenshot( 'Reading Settings.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Discussion Settings', async ({ admin, page }) => {
		await admin.visitAdminPage( '/options-discussion.php' );
		await expect( page ).toHaveScreenshot( 'Discussion Settings.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Media Settings', async ({ admin, page }) => {
		await admin.visitAdminPage( '/options-media.php' );
		await expect( page ).toHaveScreenshot( 'Media Settings.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );

	test( 'Privacy Settings', async ({ admin, page }) => {
		await admin.visitAdminPage( '/options-privacy.php' );
		await expect( page ).toHaveScreenshot( 'Privacy Settings.png', {
			mask: elementsToHide.map( ( selector ) => page.locator( selector ) ),
		});
	} );
} );
