<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax comment functionality.
 *
 * @package    WordPress
 * @subpackage UnitTests
 * @since      3.4.0
 * @group      ajax
 */
class Tests_Ajax_EditComment extends WP_Ajax_UnitTestCase {

	/**
	 * A post with at least one comment.
	 *
	 * @var mixed
	 */
	protected $_comment_post = null;

	/**
	 * Sets up the test fixture.
	 */
	public function setUp() {
		parent::setUp();
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
	function test_editor_can_edit_orphan_comments() {
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
		$this->setExpectedException( 'WPAjaxDieStopException', '-1' );
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
		$this->setExpectedException( 'WPAjaxDieStopException', '-1' );
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
		$this->setExpectedException( 'WPAjaxDieStopException', '-1' );
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
		$this->setExpectedException( 'WPAjaxDieStopException', 'wp_update_comment_data filter fails for this comment.' );
		$this->_handleAjax( 'edit-comment' );
	}

	/**
	 * Blocks comments from being updated by returning WP_Error.
	 */
	public function _wp_update_comment_data_filter( $data, $comment, $commentarr ) {
		return new WP_Error( 'comment_wrong', 'wp_update_comment_data filter fails for this comment.', 500 );
	}
}
