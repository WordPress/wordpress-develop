<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_send_password_reset() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.7.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_send_password_reset
 */
class Tests_wp_ajax_send_password_reset extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Target user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_id  = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_send-password-reset', 'wp_ajax_send_password_reset', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );

		// Clear mock email.
		reset_phpmailer_instance();
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
	 * Tests success for sending a password reset email.
	 *
	 * @ticket 65252
	 */
	public function test_send_password_reset_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['user_id'] = self::$user_id;
		$_POST['nonce']   = wp_create_nonce( 'reset-password-for-' . self::$user_id );

		try {
			$this->_handleAjax( 'send-password-reset' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$user     = get_userdata( self::$user_id );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertStringContainsString( sprintf( 'A password reset link was emailed to %s.', $user->display_name ), $response['data'] );

		// Verify email was sent.
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( 'Password Reset', $mailer->get_sent()->subject );
		$this->assertEquals( $user->user_email, $mailer->get_sent()->to[0][0] );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_send_password_reset().
	 *
	 * @ticket 65252
	 */
	public function test_send_password_reset_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['user_id'] = self::$user_id;
		$_POST['nonce']   = 'invalid-nonce';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'send-password-reset' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_send_password_reset().
	 *
	 * @ticket 65252
	 */
	public function test_send_password_reset_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['user_id'] = self::$admin_id;
		$_POST['nonce']   = wp_create_nonce( 'reset-password-for-' . self::$admin_id );

		try {
			$this->_handleAjax( 'send-password-reset' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'Cannot send password reset, permission denied.', $response['data'] );
	}
}
