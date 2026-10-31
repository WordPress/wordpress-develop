<?php
/**
 * Tests for the WP_Filesystem_Direct::exists() method.
 *
 * @package WordPress
 */

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::exists
 */
class Tests_Filesystem_WpFilesystemDirect_Exists extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::exists()` determines that
	 * a path exists.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_exist
	 *
	 * @param string $path The path to check.
	 */
	public function test_should_determine_that_a_path_exists( $path ) {
		$this->assertTrue( self::$filesystem->exists( self::$file_structure['test_dir']['path'] . $path ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::exists()` determines that
	 * a path does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path to check.
	 */
	public function test_should_determine_that_a_path_does_not_exist( $path ) {
		$this->assertFalse( self::$filesystem->exists( self::$file_structure['test_dir']['path'] . $path ) );
	}
}
