<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_destroy_sessions() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_destroy_sessions
 */
class Tests_wp_ajax_destroy_sessions extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_destroy-sessions', 'wp_ajax_destroy_sessions', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
		parent::tear_down();
	}

	/**
	 * Returns our custom die handler.
	 *
	 * @return callable
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Custom die handler that throws an exception.
	 *
	 * @param string|WP_Error $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				$this->_last_response = (string) $message;
			} else {
				$this->_last_response = '0';
			}
		}

		if ( '-1' === $this->_last_response || ( is_int( $message ) && -1 === $message ) ) {
			throw new WPAjaxDieStopException( $this->_last_response );
		}

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Tests success for wp_ajax_destroy_sessions() for current user.
	 *
	 * @ticket 65252
	 */
	public function test_destroy_sessions_current_user_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['user_id'] = self::$admin_id;
		$_POST['nonce']   = wp_create_nonce( 'update-user_' . self::$admin_id );

		try {
			$this->_handleAjax( 'destroy-sessions' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'logged out everywhere else', $response['data']['message'] );
	}

	/**
	 * Tests success for wp_ajax_destroy_sessions() for another user.
	 *
	 * @ticket 65252
	 */
	public function test_destroy_sessions_other_user_success(): void {
		wp_set_current_user( self::$admin_id );

		$other_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$_POST['user_id'] = $other_user_id;
		$_POST['nonce']   = wp_create_nonce( 'update-user_' . $other_user_id );

		try {
			$this->_handleAjax( 'destroy-sessions' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( 'has been logged out', $response['data']['message'] );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_destroy_sessions().
	 *
	 * @ticket 65252
	 */
	public function test_destroy_sessions_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['user_id'] = self::$admin_id;
		$_POST['nonce']   = 'invalid-nonce';

		try {
			$this->_handleAjax( 'destroy-sessions' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_destroy_sessions().
	 *
	 * @ticket 65252
	 */
	public function test_destroy_sessions_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['user_id'] = self::$admin_id;
		$_POST['nonce']   = wp_create_nonce( 'update-user_' . self::$admin_id );

		try {
			$this->_handleAjax( 'destroy-sessions' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
