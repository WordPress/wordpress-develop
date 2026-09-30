<?php

/**
 * Test send_frame_options_header().
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * @group functions
 *
 * @covers ::send_frame_options_header
 */
class Tests_Functions_sendFrameOptionsHeader extends WP_UnitTestCase {

	/**
	 * @ticket 59851
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_send_frame_options_header_sends_expected_headers() {
		send_frame_options_header();

		$headers = xdebug_get_headers();

		$this->assertContains( 'X-Frame-Options: SAMEORIGIN', $headers );
		$this->assertContains( "Content-Security-Policy: frame-ancestors 'self';", $headers );
	}
}
