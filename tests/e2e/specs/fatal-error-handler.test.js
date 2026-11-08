/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

test.describe( 'Fatal error handler', () => {
	test( 'should display fatal error notice', async ( { admin, page, muPlugin } ) => {
		await muPlugin.write( 'fatal-error.php', `<?php new NonExistentClass();` );

		await admin.visitAdminPage( '/' );

		await expect(
			page.getByText( /Fatal error:/ ),
			'should display PHP error message'
		).toBeVisible();

		await expect(
			page.getByText( /There has been a critical error on this website/ ),
			'should display WordPress fatal error handler message'
		).toBeVisible();
	} );
} );
