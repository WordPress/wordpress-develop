<?php

/**
 * Tests for wp_is_writable().
 *
 * @group functions
 *
 * @covers ::wp_is_writable
 */
class Tests_Functions_wpIsWritable extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_for_writable_directory() {
		$this->assertTrue( wp_is_writable( get_temp_dir() ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_path_with_missing_parent() {
		// Parent directory must not exist so Windows win_is_writable() cannot create the file.
		$path = trailingslashit( get_temp_dir() ) . 'wp-is-writable-missing-' . wp_generate_password( 8, false ) . '/file.txt';

		$this->assertFalse( wp_is_writable( $path ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_for_writable_file() {
		$file = wp_tempnam( 'wp-is-writable-' );

		$this->assertNotFalse( $file );
		$this->assertTrue( wp_is_writable( $file ) );

		unlink( $file );
	}
}
