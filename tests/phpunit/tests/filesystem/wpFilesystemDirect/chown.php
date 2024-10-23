<?php
/**
 * Tests for the WP_Filesystem_Direct::chown() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::chown
 */
class Tests_Filesystem_WpFilesystemDirect_Chown extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::chown()`
	 * returns false for a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_return_false( $path ) {
		$this->assertFalse( self::$filesystem->chown( $path, fileowner( __FILE__ ) ) );
	}
}
