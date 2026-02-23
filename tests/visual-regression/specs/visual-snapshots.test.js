/**
 * Visual regression tests for WordPress admin screens.
 *
 * Each entry in the `pages` array generates a test that navigates to the page,
 * waits for stability, and takes a full-page screenshot compared against a
 * baseline snapshot.
 *
 * To add a new page, append an entry to the `pages` array. If the page
 * contains dynamic content not already covered by screenshot.css, add a
 * `masks` array of CSS selectors for those elements.
 *
 * Tests are grouped by `section` (matching the admin menu) so the HTML
 * report shows collapsible groups instead of a flat list.
 *
 * @see tests/visual-regression/config/screenshot.css for globally hidden elements.
 * @see tests/visual-regression/playwright.config.js for snapshot settings.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Waits for network activity, fonts, and jQuery animations to settle.
 *
 * @param {import('@playwright/test').Page} page
 */
async function waitForPageReady( page ) {
	await page.waitForLoadState( 'load' );

	// Wait for in-flight requests (AJAX heartbeat, dashboard widgets) to
	// finish. The 5 s timeout keeps the suite moving when a long-poll
	// endpoint (e.g. heartbeat-tick) holds the connection open.
	await page
		.waitForLoadState( 'networkidle', { timeout: 5000 } )
		.catch( () => {} );

	// If a webfont fails to load (network issue, Docker DNS), this resolves
	// with fallback fonts and the diff will surface the discrepancy.
	await page.evaluate( () => document.fonts.ready );

	// Wait for jQuery animations (e.g. dashboard widget slide-in) to
	// complete. CSS animations are already disabled in the Playwright config,
	// but jQuery .animate() bypasses that setting.
	await page.evaluate( () => {
		if ( typeof jQuery === 'undefined' ) {
			return;
		}
		return new Promise( ( resolve ) => {
			if ( jQuery.active === 0 && jQuery( ':animated' ).length === 0 ) {
				resolve();
				return;
			}
			const interval = setInterval( () => {
				if ( jQuery.active === 0 && jQuery( ':animated' ).length === 0 ) {
					clearInterval( interval );
					resolve();
				}
			}, 100 );
			// Safety valve: resolve after 10 s so a stuck animation or
			// unresolved AJAX request doesn't hang the entire suite.
			setTimeout( () => {
				clearInterval( interval );
				resolve();
			}, 10000 );
		} );
	} );

	// Blur any focused element to prevent non-deterministic focus-ring
	// diffs. Some admin pages auto-focus an input on load (e.g. Tags
	// focuses the Name field); whether the focus ring is captured depends
	// on timing, so removing it makes screenshots stable.
	await page.evaluate( () => {
		if (
			document.activeElement &&
			document.activeElement !== document.body
		) {
			document.activeElement.blur();
		}
	} );
}

/**
 * @typedef  {Object}   PageEntry
 * @property {string}   section               Admin menu section (used as the describe group name).
 * @property {string}   name                  Display name used as the test title and snapshot filename.
 * @property {string}   path                  Admin-relative URL path (e.g. '/edit.php').
 * @property {string | ( data: * ) => string} [query] Query string appended to path.
 *           When a function, it receives the return value of `setup`.
 * @property {string[]} [masks]               CSS selectors for elements to mask in the screenshot.
 * @property {( requestUtils: Object ) => Promise<*>} [setup]
 *           Called before navigation. Return value is forwarded to `query` (if a function) and `teardown`.
 * @property {( requestUtils: Object, data: * ) => Promise<void>} [teardown]
 *           Called after the screenshot assertion (in a `finally` block) to clean up resources created by `setup`.
 */

/**
 * Admin pages to capture, ordered by admin menu section.
 *
 * Convention: use screenshot.css for elements that appear on many pages
 * (admin bar, footer, notices); use masks here for page-specific volatility.
 */
