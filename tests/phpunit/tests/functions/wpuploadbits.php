<?php
/**
 * Tests the wp_upload_bits() function.
 *
 * @group functions.php
 * @group upload
 *
 * @covers ::wp_upload_bits
 */

class Tests_Functions_WpUploadBits extends WP_UnitTestCase {

	/**
	 * @ticket 57130
	 *
	 * @expectedDeprecated wp_upload_bits
	 */
	public function test_wp_upload_bits_should_throw_Deprecated_error_if_second_parm_present_and_not_null() {
		wp_upload_bits( 'filename.txt', 'not_null', 'bits' );
	}

	/**
	 * Tests that wp_upload_bits() returns an array with an error message if the filename is empty.
	 *
	 * @ticket 57130
	 **/
	public function test_wp_upload_bits_should_return_an_array_with_error_message_if_no_name_present() {
		$this->assertSameSets( array( 'error' => __( 'Empty filename' ) ), wp_upload_bits( '', '', 'bits' ) );
	}

	/**
	 * Tests that wp_upload_bits() returns an array with an error message if the file type is not allowed.
	 *
	 * @ticket 57130
	 **/
	public function test_wp_upload_bits_should_return_an_array_with_error_message_if_filename_without_an_extension() {
		$this->assertSameSets( array( 'error' => __( 'Sorry, you are not allowed to upload this file type.' ) ), wp_upload_bits( 'filename', '', 'bits' ) );
	}

	/**
	 * @ticket 57130
	 *
	 *
	 **/
	public function test_should_return_error_if_bad_time_path_is_passed() {
		$this->assertSameSets(
			array(
				'path'    => ABSPATH . 'wp-content/uploads/.././/1',
				'url'     => 'http://example.org/wp-content/uploads/.././/1',
				'subdir'  => '/.././/1',
				'basedir' => ABSPATH . 'wp-content/uploads',
				'baseurl' => 'http://example.org/wp-content/uploads',
				'error'   => 'Unable to create directory wp-content/uploads/.././/1. Is its parent directory writable by the server?',
			),
			wp_upload_bits( 'filename.jpg', null, 'bits', '../../12' )
		);
	}

	/**
	 * Tests that wp_upload_bits() creates a file in the upload folder with the given content.
	 *
	 * @ticket 57130
	 */
	public function test_wp_upload_bits_should_create_file_in_upload_folder_with_given_content() {
		$filename = ABSPATH . 'wp-content/uploads/9999/12/filename.txt';
		$content  = 'file content';

		$this->assertSameSets(
			array(
				'error' => false,
				'path'  => $filename,
				'url'   => 'http://example.org/wp-content/uploads/9999/12/filename.txt',
				'type'  => 'text/plain',

			),
			wp_upload_bits( 'filename.txt', null, $content, '9999/12' ),
			'wp_upload_bits() did not return the expected result.'
		);
		$file          = fopen( $filename, 'rb' );
		$file_contents = fread( $file, filesize( $filename ) );
		fclose( $file );
		$this->unlink( $filename );

		$this->assertSame( $content, $file_contents, 'The content of the file does not match the expected value.' );
	}
}
