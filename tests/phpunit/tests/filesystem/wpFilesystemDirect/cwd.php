<?php
/**
 * Tests for the WP_Filesystem_Direct::cwd() method.
 *
 * @package WordPress
 */

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::cwd
 */
class Tests_Filesystem_WpFilesystemDirect_Cwd extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::cwd()` returns the current
	 * working directory.
	 *
	 * @ticket 57774
	 */
	public function test_should_get_current_working_directory() {
		$this->assertSame( wp_normalize_path( dirname( ABSPATH ) ), wp_normalize_path( self::$filesystem->cwd() ) );
	}
}
