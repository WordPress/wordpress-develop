<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax comment functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_edit_comment
 */
class Tests_Ajax_wpAjaxEditComment extends WP_Ajax_UnitTestCase {

	/**
	 * A post with at least one comment.
	 *
	 * @var mixed
	 */
	protected $_comment_post = null;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();
		$post_id = self::factory()->post->create();
		self::factory()->comment->create_post_comments( $post_id, 5 );
		$this->_comment_post = get_post( $post_id );
	}

	/**
	 * Gets comments as a privileged user (administrator).
	 *
	 * Expects test to pass.
	 */
	public function test_as_admin() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => $this->_comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

		// Make the request.
		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$xml = simplexml_load_string( $this->_last_response, 'SimpleXMLElement', LIBXML_NOCDATA );

		// Check the meta data.
		$this->assertSame( '-1', (string) $xml->response[0]->edit_comment['position'] );
		$this->assertSame( $comment->comment_ID, (string) $xml->response[0]->edit_comment['id'] );
		$this->assertSame( 'edit-comment_' . $comment->comment_ID, (string) $xml->response['action'] );

		// Check the payload.
		$this->assertNotEmpty( (string) $xml->response[0]->edit_comment[0]->response_data );

		// And supplemental is empty.
		$this->assertEmpty( (string) $xml->response[0]->edit_comment[0]->supplemental );
	}

	/**
	 * @ticket 33154
	 */
	public function test_editor_can_edit_orphan_comments() {
		global $wpdb;

		// Become an editor.
		$this->_setRole( 'editor' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => $this->_comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Manually update the comment_post_ID, because wp_update_comment() will prevent it..
		$wpdb->update( $wpdb->comments, array( 'comment_post_ID' => 0 ), array( 'comment_ID' => $comment->comment_ID ) );
		clean_comment_cache( $comment->comment_ID );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

		// Make the request.
		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$xml = simplexml_load_string( $this->_last_response, 'SimpleXMLElement', LIBXML_NOCDATA );

		// Check the meta data.
		$this->assertSame( '-1', (string) $xml->response[0]->edit_comment['position'] );
		$this->assertSame( $comment->comment_ID, (string) $xml->response[0]->edit_comment['id'] );
		$this->assertSame( 'edit-comment_' . $comment->comment_ID, (string) $xml->response['action'] );

		// Check the payload.
		$this->assertNotEmpty( (string) $xml->response[0]->edit_comment[0]->response_data );

		// And supplemental is empty.
		$this->assertEmpty( (string) $xml->response[0]->edit_comment[0]->supplemental );
	}

	/**
	 * Gets comments as a non-privileged user (subscriber).
	 *
	 * Expects test to fail.
	 */
	public function test_as_subscriber() {

		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => $this->_comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'edit-comment' );
	}

	/**
	 * Gets comments with a bad nonce.
	 *
	 * Expects test to fail.
	 */
	public function test_bad_nonce() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => $this->_comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( uniqid() );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'get-comments' );
	}

	/**
	 * Gets comments for an invalid post.
	 *
	 * This should return valid XML.
	 */
	public function test_invalid_comment() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = 123456789;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'edit-comment' );
	}

	/**
	 * @ticket 39732
	 */
	public function test_wp_update_comment_data_is_wp_error() {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => $this->_comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';

		// Simulate filter check error.
		add_filter( 'wp_update_comment_data', array( $this, '_wp_update_comment_data_filter' ), 10, 3 );

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( 'wp_update_comment_data filter fails for this comment.' );
		$this->_handleAjax( 'edit-comment' );
	}

	/**
	 * Blocks comments from being updated by returning WP_Error.
	 */
	public function _wp_update_comment_data_filter( $data, $comment, $commentarr ) {
		return new WP_Error( 'comment_wrong', 'wp_update_comment_data filter fails for this comment.', 500 );
	}

	/**
	 * Creates an administrator's open note and approved reaction on a post.
	 *
	 * @param int $post_id  Post to attach the note to.
	 * @param int $admin_id User who owns the note and reaction.
	 * @return int The reaction ID.
	 */
	private function create_admin_reaction( int $post_id, int $admin_id ): int {
		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 0,
				'user_id'          => $admin_id,
				'comment_content'  => 'Editorial note',
			)
		);

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'reaction',
				'comment_parent'   => $note_id,
				'comment_approved' => 1,
				'user_id'          => $admin_id,
				'comment_content'  => '2764',
			)
		);
	}

	/**
	 * Reactions are immutable. The REST API refuses to update them, and the
	 * admin-ajax writer has to refuse too, or anyone who can edit the post
	 * rewrites and reattributes other users' reactions.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_edit_reaction(): void {
		$this->_setRole( 'contributor' );

		$admin_id    = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$draft_id    = self::factory()->post->create(
			array(
				'post_author' => get_current_user_id(),
				'post_status' => 'draft',
			)
		);
		$reaction_id = $this->create_admin_reaction( $draft_id, $admin_id );

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $reaction_id;
		$_POST['content']                     = 'rewritten';
		$_POST['newcomment_author']           = 'Forged';
		$_POST['newcomment_author_email']     = 'forged@example.org';
		$_POST['user_id']                     = get_current_user_id();

		try {
			$this->_handleAjax( 'edit-comment' );
			$this->fail( 'The handler should have refused to edit a reaction.' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$reaction = get_comment( $reaction_id );
		$this->assertInstanceOf( WP_Comment::class, $reaction );
		$this->assertSame( '2764', $reaction->comment_content );
		$this->assertSame( (string) $admin_id, $reaction->user_id );
		$this->assertSame( '', $reaction->comment_author_email );
	}

	/**
	 * Moving a comment requires edit access to the destination post, not only
	 * to the post it is on.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_move_comment_to_post_user_cannot_edit(): void {
		$this->_setRole( 'contributor' );

		$admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$draft_id      = self::factory()->post->create(
			array(
				'post_author' => get_current_user_id(),
				'post_status' => 'draft',
			)
		);
		$admin_post_id = self::factory()->post->create( array( 'post_author' => $admin_id ) );
		$comment_id    = self::factory()->comment->create( array( 'comment_post_ID' => $draft_id ) );

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment_id;
		$_POST['content']                     = 'Moved';
		$_POST['comment_post_ID']             = $admin_post_id;

		try {
			$this->_handleAjax( 'edit-comment' );
			$this->fail( 'The handler should have refused to move the comment.' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$comment = get_comment( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $comment );
		$this->assertSame( (string) $draft_id, $comment->comment_post_ID );
	}

	/**
	 * A user who can edit both posts can still move a comment between them.
	 *
	 * @ticket 66635
	 */
	public function test_can_move_comment_to_post_user_can_edit(): void {
		$this->_setRole( 'editor' );

		$source_post_id = self::factory()->post->create();
		$target_post_id = self::factory()->post->create();
		$comment_id     = self::factory()->comment->create( array( 'comment_post_ID' => $source_post_id ) );

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment_id;
		$_POST['content']                     = 'Moved';
		$_POST['comment_post_ID']             = $target_post_id;

		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$comment = get_comment( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $comment );
		$this->assertSame( (string) $target_post_id, $comment->comment_post_ID );
	}

	/**
	 * An author who can reply beneath a note on their own post must not be
	 * able to turn that reply into a reaction attributed to someone else.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_convert_comment_into_reaction(): void {
		$this->_setRole( 'author' );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id  = self::factory()->post->create( array( 'post_author' => get_current_user_id() ) );
		$note_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => 0,
				'user_id'          => $admin_id,
				'comment_content'  => 'Editorial note',
			)
		);

		// Step 1: reply beneath the note with an ordinary comment.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_post_ID']             = $post_id;
		$_POST['comment_ID']                  = $note_id;
		$_POST['content']                     = 'Ordinary reply';

		try {
			$this->_handleAjax( 'replyto-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$replies = get_comments(
			array(
				'post_id' => $post_id,
				'parent'  => $note_id,
				'type'    => 'comment',
				'status'  => 'all',
				'fields'  => 'ids',
			)
		);
		$this->assertCount( 1, $replies );
		$reply_id = (int) $replies[0];

		// Step 2: edit the reply into a reaction owned by the administrator.
		$_POST                                = array();
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $reply_id;
		$_POST['content']                     = '2764';
		$_POST['comment_type']                = 'reaction';
		$_POST['user_id']                     = $admin_id;

		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$reply = get_comment( $reply_id );
		$this->assertInstanceOf( WP_Comment::class, $reply );
		$this->assertSame( 'comment', $reply->comment_type );
		$this->assertSame( 'Ordinary reply', $reply->comment_content );
		$this->assertSame( (string) get_current_user_id(), $reply->user_id );
	}

	/**
	 * A post ID of zero skips the destination check and would orphan the
	 * comment, which drops its capability mapping to the post.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_detach_comment_from_its_post(): void {
		$this->_setRole( 'contributor' );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$draft_id = self::factory()->post->create(
			array(
				'post_author' => get_current_user_id(),
				'post_status' => 'draft',
			)
		);
		$note_id  = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $draft_id,
				'comment_type'     => 'note',
				'comment_approved' => 0,
				'user_id'          => $admin_id,
				'comment_content'  => 'Editorial note',
			)
		);

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $note_id;
		$_POST['content']                     = 'Editorial note';
		$_POST['comment_post_ID']             = 0;

		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$note = get_comment( $note_id );
		$this->assertInstanceOf( WP_Comment::class, $note );
		$this->assertSame( (string) $draft_id, $note->comment_post_ID );
	}

	/**
	 * The new parent has to be on the post the comment is moving to, not the
	 * one it is leaving.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_move_comment_under_parent_on_the_post_it_leaves(): void {
		$this->_setRole( 'editor' );
		update_option( 'thread_comments', 1 );

		$post_a_id  = self::factory()->post->create();
		$post_b_id  = self::factory()->post->create();
		$parent_id  = self::factory()->comment->create( array( 'comment_post_ID' => $post_a_id ) );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_a_id ) );

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment_id;
		$_POST['content']                     = 'Moved';
		$_POST['comment_post_ID']             = $post_b_id;
		$_POST['comment_parent']              = $parent_id;

		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$comment = get_comment( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $comment );
		$this->assertSame( (string) $post_a_id, $comment->comment_post_ID );
		$this->assertSame( '0', $comment->comment_parent );
	}

	/**
	 * A reply cannot move to another post while still threaded under a
	 * comment on the post it leaves.
	 *
	 * @ticket 66635
	 */
	public function test_cannot_move_reply_away_from_its_parent(): void {
		$this->_setRole( 'editor' );
		update_option( 'thread_comments', 1 );

		$post_a_id = self::factory()->post->create();
		$post_b_id = self::factory()->post->create();
		$parent_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_a_id ) );
		$reply_id  = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_a_id,
				'comment_parent'  => $parent_id,
			)
		);

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $reply_id;
		$_POST['content']                     = 'Moved';
		$_POST['comment_post_ID']             = $post_b_id;

		try {
			$this->_handleAjax( 'edit-comment' );
		} catch ( WPDieException $e ) {
			unset( $e );
		}

		$reply = get_comment( $reply_id );
		$this->assertInstanceOf( WP_Comment::class, $reply );
		$this->assertSame( (string) $post_a_id, $reply->comment_post_ID );
	}
}
