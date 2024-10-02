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
}
