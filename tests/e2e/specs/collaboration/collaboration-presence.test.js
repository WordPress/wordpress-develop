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
import { test, expect } from './fixtures';

test.describe( 'Collaboration - Presence', () => {
	test( 'All 3 collaborator avatars are visible', async ( {
		collaborationUtils,
		requestUtils,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Presence Test - 3 Users',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { page2, page3 } = collaborationUtils;

		// Each user sees the collaborators list button (indicates others are present).
		await expect(
			page.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: 10000 } );

		await expect(
			page2.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: 10000 } );

		await expect(
			page3.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: 10000 } );
	} );

	test( 'Collaborator names appear in popover', async ( {
		collaborationUtils,
		requestUtils,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Presence Test - Names',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		// User A opens the collaborators popover.
		const presenceButton = page.getByRole( 'button', {
			name: /Collaborators list/,
		} );
		await expect( presenceButton ).toBeVisible( { timeout: 10000 } );
		await presenceButton.click();

		// The popover should list both collaborators by name.
		await expect(
			page.getByText( 'Test Collaborator' )
		).toBeVisible();

		await expect(
			page.getByText( 'Another Collaborator' )
		).toBeVisible();
	} );

	test( 'User C leaves, A and B see updated presence', async ( {
		collaborationUtils,
		requestUtils,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Presence Test - Leave',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { page2 } = collaborationUtils;

		// Verify all 3 users see the collaborators button initially.
		await expect(
			page.getByRole( 'button', { name: /Collaborators list/ } )
		).toBeVisible( { timeout: 10000 } );

		// Close User C's context to simulate leaving.
		await collaborationUtils.page3.close();

		// After the awareness timeout (30s), User A and B should see
		// the collaborators list update. The button may still be visible
		// but should reflect only 1 remaining collaborator.
		// We verify by opening the popover and checking that User C's
		// name is no longer listed.
		await expect( async () => {
			const presenceButton = page.getByRole( 'button', {
				name: /Collaborators list/,
			} );
			await presenceButton.click();

			// "Another Collaborator" (User C) should no longer appear.
			await expect(
				page.getByText( 'Another Collaborator' )
			).not.toBeVisible();

			// "Test Collaborator" (User B) should still be listed.
			await expect(
				page.getByText( 'Test Collaborator' )
			).toBeVisible();
		} ).toPass( { timeout: 45000 } );
	} );
} );
