/**
 * Accessibility test pages registry.
 *
 * Defines WordPress admin pages to scan for accessibility violations.
 * Each page spec can have multiple state variants for testing different UI states.
 *
 * OVERVIEW:
 * - `id`: Unique identifier for the page (used for logging and filtering)
 * - `path`: WordPress admin path relative to /wp-admin/ (e.g., '/upload.php?mode=grid')
 * - `name`: Human-readable name (shown in test output)
 * - `rules`: (optional) Per-page axe rules override. Merged with global rules.
 * - `stateVariants`: (optional) Array of UI states to test. Each variant has:
 *   - `name`: State identifier (shown in test name)
 *   - `setup`: (optional) Async function to set up the state. Receives (page, requestUtils) objects.
 *   - `rules`: (optional) State-specific rule override. Merged with page and global rules.
 *
 * HOW TO ADD A PAGE:
 *
 * 1. Simple page (single state):
 *    {
 *      id: 'my-page',
 *      path: '/my-page.php',
 *      name: 'My Page Name',
 *    }
 *
 * 2. Page with multiple states:
 *    {
 *      id: 'my-page-multi',
 *      path: '/my-page.php',
 *      name: 'My Page with Multiple States',
 *      stateVariants: [
 *        { name: 'default' },
 *        {
 *          name: 'with-filter',
 *          setup: async (page, requestUtils) => {
            // Create test data if needed.
            await requestUtils.createPost({ title: 'Test', status: 'draft' });
            // Apply UI state.
 *            await page.getByRole('link', { name: 'Draft' }).click();
 *            await page.waitForLoadState('networkidle');
 *          }
 *        },
 *      ]
 *    }
 *
 * HOW TO MODIFY:
 * - To disable a page from scanning, remove or comment out its entry.
 * - To add a new state variant, add another object to stateVariants.
 * - To change rule config per-page, modify the `rules` property.
 *
 * IMPORT IN TESTS:
 * const { pages } = require( './accessibility-pages' );
 * pages.forEach( (pageSpec) => { ... } );
 */

const { filterByStatus } = require( './admin-interactions' );

const pages = [
	// Media.
	{
		id: 'media-library-grid',
		path: '/upload.php?mode=grid',
		name: 'Media Library (Grid View)',
		stateVariants: [
			{
				name: 'default',
			},
			{
				name: 'image-modal-open',
				setup: async ( page ) => {
					// Click the image to open the attachment modal.
					const imageLink = page.locator( '.attachment' ).first();
					await imageLink.click();
					// Wait for modal to appear.
					await page.waitForSelector( '.media-modal' );
				},
			},
		],
	},
	{
		id: 'media-library-list',
		path: '/upload.php?mode=list',
		name: 'Media Library (List View)',
	},

	// Posts & pages.
	{
		id: 'posts-list',
		path: '/edit.php?post_type=post',
		name: 'Posts',
		stateVariants: [
			{
				name: 'default',
			},
			{
				name: 'draft-filter',
				setup: async ( page, requestUtils ) => {
					// Create a draft post so there's something to filter.
					await requestUtils.createPost( {
						title: 'Test Draft Post',
						status: 'draft',
					} );
					// Reload the page to show the draft.
					await page.reload();
				// Ensure table is visible before filtering.
					const tableVisible = await page.locator( 'table.wp-list-table' ).isVisible();
					if ( tableVisible ) {
						await filterByStatus( page, 'draft' );
					}
				},
			},
		],
	},
	{
		id: 'pages-list',
		path: '/edit.php?post_type=page',
		name: 'Pages',
	},

	// Comments.
	{
		id: 'comments-all',
		path: '/edit-comments.php',
		name: 'Comments',
	},

	// Settings.
	{
		id: 'settings-general',
		path: '/options-general.php',
		name: 'Settings - General',
	},

	{
		id: 'settings-connectors',
		path: '/options-connectors.php',
		name: 'Settings - Connectors',
	},

	{
		id: 'settings-writing',
		path: '/options-writing.php',
		name: 'Settings - Writing',
	},

	{
		id: 'settings-reading',
		path: '/options-reading.php',
		name: 'Settings - Reading',
	},

	{
		id: 'settings-discussion',
		path: '/options-discussion.php',
		name: 'Settings - Discussion',
	},

	{
		id: 'settings-media',
		path: '/options-media.php',
		name: 'Settings - Media',
	},

	{
		id: 'settings-permalinks',
		path: '/options-permalink.php',
		name: 'Settings - Permalinks',
	},

	{
		id: 'settings-privacy',
		path: '/options-privacy.php',
		name: 'Settings - Privacy',
	},

	// Users.
	{
		id: 'users-list',
		path: '/users.php',
		name: 'Users',
	},

	// Dashboard & tools.
	{
		id: 'dashboard',
		path: '/',
		name: 'Dashboard',
	},

	{
		id: 'tools-import',
		path: '/import.php',
		name: 'Tools - Import',
	},

	{
		id: 'tools-export',
		path: '/export.php',
		name: 'Tools - Export',
	},

	// Admin profile.
	{
		id: 'profile',
		path: '/profile.php',
		name: 'Profile',
	},
];

module.exports = {
	pages,
};