const pages = [
	// -- Dashboard --
	{
		section: 'Dashboard',
		name: 'Dashboard',
		path: '/index.php',
		masks: [
			// Health status varies by environment and installed plugins.
			'#dashboard_site_health',
			// Welcome panel references recent posts — content changes when
			// parallel workers create/delete test data.
			'#welcome-panel',
			// Quick Draft shows recent draft titles which may vary.
			'#dashboard_quick_press .inside',
		],
	},
	{
		section: 'Dashboard',
		name: 'Updates',
		path: '/update-core.php',
		masks: [
			// Available updates and version numbers change per environment.
			'form.upgrade',
			'.last-checked',
		],
	},

	// -- Posts --
	{
		section: 'Posts',
		name: 'All Posts',
		path: '/edit.php',
		masks: [
			// Row count and content vary when parallel workers create test posts.
			'.wp-list-table',
			'.subsubsub',
			'.displaying-num',
		],
	},
	{
		section: 'Posts',
		name: 'Add New Post',
		path: '/post-new.php',
		masks: [
			// Block editor canvas — content, cursor position, and block
			// selection state vary between runs.
			'.editor-visual-editor',
		],
	},
	{
		section: 'Posts',
		name: 'Edit Post',
		path: '/post.php',
		query: ( data ) => `post=${ data.id }&action=edit`,
		masks: [
			// Block editor canvas — content and selection state vary.
			'.editor-visual-editor',
		],
		setup: async ( requestUtils ) =>
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/posts',
				data: {
					title: 'Visual Regression Test Post',
					content: 'Test content for visual regression.',
					status: 'publish',
				},
			} ),
		teardown: async ( requestUtils, data ) =>
			// force: true bypasses the trash — permanently deletes the post.
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/posts/${ data.id }`,
				params: { force: true },
			} ),
	},
	{ section: 'Posts', name: 'Categories', path: '/edit-tags.php', query: 'taxonomy=category' },
	{
		section: 'Posts',
		name: 'Tags',
		path: '/edit-tags.php',
		query: 'taxonomy=post_tag',
		masks: [
			// Tag list content can shift due to notice presence/absence
			// and focus-state timing on form inputs.
			'.wp-list-table',
		],
	},

	// -- Media --
	{ section: 'Media', name: 'Media Library', path: '/upload.php' },
	{ section: 'Media', name: 'Add Media',    path: '/media-new.php' },

	// -- Pages --
	{ section: 'Pages', name: 'All Pages', path: '/edit.php', query: 'post_type=page' },
	{
		section: 'Pages',
		name: 'Add New Page',
		path: '/post-new.php',
		query: 'post_type=page',
		masks: [
			// Block editor canvas — content and block state vary.
			'.editor-visual-editor',
		],
	},
	{
		section: 'Pages',
		name: 'Edit Page',
		path: '/post.php',
		query: ( data ) => `post=${ data.id }&action=edit`,
		masks: [
			// Block editor canvas — content and selection state vary.
			'.editor-visual-editor',
		],
		setup: async ( requestUtils ) =>
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/pages',
				data: {
					title: 'Visual Regression Test Page',
					content: 'Test content for visual regression.',
					status: 'publish',
				},
			} ),
		teardown: async ( requestUtils, data ) =>
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ data.id }`,
				params: { force: true },
			} ),
	},

	// -- Comments --
	{ section: 'Comments', name: 'Comments', path: '/edit-comments.php' },

	// -- Appearance --
	{
		section: 'Appearance',
		name: 'Themes',
		path: '/themes.php',
		masks: [
			// Theme screenshot images differ across environments.
			'.theme-screenshot img',
		],
	},
	{ section: 'Appearance', name: 'Widgets',           path: '/widgets.php' },
	{ section: 'Appearance', name: 'Menus',             path: '/nav-menus.php' },
	{ section: 'Appearance', name: 'Theme File Editor', path: '/theme-editor.php', masks: [ '#newcontent' ] },

	// -- Plugins --
	{
		section: 'Plugins',
		name: 'Plugins',
		path: '/plugins.php',
		masks: [
			// Version numbers and author URIs change with plugin updates.
			'.plugin-version-author-uri',
		],
	},
	{
		section: 'Plugins',
		name: 'Add New Plugin',
		path: '/plugin-install.php',
		masks: [
			// Plugin cards show external content (descriptions, ratings,
			// download counts) that changes frequently. Masking all cards
			// means this test only verifies the page shell — search bar,
			// header tabs, and pagination layout.
			'.plugin-card',
		],
	},
	{ section: 'Plugins', name: 'Plugin File Editor', path: '/plugin-editor.php', masks: [ '#newcontent' ] },

	// -- Users --
	{ section: 'Users', name: 'All Users', path: '/users.php' },
	{
		section: 'Users',
		name: 'Add User',
		path: '/user-new.php',
		masks: [
			// Auto-generated password is random on every page load.
			'.password-input-wrapper',
		],
	},
	{ section: 'Users', name: 'Your Profile', path: '/profile.php' },

	// -- Tools --
	{ section: 'Tools', name: 'Available Tools',      path: '/tools.php' },
	{ section: 'Tools', name: 'Import',               path: '/import.php' },
	{ section: 'Tools', name: 'Export',               path: '/export.php' },
	{ section: 'Tools', name: 'Export Personal Data', path: '/export-personal-data.php' },
	{ section: 'Tools', name: 'Erase Personal Data',  path: '/erase-personal-data.php' },
	{
		section: 'Tools',
		name: 'Site Health',
		path: '/site-health.php',
		masks: [
			// Health check results depend on server config and plugins.
			'.site-health-issues .health-check-accordion',
			'.site-status-all-clear',
			'.site-health-progress',
		],
	},

	// -- Settings --
	{
		section: 'Settings',
		name: 'General Settings',
		path: '/options-general.php',
		masks: [
			// Timezone dropdown value depends on server config.
			'td:has(> #timezone_string)',
			'.timezone-info',
		],
	},
	{ section: 'Settings', name: 'Writing Settings',    path: '/options-writing.php' },
	{ section: 'Settings', name: 'Reading Settings',    path: '/options-reading.php' },
	{ section: 'Settings', name: 'Discussion Settings', path: '/options-discussion.php' },
	{ section: 'Settings', name: 'Media Settings',      path: '/options-media.php' },
	{ section: 'Settings', name: 'Permalink Settings',  path: '/options-permalink.php' },
	{ section: 'Settings', name: 'Privacy Settings',    path: '/options-privacy.php' },
];

