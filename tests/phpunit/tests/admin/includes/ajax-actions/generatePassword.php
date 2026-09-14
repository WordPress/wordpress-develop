<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_generate_password() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_generate_password
 */
class Tests_wp_ajax_generate_password extends WP_Ajax_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_generate-password', 'wp_ajax_generate_password', 1 );
		add_action( 'wp_ajax_nopriv_generate-password', 'wp_ajax_generate_password', 1 );

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
	 * Tests success for wp_ajax_generate_password() for logged in user.
	 *
	 * @ticket 65252
	 */
	public function test_generate_password_logged_in_success(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		try {
			$this->_handleAjax( 'generate-password' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertNotEmpty( $response['data'], 'Generated password should not be empty' );
		$this->assertIsString( $response['data'], 'Generated password should be a string' );
	}

	/**
	 * Tests success for wp_ajax_generate_password() for non-logged in user.
	 *
	 * @ticket 65252
	 */
	public function test_generate_password_nopriv_success(): void {
		wp_set_current_user( 0 );

		try {
			$this->_handleAjax( 'generate-password' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertNotEmpty( $response['data'], 'Generated password should not be empty' );
	}
}
