/**
 * External dependencies
 */
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Maintenance mode', () => {
	const documentRoot = join(
		process.cwd(),
		process.env.LOCAL_DIR ?? 'src',
		'wp-content/mu-plugins'
	);
	const maintenanceLockFile = join( documentRoot, '.maintenance' );

	test.beforeAll( async () => {
		writeFileSync( maintenanceLockFile, '<?php $upgrading = 1700000000; ?>' );
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
