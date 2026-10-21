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
		$upload_dir = wp_upload_dir();
		if ( is_dir( $upload_dir['basedir'] . '/9999/12' ) ) {
			rmdir( $upload_dir['basedir'] . '/9999/12' );
		}
		if ( is_dir( $upload_dir['basedir'] . '/9999' ) ) {
			rmdir( $upload_dir['basedir'] . '/9999' );
		}

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
	 * Tests that wp_upload_bits() returns an array with an error message if the file type is not allowed.
	 *
	 * @ticket 57130
	 */
	public function test_wp_upload_bits_should_return_an_array_with_error_message_if_filename_without_an_extension() {
		$this->assertSame( array( 'error' => __( 'Sorry, you are not allowed to upload this file type.' ) ), wp_upload_bits( 'filename', '', 'bits' ) );
	}

	/**
	 * Tests that wp_upload_bits() returns an error if a bad time path is passed.
	 *
	 * @ticket 57130
	 */
	public function test_should_return_error_if_bad_time_path_is_passed() {
		$upload_dir = wp_upload_dir();
		$expected   = array(
			'path'    => $upload_dir['basedir'] . '/.././/1',
			'url'     => $upload_dir['baseurl'] . '/.././/1',
			'subdir'  => '/.././/1',
			'basedir' => $upload_dir['basedir'],
			'baseurl' => $upload_dir['baseurl'],
			'error'   => 'Unable to create directory wp-content/uploads/.././/1. Is its parent directory writable by the server?',
		);

		$this->assertSame(
			$expected,
			wp_upload_bits( 'filename.jpg', null, 'bits', '../../12' )
		);
	}

	/**
	 * Tests that wp_upload_bits() creates a file in the upload folder with the given content.
	 *
	 * @ticket 57130
	 */
	public function test_wp_upload_bits_should_create_file_in_upload_folder_with_given_content() {
		$upload_dir = wp_upload_dir();
		$filename   = $upload_dir['basedir'] . '/9999/12/filename.txt';
		$content    = 'file content';

		$expected = array(
			'file'  => $filename,
			'url'   => $upload_dir['baseurl'] . '/9999/12/filename.txt',
			'type'  => 'text/plain',
			'error' => false,
		);

		$this->assertSame(
			$expected,
			wp_upload_bits( 'filename.txt', null, $content, '9999/12' ),
			'wp_upload_bits() did not return the expected result.'
		);

		$this->assertFileExists( $filename );
		$this->assertSame( $content, file_get_contents( $filename ), 'The content of the file does not match the expected value.' );

		$this->unlink( $filename );
	}
}
