/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/** The comment content used across tests. */
const COMMENT_CONTENT = 'A comment awaiting moderation.';
const postIds = [];

/**
 * Creates a post with a comment in the given moderation status.
 *
 * Comments are created via the REST API as the admin user (which approves
 * them automatically), then moved to the requested status so each test
 * starts from a known moderation state.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @param {string}                                                      status       Comment status: 'approved' or 'hold'.
 * @param {string}                                                      [content]    Comment content.
 * @return {Promise<{post: Object, comment: Object}>} The created records.
 */
async function createPostWithComment(
	requestUtils,
	status,
	content = COMMENT_CONTENT
) {
	const post = await requestUtils.createPost( {
		title: 'Post with comments',
		content: '<p>Comment target.</p>',
		status: 'publish',
	} );
	postIds.push( post.id );
	const comment = await requestUtils.createComment( {
		post: post.id,
		content,
	} );

	if ( status !== 'approved' ) {
		await requestUtils.rest( {
			method: 'PUT',
			path: `/wp/v2/comments/${ comment.id }`,
			data: { status },
		} );
	}

	return { post, comment };
}

/**
 * Reads a comment's current status via the REST API.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @param {number}                                                      commentId    The comment ID.
 * @return {Promise<string>} The comment status.
 */
async function getCommentStatus( requestUtils, commentId ) {
	const comment = await requestUtils.rest( {
		path: `/wp/v2/comments/${ commentId }`,
		params: { context: 'edit' },
	} );
	return comment.status;
}

