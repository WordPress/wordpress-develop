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

	test.describe( 'Default (en_US)', () => {
		test( 'should display maintenance mode page', async ( { page } ) => {
			await page.goto( '/' );
			await expect(
				page.getByText( /Briefly unavailable for scheduled maintenance\. Check back in a minute\./ )
			).toBeVisible();
		} );
	} );

	test.describe( 'Localized (de_DE)', () => {
		test.use( { locale: 'de-DE' } ); // Sets the Accept-Language header.

		test( 'should display maintenance mode page', async ( { page } ) => {
			await page.goto( '/' );
			await expect(
				page.getByText( /Wegen Wartungsarbeiten ist diese Website kurzzeitig nicht verfügbar/ )
			).toBeVisible();
		} );
	} );
} );
