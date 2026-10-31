/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Quick Draft', () => {
	test.beforeEach( async ({ requestUtils }) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'should allow Quick Draft to be created with Title and Content', async ( {
	   admin,
	   page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Quick Draft title field to appear.
		const draftTitleField = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Title' } );

		await expect( draftTitleField ).toBeVisible();

		// Focus and fill in a title.
		await draftTitleField.fill( 'Quick Draft test title' );

		// Wait for the Quick Draft content textarea to appear.
		const quickDraftContentTextarea = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Content' } );

		await expect( quickDraftContentTextarea ).toBeVisible();

		// Focus and fill in some content.
		await quickDraftContentTextarea.fill( 'Quick Draft test content' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that the new draft title appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( 'Quick Draft test title' );

		// Check that the new draft content appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-content' ).first()
		).toHaveText( 'Quick Draft test content' );

		// Check that the new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( 'Quick Draft test title' );
	} );

	test( 'should prevent Quick Draft from being created without Title and Content', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that an admin notice with ARIA role 'alert' appears.
		await expect(
			page.locator( '#quick-press' ).getByRole( 'alert' )
		).toHaveText( 'Cannot create a draft post with empty title and content.' );

		// Check that no new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '#the-list .no-items .colspanchange' )
		).toContainText( 'No posts found.' );
	} );

	test( 'should allow Quick Draft to be created with only the Title', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Quick Draft title field to appear.
		const quickDraftTitleField = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Title' } );

		await expect( quickDraftTitleField ).toBeVisible();

		// Focus and fill in a title.
		await quickDraftTitleField.fill( 'Quick Draft test title' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that the new draft title appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( 'Quick Draft test title' );

		// Check that the new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( 'Quick Draft test title' );
	} );

	test( 'should allow Quick Draft to be created with only the Content', async ( {
		 admin,
		 page
	} ) => {
		await admin.visitAdminPage( '/' );

		// Wait for the Quick Draft content textarea to appear.
		const quickDraftContentTextarea = page.locator(
			'#quick-press'
		).getByRole( 'textbox', { name: 'Content' } );

		await expect( quickDraftContentTextarea ).toBeVisible();

		// Focus and fill in some content.
		await quickDraftContentTextarea.fill( 'Quick Draft test content' );

		// Wait for the Save Draft button to appear and click it.
		const saveDraftButton = page.locator(
			'#quick-press'
		).getByRole( 'button', { name: 'Save Draft' } );

		await expect( saveDraftButton ).toBeVisible();
		await saveDraftButton.click();

		// Check that the new draft title appears in the 'Your Recent Drafts' section.
		await expect(
			page.locator( '.drafts .draft-title' ).first().getByRole( 'link' )
		).toHaveText( '(no title)' );

		await expect(
			page.locator( '.drafts .draft-content' ).first()
		).toHaveText( 'Quick Draft test content' );

		// Check that the new draft appears in the Posts page.
		await admin.visitAdminPage( '/edit.php' );

		await expect(
			page.locator( '.type-post.status-draft .title' ).first()
		).toContainText( '(no title)' );
	} );
} );

// Wait for the matching save before reloading, rather than racing the Ajax request.
async function saveDashboardPreference( page, action, change ) {
	const [ response ] = await Promise.all( [
		page.waitForResponse(
			( result ) =>
				new URL( result.url() ).pathname.endsWith(
					'/admin-ajax.php'
				) &&
				new URLSearchParams( result.request().postData() || '' ).get(
					'action'
				) === action
		),
		change(),
	] );
	expect( response.ok() ).toBe( true );
}

