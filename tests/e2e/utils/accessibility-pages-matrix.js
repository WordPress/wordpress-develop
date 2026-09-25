/**
 * Accessibility test pages matrix.
 *
 * Defines WordPress admin pages to scan for accessibility violations.
 * Each page spec can have multiple state variants for testing different UI states.
 *
 * OVERVIEW:
 * - `id`: Unique identifier for the page (used for logging and filtering)
 * - `path`: WordPress admin path relative to /wp-admin/ (e.g., '/upload.php?mode=grid')
 * - `name`: Human-readable name (shown in test output)
 * - `rules`: (optional) Per-page axe rules override. Merged with global rules.
 * - `waitInterval`: (optional) Boolean indicating if the test should wait for a
 *                   short interval before scanning. This is particularly important
 *                   for pages rendered via React components like the Settings > Connectors
 *                   page or the Fonts page. Without this wait, the scan may run before all
 *                   content is fully rendered.
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
 * const { pages } = require( './accessibility-pages-matrix' );
 * pages.forEach( (pageSpec) => { ... } );
 */

const pages = [
	// Posts & Pages.
	{
		id: 'posts-revisions',
		path: '/post-new.php',
		name: 'Posts Revisions',
		waitInterval: true,
		stateVariants: [
			{
				name: 'default',
				setup: async ( page, editor ) => {

					await editor.setPreferences( 'core/edit-post', {
						welcomeGuide: false,
						fullscreenMode: true,
					} );

					await editor.insertBlock( {
						name: 'core/paragraph',
						attributes: {
							content:
								'First paragraph',
						},
					} );

					// Save draft to create first revision.
					await editor.saveDraft();

					await editor.insertBlock( {
						name: 'core/spacer',
						attributes: { height: '100px' },
					} );

					// Save draft again to create second revision.
					await editor.saveDraft();

					await editor.insertBlock( {
						name: 'core/paragraph',
						attributes: {
							content: 'Second paragraph',
						},
					} );

					// Save draft again to create third revision.
					await editor.saveDraft();

					// await page.goto( `/?p=${ postId }#target` );

					// Open revisions.
					await editor.openDocumentSettingsSidebar();
					const settingsSidebar = page.getByRole( 'region', {
						name: 'Editor settings',
					} );
					await settingsSidebar.getByRole( 'tab', { name: 'Post' } ).click();
					await settingsSidebar
						.getByRole( 'button', {
							name: 'Open revisions screen: 3 revisions',
						} )
						.click();
				}
			},
			{
				name: 'previous-revision',
				setup: async ( page, editor ) => {
					await editor.setPreferences( 'core/edit-post', {
						welcomeGuide: false,
						fullscreenMode: true,
					} );

					await editor.insertBlock( {
						name: 'core/paragraph',
						attributes: {
							content:
								'First paragraph',
						},
					} );

					// Save draft to create first revision.
					await editor.saveDraft();

					await editor.insertBlock( {
						name: 'core/spacer',
						attributes: { height: '100px' },
					} );

					// Save draft again to create second revision.
					await editor.saveDraft();

					await editor.insertBlock( {
						name: 'core/paragraph',
						attributes: {
							content: 'Second paragraph',
						},
					} );

					// Save draft again to create third revision.
					await editor.saveDraft();

					// await page.goto( `/?p=${ postId }#target` );

					// Open revisions.
					await editor.openDocumentSettingsSidebar();
					const settingsSidebar = page.getByRole( 'region', {
						name: 'Editor settings',
					} );
					await settingsSidebar.getByRole( 'tab', { name: 'Post' } ).click();
					await settingsSidebar
						.getByRole( 'button', {
							name: 'Open revisions screen: 3 revisions',
						} )
						.click();

					const revisonSlider = page.getByRole( 'slider', {
						name: 'Revision',
					} );
					// Focus the revison slider.
					await revisonSlider.focus();
					// Move to previous revision.
					await page.keyboard.press( 'ArrowLeft' );
					await page.waitForTimeout( 500 );
				}
			},
		],
	},
];

module.exports = {
	pages,
};
