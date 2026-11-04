<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `wp_upload_bits()` function.
 *
 * @group functions
 * @group upload
 *
 * @covers ::wp_upload_bits
 */
class WpUploadBitsTest extends WP_UnitTestCase {

	public function tear_down() {
		$this->remove_added_uploads();

		parent::tear_down();
	}

	/**
	 * Tests that wp_upload_bits() triggers a deprecation warning when the second argument is not empty.
	 *
	 * @ticket 57130
	 *
	 * @expectedDeprecated wp_upload_bits
	 */
	public function test_wp_upload_bits_should_throw_deprecated_error_if_second_parm_present_and_not_null() {
		wp_upload_bits( 'filename.txt', 'not_null', 'bits' );
	}

	/**
	 * Tests that wp_upload_bits() returns an array with an error message if the filename is empty.
	 *
	 * @ticket 57130
	 */
	public function test_wp_upload_bits_should_return_an_array_with_error_message_if_no_name_present() {
		$this->assertSame( array( 'error' => __( 'Empty filename' ) ), wp_upload_bits( '', '', 'bits' ) );
	}

	/**
	 * Tests that wp_upload_bits() returns an error if a bad time path is passed.
	 *
	 * @ticket 57130
	 */
	public function test_should_return_error_if_bad_time_path_is_passed() {
		$this->assertSame(
			wp_upload_dir( '../../12' ),
			wp_upload_bits( 'filename.jpg', null, 'bits', '../../12' )
		);
	}
}
