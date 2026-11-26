/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

/**
 * WordPress dependencies
 */
const database = require( './config/database.js' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

const { getBaseURL, isDefaultEnvironment } = database;

const baseURL = getBaseURL();

process.env.WP_ARTIFACTS_PATH ??= path.join( process.cwd(), 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

const SERIAL_SPECS = [
	'install.test.js',
	'maintenance-mode.test.js',
	'gutenberg-plugin.test.js',
	'theme-activation.test.js',
];

const webServer = isDefaultEnvironment( baseURL )
	? {
			...baseConfig.webServer,
			command: 'npm run env:start',
			port: new URL( baseURL ).port || '80',
	  }
	: undefined;

const config = defineConfig( {
	...baseConfig,
	use: {
		...baseConfig.use,
		baseURL,
	},
	globalSetup: require.resolve( './config/global-setup.js' ),
	globalTeardown: require.resolve( './config/global-teardown.js' ),
	workers: '50%',
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : [ [ 'list' ] ],
	webServer,
	projects: [
		{
			name: 'all',
			fullyParallel: false,
			workers: 1,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'parallel',
			default: false,
			testIgnore: SERIAL_SPECS,
			fullyParallel: true,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'serial',
			default: false,
			testMatch: SERIAL_SPECS,
			fullyParallel: false,
			workers: 1,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );

export default config;
