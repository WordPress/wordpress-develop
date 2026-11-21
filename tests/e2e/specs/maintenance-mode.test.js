/**
 * External dependencies
 */
import { writeFileSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Maintenance mode', () => {
	const documentRoot = join(
		process.cwd(),
		process.env.LOCAL_DIR ?? 'src',
	);
	const maintenanceLockFile = join( documentRoot, '.maintenance' );

	test.beforeAll( async () => {
		writeFileSync( maintenanceLockFile, '<?php $upgrading = 10000000000; ?>' ); // Year 2286.
	} );

	test.afterAll( async ( { request } ) => {
		unlinkSync( maintenanceLockFile );

		// A Docker file share can report a deleted file as present for about a
		// second, and require() then fails in wp_is_maintenance_mode().
		await expect( async () => {
			const response = await request.get( '/' );
			await expect( response ).toBeOK();
			expect( await response.text() ).not.toContain( '.maintenance' );
		}, 'should leave maintenance mode after the lock file is deleted' ).toPass( { timeout: 10_000 } );
	} );

	test( 'should display maintenance mode page', async ( { page } ) => {
		await page.goto( '/' );
		await expect(
			page.getByText( /Briefly unavailable for scheduled maintenance\. Check back in a minute\./ )
		).toBeVisible();
	} );
} );
