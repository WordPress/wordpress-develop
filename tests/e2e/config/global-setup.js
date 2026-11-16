/**
 * External dependencies
 */
import { rmSync } from 'node:fs';
import { join } from 'node:path';
import { request } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 *
 * @param {import('@playwright/test').FullConfig} config
 * @returns {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	// An interrupted run can leave these behind, and each one breaks the whole site.
	const documentRoot = join( process.cwd(), process.env.LOCAL_DIR ?? 'src' );
	rmSync( join( documentRoot, '.maintenance' ), { force: true } );
	rmSync( join( documentRoot, 'wp-content/mu-plugins/fatal-error.php' ), {
		force: true,
	} );

	const requestContext = await request.newContext( {
		baseURL,
	} );

	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
	} );

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	/*
	 * Switch the theme before the other resets. switch_theme() updates the `template`
	 * and `stylesheet` options one at a time, so a request that loads between the two
	 * sees a mismatched theme pair and can fail with a PHP error.
	 */
	await requestUtils.activateTheme( 'twentytwentyone' );

	// Reset the test environment before running the tests.
	await Promise.all( [
		requestUtils.deleteAllPosts(),
		requestUtils.deleteAllBlocks(),
		requestUtils.resetPreferences(),
	] );

	await requestContext.dispose();
}

export default globalSetup;
