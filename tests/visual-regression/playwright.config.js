/**
 * Playwright config for visual regression tests.
 *
 * Captures full-page screenshots of WordPress admin screens and compares
 * them against baseline snapshots. Intended for local use to catch
 * unintended visual changes during development.
 *
 * Usage:
 *   npm run test:visual -- --update-snapshots   # generate baselines
 *   npm run test:visual                         # compare against baselines
 *
 * @see tests/visual-regression/config/screenshot.css for globally hidden elements.
 * @see tests/visual-regression/specs/visual-snapshots.test.js for the test spec.
 */

/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig } from '@playwright/test';

/**
 * WordPress dependencies
 */
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

process.env.WP_ARTIFACTS_PATH ??= path.join( process.cwd(), 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states/admin.json'
);

// Reporters:
// - 'list'   — prints pass/fail per test in the terminal.
// - 'github' — adds inline PR annotations when running in CI.
// - 'html'   — generates a visual report with side-by-side diff images;
//              opens automatically after local runs.
const reporter = [
	[ 'list' ],
	...( process.env.CI ? [ [ 'github' ] ] : [] ),
	[
		'html',
		{
			open: process.env.CI ? 'never' : 'always',
			outputFolder: path.join(
				process.env.WP_ARTIFACTS_PATH,
				'visual-report'
			),
		},
	],
];

const config = defineConfig( {
	...baseConfig,
	fullyParallel: true,
	// No retries — visual diffs are expected when regressions exist;
	// retrying would just re-confirm the same diff.
	retries: 0,
	// Serialize tests in CI to reduce flakiness from resource contention.
	workers: process.env.CI ? 1 : undefined,
	reporter,
	use: {
		...baseConfig.use,
		viewport: { width: 1280, height: 720 },
	},
	expect: {
		toHaveScreenshot: {
			// Only disables CSS animations/transitions. JavaScript-driven
			// animations (e.g. jQuery .animate()) can still cause flakes.
			animations: 'disabled',
			// Captures the entire scrollable page, not just the viewport.
			// The viewport width (1280) still matters — it controls layout.
			fullPage: true,
			// 1% tolerance — catches real regressions while ignoring
			// sub-pixel anti-aliasing differences across environments.
			maxDiffPixelRatio: 0.01,
			stylePath: path.join( __dirname, 'config', 'screenshot.css' ),
		},
	},
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:start',
	},
} );

export default config;
