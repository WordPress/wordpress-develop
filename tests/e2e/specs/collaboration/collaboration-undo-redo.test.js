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
import { test, expect } from './fixtures';

test.describe( 'Collaboration - Undo/Redo', () => {
	test( 'User A undo only affects their own changes, B and C blocks remain', async ( {
		collaborationUtils,
		requestUtils,
		editor,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Undo Test - 3 Users',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3, page2, page3 } = collaborationUtils;

		// User B adds a block.
		await page2.evaluate( () => {
			const block = window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'From User B',
			} );
			window.wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );

		// User C adds a block.
		await page3.evaluate( () => {
			const block = window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'From User C',
			} );
			window.wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );

		// Wait for both blocks to appear on User A.
		await expect( async () => {
			const blocks = await editor.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).toContain( 'From User B' );
			expect( contents ).toContain( 'From User C' );
		} ).toPass( { timeout: 10000 } );

		// User A adds their own block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'From User A' },
		} );

		// Wait for all 3 blocks to appear on all editors.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect( async () => {
				const blocks = await ed.getBlocks();
				const contents = blocks.map( ( b ) => b.attributes.content );
				expect( contents ).toContain( 'From User A' );
				expect( contents ).toContain( 'From User B' );
				expect( contents ).toContain( 'From User C' );
			} ).toPass( { timeout: 10000 } );
		}

		// User A performs undo via the data API.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).undo();
		} );

		// User A should see only B and C's blocks (their own is undone).
		await expect( async () => {
			const blocks = await editor.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).not.toContain( 'From User A' );
			expect( contents ).toContain( 'From User B' );
			expect( contents ).toContain( 'From User C' );
		} ).toPass( { timeout: 10000 } );

		// User B should also see the undo result.
		await expect( async () => {
			const blocks = await editor2.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).not.toContain( 'From User A' );
			expect( contents ).toContain( 'From User B' );
			expect( contents ).toContain( 'From User C' );
		} ).toPass( { timeout: 10000 } );

		// User C should also see the undo result.
		await expect( async () => {
			const blocks = await editor3.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).not.toContain( 'From User A' );
			expect( contents ).toContain( 'From User B' );
			expect( contents ).toContain( 'From User C' );
		} ).toPass( { timeout: 10000 } );
	} );

	test( 'Redo restores the undone change across all users', async ( {
		collaborationUtils,
		requestUtils,
		editor,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Redo Test - 3 Users',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3 } = collaborationUtils;

		// User A adds a block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'Undoable content' },
		} );

		// Verify the block exists on all editors.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: 10000 } )
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
			.poll( () => editor.getBlocks(), { timeout: 10000 } )
			.toHaveLength( 0 );

		// Redo via data API.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).redo();
		} );

		// All users should see the restored block.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: 10000 } )
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
		requestUtils,
		editor,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Undo Test - Bystander',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor3, page2 } = collaborationUtils;

		// User B adds a block.
		await page2.evaluate( () => {
			const block = window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'From User B',
			} );
			window.wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );

		// Wait for User B's block to appear on User A.
		await expect
			.poll( () => editor.getBlocks(), { timeout: 10000 } )
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
		await expect( async () => {
			const blocks = await editor3.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).toContain( 'From User A' );
			expect( contents ).toContain( 'From User B' );
		} ).toPass( { timeout: 10000 } );

		// User A undoes their own block.
		await page.evaluate( () => {
			window.wp.data.dispatch( 'core/editor' ).undo();
		} );

		// Bystander (User C) should see only User B's block.
		await expect( async () => {
			const blocks = await editor3.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).not.toContain( 'From User A' );
			expect( contents ).toContain( 'From User B' );
		} ).toPass( { timeout: 10000 } );
	} );
} );
