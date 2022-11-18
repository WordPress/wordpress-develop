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
	 * @expectedDeprecated
	 * @return void
	 *
	 */
	public function test_second_parm_present() {
		$this->expectExceptionMessage( 'Function wp_upload_bits was called with an argument that is deprecated since version 2.0.0 with no alternative available.' );
		$this->expectDeprecation();
		$this->expectException( 'PHPUnit\Framework\Error\Deprecated' );
		wp_upload_bits( 'filename.txt', 'not_null', 'bits' );
	}
	/**
	 * return error if no filename pass in
	 * @expectedDeprecated
	 **/
	public function test_no_name_present() {
		$this->expectDeprecation();
		$this->assertSameSets( array( 'error' => __( 'Empty filename' ) ), wp_upload_bits( '', '', 'bits' ) );
	}

	/**
	 * return an array with error message in bad/no extension in the file name
	 * @expectedDeprecated
	 **/
	public function test_no_ext_present() {
		$this->assertSameSets( array( 'error' => __( 'Sorry, you are not allowed to upload this file type.' ) ), wp_upload_bits( 'filename', '', 'bits' ) );
	}

	public function test_bad_time_present() {
		$this->assertSameSets(
			array(
				'path'    => '/var/www/src/wp-content/uploads/../1/',
				'url'     => 'http://example.org/wp-content/uploads/../1/',
				'subdir'  => '/../1/',
				'basedir' => '/var/www/src/wp-content/uploads',
				'baseurl' => 'http://example.org/wp-content/uploads',
				'error'   => 'Unable to create directory wp-content/uploads/../1/. Is its parent directory writable by the server?',
			),
			wp_upload_bits( 'filename.jpg', null, 'bits', '../12' )
		);
	}

	public function test_file_content() {
		$filename = '/var/www/src/wp-content/uploads/99/1//filename.txt';
		$content = 'file content';

		$this->unlink( $filename );
		$this->assertSameSets(
			array(
				'error' => false,
				'path'  => $filename,
				'url'   => 'http://example.org/wp-content/uploads/99/1//filename.txt',
				'type'  => 'text/plain',

			),
			wp_upload_bits( 'filename.txt', null, $content, '99/12' )
		);
		$file = fopen( $filename, 'r' );
		$this->assertEquals( $content, fread( $file, filesize( $filename ) ) );
		fclose( $file );
		$this->unlink( $filename );
	}
}
