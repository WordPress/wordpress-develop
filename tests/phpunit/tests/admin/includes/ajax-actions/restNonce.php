<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_rest_nonce() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.7.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_rest_nonce
 */
class Tests_wp_ajax_rest_nonce extends WP_Ajax_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_rest-nonce', 'wp_ajax_rest_nonce', 1 );

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
	 * Tests success for wp_ajax_rest_nonce().
	 *
	 * @ticket 65243
	 */
	public function test_rest_nonce_success(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		try {
			$this->_handleAjax( 'rest-nonce' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$this->assertNotEmpty( $this->_last_response, 'Response should not be empty' );
		$this->assertEquals( wp_create_nonce( 'wp_rest' ), $this->_last_response, 'Response should be a valid REST nonce' );
	}
}
