<?php
/**
 * Tests for the WP_Filesystem_Direct::is_readable() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::is_readable
 */
class Tests_Filesystem_WpFilesystemDirect_IsReadable extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::is_readable()` determines that
	 * a path is readable.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_determine_that_a_path_is_readable( $path ) {
		$this->assertTrue( self::$filesystem->is_readable( self::$file_structure['test_dir']['path'] . $path ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::is_readable()` determines that
	 * a path is not readable.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_determine_that_a_path_is_not_readable( $path ) {
		$this->assertFalse( self::$filesystem->is_readable( self::$file_structure['test_dir']['path'] . $path ) );
	}
}
