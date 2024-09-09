/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugins.php', () => {
	test( 'Should display admin notice when bulk actions plugins updated but none selected with JS disabled #61940',
		async ( { admin, page } ) => {

			// Disable JavaScript.
			await page.route( /.*\.js.*/, ( route ) => {
				route.fulfill( { status: 200, body: '' } );
			} );

			// Open plugins.php.
			await admin.visitAdminPage( '/plugins.php' );

			// Submit the "Bulk actions" form with "Update" selected, but no plugins checked in the list.
			await page.locator( 'select#bulk-action-selector-top' ).selectOption( 'update-selected' );
			await page.locator( 'input#doaction' ).click();

			// The bug: without JS, the Update Plugins routine will start, but no plugins are selected.
			await expect(
				page.getByRole( 'heading', { name: 'Update Plugins', level: 1 } )
			).not.toBeVisible();

			// The desired behavior: an admin notice should be displayed.
			await expect(
				page.locator( '#no-items-selected' )
			).toContainText( 'Please select at least one item to perform this action on.' );
	} );
} );