test.describe( 'Moderate Comments', () => {
	test.afterEach( async ( { requestUtils } ) => {
		// Deleting each fixture post also deletes its comments, including replies.
		for ( const id of postIds.splice( 0 ) ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/posts/${ id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'approves a pending comment', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { comment } = await createPostWithComment( requestUtils, 'hold' );

		await admin.visitAdminPage(
			'/edit-comments.php',
			'comment_status=moderated'
		);

		const row = page.locator( `#comment-${ comment.id }` );
		await expect( row ).toBeVisible();

		// Row actions appear on hover.
		await row.hover();
		await row
			.getByRole( 'button', {
				name: 'Approve this comment',
				exact: true,
			} )
			.click();

		await expect
			.poll( () => getCommentStatus( requestUtils, comment.id ), {
				message: 'the comment should be approved',
			} )
			.toBe( 'approved' );
		await expect( row ).toBeHidden();
		await admin.visitAdminPage(
			'/edit-comments.php',
			'comment_status=approved'
		);
		await expect( row ).toBeVisible();
	} );

	test( 'marks a comment as spam', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { comment } = await createPostWithComment(
			requestUtils,
			'approved'
		);

		await admin.visitAdminPage( '/edit-comments.php' );

		const row = page.locator( `#comment-${ comment.id }` );
		await expect( row ).toBeVisible();

		await row.hover();
		await row
			.getByRole( 'button', { name: 'Mark this comment as spam' } )
			.click();

		await expect
			.poll( () => getCommentStatus( requestUtils, comment.id ), {
				message: 'the comment should be marked as spam',
			} )
			.toBe( 'spam' );
		await expect( row ).toBeHidden();
		await expect(
			page
				.locator( `#undo-${ comment.id }` )
				.getByRole( 'button', { name: 'Undo', exact: true } )
		).toBeVisible();
	} );

	test( 'replies to a comment from the admin', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { post, comment } = await createPostWithComment(
			requestUtils,
			'approved'
		);

		await admin.visitAdminPage( '/edit-comments.php' );

		const row = page.locator( `#comment-${ comment.id }` );
		await expect( row ).toBeVisible();

		await row.hover();
		await row
			.getByRole( 'button', {
				name: 'Reply to this comment',
			} )
			.click();

		// The inline reply form appears below the comment.
		const replyField = page.getByRole( 'textbox', {
			name: 'Comment',
			exact: true,
		} );
		await expect( replyField ).toBeVisible();
		await replyField.fill( 'An admin reply.' );
		await page
			.getByRole( 'button', { name: 'Reply', exact: true } )
			.click();

		// Verify the saved reply, including its content and parent comment.
		let replyId;
		await expect
			.poll(
				async () => {
					const comments = await requestUtils.rest( {
						path: '/wp/v2/comments',
						params: {
							post: post.id,
							status: 'approve',
							context: 'edit',
						},
					} );
					const reply = comments.find(
						( item ) => item.id !== comment.id
					);
					replyId = reply?.id;
					return {
						id: replyId,
						count: comments.length,
						parent: reply?.parent,
						content: reply?.content.raw,
						post: reply?.post,
						status: reply?.status,
					};
				},
				{
					message:
						'the reply should be saved with the correct parent, content, post, and status',
				}
			)
			.toEqual( {
				id: expect.any( Number ),
				count: 2,
				parent: comment.id,
				content: 'An admin reply.',
				post: post.id,
				status: 'approved',
			} );
		await expect(
			page
				.locator( `#comment-${ replyId }` )
				.getByRole( 'paragraph' )
				.filter( { hasText: /^An admin reply\.$/ } )
		).toBeVisible();
		await expect( replyField ).toBeHidden();
	} );

	test( 'moves multiple comments to spam with a bulk action', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { post, comment } = await createPostWithComment(
			requestUtils,
			'hold'
		);
		const secondComment = await requestUtils.createComment( {
			post: post.id,
			content: 'A second comment awaiting moderation.',
		} );
		await requestUtils.rest( {
			method: 'PUT',
			path: `/wp/v2/comments/${ secondComment.id }`,
			data: { status: 'hold' },
		} );

		await admin.visitAdminPage(
			'/edit-comments.php',
			'comment_status=moderated'
		);

		const firstRow = page.locator( `#comment-${ comment.id }` );
		const secondRow = page.locator( `#comment-${ secondComment.id }` );
		await firstRow.getByRole( 'checkbox' ).check();
		await secondRow.getByRole( 'checkbox' ).check();
		await page
			.getByLabel( 'Select bulk action' )
			.first()
			.selectOption( 'spam' );
		await page
			.getByRole( 'button', { name: 'Apply', exact: true } )
			.first()
			.click();

		await expect
			.poll( () => getCommentStatus( requestUtils, comment.id ), {
				message: 'the first comment should be spam',
			} )
			.toBe( 'spam' );
		await expect
			.poll( () => getCommentStatus( requestUtils, secondComment.id ), {
				message: 'the second comment should be spam',
			} )
			.toBe( 'spam' );
		await expect( firstRow ).toBeHidden();
		await expect( secondRow ).toBeHidden();
		await admin.visitAdminPage(
			'/edit-comments.php',
			'comment_status=spam'
		);
		await expect( firstRow ).toBeVisible();
		await expect( secondRow ).toBeVisible();
	} );

	test( 'quick edits a comment and persists its content', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { post, comment } = await createPostWithComment(
			requestUtils,
			'approved'
		);
		await admin.visitAdminPage(
			'/edit-comments.php',
			`p=${ post.id }`
		);

		const row = page.locator( `#comment-${ comment.id }` );
		await row.hover();
		await row
			.getByRole( 'button', { name: 'Quick edit this comment inline' } )
			.click();

		const commentField = page.getByRole( 'textbox', {
			name: 'Comment',
			exact: true,
		} );
		await expect( commentField ).toHaveValue( COMMENT_CONTENT );
		const newContent = 'A comment updated with Quick Edit.';
		await commentField.fill( newContent );
		await page
			.getByRole( 'button', { name: 'Update Comment', exact: true } )
			.click();

		await expect(
			row
				.getByRole( 'paragraph' )
				.and( row.getByText( newContent, { exact: true } ) )
		).toBeVisible();
		await expect( commentField ).toBeHidden();
		await expect
			.poll(
				async () => {
					const saved = await requestUtils.rest( {
						path: `/wp/v2/comments/${ comment.id }`,
						params: { context: 'edit' },
					} );
					return {
						content: saved.content.raw,
						status: saved.status,
						authorName: saved.author_name,
						authorEmail: saved.author_email,
						authorUrl: saved.author_url,
					};
				},
				{
					message:
						'the comment should be saved with the new content and unchanged author and status',
				}
			)
			.toEqual( {
				content: newContent,
				status: 'approved',
				authorName: comment.author_name,
				authorEmail: comment.author_email,
				authorUrl: comment.author_url,
			} );
	} );
} );
