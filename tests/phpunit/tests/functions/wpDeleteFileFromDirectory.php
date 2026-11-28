<?php
/**
 * Tests for the wp_delete_file_from_directory function.
 *
 * @group functions
 *
 * @covers ::wp_delete_file_from_directory
 */
class Tests_Functions_wpDeleteFileFromDirectory extends WP_UnitTestCase {

	/**
	 * Call with file and correct directory.
	 *
	 * @ticket 59781
	 */
	public function test_wp_delete_file_from_directory() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
		touch( $file );

		$this->assertTrue( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Call with missing file.
	 *
	 * @ticket 59781
	 */
	public function test_wp_delete_file_from_directory_no_file() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );

		$this->assertFalse( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Call with streamed file.
	 *
	 * @ticket 59781
	 */
	public function test_wp_delete_file_from_directory_file_stream() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
		touch( $file );

		$file_url      = 'file://' . ( '/' === $file[0] ? $file : '/' . wp_normalize_path( $file ) );
		$directory_url = 'file://' . ( '/' === $directory[0] ? $directory : '/' . wp_normalize_path( $directory ) );

		$this->assertTrue( wp_delete_file_from_directory( $file_url, $directory_url ) );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Call with unsupported stream file.
	 *
	 * @ticket 59781
	 */
	public function test_wp_delete_file_from_directory_non_file_stream() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA );
		touch( $file );

		$this->assertFalse( wp_delete_file_from_directory( 'php://temp', 'php://temp' ) );
		$this->assertFileExists( $file );
		@unlink( $file );
	}

	/**
	 * Call with incorrect directory.
	 *
	 * @ticket 59781
	 */
	public function test_wp_delete_file_from_directory_no_folder() {
		$file      = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		$directory = realpath( DIR_TESTDATA ) . '/images';
		touch( $file );

		$this->assertFalse( wp_delete_file_from_directory( $file, $directory ) );
		$this->assertFileExists( $file );
		@unlink( $file );
	}
}
