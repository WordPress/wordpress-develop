<?php

/**
 * Tests for wp_delete_file().
 *
 * @group functions
 *
 * @covers ::wp_delete_file
 */
class Tests_Functions_WpDeleteFile extends WP_UnitTestCase {

	/**
	 * @ticket 61590
	 */
	public function test_wp_delete_file() {
		$file = wp_tempnam( 'a_file_that_exists.txt' );

		$this->assertTrue( wp_delete_file( $file ), 'File deletion failed.' );
		$this->assertFileDoesNotExist( $file, 'The file was not deleted.' );
	}

	/**
	 * @ticket 61590
	 */
	public function test_wp_delete_file_with_empty_path() {
		$this->assertFalse( wp_delete_file( '' ) );
	}

	/**
	 * @ticket 61590
	 */
	public function test_wp_delete_file_with_file_that_does_not_exist() {
		$file = DIR_TESTDATA . '/a_file_that_does_not_exist.txt';

		$this->assertFileDoesNotExist( $file, "$file already existed as a file before testing." );
		$this->assertFalse( wp_delete_file( $file ), 'Attempting to delete a non-existent file should return false.' );
	}

	/**
	 * @ticket 59788
	 */
	public function test_wp_delete_file_does_not_delete_when_filter_returns_empty() {
		$file = wp_tempnam( 'a_file_that_exists.txt' );

		add_filter( 'wp_delete_file', '__return_empty_string' );

		$this->assertFalse( wp_delete_file( $file ), 'wp_delete_file() should return false when the filter returns an empty path.' );
		$this->assertFileExists( $file, 'The file should not be deleted when the filter returns an empty path.' );

		unlink( $file );
	}

	/**
	 * @ticket 59788
	 */
	public function test_wp_delete_file_deletes_the_filtered_path() {
		$file     = wp_tempnam( 'a_file_that_exists.txt' );
		$filtered = wp_tempnam( 'a_filtered_file.txt' );

		add_filter(
			'wp_delete_file',
			static function () use ( $filtered ) {
				return $filtered;
			}
		);

		$this->assertTrue( wp_delete_file( $file ), 'wp_delete_file() should return true when the filtered path is deleted.' );
		$this->assertFileDoesNotExist( $filtered, 'The filtered path should be deleted.' );
		$this->assertFileExists( $file, 'The original path should not be deleted when the filter changes it.' );

		unlink( $file );
	}
}
