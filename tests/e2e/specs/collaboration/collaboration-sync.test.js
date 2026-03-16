/**
 * Tests for collaborative editing sync (CRDT document replication).
 *
 * Verifies that block insertions, deletions, edits, title changes,
 * and late-join state transfer propagate correctly between three
 * concurrent users.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Internal dependencies
 */
import { test, expect, SYNC_TIMEOUT } from './fixtures';

test.describe( 'Collaboration - Sync', () => {
	test( 'User A adds a paragraph block, Users B and C both see it', async ( {
		collaborationUtils,
		editor,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - Fan Out',
		} );

		const { editor2, editor3 } = collaborationUtils;

		// User A inserts a paragraph block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'Hello from User A' },
		} );

		// User B should see the paragraph after sync propagation.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User A' },
				},
			] );

		// User C should also see the paragraph.
		await expect
			.poll( () => editor3.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User A' },
				},
			] );
	} );

	test( 'User C adds a paragraph block, Users A and B see it', async ( {
		collaborationUtils,
		editor,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - C to A and B',
		} );

		const { editor2, page3 } = collaborationUtils;

		// User C inserts a paragraph block via the data API.
		await collaborationUtils.insertBlockViaEvaluate(
			page3,
			'core/paragraph',
			{ content: 'Hello from User C' }
		);

		// User A should see the paragraph.
		await expect
			.poll( () => editor.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User C' },
				},
			] );

		// User B should also see the paragraph.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User C' },
				},
			] );
	} );

	test( 'All 3 users add blocks simultaneously, all changes appear everywhere', async ( {
		collaborationUtils,
		editor,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - 3-Way Merge',
		} );

		const { page2, page3 } = collaborationUtils;

		// All 3 users insert blocks concurrently.
		await Promise.all( [
			editor.insertBlock( {
				name: 'core/paragraph',
				attributes: { content: 'From User A' },
			} ),
			collaborationUtils.insertBlockViaEvaluate(
				page2,
				'core/paragraph',
				{ content: 'From User B' }
			),
			collaborationUtils.insertBlockViaEvaluate(
				page3,
				'core/paragraph',
				{ content: 'From User C' }
			),
		] );

		// All 3 users should eventually see all 3 blocks.
		await collaborationUtils.assertAllEditorsHaveContent( [
			'From User A',
			'From User B',
			'From User C',
		] );
	} );

	test( 'Title change from User A propagates to B and C', async ( {
		collaborationUtils,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - Title',
		} );

		const { page2, page3 } = collaborationUtils;

		// User A changes the title.
		await page.evaluate( () => {
			window.wp.data
				.dispatch( 'core/editor' )
				.editPost( { title: 'New Title from User A' } );
		} );

		// User B should see the updated title.
		await expect
			.poll(
				() =>
					page2.evaluate( () =>
						window.wp.data
							.select( 'core/editor' )
							.getEditedPostAttribute( 'title' )
					),
				{ timeout: SYNC_TIMEOUT }
			)
			.toBe( 'New Title from User A' );

		// User C should also see the updated title.
		await expect
			.poll(
				() =>
					page3.evaluate( () =>
						window.wp.data
							.select( 'core/editor' )
							.getEditedPostAttribute( 'title' )
					),
				{ timeout: SYNC_TIMEOUT }
			)
			.toBe( 'New Title from User A' );
	} );

	test( 'User C joins late and sees existing content from A and B', async ( {
		collaborationUtils,
		editor,
	} ) => {
		const post = await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - Late Join',
		} );

		const { page2, page3, editor3 } = collaborationUtils;

		// Navigate User C away from the editor to simulate not being
		// present while A and B make edits.
		await page3.goto( '/wp-admin/' );

		// User A and B each add a block while User C is away.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'Block from A (early)' },
		} );

		await collaborationUtils.insertBlockViaEvaluate(
			page2,
			'core/paragraph',
			{ content: 'Block from B (early)' }
		);

		// Wait for A and B to sync with each other.
		await collaborationUtils.assertEditorHasContent( editor, [
			'Block from A (early)',
			'Block from B (early)',
		] );

		// Now User C joins late by navigating back to the editor.
		await collaborationUtils.navigateToEditor( page3, post.id );
		await collaborationUtils.waitForCollaborationReady( page3 );

		// User C should see all existing blocks from A and B after sync.
		await collaborationUtils.assertEditorHasContent( editor3, [
			'Block from A (early)',
			'Block from B (early)',
		] );
	} );

	test( 'Block deletion syncs to all users', async ( {
		collaborationUtils,
		editor,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - Block Deletion',
			content:
				'<!-- wp:paragraph --><p>Block to delete</p><!-- /wp:paragraph -->',
		} );

		const { editor2, editor3 } = collaborationUtils;

		// Wait for all users to see the seeded block.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: SYNC_TIMEOUT } )
				.toMatchObject( [
					{
						name: 'core/paragraph',
						attributes: { content: 'Block to delete' },
					},
				] );
		}

		// User A removes the block.
		await page.evaluate( () => {
			const blocks = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks();
			window.wp.data
				.dispatch( 'core/block-editor' )
				.removeBlock( blocks[ 0 ].clientId );
		} );

		// Users B and C should see 0 blocks after sync.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toHaveLength( 0 );

		await expect
			.poll( () => editor3.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toHaveLength( 0 );
	} );

	test( 'Editing existing block content syncs to all users', async ( {
		collaborationUtils,
		editor,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - Edit Content',
			content:
				'<!-- wp:paragraph --><p>Original text</p><!-- /wp:paragraph -->',
		} );

		const { editor2, editor3, page2 } = collaborationUtils;

		// Wait for all users to see the seeded block.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: SYNC_TIMEOUT } )
				.toMatchObject( [
					{
						name: 'core/paragraph',
						attributes: { content: 'Original text' },
					},
				] );
		}

		// User B updates the block content.
		await page2.evaluate( () => {
			const blocks = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks();
			window.wp.data
				.dispatch( 'core/block-editor' )
				.updateBlockAttributes( blocks[ 0 ].clientId, {
					content: 'Edited by User B',
				} );
		} );

		// Users A and C should see the updated content.
		await expect
			.poll( () => editor.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Edited by User B' },
				},
			] );

		await expect
			.poll( () => editor3.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Edited by User B' },
				},
			] );
	} );

	test( 'Non-paragraph block type syncs to all users', async ( {
		collaborationUtils,
		editor,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Sync Test - Heading Block',
		} );

		const { editor2, editor3 } = collaborationUtils;

		// User A inserts a heading block.
		await editor.insertBlock( {
			name: 'core/heading',
			attributes: { content: 'Synced Heading', level: 3 },
		} );

		// User B should see the heading with correct attributes.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/heading',
					attributes: { content: 'Synced Heading', level: 3 },
				},
			] );

		// User C should also see the heading with correct attributes.
		await expect
			.poll( () => editor3.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/heading',
					attributes: { content: 'Synced Heading', level: 3 },
				},
			] );
	} );
} );
