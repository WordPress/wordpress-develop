<?php

/**
 * Tests for the send_frame_options_header function.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group functions
 * @group xdebug
 *
 * @covers ::send_frame_options_header
 */
class Tests_Functions_SendFrameOptionsHeader extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Suppress warnings from "Cannot modify header information - headers already sent by".
		$this->_error_level = error_reporting();
		error_reporting( $this->_error_level & ~E_WARNING );
	}

	/**
	 * Tear down the test fixture.
	 * Remove the wp_die() override, restore error reporting
	 */
	public function tear_down() {
		error_reporting( $this->_error_level );
		parent::tear_down();
	}

	/**
	 * Just test for function
	 *
	 * @ticket 59851
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_send_frame_options_header() {
		$this->assertTrue( function_exists( 'send_frame_options_header' ) );

		ob_start();
		send_frame_options_header();
		// Check the header.
		$headers = xdebug_get_headers();
		ob_end_clean();

		$this->assertContains( 'X-Frame-Options: SAMEORIGIN', $headers );
	}
}
