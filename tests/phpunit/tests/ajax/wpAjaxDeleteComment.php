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
 * @covers ::wp_ajax_delete_comment
 */
class Tests_Ajax_wpAjaxDeleteComment extends WP_Ajax_UnitTestCase {

	/**
	 * List of comments.
	 *
	 * @var array
	 */
	protected static $comments = array();

	/**
	 * ID of a post.
	 *
	 * @var int
	 */
	protected static $post_id;

	public function set_up() {
		parent::set_up();

		add_action( 'wp_ajax_empty-comments', 'wp_ajax_empty_comments', 1 );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();

		$comment_ids    = $factory->comment->create_post_comments( self::$post_id, 8 );
		self::$comments = array_map( 'get_comment', $comment_ids );
	}

	/**
	 * Clears the POST actions in between requests.
	 */
	protected function _clear_post_action() {

		unset( $_POST['trash'] );
		unset( $_POST['untrash'] );
		unset( $_POST['spam'] );
		unset( $_POST['unspam'] );
		unset( $_POST['delete'] );
		unset( $_POST['comment_status'] );
		unset( $_POST['pagegen_timestamp'] );
		$this->_last_response = '';
	}

	/**
	 * Makes an AJAX request to empty spam or trash comments.
	 *
	 * @param string $comment_status Comment status.
	 * @param string $delete_time    Delete timestamp.
	 * @param string $nonce          Nonce.
	 * @return array Decoded response.
	 */
	protected function _empty_comments( $comment_status, $delete_time, $nonce = '' ) {

		$this->_clear_post_action();

		$_POST['comment_status']    = $comment_status;
		$_POST['pagegen_timestamp'] = $delete_time;
		$_POST['_ajax_nonce']       = $nonce ? $nonce : wp_create_nonce( 'bulk-comments' );

		try {
			$this->_handleAjax( 'empty-comments' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}
	/*
	 * Test prototype
	 */

	/**
	 * Tests as a privileged user (administrator).
	 *
	 * Expects test to pass.
	 *
	 * @covers ::_wp_ajax_delete_comment_response
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param string     $action  Action: 'trash', 'untrash', etc.
	 */
	public function _test_as_admin( $comment, $action ) {

		// Reset request.
		$this->_clear_post_action();

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_POST['id']          = $comment->comment_ID;
		$_POST['_ajax_nonce'] = wp_create_nonce( 'delete-comment_' . $comment->comment_ID );
		$_POST[ $action ]     = '1';
		$_POST['_total']      = count( self::$comments );
		$_POST['_per_page']   = '100';
		$_POST['_page']       = '1';
		$_POST['_url']        = admin_url( 'edit-comments.php' );

		// Make the request.
		try {
			$this->_handleAjax( 'delete-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response.
		$xml = simplexml_load_string( $this->_last_response, 'SimpleXMLElement', LIBXML_NOCDATA );

		// Ensure everything is correct.
		$this->assertSame( $comment->comment_ID, (string) $xml->response[0]->comment['id'] );
		$this->assertSame( 'delete-comment_' . $comment->comment_ID, (string) $xml->response['action'] );
		$this->assertGreaterThanOrEqual( time() - 10, (int) $xml->response[0]->comment[0]->supplemental[0]->time[0] );
		$this->assertLessThanOrEqual( time(), (int) $xml->response[0]->comment[0]->supplemental[0]->time[0] );

		// 'trash', 'spam', 'delete' should make the total go down.
		if ( in_array( $action, array( 'trash', 'spam', 'delete' ), true ) ) {
			$total = $_POST['_total'] - 1;

			// 'unspam', 'untrash' should make the total go up.
		} elseif ( in_array( $action, array( 'untrash', 'unspam' ), true ) ) {
			$total = $_POST['_total'] + 1;
		}

		// The total is calculated based on a page break -OR- a random number. Let's look for both possible outcomes.
		$comment_count = wp_count_comments( 0 );
		$recalc_total  = $comment_count->total_comments;

		// Check for either possible total.
		$message = sprintf( 'returned value: %1$d $total: %2$d  $recalc_total: %3$d', (int) $xml->response[0]->comment[0]->supplemental[0]->total[0], $total, $recalc_total );
		$this->assertContains( (int) $xml->response[0]->comment[0]->supplemental[0]->total[0], array( $total, $recalc_total ), $message );
	}

	/**
	 * Tests as a non-privileged user (subscriber).
	 *
	 * Expects test to fail.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param string     $action  Action: 'trash', 'untrash', etc.
	 */
	public function _test_as_subscriber( $comment, $action ) {

		// Reset request.
		$this->_clear_post_action();

		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		// Set up the $_POST request.
		$_POST['id']          = $comment->comment_ID;
		$_POST['_ajax_nonce'] = wp_create_nonce( 'delete-comment_' . $comment->comment_ID );
		$_POST[ $action ]     = '1';
		$_POST['_total']      = count( self::$comments );
		$_POST['_per_page']   = '100';
		$_POST['_page']       = '1';
		$_POST['_url']        = admin_url( 'edit-comments.php' );

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'delete-comment' );
	}


	/**
	 * Tests with a bad nonce.
	 *
	 * Expects test to fail.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param string     $action  Action: 'trash', 'untrash', etc.
	 */
	public function _test_with_bad_nonce( $comment, $action ) {

		// Reset request.
		$this->_clear_post_action();

		// Become a subscriber.
		$this->_setRole( 'administrator' );

		// Set up the $_POST request.
		$_POST['id']          = $comment->comment_ID;
		$_POST['_ajax_nonce'] = wp_create_nonce( uniqid() );
		$_POST[ $action ]     = '1';
		$_POST['_total']      = count( self::$comments );
		$_POST['_per_page']   = '100';
		$_POST['_page']       = '1';
		$_POST['_url']        = admin_url( 'edit-comments.php' );

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'delete-comment' );
	}

	/**
	 * Tests with a bad ID.
	 *
	 * Expects test to fail.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param string     $action  Action: 'trash', 'untrash', etc.
	 */
	public function _test_with_bad_id( $comment, $action ) {

		// Reset request.
		$this->_clear_post_action();

		// Become a subscriber.
		$this->_setRole( 'administrator' );

		// Set up the $_POST request.
		$_POST['id']          = 12346789;
		$_POST['_ajax_nonce'] = wp_create_nonce( 'delete-comment_12346789' );
		$_POST[ $action ]     = '1';
		$_POST['_total']      = count( self::$comments );
		$_POST['_per_page']   = '100';
		$_POST['_page']       = '1';
		$_POST['_url']        = admin_url( 'edit-comments.php' );

		// Make the request, look for a timestamp in the exception.
		try {
			$this->_handleAjax( 'delete-comment' );
			$this->fail( 'Expected exception: WPAjaxDieStopException' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( 10, strlen( $e->getMessage() ) );
			$this->assertIsNumeric( $e->getMessage() );
		} catch ( Exception $e ) {
			$this->fail( 'Unexpected exception type: ' . get_class( $e ) );
		}
	}

	/**
	 * Tests doubling the action (e.g. trash a trashed comment).
	 *
	 * Expects test to fail.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param string     $action  Action: 'trash', 'untrash', etc.
	 */
	public function _test_double_action( $comment, $action ) {

		// Reset request.
		$this->_clear_post_action();

		// Become a subscriber.
		$this->_setRole( 'administrator' );

		// Set up the $_POST request.
		$_POST['id']          = $comment->comment_ID;
		$_POST['_ajax_nonce'] = wp_create_nonce( 'delete-comment_' . $comment->comment_ID );
		$_POST[ $action ]     = '1';
		$_POST['_total']      = count( self::$comments );
		$_POST['_per_page']   = '100';
		$_POST['_page']       = '1';
		$_POST['_url']        = admin_url( 'edit-comments.php' );

		// Make the request.
		try {
			$this->_handleAjax( 'delete-comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		$this->_last_response = '';

		// Force delete the comment.
		if ( 'delete' === $action ) {
			wp_delete_comment( $comment->comment_ID, true );
		}

		// Make the request again, look for a timestamp in the exception.
		try {
			$this->_handleAjax( 'delete-comment' );
			$this->fail( 'Expected exception: WPAjaxDieStopException' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( 10, strlen( $e->getMessage() ) );
			$this->assertIsNumeric( $e->getMessage() );
		} catch ( Exception $e ) {
			$this->fail( 'Unexpected exception type: ' . get_class( $e ) );
		}
	}

	/**
	 * Deletes a comment as an administrator (expects success).
	 *
	 * @covers ::_wp_ajax_delete_comment_response
	 */
	public function test_ajax_comment_trash_actions_as_administrator() {
		// Test trash/untrash.
		$this->_test_as_admin( self::$comments[0], 'trash' );
		$this->_test_as_admin( self::$comments[0], 'untrash' );

		// Test spam/unspam.
		$this->_test_as_admin( self::$comments[1], 'spam' );
		$this->_test_as_admin( self::$comments[1], 'unspam' );

		// Test delete.
		$this->_test_as_admin( self::$comments[2], 'delete' );
	}

	/**
	 * Deletes a comment as a subscriber (expects permission denied).
	 */
	public function test_ajax_comment_trash_actions_as_subscriber() {
		// Test trash/untrash.
		$this->_test_as_subscriber( self::$comments[0], 'trash' );
		$this->_test_as_subscriber( self::$comments[0], 'untrash' );

		// Test spam/unspam.
		$this->_test_as_subscriber( self::$comments[1], 'spam' );
		$this->_test_as_subscriber( self::$comments[1], 'unspam' );

		// Test delete.
		$this->_test_as_subscriber( self::$comments[2], 'delete' );
	}

	/**
	 * Deletes a comment with no ID.
	 *
	 * @covers ::_wp_ajax_delete_comment_response
	 */
	public function test_ajax_trash_comment_no_id() {
		// Test trash/untrash.
		$this->_test_as_admin( self::$comments[0], 'trash' );
		$this->_test_as_admin( self::$comments[0], 'untrash' );

		// Test spam/unspam.
		$this->_test_as_admin( self::$comments[1], 'spam' );
		$this->_test_as_admin( self::$comments[1], 'unspam' );

		// Test delete.
		$this->_test_as_admin( self::$comments[2], 'delete' );
	}

	/**
	 * Deletes a comment with a bad nonce.
	 */
	public function test_ajax_trash_comment_bad_nonce() {
		// Test trash/untrash.
		$this->_test_with_bad_nonce( self::$comments[0], 'trash' );
		$this->_test_with_bad_nonce( self::$comments[0], 'untrash' );

		// Test spam/unspam.
		$this->_test_with_bad_nonce( self::$comments[1], 'spam' );
		$this->_test_with_bad_nonce( self::$comments[1], 'unspam' );

		// Test delete.
		$this->_test_with_bad_nonce( self::$comments[2], 'delete' );
	}

	/**
	 * Tests trashing an already trashed comment, etc.
	 */
	public function test_ajax_trash_double_action() {

		// Test trash/untrash.
		$this->_test_double_action( self::$comments[0], 'trash' );
		$this->_test_double_action( self::$comments[0], 'untrash' );
		// Test spam/unspam.
		$this->_test_double_action( self::$comments[1], 'spam' );
		$this->_test_double_action( self::$comments[1], 'unspam' );

		// Test delete.
		$this->_test_double_action( self::$comments[2], 'delete' );
	}

	/**
	 * Tests emptying spam comments in batches.
	 *
	 * @covers ::wp_ajax_empty_comments
	 */
	public function test_ajax_empty_comments_deletes_spam_in_batches() {

		$this->_setRole( 'administrator' );

		$comment_ids = self::factory()->comment->create_many(
			3,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
			)
		);

		add_filter( 'wp_empty_comments_batch_size', array( $this, 'filter_empty_comments_batch_size' ) );

		$response = $this->_empty_comments( 'spam', gmdate( 'Y-m-d H:i:s', time() + 10 ) );

		remove_filter( 'wp_empty_comments_batch_size', array( $this, 'filter_empty_comments_batch_size' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, $response['data']['deleted'] );
		$this->assertSame( 1, $response['data']['remaining'] );
		$this->assertFalse( $response['data']['done'] );
		$this->assertNull( get_comment( $comment_ids[0] ) );
		$this->assertNull( get_comment( $comment_ids[1] ) );
		$this->assertInstanceOf( WP_Comment::class, get_comment( $comment_ids[2] ) );
	}

	/**
	 * Tests emptying trash comments returns done when all matching comments are deleted.
	 *
	 * @covers ::wp_ajax_empty_comments
	 */
	public function test_ajax_empty_comments_returns_done_when_trash_is_empty() {

		$this->_setRole( 'administrator' );

		self::factory()->comment->create_many(
			2,
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'trash',
			)
		);

		$response = $this->_empty_comments( 'trash', gmdate( 'Y-m-d H:i:s', time() + 10 ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 2, $response['data']['deleted'] );
		$this->assertSame( 0, $response['data']['remaining'] );
		$this->assertTrue( $response['data']['done'] );
	}

	/**
	 * Tests emptying comments does not delete comments newer than the page generation timestamp.
	 *
	 * @covers ::wp_ajax_empty_comments
	 */
	public function test_ajax_empty_comments_preserves_comments_after_pagegen_timestamp() {

		$this->_setRole( 'administrator' );

		$delete_time = gmdate( 'Y-m-d H:i:s', time() );
		$old_comment = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
				'comment_date'     => gmdate( 'Y-m-d H:i:s', time() - 10 ),
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 10 ),
			)
		);
		$new_comment = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 'spam',
				'comment_date'     => gmdate( 'Y-m-d H:i:s', time() + 10 ),
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + 10 ),
			)
		);

		$response = $this->_empty_comments( 'spam', $delete_time );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['deleted'] );
		$this->assertSame( 0, $response['data']['remaining'] );
		$this->assertNull( get_comment( $old_comment ) );
		$this->assertInstanceOf( WP_Comment::class, get_comment( $new_comment ) );
	}

	/**
	 * Tests emptying comments rejects unsupported statuses.
	 *
	 * @covers ::wp_ajax_empty_comments
	 */
	public function test_ajax_empty_comments_rejects_invalid_status() {

		$this->_setRole( 'administrator' );

		$response = $this->_empty_comments( 'approved', gmdate( 'Y-m-d H:i:s', time() + 10 ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid comment status.', $response['data']['message'] );
	}

	/**
	 * Tests emptying comments requires moderation permissions.
	 *
	 * @covers ::wp_ajax_empty_comments
	 */
	public function test_ajax_empty_comments_requires_moderation_permission() {

		$this->_setRole( 'subscriber' );

		$response = $this->_empty_comments( 'spam', gmdate( 'Y-m-d H:i:s', time() + 10 ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Sorry, you are not allowed to moderate comments on this site.', $response['data']['message'] );
	}

	/**
	 * Tests emptying comments requires a valid nonce.
	 *
	 * @covers ::wp_ajax_empty_comments
	 */
	public function test_ajax_empty_comments_requires_valid_nonce() {

		$this->_setRole( 'administrator' );

		$this->_clear_post_action();

		$_POST['comment_status']    = 'spam';
		$_POST['pagegen_timestamp'] = gmdate( 'Y-m-d H:i:s', time() + 10 );
		$_POST['_ajax_nonce']       = wp_create_nonce( uniqid() );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'empty-comments' );
	}

	/**
		* Filters the empty comments batch size for tests.
		*
	 * @return int Batch size.
	 */
	public function filter_empty_comments_batch_size() {

		return 2;
	}
}