// Group pages by section for nested test.describe blocks.
// Uses insertion order so the report mirrors the admin menu.
const sections = pages.reduce( ( acc, entry ) => {
	if ( ! acc[ entry.section ] ) {
		acc[ entry.section ] = [];
	}
	acc[ entry.section ].push( entry );
	return acc;
}, /** @type {Record<string, PageEntry[]>} */ ( {} ) );

test.describe( 'Admin Visual Snapshots', () => {
	for ( const [ sectionName, sectionPages ] of Object.entries( sections ) ) {
		test.describe( sectionName, () => {
			for ( const {
				name,
				path,
				query,
				masks,
				setup,
				teardown,
			} of sectionPages ) {
				test( name, async ( { admin, page, requestUtils } ) => {
					const data = setup
						? await setup( requestUtils )
						: undefined;

					try {
						const resolvedQuery =
							typeof query === 'function'
								? query( data )
								: query;

						await admin.visitAdminPage( path, resolvedQuery );
						await waitForPageReady( page );

						let screenshotOptions = {};
						if ( Array.isArray( masks ) ) {
							const locators = masks.map( ( s ) =>
								page.locator( s )
							);

							// Warn when a mask selector matches nothing — the volatile
							// element may have been removed or renamed, causing false diffs.
							for ( let i = 0; i < locators.length; i++ ) {
								const count =
									await locators[ i ].count();
								if ( count === 0 ) {
									// eslint-disable-next-line no-console
									console.warn(
										`[${ name }] mask selector "${ masks[ i ] }" matched 0 elements`
									);
								}
							}

							screenshotOptions = { mask: locators };
						}

						await expect( page ).toHaveScreenshot(
							`${ name }.png`,
							screenshotOptions
						);
					} finally {
						if ( teardown ) {
							try {
								await teardown(
									requestUtils,
									data
								);
							} catch ( err ) {
								// Log but don't mask the original assertion failure.
								// eslint-disable-next-line no-console
								console.error(
									`[${ name }] teardown failed:`,
									err.message
								);
							}
						}
					}
				} );
			}
		} );
	}
} );

test.describe( 'Unauthenticated Visual Snapshots', () => {
	// Clear authentication so the login page is captured as a logged-out user.
	// Must be an empty object — omitting storageState entirely inherits the
	// authenticated state from the parent config.
	test.use( { storageState: {} } );

	test( 'Login', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await waitForPageReady( page );
		await expect( page ).toHaveScreenshot( 'Login.png' );
	} );
} );
