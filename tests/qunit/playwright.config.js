/**
 * Playwright configuration for QUnit tests.
 */
const path = require( 'path' );
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: __dirname,
	outputDir: path.join( __dirname, '..', '..', 'artifacts', 'test-results' ),
	testMatch: 'qunit.js',
	timeout: 30_000,
	workers: 1,
	use: {
		headless: true,
		/* The system Chrome channel avoids a browser download in CI. */
		channel: process.env.CI ? 'chrome' : undefined,
	},
	reporter: process.env.CI ? 'github' : 'list',
} );
