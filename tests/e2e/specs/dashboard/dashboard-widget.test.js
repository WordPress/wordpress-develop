/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Dashboard widget management', () => {
	/**
	 * Validates the visibility of widgets based on "Screen Options" checkboxes dynamically.
	 *
	 * @param {object} page - Playwright's page object.
	 * @param {boolean} checkedStatus - Whether the checkbox should be checked
	 */
	async function validateWidgetVisibility( page, checkedStatus ) {
		const checkboxes = page.locator(
			`${ '.metabox-prefs-container' } input[type="checkbox"]`
		);
		const count = await checkboxes.count();

		for ( let i = 0; i < count; i++ ) {
			const checkbox = checkboxes.nth( i );
			const label = await checkbox
				.locator( 'xpath=ancestor::label' )
				.textContent(); // Get associated label text
			const isChecked = await checkbox.isChecked();

			const widgetHeadingText = label.trim();
			// Skip if the label is "Welcome"
			if ( label.trim() === 'Welcome' ) {
				continue; // Skip the current iteration and move to the next checkbox
			}
			const widgetHeading = page.locator(
				`h2.hndle.ui-sortable-handle:has-text("${ widgetHeadingText }")`
			);

			if ( checkedStatus ) {
				if ( ! isChecked ) {
					await checkbox.check(); // Click to check
					await expect( widgetHeading ).toBeVisible();
				} else {
					await expect( widgetHeading ).toBeVisible();
				}
			} else {
				if ( isChecked ) {
					await checkbox.uncheck(); // Click to check
					await expect( widgetHeading ).toBeHidden();
				} else {
					await expect( widgetHeading ).toBeHidden();
				}
			}
		}
	}

	async function showScreenOptions( page ) {
		// Ensure all widgets are visible
		const screenOptionsTab = page.locator( '#show-settings-link' );
		await screenOptionsTab.click();
	}

	/**
	 * Moves a widget up or down and verifies the movement.
	 *
	 * @param {Page} page - The Playwright page object.
	 * @param {string} direction - The direction to move the widget ("up" or "down").
	 */
	async function moveWidget( page, direction ) {
		const widgetSelector = '#dashboard_right_now';
		const buttonSelector = direction === 'down' ? 'Move down' : 'Move up';

		const button = page
			.locator( widgetSelector )
			.getByRole( 'button', { name: buttonSelector } );

		// Ensure the button is visible
		await expect( button ).toBeVisible();

		// Get the initial position
		const initialPosition = await page
			.locator( widgetSelector )
			.boundingBox()
			.then( ( box ) => box.y );

		// Click the button
		await button.click();

		// Wait for the movement to complete
		await page.waitForResponse(
			( response ) =>
				response.url().includes( 'admin-ajax.php' ) &&
				response.status() === 200
		);

		// Get the new position
		const newPosition = await page
			.locator( widgetSelector )
			.boundingBox()
			.then( ( box ) => box.y );

		// Assert based on direction
		if ( direction === 'down' ) {
			expect( newPosition ).toBeGreaterThan( initialPosition );
		} else {
			expect( newPosition ).toBeLessThan( initialPosition );
		}
	}

	test.beforeEach( async ( { admin } ) => {
		// Navigate to the admin dashboard
		await admin.visitAdminPage( '/' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Navigate to the admin dashboard
		await requestUtils.deleteAllPosts();
	} );
	test( 'hide/show widgets using Screen Options', async ( { page } ) => {
		await showScreenOptions( page );

		// Verify if widget is checked in screen option it should be visible
		await validateWidgetVisibility( page, true );
		// Verify if widget is unchecked in screen option it should not be visible
		await validateWidgetVisibility( page, false );
	} );

	test( 'moves widgets using arrows', async ( { page, admin } ) => {
		// Ensure all widgets are visible
		await showScreenOptions( page );
		await validateWidgetVisibility(
			page,
			'.metabox-prefs-container',
			true
		);

		await moveWidget( page, 'down' );
		await moveWidget( page, 'up' );
	} );

	test( 'collapses and expands widgets', async ( { page, admin } ) => {
		const widget = page.locator(
			'div#postbox-container-1 #normal-sortables .postbox:first-of-type'
		);

		const collapseButton = widget.locator( 'button.handlediv' );
		const content = widget.locator( '.inside' );

		// Collapse the widget
		await collapseButton.click();
		await expect( content ).not.toBeVisible();

		// Expand the widget
		await collapseButton.click();
		await expect( content ).toBeVisible();
	} );

	test( 'adds a quick draft in widgets', async ( { page, admin } ) => {
		// Locate the Quick Draft widget
		const quickDraftWidget = page.locator( '#dashboard_quick_press' );
		const collapseButton = quickDraftWidget.locator( 'button.handlediv' );
		const content = quickDraftWidget.locator( '.inside' );

		// Collapse the Quick Draft widget
		await collapseButton.click();
		await expect( content ).not.toBeVisible();

		// Expand the Quick Draft widget
		await collapseButton.click();
		await expect( content ).toBeVisible();

		// Add a quick draft
		const randomString = Math.random().toString( 36 ).substring( 2, 10 );
		const draftTitle = `Draft Title ${ randomString }`;
		const draftContent = `Draft Content ${ randomString }`;

		await quickDraftWidget.locator( '#title' ).fill( draftTitle );
		await quickDraftWidget.locator( '#content' ).fill( draftContent );
		await quickDraftWidget.locator( '#save-post' ).click();

		// Verify the draft appears in the Recent Drafts section
		const recentDrafts = page.locator( 'div.drafts div.draft-title' );
		await expect( recentDrafts ).toContainText( draftTitle );
	} );
} );
