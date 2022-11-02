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
	 * Test as a logged out user
	 */
	public function test_logged_out() {
		$this->logout();

		// Set up a default request.
		$_GET['test'] = 1;

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'wp-compression-test' );
	}

	/**
	 * Fetch the test text
	 */
	public function test_text() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['test'] = 1;

		// Make the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Ensure we found the right match.
		$this->assertStringContainsString( 'wpCompressionTest', $this->_last_response );
	}

	/**
	 * Fetch the test text (gzdeflate)
	 *
	 * @requires function gzdeflate
	 */
	public function test_gzdeflate() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['test']                    = 2;
		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'deflate';

		// Make the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Ensure we found the right match.
		$this->assertStringContainsString( 'wpCompressionTest', gzinflate( $this->_last_response ) );
	}

	/**
	 * Fetch the test text (gzencode)
	 *
	 * @requires function gzencode
	 */
	public function test_gzencode() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['test']                    = 2;
		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

		// Make the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Ensure we found the right match.
		$this->assertStringContainsString( 'wpCompressionTest', $this->_gzdecode( $this->_last_response ) );
	}

	/**
	 * Fetch the test text (unknown encoding)
	 */
	public function test_unknown_encoding() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['test']                    = 2;
		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'unknown';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'wp-compression-test' );
	}

	/**
	 * Set the 'can_compress_scripts' site option to true
	 */
	public function test_set_yes() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['test'] = 'yes';

		// Set the option to false.
		update_site_option( 'can_compress_scripts', 0 );

		// Make the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		// Check the site option is not changed due to lack of nonce.
		$this->assertSame( 0, get_site_option( 'can_compress_scripts' ) );

		// Add a nonce.
		$_GET['_ajax_nonce'] = wp_create_nonce( 'update_can_compress_scripts' );

		// Retry the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		// Check the site option is changed.
		$this->assertSame( 1, get_site_option( 'can_compress_scripts' ) );
	}

	/**
	 * Set the 'can_compress_scripts' site option to false
	 */
	public function test_set_no() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['test'] = 'no';

		// Set the option to true.
		update_site_option( 'can_compress_scripts', 1 );

		// Make the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		// Check the site option is not changed due to lack of nonce.
		$this->assertSame( 1, get_site_option( 'can_compress_scripts' ) );

		// Add a nonce.
		$_GET['_ajax_nonce'] = wp_create_nonce( 'update_can_compress_scripts' );

		// Retry the request.
		try {
			$this->_handleAjax( 'wp-compression-test' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		// Check the site option is changed.
		$this->assertSame( 0, get_site_option( 'can_compress_scripts' ) );
	}

	/**
	 * Undo gzencode.  This is ugly, but there's no stock gzdecode() function.
	 *
	 * @param string $encoded_data
	 * @return string
	 */
	protected function _gzdecode( $encoded_data ) {

		// Save the encoded data to a temp file.
		$file = wp_tempnam( 'gzdecode' );
		file_put_contents( $file, $encoded_data );

		// Flush it to the output buffer and delete the temp file.
		ob_start();
		readgzfile( $file );
		unlink( $file );

		// Save the data stop buffering.
		$data = ob_get_clean();

		// Done.
		return $data;
	}
}
