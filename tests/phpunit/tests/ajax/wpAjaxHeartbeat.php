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
		$this->assertSame( self::$editor_id, wp_check_post_lock( self::$post_id ) );

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

	public function tear_down() {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		parent::tear_down();
	}

	/**
	 * Tests that an expired Heartbeat nonce without a refresh nonce does not return fresh nonces.
	 */
	public function test_expired_nonce_without_refresh_nonce_returns_nonces_expired_only() {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action' => 'heartbeat',
			'_nonce' => 'expired123',
		);

		$response = $this->make_heartbeat_request();

		$this->assertSame( array( 'nonces_expired' => true ), $response );
	}

	/**
	 * Tests that an expired Heartbeat nonce with an invalid refresh nonce does not return fresh nonces.
	 */
	public function test_expired_nonce_with_invalid_refresh_nonce_returns_nonces_expired_only() {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'        => 'heartbeat',
			'_nonce'        => 'expired123',
			'refresh_nonce' => 'invalid123',
		);

		$response = $this->make_heartbeat_request();

		$this->assertSame( array( 'nonces_expired' => true ), $response );
	}

	/**
	 * Tests that an expired Heartbeat nonce with a valid refresh nonce returns fresh nonces.
	 */
	public function test_expired_nonce_with_valid_refresh_nonce_returns_fresh_nonces() {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'        => 'heartbeat',
			'_nonce'        => 'expired123',
			'refresh_nonce' => wp_create_nonce( 'heartbeat-refresh-nonce' ),
		);

		$response = $this->make_heartbeat_request();

		$this->assertArrayNotHasKey( 'nonces_expired', $response, 'Nonces should have been refreshed.' );
		$this->assertSame( 1, wp_verify_nonce( $response['rest_nonce'], 'wp_rest' ), 'The REST API nonce should be fresh.' );
		$this->assertSame( 1, wp_verify_nonce( $response['heartbeat_nonce'], 'heartbeat-nonce' ), 'The Heartbeat nonce should be fresh.' );
		$this->assertSame( 1, wp_verify_nonce( $response['heartbeat_refresh_nonce'], 'heartbeat-refresh-nonce' ), 'The refresh nonce should be rotated.' );
	}

	/**
	 * Tests that a refresh nonce belonging to another user does not return fresh nonces.
	 */
	public function test_expired_nonce_with_other_users_refresh_nonce_returns_nonces_expired_only() {
		wp_set_current_user( self::$editor_id );
		$refresh_nonce = wp_create_nonce( 'heartbeat-refresh-nonce' );

		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'        => 'heartbeat',
			'_nonce'        => 'expired123',
			'refresh_nonce' => $refresh_nonce,
		);

		$response = $this->make_heartbeat_request();

		$this->assertSame( array( 'nonces_expired' => true ), $response );
	}

	/**
	 * Tests that nonces from before an interim login need the refresh nonce of the new session.
	 *
	 * Logging in again through the interim login dialog starts a new session.
	 * The page behind the dialog keeps the nonces of the old one, so its own
	 * refresh nonce can no longer renew them; the login success page hands it a
	 * refresh nonce for the new session instead.
	 */
	public function test_expired_session_nonces_renew_with_refresh_nonce_of_new_session() {
		wp_set_current_user( self::$admin_id );

		$this->use_session( self::$admin_id );
		$stale_heartbeat_nonce = wp_create_nonce( 'heartbeat-nonce' );
		$stale_refresh_nonce   = wp_create_nonce( 'heartbeat-refresh-nonce' );

		// The interim login starts a new session.
		$this->use_session( self::$admin_id );

		$_POST = array(
			'action'        => 'heartbeat',
			'_nonce'        => $stale_heartbeat_nonce,
			'refresh_nonce' => $stale_refresh_nonce,
		);

		$this->assertSame(
			array( 'nonces_expired' => true ),
			$this->make_heartbeat_request(),
			'A refresh nonce from the previous session should not renew nonces.'
		);

		$_POST['refresh_nonce'] = wp_create_nonce( 'heartbeat-refresh-nonce' );
		$this->_last_response   = '';

		$response = $this->make_heartbeat_request();

		$this->assertArrayNotHasKey( 'nonces_expired', $response, 'Nonces should have been refreshed.' );
		$this->assertSame( 1, wp_verify_nonce( $response['heartbeat_nonce'], 'heartbeat-nonce' ), 'The Heartbeat nonce should belong to the new session.' );
		$this->assertSame( 1, wp_verify_nonce( $response['rest_nonce'], 'wp_rest' ), 'The REST API nonce should belong to the new session.' );
	}

	/**
	 * Points the logged-in cookie at a new session for a user.
	 *
	 * @param int $user_id User ID.
	 */
	private function use_session( $user_id ) {
		$expiration                  = time() + DAY_IN_SECONDS;
		$token                       = WP_Session_Tokens::get_instance( $user_id )->create( $expiration );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );
	}

	/**
	 * Makes a Heartbeat request and returns the decoded response.
	 *
	 * @return array The decoded Heartbeat response.
	 */
	private function make_heartbeat_request() {
		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}
}
