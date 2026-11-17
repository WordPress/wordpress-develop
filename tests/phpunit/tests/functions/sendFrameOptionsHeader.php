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

	/**
	 * Just test for function
	 *
	 * @ticket 59851
	 *
	 * @requires function xdebug_get_headers
	 */
	public function test_send_frame_options_header() {
		ob_start();
		send_frame_options_header();
		// Check the header.
		$headers = xdebug_get_headers();
		ob_end_clean();

		$this->assertContains( 'X-Frame-Options: SAMEORIGIN', $headers );
	}
}
