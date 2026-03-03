/**
 * Internal dependencies
 */
import { test, expect } from './fixtures';

test.describe( 'Collaboration - Sync', () => {
	test( 'User A adds a paragraph block, Users B and C both see it', async ( {
		collaborationUtils,
		requestUtils,
		editor,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - Fan Out',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3 } = collaborationUtils;

		// User A inserts a paragraph block.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'Hello from User A' },
		} );

		// User B should see the paragraph after sync propagation.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User A' },
				},
			] );

		// User C should also see the paragraph.
		await expect
			.poll( () => editor3.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User A' },
				},
			] );
	} );

	test( 'User C adds a paragraph block, Users A and B see it', async ( {
		collaborationUtils,
		requestUtils,
		editor,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - C to A and B',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, page3 } = collaborationUtils;

		// User C inserts a paragraph block via the data API.
		await page3.evaluate( () => {
			const block = window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'Hello from User C',
			} );
			window.wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );

		// User A should see the paragraph.
		await expect
			.poll( () => editor.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User C' },
				},
			] );

		// User B should also see the paragraph.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Hello from User C' },
				},
			] );
	} );

	test( 'All 3 users add blocks simultaneously, all changes appear everywhere', async ( {
		collaborationUtils,
		requestUtils,
		editor,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - 3-Way Merge',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3, page2, page3 } = collaborationUtils;

		// All 3 users insert blocks concurrently.
		await Promise.all( [
			editor.insertBlock( {
				name: 'core/paragraph',
				attributes: { content: 'From User A' },
			} ),
			page2.evaluate( () => {
				const block = window.wp.blocks.createBlock( 'core/paragraph', {
					content: 'From User B',
				} );
				window.wp.data
					.dispatch( 'core/block-editor' )
					.insertBlock( block );
			} ),
			page3.evaluate( () => {
				const block = window.wp.blocks.createBlock( 'core/paragraph', {
					content: 'From User C',
				} );
				window.wp.data
					.dispatch( 'core/block-editor' )
					.insertBlock( block );
			} ),
		] );

		// All 3 users should eventually see all 3 blocks.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect( async () => {
				const blocks = await ed.getBlocks();
				const contents = blocks.map( ( b ) => b.attributes.content );
				expect( contents ).toContain( 'From User A' );
				expect( contents ).toContain( 'From User B' );
				expect( contents ).toContain( 'From User C' );
			} ).toPass( { timeout: 10000 } );
		}
	} );

	test( 'Title change from User A propagates to B and C', async ( {
		collaborationUtils,
		requestUtils,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - Title',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

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
				{ timeout: 10000 }
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
				{ timeout: 10000 }
			)
			.toBe( 'New Title from User A' );
	} );

	test( 'User C joins late and sees existing content from A and B', async ( {
		collaborationUtils,
		requestUtils,
		editor,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - Late Join',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { page2, page3, editor3 } = collaborationUtils;

		// Navigate User C away from the editor to simulate not being
		// present while A and B make edits.
		await page3.goto( '/wp-admin/' );

		// User A and B each add a block while User C is away.
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: { content: 'Block from A (early)' },
		} );

		await page2.evaluate( () => {
			const block = window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'Block from B (early)',
			} );
			window.wp.data.dispatch( 'core/block-editor' ).insertBlock( block );
		} );

		// Wait for A and B to sync with each other.
		await expect( async () => {
			const blocksA = await editor.getBlocks();
			const contentsA = blocksA.map( ( b ) => b.attributes.content );
			expect( contentsA ).toContain( 'Block from A (early)' );
			expect( contentsA ).toContain( 'Block from B (early)' );
		} ).toPass( { timeout: 10000 } );

		// Now User C joins late by navigating back to the editor.
		await collaborationUtils.navigateToEditor( page3, post.id );
		await collaborationUtils.waitForCollaborationReady( page3 );

		// User C should see all existing blocks from A and B after sync.
		await expect( async () => {
			const blocks = await editor3.getBlocks();
			const contents = blocks.map( ( b ) => b.attributes.content );
			expect( contents ).toContain( 'Block from A (early)' );
			expect( contents ).toContain( 'Block from B (early)' );
		} ).toPass( { timeout: 10000 } );
	} );

	test( 'Block deletion syncs to all users', async ( {
		collaborationUtils,
		requestUtils,
		editor,
		page,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - Block Deletion',
			status: 'draft',
			content:
				'<!-- wp:paragraph --><p>Block to delete</p><!-- /wp:paragraph -->',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3 } = collaborationUtils;

		// Wait for all users to see the seeded block.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: 10000 } )
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
			.poll( () => editor2.getBlocks(), { timeout: 10000 } )
			.toHaveLength( 0 );

		await expect
			.poll( () => editor3.getBlocks(), { timeout: 10000 } )
			.toHaveLength( 0 );
	} );

	test( 'Editing existing block content syncs to all users', async ( {
		collaborationUtils,
		requestUtils,
		editor,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - Edit Content',
			status: 'draft',
			content:
				'<!-- wp:paragraph --><p>Original text</p><!-- /wp:paragraph -->',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3, page2 } = collaborationUtils;

		// Wait for all users to see the seeded block.
		for ( const ed of [ editor, editor2, editor3 ] ) {
			await expect
				.poll( () => ed.getBlocks(), { timeout: 10000 } )
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
			.poll( () => editor.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Edited by User B' },
				},
			] );

		await expect
			.poll( () => editor3.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/paragraph',
					attributes: { content: 'Edited by User B' },
				},
			] );
	} );

	test( 'Non-paragraph block type syncs to all users', async ( {
		collaborationUtils,
		requestUtils,
		editor,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Sync Test - Heading Block',
			status: 'draft',
			date_gmt: new Date().toISOString(),
		} );
		await collaborationUtils.openCollaborativeSession( post.id );

		const { editor2, editor3 } = collaborationUtils;

		// User A inserts a heading block.
		await editor.insertBlock( {
			name: 'core/heading',
			attributes: { content: 'Synced Heading', level: 3 },
		} );

		// User B should see the heading with correct attributes.
		await expect
			.poll( () => editor2.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/heading',
					attributes: { content: 'Synced Heading', level: 3 },
				},
			] );

		// User C should also see the heading with correct attributes.
		await expect
			.poll( () => editor3.getBlocks(), { timeout: 10000 } )
			.toMatchObject( [
				{
					name: 'core/heading',
					attributes: { content: 'Synced Heading', level: 3 },
				},
			] );
	} );
} );
