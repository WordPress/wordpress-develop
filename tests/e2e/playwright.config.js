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
	'themes.test.js',
	// These delete all posts or media, including other workers' content.
	'cache-control-headers-directives.test.js',
	'dashboard.test.js',
	'edit-posts.test.js',
	'empty-trash-restore-trashed-posts.test.js',
	'media-library-client-side-upload.test.js',
	'media-modal-client-side-upload.test.js',
	'media-new-client-side-upload.test.js',
];

const config = defineConfig( {
	...baseConfig,
	globalSetup: require.resolve( './config/global-setup.js' ),
	workers: '50%',
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:start',
	},
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
			fullyParallel: false,
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
