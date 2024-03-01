<?php

/**
 * Tests for the send_frame_options_header function.
 *
 * @group functions
 *
 * @covers ::send_frame_options_header
 */
class Tests_Functions_SendFrameOptionsHeader extends WP_UnitTestCase {


	/**
	 * Just test for function
	 *
	 * @ticket 59851
	 */
	public function test_send_frame_options_header() {

		$this->assertTrue( function_exists('send_frame_options_header') );
	}
}
