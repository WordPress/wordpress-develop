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
 * @since 5.3.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_rest_nonce
 */
class Tests_Ajax_wpAjaxRestNonce extends WP_Ajax_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_action( 'wp_ajax_rest-nonce', 'wp_ajax_rest_nonce' );
	}

	/**
	 * Tests the rest-nonce AJAX action.
	 *
	 * @ticket 65243
	 */
	public function test_wp_ajax_rest_nonce(): void {
		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		// Set up the request.
		$_REQUEST['action'] = 'rest-nonce';

		// Make the request.
		try {
			ob_start();
			$this->_handleAjax( 'rest-nonce' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			$this->_last_response = ob_get_clean();
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
			ob_end_clean();
		}

		// The response should be a valid nonce for 'wp_rest'.
		$this->assertNotEmpty( $this->_last_response, 'The response should not be empty' );
		$this->assertSame( 1, wp_verify_nonce( $this->_last_response, 'wp_rest' ), 'The response should be a valid nonce for "wp_rest"' );
	}

	/**
	 * Tests the rest-nonce AJAX action as a logged-out user.
	 *
	 * @ticket 65243
	 */
	public function test_wp_ajax_rest_nonce_logged_out(): void {
		// Log out.
		wp_set_current_user( 0 );

		// To test the "logged-out" behavior properly, we should verify it DOES NOT have a nopriv handler.
		$this->assertFalse( has_action( 'wp_ajax_nopriv_rest-nonce' ), 'Should not have a nopriv handler' );
	}
}
