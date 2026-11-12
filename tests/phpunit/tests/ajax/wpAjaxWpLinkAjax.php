<?php

/**
 * Tests for the wp_ajax_wp_link_ajax() function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.9.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_wp_link_ajax
 */
class Tests_Ajax_wpAjaxWpLinkAjax extends WP_Ajax_UnitTestCase {

	/**
	 * Tests that the response is sent as JSON.
	 *
	 * @ticket 49408
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @group xdebug
	 * @requires function xdebug_get_headers
	 */
	public function test_response_is_sent_as_json() {
		$this->_setRole( 'administrator' );

		$_POST['_ajax_linking_nonce'] = wp_create_nonce( 'internal-linking' );

		try {
			$this->_handleAjax( 'wp-link-ajax' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$this->assertIsArray( json_decode( $this->_last_response, true ) );
		$this->assertContains( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ), xdebug_get_headers() );
	}
}
