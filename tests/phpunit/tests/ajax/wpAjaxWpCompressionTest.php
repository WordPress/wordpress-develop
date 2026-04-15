<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax compression test functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_wp_compression_test
 */
class Tests_Ajax_wpAjaxWpCompressionTest extends WP_Ajax_UnitTestCase {

	/**
	 * Tests that the compression test AJAX handler is deprecated.
	 *
	 * @ticket 57548
	 * @expectedDeprecated wp_ajax_wp_compression_test
	 */
	public function test_handler_is_deprecated() {
		$this->_setRole( 'administrator' );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '0' );
		$this->_handleAjax( 'wp-compression-test' );
	}
}
