/**
 * External dependencies
 */
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Fatal error handler', () => {
	const muPlugins = join(
		process.cwd(),
		process.env.LOCAL_DIR ?? 'src',
		'wp-content/mu-plugins'
	);
	const muPluginFile = join( muPlugins, 'fatal-error.php' );

	test.beforeAll( async () => {
		const muPluginCode = `<?php new NonExistentClass();`;

		if ( ! existsSync( muPlugins ) ) {
			mkdirSync( muPlugins, { recursive: true } );
		}
		writeFileSync( muPluginFile, muPluginCode );
	} );

	test.afterAll( async () => {
		unlinkSync( muPluginFile );
	} );

	test( 'should display fatal error notice', async ( { admin, page } ) => {
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
