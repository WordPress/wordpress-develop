/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/** The comment content used across tests. */
const COMMENT_CONTENT = 'A comment awaiting moderation.';

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
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllComments();
		await requestUtils.deleteAllPosts();
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
			.getByRole( 'button', { name: 'Approve this comment' } )
			.click();

		await expect
			.poll( () => getCommentStatus( requestUtils, comment.id ), {
				message: 'the comment should be approved',
			} )
			.toBe( 'approved' );
	} );

	test( 'unapproves an approved comment', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { comment } = await createPostWithComment(
			requestUtils,
			'approved'
		);

		await admin.visitAdminPage(
			'/edit-comments.php',
			'comment_status=approved'
		);

		const row = page.locator( `#comment-${ comment.id }` );
		await expect( row ).toBeVisible();

		await row.hover();
		await row
			.getByRole( 'button', { name: 'Unapprove this comment' } )
			.click();

		await expect
			.poll( () => getCommentStatus( requestUtils, comment.id ), {
				message: 'the comment should return to moderation',
			} )
			.toBe( 'hold' );
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

		// The inline reply form replaces the row.
		const replyField = page.getByRole( 'textbox', { name: 'Comment' } );
		await expect( replyField ).toBeVisible();
		await replyField.fill( 'An admin reply.' );
		await page
			.getByRole( 'button', { name: 'Reply', exact: true } )
			.click();

		// The reply lands as an approved comment on the same post.
		await expect
			.poll( async () => {
				const comments = await requestUtils.rest( {
					path: '/wp/v2/comments',
					params: { post: post.id, status: 'approve' },
				} );
				return comments.length;
			} )
			.toBe( 2 );
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

		// Select every comment in the list.
		await page
			.getByRole( 'checkbox', { name: 'Select All' } )
			.first()
			.check();
		await page
			.getByLabel( 'Select bulk action' )
			.first()
			.selectOption( 'spam' );
		await page.getByRole( 'button', { name: 'Apply' } ).first().click();

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
	} );
} );
