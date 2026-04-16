/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig } from '@playwright/test';

/**
 * WordPress dependencies
 */
import baseConfig from '@wordpress/scripts/config/playwright.config';

process.env.WP_ARTIFACTS_PATH ??= path.join( process.cwd(), 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);
process.env.TEST_RUNS ??= '20';

const config = defineConfig( {
	...baseConfig,
	globalSetup: require.resolve( './config/global-setup.js' ),
	reporter: [ [ 'list' ], [ './config/performance-reporter.js' ] ],
	forbidOnly: !! process.env.CI,
	workers: 1,
	retries: 0,
	repeatEach: 2,
	timeout: parseInt( process.env.TIMEOUT || '', 10 ) || 600_000, // Defaults to 10 minutes.
	// Don't report slow test "files", as we will be running our tests in serial.
	reportSlowTests: null,
	preserveOutput: 'never',
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:start',
	},
	use: {
		...baseConfig.use,
		video: 'off',
	},
} );

export default config;
