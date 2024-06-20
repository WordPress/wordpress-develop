<?php
/**
 * Tests for the wp_delete_file function.
 *
 * @group functions.php
 *
 * @covers ::wp_delete_file
 */#
class Tests_functions_wpDeleteFile extends WP_UnitTestCase {

	/**
	 * delete file
	 *
	 * @ticket 59788
	 */
	public function test_wp_delete_file() {
		$file = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		touch( $file );

		wp_delete_file( $file );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * delete file
	 *
	 * @ticket 59788
	 */
	public function test_wp_delete_file_Filter() {
		$file = realpath( DIR_TESTDATA ) . '/temp_file.txt';
		touch( $file );
		add_filter( 'wp_delete_file', '__return_null' );
		wp_delete_file( $file );

		$this->assertFileExists( $file );
		@unlink( $file );
	}
}
