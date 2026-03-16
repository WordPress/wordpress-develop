/**
 * Tests for collaborative editing undo/redo.
 *
 * Verifies that undo and redo operations affect only the originating
 * user's changes while preserving other collaborators' edits.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Internal dependencies
 */
import { test, expect, SYNC_TIMEOUT } from './fixtures';

test.describe( 'Collaboration - Undo/Redo', () => {
	test( 'User A undo only affects their own changes, B and C blocks remain', async ( {
		collaborationUtils,
		editor,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Undo Test - 3 Users',
		} );

		const { page2, page3 } = collaborationUtils;

		// User B adds a block.
		await collaborationUtils.insertBlockViaEvaluate(
			page2,
			'core/paragraph',
			{ content: 'From User B' }
		);

		// User C adds a block.
		await collaborationUtils.insertBlockViaEvaluate(
			page3,
			'core/paragraph',
			{ content: 'From User C' }
		);

		// Wait for both blocks to appear on User A.
		await collaborationUtils.assertEditorHasContent( editor, [
			'From User B',
			'From User C',
		] );

		// User A adds their own block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'From User A' },
		} );

		// Wait for all 3 blocks to appear on all editors.
		await collaborationUtils.assertAllEditorsHaveContent( [
			'From User A',
			'From User B',
			'From User C',
		] );

		// User A performs undo via the data API.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).undo();
		} );

		// All users should see only B and C's blocks (A's is undone).
		await collaborationUtils.assertAllEditorsHaveContent(
			[ 'From User B', 'From User C' ],
			{ not: [ 'From User A' ] }
		);
	} );

	test( 'Redo restores the undone change across all users', async ( {
		collaborationUtils,
		editor,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Redo Test - 3 Users',
		} );

		const { editor2, editor3 } = collaborationUtils;

		// User A adds a block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'Undoable content' },
		} );

		// Verify the block exists on all editors.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: SYNC_TIMEOUT } )
				.toMatchObject( [
					{
						name: 'core/paragraph',
						attributes: { content: 'Undoable content' },
					},
				] );
		}

		// Undo via data API.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).undo();
		} );

		await expect
			.poll( () => editor.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toHaveLength( 0 );

		// Redo via data API.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).redo();
		} );

		// All users should see the restored block.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: SYNC_TIMEOUT } )
				.toMatchObject( [
					{
						name: 'core/paragraph',
						attributes: { content: 'Undoable content' },
					},
				] );
		}
	} );

	test( 'Bystander sees correct state after undo', async ( {
		collaborationUtils,
		editor,
		page,
	} ) => {
		await collaborationUtils.createCollaborativePost( {
			title: 'Undo Test - Bystander',
		} );

		const { editor3, page2 } = collaborationUtils;

		// User B adds a block.
		await collaborationUtils.insertBlockViaEvaluate(
			page2,
			'core/paragraph',
			{ content: 'From User B' }
		);

		// Wait for User B's block to appear on User A.
		await expect
			.poll( () => editor.getBlocks(), { timeout: SYNC_TIMEOUT } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'From User B' },
				},
			] );

		// User A adds a block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'From User A' },
		} );

		// Wait for both blocks to appear on the bystander (User C).
		await collaborationUtils.assertEditorHasContent( editor3, [
			'From User A',
			'From User B',
		] );

		// User A undoes their own block.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).undo();
		} );

		// Bystander (User C) should see only User B's block.
		await collaborationUtils.assertEditorHasContent(
			editor3,
			[ 'From User B' ],
			{ not: [ 'From User A' ] }
		);
	} );
} );
