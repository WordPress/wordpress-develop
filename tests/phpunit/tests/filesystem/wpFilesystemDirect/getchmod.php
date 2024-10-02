<?php
/**
 * Tests for the WP_Filesystem_Direct::getchmod() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::getchmod
 */
class Tests_Filesystem_WpFilesystemDirect_Getchmod extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::getchmod()` returns
	 * the permissions for a path that exists.
	 *
	 * @dataProvider data_paths_that_exist
	 *
	 * @ticket 57774
	 *
	 * @param string $path The path.
	 */
	public function test_should_get_chmod_for_a_path_that_exists( $path ) {
		$actual = self::$filesystem->getchmod( self::$file_structure['test_dir']['path'] . $path );
		$this->assertNotSame( '', $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::getchmod()` returns
	 * the permissions for a path that does not exist.
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @ticket 57774
	 *
	 * @param string $path The path.
	 */
	public function test_should_get_chmod_for_a_path_that_does_not_exist( $path ) {
		$actual = self::$filesystem->getchmod( self::$file_structure['test_dir']['path'] . $path );
		$this->assertNotSame( '', $actual );
	}
}
