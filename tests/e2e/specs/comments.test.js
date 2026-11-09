/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Front-end comments', () => {
	let postId;

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! postId ) {
			return;
		}
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/posts/${ postId }`,
			params: { force: true },
		} );
		postId = undefined;
	} );

	test( 'submits a comment on a post and shows it in Comments', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const commentContent = `Front-end comment ${ randomUUID() }`;
		const post = await requestUtils.createPost( {
			title: `Post for front-end comment ${ randomUUID() }`,
			content: '<!-- wp:paragraph --><p>Comment target.</p><!-- /wp:paragraph -->',
			status: 'publish',
			comment_status: 'open',
		} );
		postId = post.id;

		await page.goto( new URL( post.link ).pathname + new URL( post.link ).search );
		await expect(
			page.getByRole( 'heading', { name: post.title.rendered, level: 1 } )
		).toBeVisible();

		const commentField = page.getByRole( 'textbox', {
			name: 'Comment *',
			exact: true,
		} );
		await expect( commentField ).toBeVisible();
		await commentField.fill( commentContent );
		await page
			.getByRole( 'button', { name: 'Post Comment', exact: true } )
			.click();

		let commentId;
		await expect
			.poll(
				async () => {
					const comments = await requestUtils.rest( {
						path: '/wp/v2/comments',
						params: {
							post: post.id,
							search: commentContent,
							context: 'edit',
						},
					} );
					const match = comments.find(
						( item ) => item.content?.raw === commentContent
					);
					commentId = match?.id;
					return {
						id: commentId,
						content: match?.content?.raw,
						status: match?.status,
					};
				},
				{
					message:
						'the front-end comment should be saved for the post',
				}
			)
			.toEqual( {
				id: expect.any( Number ),
				content: commentContent,
				status: 'approved',
			} );

		await admin.visitAdminPage( '/edit-comments.php' );
		const row = page.locator( `#comment-${ commentId }` );
		await expect( row ).toBeVisible();
		await expect(
			row
				.getByRole( 'paragraph' )
				.filter( { hasText: commentContent } )
		).toBeVisible();
	} );
} );
