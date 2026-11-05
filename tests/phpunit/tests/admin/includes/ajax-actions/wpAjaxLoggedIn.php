<?php
/**
 * Testing wp_ajax_logged_in() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.8.0
 */

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_logged_in() functionality.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_logged_in
 */
class Tests_Ajax_WpAjaxLoggedIn extends WP_Ajax_UnitTestCase {

	/**
	 * Tests the logged-in AJAX action.
	 *
	 * @ticket 65242
	 */
	public function test_wp_ajax_logged_in() {
		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		// Set up the request.
		$_REQUEST['action'] = 'logged-in';

		// Make the request.
		try {
			$this->_handleAjax( 'logged-in' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = $e->getMessage();
		}

		// The response should be 1.
		$this->assertSame( '1', $this->_last_response, 'The response should be 1 for logged-in users' );
	}
}
