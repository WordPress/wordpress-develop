<?php

/**
 * Tests the get_temp_dir() function.
 *
 * @group functions
 *
 * @covers ::get_temp_dir
 */
class Tests_Functions_GetTempDir extends WP_UnitTestCase {

	/**
	 * Tests that get_temp_dir() returns a path with a trailing slash.
	 *
	 * @ticket 65651
	 */
	public function test_get_temp_dir_has_trailing_slash() {
		$temp_dir = get_temp_dir();
		$this->assertStringEndsWith( '/', $temp_dir );
	}

	/**
	 * Tests that get_temp_dir() returns a writable directory.
	 *
	 * @ticket 65651
	 */
	public function test_get_temp_dir_is_writable_directory() {
		$temp_dir = get_temp_dir();
		$this->assertDirectoryExists( $temp_dir );
		$this->assertTrue( wp_is_writable( $temp_dir ), 'The temporary directory must be writable.' );
	}

	/**
	 * Tests that get_temp_dir() respects the WP_TEMP_DIR constant.
	 *
	 * Since constants cannot be redefined, we use a separate process to test this.
	 *
	 * @ticket 65651
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_temp_dir_respects_constant() {
		$custom_temp = '/tmp/wp-custom-temp/';
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$custom_temp = 'C:\\Windows\\Temp\\wp-custom-temp\\';
		}

		define( 'WP_TEMP_DIR', $custom_temp );

		$this->assertSame( trailingslashit( $custom_temp ), get_temp_dir() );
	}
}
