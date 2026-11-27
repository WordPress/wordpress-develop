<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `get_temp_dir()` function.
 *
 * @group functions
 *
 * @covers ::get_temp_dir
 */
class GetTempDirTest extends WP_UnitTestCase {

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
		$custom_temp = '/tmp/wp-custom-temp';
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$custom_temp = 'C:\\Windows\\Temp\\wp-custom-temp';
		}

		define( 'WP_TEMP_DIR', $custom_temp );

		$this->assertSame( trailingslashit( $custom_temp ), get_temp_dir() );
	}

	/**
	 * Tests that get_temp_dir() prefers sys_get_temp_dir() when it is usable.
	 *
	 * @ticket 65651
	 */
	public function test_get_temp_dir_prefers_system_temp_dir() {
		$this->assertSame( trailingslashit( sys_get_temp_dir() ), get_temp_dir() );
	}

	/**
	 * Tests that repeated calls return the same value (static cache).
	 *
	 * @ticket 65651
	 */
	public function test_get_temp_dir_is_consistent_across_calls() {
		$this->assertSame( get_temp_dir(), get_temp_dir() );
	}

	/**
	 * Tests that WP_TEMP_DIR takes priority over every other location.
	 *
	 * @ticket 65651
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_temp_dir_constant_takes_priority_over_system_temp_dir() {
		$custom_temp = '/wp-temp-dir-priority';
		define( 'WP_TEMP_DIR', $custom_temp );

		$this->assertNotSame( trailingslashit( sys_get_temp_dir() ), get_temp_dir() );
		$this->assertSame( $custom_temp . '/', get_temp_dir() );
	}

	/**
	 * Tests that WP_TEMP_DIR is returned with a single trailing slash, with no filesystem checks.
	 *
	 * @ticket 65651
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_temp_dir_constant_with_trailing_slash_is_not_doubled() {
		define( 'WP_TEMP_DIR', '/wp-temp-dir-slash/' );

		$this->assertSame( '/wp-temp-dir-slash/', get_temp_dir() );
	}

	/**
	 * Tests that an unusable system temp directory falls through to WP_CONTENT_DIR.
	 *
	 * The system temp directory is controlled through the TMPDIR environment variable.
	 * PHP caches that value on first use, so this runs in a separate process.
	 * The test is skipped when `upload_tmp_dir` is set, as it is the earlier fallback.
	 *
	 * @ticket 65651
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_temp_dir_falls_back_to_wp_content_dir() {
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$this->markTestSkipped( 'TMPDIR is not honored on Windows.' );
		}

		$upload_tmp_dir = ini_get( 'upload_tmp_dir' );
		if ( $upload_tmp_dir && is_dir( $upload_tmp_dir ) && wp_is_writable( $upload_tmp_dir ) ) {
			$this->markTestSkipped( 'A usable upload_tmp_dir is configured and cannot be changed at runtime.' );
		}

		putenv( 'TMPDIR=/wp-nonexistent-temp-dir' );
		if ( '/wp-nonexistent-temp-dir' !== sys_get_temp_dir() ) {
			$this->markTestSkipped( 'sys_get_temp_dir() was already cached by PHP and cannot be overridden.' );
		}

		$this->assertSame( WP_CONTENT_DIR . '/', get_temp_dir() );
	}
}
