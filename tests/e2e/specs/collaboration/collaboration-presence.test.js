/**
 * Tests for collaborative editing presence (awareness).
 *
 * Verifies that collaborator avatars, names, and leave events
 * propagate correctly between three concurrent users.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Internal dependencies
 */
import { test, expect, SYNC_TIMEOUT } from './fixtures';

test.describe( 'Collaboration - Presence', () => {
	test( 'All 3 collaborator avatars are visible', async ( {
		collaborationUtils,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Presence Test - 3 Users',
		} );

		const { page2, page3 } = collaborationUtils;

		// Each user sees the collaborators list button (indicates others are present).
		await expect(
			page.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: SYNC_TIMEOUT } );

		await expect(
			page2.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: SYNC_TIMEOUT } );

		await expect(
			page3.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: SYNC_TIMEOUT } );
	} );

	test( 'Collaborator names appear in popover', async ( {
		collaborationUtils,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Presence Test - Names',
		} );

		// User A opens the collaborators popover.
		const presenceButton = page.getByRole( 'button', {
			name: /Collaborators list/,
		} );
		await expect( presenceButton ).toBeVisible( {
			timeout: SYNC_TIMEOUT,
		} );
		await presenceButton.click();

		// The popover should list both collaborators by name.
		// Use the presence list item class to avoid matching snackbar toasts.
		await expect(
			page.locator( '.editor-collaborators-presence__list-item-name', { hasText: 'Test Collaborator' } )
		).toBeVisible();

		await expect(
			page.locator( '.editor-collaborators-presence__list-item-name', { hasText: 'Another Collaborator' } )
		).toBeVisible();
	} );

	test( 'User C leaves, A and B see updated presence', async ( {
		collaborationUtils,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Presence Test - Leave',
		} );

		// Verify all 3 users see the collaborators button initially.
		await expect(
			page.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: SYNC_TIMEOUT } );

		// Navigate User C away from the editor to stop their polling.
		// Avoids closing the context directly which corrupts Playwright state.
		await collaborationUtils.page3.goto( '/wp-admin/' );

		// Wait for User C's awareness entry to expire on the server (30s timeout)
		// by watching the button label drop from 3 to 2 collaborators.
		const presenceButton = page.getByRole( 'button', {
			name: /Collaborators list/,
		} );
		await expect( presenceButton ).toHaveAccessibleName(
			/1 online/,
			{ timeout: 45000 }
		);

		// Open the popover once, then verify the list contents.
		await presenceButton.click();

		// "Another Collaborator" (User C) should no longer appear in the presence list.
		await expect(
			page.locator( '.editor-collaborators-presence__list-item-name', { hasText: 'Another Collaborator' } )
		).not.toBeVisible();

		// "Test Collaborator" (User B) should still be listed.
		await expect(
			page.locator( '.editor-collaborators-presence__list-item-name', { hasText: 'Test Collaborator' } )
		).toBeVisible();
	} );
} );
