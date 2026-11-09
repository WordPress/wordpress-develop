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
 * @covers ::wp_ajax_replyto_comment
 */
class Tests_Ajax_wpAjaxReplytoComment extends WP_Ajax_UnitTestCase {

	/**
	 * A post with at least one comment.
	 *
	 * @var mixed
	 */
	protected static $comment_post = null;

	/**
	 * Draft post.
	 *
	 * @var mixed
	 */
	protected static $draft_post = null;

	protected static $comment_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$comment_post = $factory->post->create_and_get();
		self::$comment_ids  = $factory->comment->create_post_comments( self::$comment_post->ID, 5 );
		self::$draft_post   = $factory->post->create_and_get( array( 'post_status' => 'draft' ) );
	}

	public function tear_down() {
		remove_filter( 'query', array( $this, '_block_comments' ) );
		parent::tear_down();
	}

	/**
	 * Tests reply as a privileged user (administrator).
	 *
	 * Expects test to pass.
	 */
	public function test_as_admin() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => self::$comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = self::$comment_post->ID;

		// Make the request.
		try {
			$this->_handleAjax( 'replyto-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$xml = simplexml_load_string( $this->_last_response, 'SimpleXMLElement', LIBXML_NOCDATA );

		// Check the meta data.
		$this->assertSame( '-1', (string) $xml->response[0]->comment['position'] );
		$this->assertGreaterThan( 0, (int) $xml->response[0]->comment['id'] );
		$this->assertNotEmpty( (string) $xml->response['action'] );

		// Check the payload.
		$this->assertNotEmpty( (string) $xml->response[0]->comment[0]->response_data );

		// And supplemental is empty.
		$this->assertEmpty( (string) $xml->response[0]->comment[0]->supplemental );
	}

	/**
	 * Tests reply as a non-privileged user (subscriber).
	 *
	 * Expects test to fail.
	 */
	public function test_as_subscriber() {

		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => self::$comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = self::$comment_post->ID;

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'replyto-comment' );
	}

	/**
	 * Tests reply using a bad nonce.
	 *
	 * Expects test to fail.
	 */
	public function test_bad_nonce() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Get a comment.
		$comments = get_comments(
			array(
				'post_id' => self::$comment_post->ID,
			)
		);
		$comment  = array_pop( $comments );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( uniqid() );
		$_POST['comment_ID']                  = $comment->comment_ID;
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = self::$comment_post->ID;

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'replyto-comment' );
	}

	/**
	 * Tests reply to an invalid post.
	 *
	 * Expects test to fail.
	 */
	public function test_invalid_post() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = 123456789;

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'replyto-comment' );
	}

	/**
	 * Tests reply to a draft post.
	 *
	 * Expects test to fail.
	 */
	public function test_with_draft_post() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = self::$draft_post->ID;

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( 'You cannot reply to a comment on a draft post.' );
		$this->_handleAjax( 'replyto-comment' );
	}

	/**
	 * Tests reply to a post with a simulated database failure.
	 *
	 * Expects test to fail.
	 *
	 * @global $wpdb
	 */
	public function test_blocked_comment() {
		global $wpdb;

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = self::$comment_post->ID;

		// Block comments from being saved, simulate a DB error.
		add_filter( 'query', array( $this, '_block_comments' ) );

		// Make the request.
		try {
			$wpdb->suppress_errors( true );
			$this->_handleAjax( 'replyto-comment' );
			$wpdb->suppress_errors( false );
			$this->fail();
		} catch ( WPAjaxDieStopException $e ) {
			$wpdb->suppress_errors( false );
			$this->assertStringContainsString( '1', $e->getMessage() );
		}
	}

	/**
	 * Blocks comments from being saved.
	 *
	 * @param string $sql
	 * @return string
	 */
	public function _block_comments( $sql ) {
		global $wpdb;
		if ( false !== strpos( $sql, $wpdb->comments ) && 0 === stripos( trim( $sql ), 'INSERT INTO' ) ) {
			return '';
		}
		return $sql;
	}

	/**
	 * Tests blocking a comment from being saved on 'pre_comment_approved'.
	 *
	 * @ticket 39730
	 */
	public function test_pre_comments_approved() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['content']                     = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
		$_POST['comment_post_ID']             = self::$comment_post->ID;

		// Simulate filter check error.
		add_filter( 'pre_comment_approved', array( $this, '_pre_comment_approved_filter' ), 10, 2 );

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( 'pre_comment_approved filter fails for new comment.' );
		$this->_handleAjax( 'replyto-comment' );
	}

	/**
	 * Blocks comments from being saved on 'pre_comment_approved', by returning WP_Error.
	 */
	public function _pre_comment_approved_filter( $approved, $commentdata ) {
		return new WP_Error( 'comment_wrong', 'pre_comment_approved filter fails for new comment.', 403 );
	}

	/**
	 * Notes and reactions are created through the REST API, which enforces
	 * their invariants. The reply handler must not mint them.
	 *
	 * @ticket security-2719
	 *
	 * @dataProvider data_restricted_comment_types
	 *
	 * @param string $type The comment type to request.
	 */
	public function test_cannot_reply_with_restricted_comment_type( string $type ): void {
		$this->_setRole( 'administrator' );

		$post_id   = self::factory()->post->create();
		$parent_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $parent_id;
		$_POST['content']                     = '2764';
		$_POST['comment_post_ID']             = $post_id;
		$_POST['comment_type']                = $type;

		try {
			$this->_handleAjax( 'replyto-comment' );
			$this->fail( 'The handler should have refused the comment type.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}

		$this->assertSame(
			array(),
			get_comments(
				array(
					'type'   => $type,
					'status' => 'any',
					'fields' => 'ids',
				)
			),
			'No comment of the restricted type should have been created.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_restricted_comment_types(): array {
		return array(
			'reaction'              => array( 'reaction' ),
			'reaction, capitalized' => array( 'Reaction' ),
			'note'                  => array( 'note' ),
			'note, capitalized'     => array( 'Note' ),
		);
	}

	/**
	 * A reply whose parent is on a different post is stored unthreaded.
	 *
	 * @ticket security-2719
	 */
	public function test_reply_to_comment_on_another_post_is_not_threaded(): void {
		$this->_setRole( 'administrator' );

		$post_id       = self::factory()->post->create();
		$other_post    = self::factory()->post->create();
		$other_comment = self::factory()->comment->create( array( 'comment_post_ID' => $other_post ) );

		$_POST['_ajax_nonce-replyto-comment'] = wp_create_nonce( 'replyto-comment' );
		$_POST['comment_ID']                  = $other_comment;
		$_POST['content']                     = 'Reply across posts';
		$_POST['comment_post_ID']             = $post_id;

		try {
			$this->_handleAjax( 'replyto-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$xml = simplexml_load_string( $this->_last_response, 'SimpleXMLElement', LIBXML_NOCDATA );
		$this->assertInstanceOf( SimpleXMLElement::class, $xml );

		$comment = get_comment( (int) $xml->response[0]->comment['id'] );
		$this->assertInstanceOf( WP_Comment::class, $comment );

		$this->assertSame( (string) $post_id, $comment->comment_post_ID );
		$this->assertSame( '0', $comment->comment_parent, 'A parent on another post should be dropped.' );
	}
}
