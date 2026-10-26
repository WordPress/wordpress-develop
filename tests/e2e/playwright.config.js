/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

/**
 * WordPress dependencies
 */
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

process.env.WP_ARTIFACTS_PATH ??= path.join( process.cwd(), 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

const SERIAL_SPECS = [
	'install.test.js',
	'maintenance-mode.test.js',
	'fatal-error-handler.test.js',
	'login-localize-script.test.js',
	'gutenberg-plugin.test.js',
	'plugins.test.js',
];

const config = defineConfig( {
	...baseConfig,
	globalSetup: require.resolve( './config/global-setup.js' ),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:start',
	},
	projects: [
		{
			name: 'parallel',
			testIgnore: SERIAL_SPECS,
			fullyParallel: false,
			workers: process.env.CI ? 4 : '50%',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'serial',
			testMatch: SERIAL_SPECS,
			fullyParallel: false,
			workers: 1,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );

export default config;
