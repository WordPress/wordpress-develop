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

	test.afterAll( async () => {
		unlinkSync( maintenanceLockFile );
	} );

	test( 'should display maintenance mode page', async ( { page } ) => {
		await page.goto( '/' );
		await expect(
			page.getByText( /Briefly unavailable for scheduled maintenance\. Check back in a minute\./ )
		).toBeVisible();
	} );
} );
