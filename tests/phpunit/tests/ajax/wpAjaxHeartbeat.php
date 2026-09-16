<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax save draft functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_heartbeat
 */
class Tests_Ajax_wpAjaxHeartbeat extends WP_Ajax_UnitTestCase {

	/**
	 * Post
	 *
	 * @var mixed
	 */
	protected $_post = null;

	protected static $admin_id  = 0;
	protected static $editor_id = 0;
	protected static $post;
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );

		// Set a user so the $post has 'post_author'.
		wp_set_current_user( self::$admin_id );

		self::$post_id = $factory->post->create( array( 'post_status' => 'draft' ) );
		self::$post    = get_post( self::$post_id );
	}

	/**
	 * Tests autosaving a post.
	 */
	public function test_autosave_post() {
		// The original post_author.
		wp_set_current_user( self::$admin_id );

		// Set up the $_POST request.
		$md5   = md5( uniqid() );
		$_POST = array(
			'action' => 'heartbeat',
			'_nonce' => wp_create_nonce( 'heartbeat-nonce' ),
			'data'   => array(
				'wp_autosave' => array(
					'post_id'      => self::$post_id,
					'_wpnonce'     => wp_create_nonce( 'update-post_' . self::$post_id ),
					'post_content' => self::$post->post_content . PHP_EOL . $md5,
					'post_type'    => 'post',
				),
			),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response, it is in heartbeat's response.
		$response = json_decode( $this->_last_response, true );

		// Ensure everything is correct.
		$this->assertNotEmpty( $response['wp_autosave'] );
		$this->assertTrue( $response['wp_autosave']['success'] );

		// Check that the edit happened.
		$post = get_post( self::$post_id );
		$this->assertStringContainsString( $md5, $post->post_content );
	}

	/**
	 * Tests autosaving a locked post.
	 */
	public function test_autosave_locked_post() {
		// Lock the post to another user.
		wp_set_current_user( self::$editor_id );
		wp_set_post_lock( self::$post_id );

		wp_set_current_user( self::$admin_id );

		// Ensure post is locked.
		$this->assertEquals( self::$editor_id, wp_check_post_lock( self::$post_id ) );

		// Set up the $_POST request.
		$md5   = md5( uniqid() );
		$_POST = array(
			'action' => 'heartbeat',
			'_nonce' => wp_create_nonce( 'heartbeat-nonce' ),
			'data'   => array(
				'wp_autosave' => array(
					'post_id'      => self::$post_id,
					'_wpnonce'     => wp_create_nonce( 'update-post_' . self::$post_id ),
					'post_content' => self::$post->post_content . PHP_EOL . $md5,
					'post_type'    => 'post',
				),
			),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		// Ensure everything is correct.
		$this->assertNotEmpty( $response['wp_autosave'] );
		$this->assertTrue( $response['wp_autosave']['success'] );

		// Check that the original post was NOT edited.
		$post = get_post( self::$post_id );
		$this->assertStringNotContainsString( $md5, $post->post_content );

		// Check if the autosave post was created.
		$autosave = wp_get_post_autosave( self::$post_id, get_current_user_id() );
		$this->assertNotEmpty( $autosave );
		$this->assertStringContainsString( $md5, $autosave->post_content );
	}

	/**
	 * Tests with an invalid nonce.
	 */
	public function test_with_invalid_nonce() {

		wp_set_current_user( self::$admin_id );

		// Set up the $_POST request.
		$_POST = array(
			'action' => 'heartbeat',
			'_nonce' => wp_create_nonce( 'heartbeat-nonce' ),
			'data'   => array(
				'wp_autosave' => array(
					'post_id'  => self::$post_id,
					'_wpnonce' => substr( md5( uniqid() ), 0, 10 ),
				),
			),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertNotEmpty( $response['wp_autosave'] );
		$this->assertFalse( $response['wp_autosave']['success'] );
	}

	/**
	 * Tests that an expired or invalid heartbeat nonce returns only refreshed nonces.
	 *
	 * @ticket 24447
	 */
	public function test_expired_nonce_returns_refreshed_nonces_only() {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'    => 'heartbeat',
			'_nonce'    => 'expired_invalid_nonce',
			'screen_id' => 'post',
			'data'      => array(
				'wp-refresh-post-nonces' => array(
					'post_id' => self::$post_id,
				),
			),
		);

		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		// Fresh nonces should be present for recovery.
		$this->assertArrayHasKey( 'heartbeat_nonce', $response, 'Response should contain a fresh heartbeat nonce for recovery.' );
		$this->assertArrayHasKey( 'rest_nonce', $response, 'Response should contain a fresh REST nonce for recovery.' );
		$this->assertSame( 1, wp_verify_nonce( $response['heartbeat_nonce'], 'heartbeat-nonce' ), 'The fresh heartbeat nonce should be valid.' );
		$this->assertSame( 1, wp_verify_nonce( $response['rest_nonce'], 'wp_rest' ), 'The fresh REST nonce should be valid.' );

		// Post nonces should also be refreshed via wp_refresh_nonces filter.
		$this->assertArrayHasKey( 'wp-refresh-post-nonces', $response, 'Response should contain refreshed post nonces.' );
		$this->assertArrayHasKey( '_wpnonce', $response['wp-refresh-post-nonces']['replace'], 'Post nonces should include _wpnonce.' );
		$this->assertSame(
			1,
			wp_verify_nonce( $response['wp-refresh-post-nonces']['replace']['_wpnonce'], 'update-post_' . self::$post_id ),
			'The refreshed post nonce should be valid.'
		);

		// The legacy nonces_expired flag should not be set.
		$this->assertArrayNotHasKey( 'nonces_expired', $response, 'Response should not set nonces_expired.' );

		// server_time is set after heartbeat_tick — its absence proves the early return.
		$this->assertArrayNotHasKey( 'server_time', $response, 'Response should not contain server_time since heartbeat_tick must not fire.' );
	}

	/**
	 * Tests that autosave does NOT run when the heartbeat nonce is expired.
	 *
	 * @ticket 24447
	 */
	public function test_autosave_blocked_when_nonce_expired() {
		wp_set_current_user( self::$admin_id );

		$md5   = md5( uniqid() );
		$_POST = array(
			'action'    => 'heartbeat',
			'_nonce'    => 'expired_invalid_nonce',
			'screen_id' => 'post',
			'data'      => array(
				'wp_autosave' => array(
					'post_id'      => self::$post_id,
					'_wpnonce'     => wp_create_nonce( 'update-post_' . self::$post_id ),
					'post_content' => self::$post->post_content . PHP_EOL . $md5,
					'post_type'    => 'post',
				),
			),
		);

		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		// Autosave should not have run.
		$this->assertArrayNotHasKey( 'wp_autosave', $response, 'Autosave must not run when heartbeat nonce is expired.' );

		// Post content should be unchanged.
		$post = get_post( self::$post_id );
		$this->assertStringNotContainsString( $md5, $post->post_content, 'Post should not be modified when heartbeat nonce is expired.' );
	}
}