test.describe( 'Dashboard preferences', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );
	let dashboard;
	let user;

	test.beforeEach( async ( { page, requestUtils, baseURL } ) => {
		user = undefined;
		const username = `e2e-dashboard-${ randomUUID() }`;
		const password = randomUUID();
		user = await requestUtils.createUser( {
			username,
			password,
			email: `${ username }@example.org`,
			roles: [ 'administrator' ],
		} );
		dashboard = page;
		await dashboard.goto( new URL( 'wp-login.php', baseURL ).href );
		const usernameField = dashboard.getByLabel(
			'Username or Email Address',
			{ exact: true }
		);
		await expect( usernameField ).toBeFocused();
		await usernameField.fill( username );
		await dashboard
			.getByLabel( 'Password', { exact: true } )
			.fill( password );
		await dashboard
			.getByRole( 'button', { name: 'Log In', exact: true } )
			.click();
		await expect(
			dashboard.getByRole( 'heading', { name: 'Dashboard', exact: true } )
		).toBeVisible();
		// Set a known Welcome state for this user's first visit without altering shared preferences.
		const response = await dashboard.request.get(
			new URL( 'wp-admin/index.php?welcome=1', baseURL ).href
		);
		expect( response.ok() ).toBe( true );
		await response.dispose();
		await dashboard.goto( new URL( 'wp-admin/index.php', baseURL ).href );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( user ) {
			// User deletion only trashes posts; permanently delete this user's Quick Draft auto-drafts first.
			const drafts = await requestUtils.rest( {
				path: '/wp/v2/posts',
				params: { author: user.id, status: 'auto-draft', per_page: 100 },
			} );
			for ( const draft of drafts ) {
				await requestUtils.rest( {
					method: 'DELETE',
					path: `/wp/v2/posts/${ draft.id }`,
					params: { force: true },
				} );
			}
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/users/${ user.id }`,
				params: { force: true, reassign: false },
			} );
		}
	} );

	test( 'keeps the Welcome panel dismissed after reload', async () => {
		const panel = dashboard.locator( '#welcome-panel' );
		await expect( panel ).toBeVisible();
		await saveDashboardPreference( dashboard, 'update-welcome-panel', () =>
			dashboard
				.getByRole( 'link', {
					name: 'Dismiss the welcome panel',
					exact: true,
				} )
				.click()
		);
		await expect( panel ).toBeHidden();
		await dashboard.reload();
		await expect( panel ).toBeHidden();
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		await expect(
			dashboard.getByLabel( 'Welcome', { exact: true } )
		).not.toBeChecked();
	} );

	test( 'saves Welcome visibility from Screen Options', async () => {
		const panel = dashboard.locator( '#welcome-panel' );
		await expect( panel ).toBeVisible();
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		const checkbox = dashboard.getByLabel( 'Welcome', { exact: true } );
		await saveDashboardPreference( dashboard, 'update-welcome-panel', () =>
			checkbox.uncheck()
		);
		await expect( panel ).toBeHidden();
		await dashboard.reload();
		await expect( panel ).toBeHidden();
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		await expect( checkbox ).not.toBeChecked();
		await saveDashboardPreference( dashboard, 'update-welcome-panel', () =>
			checkbox.check()
		);
		await expect( panel ).toBeVisible();
		await dashboard.reload();
		await expect( panel ).toBeVisible();
	} );

	test( 'saves widget visibility from Screen Options', async () => {
		const widget = dashboard.locator( '#dashboard_right_now' );
		await expect( widget ).toBeVisible();
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		const checkbox = dashboard.getByRole( 'checkbox', {
			name: 'At a Glance',
			exact: true,
		} );
		await saveDashboardPreference( dashboard, 'closed-postboxes', () =>
			checkbox.uncheck()
		);
		await expect( widget ).toBeHidden();
		await dashboard.reload();
		await expect( widget ).toBeHidden();
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		await expect( checkbox ).not.toBeChecked();
		await saveDashboardPreference( dashboard, 'closed-postboxes', () =>
			checkbox.check()
		);
		await expect( widget ).toBeVisible();
		await dashboard.reload();
		await expect( widget ).toBeVisible();
	} );

	test( 'saves a widget collapse and expansion', async () => {
		const widget = dashboard.locator( '#dashboard_right_now' );
		const content = widget.locator( '.inside' );
		const toggle = widget.getByRole( 'button', {
			name: 'Show or hide panel',
			exact: true,
		} );
		await expect( content ).toBeVisible();
		await saveDashboardPreference( dashboard, 'closed-postboxes', () =>
			toggle.click()
		);
		await expect( content ).toBeHidden();
		await dashboard.reload();
		await expect( content ).toBeHidden();
		await saveDashboardPreference( dashboard, 'closed-postboxes', () =>
			toggle.click()
		);
		await expect( content ).toBeVisible();
		await dashboard.reload();
		await expect( content ).toBeVisible();
	} );

	test( 'saves widget order after moving down and up', async () => {
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		const reordering = dashboard.locator( '#meta-box-reordering' );
		if ( ! ( await reordering.isChecked() ) ) {
			await saveDashboardPreference(
				dashboard,
				'meta-box-reordering',
				() => reordering.check()
			);
		}
		await dashboard
			.getByRole( 'button', { name: 'Screen Options', exact: true } )
			.click();
		const widget = dashboard.locator( '#dashboard_right_now' );
		const normal = dashboard.locator( '#normal-sortables > .postbox' );
		const originalOrder = await normal.evaluateAll( ( boxes ) =>
			boxes.map( ( box ) => box.id )
		);
		const index = originalOrder.indexOf( 'dashboard_right_now' );
		expect( index ).toBeGreaterThanOrEqual( 0 );
		expect( index ).toBeLessThan( originalOrder.length - 1 );
		const movedOrder = [ ...originalOrder ];
		[ movedOrder[ index ], movedOrder[ index + 1 ] ] = [
			movedOrder[ index + 1 ],
			movedOrder[ index ],
		];
		await saveDashboardPreference( dashboard, 'meta-box-order', () =>
			widget
				.getByRole( 'button', { name: 'Move down', exact: true } )
				.click()
		);
		await dashboard.reload();
		expect(
			await normal.evaluateAll( ( boxes ) =>
				boxes.map( ( box ) => box.id )
			)
		).toEqual( movedOrder );
		await saveDashboardPreference( dashboard, 'meta-box-order', () =>
			widget
				.getByRole( 'button', { name: 'Move up', exact: true } )
				.click()
		);
		await dashboard.reload();
		expect(
			await normal.evaluateAll( ( boxes ) =>
				boxes.map( ( box ) => box.id )
			)
		).toEqual( originalOrder );
	} );
} );
