<?php
/**
 * Test the wp_upload_bits function
 *
 * @group Functions
 * @group Upload
 * @covers ::wp_upload_bits
 */

class tests_wp_upload_bits extends WP_UnitTestCase {

	/**
	 * @ticket 57130
	 *
	 * @expectedDeprecated wp_upload_bits
	 */
	public function test_wp_upload_bits_should_throw_Deprecated_error_if_second_parm_present_and_not_null() {
		wp_upload_bits( 'filename.txt', 'not_null', 'bits' );
	}

	/**
	 * @ticket 57130
	 *
	 * return error if no filename pass in
	 **/
	public function test_wp_upload_bits_should_return_an_array_with_error_message_if_no_name_present() {
		$this->assertSameSets( array( 'error' => __( 'Empty filename' ) ), wp_upload_bits( '', '', 'bits' ) );
	}

	/**
	 * @ticket 57130
	 *
	 * return an array with error message in bad/no extension in the file name
	 **/
	public function test_wp_upload_bits_should_return_an_array_with_error_message_if_filename_without_an_extension() {
		$this->assertSameSets( array( 'error' => __( 'Sorry, you are not allowed to upload this file type.' ) ), wp_upload_bits( 'filename', '', 'bits' ) );
	}

	/**
	 * @ticket 57130
	 *
	 *
	 **/
	public function test_bad_time_present() {
		$this->assertSameSets(
			array(
				'path'    => ABSPATH . 'wp-content/uploads/../1/',
				'url'     => 'http://example.org/wp-content/uploads/../1/',
				'subdir'  => '/../1/',
				'basedir' => ABSPATH . 'wp-content/uploads',
				'baseurl' => 'http://example.org/wp-content/uploads',
				'error'   => 'Unable to create directory wp-content/uploads/../1/. Is its parent directory writable by the server?',
			),
			wp_upload_bits( 'filename.jpg', null, 'bits', '../12' )
		);
	}

	/**
	 * @ticket 57130
	 *
	 * @return void
	 */
	public function test_file_content() {
		$filename = ABSPATH . 'wp-content/uploads/99/1//filename.txt';
		$content  = 'file content';

		$this->assertSameSets(
			array(
				'error' => false,
				'path'  => $filename,
				'url'   => 'http://example.org/wp-content/uploads/99/1//filename.txt',
				'type'  => 'text/plain',

			),
			wp_upload_bits( 'filename.txt', null, $content, '99/12' )
		);
		$file          = fopen( $filename, 'rb' );
		$file_contents = fread( $file, filesize( $filename ) );
		fclose( $file );
		$this->unlink( $filename );

		$this->assertSame( $content, $file_contents );
	}
}
