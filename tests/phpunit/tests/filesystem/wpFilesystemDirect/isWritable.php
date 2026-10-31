<?php
/**
 * Tests for the WP_Filesystem_Direct::is_writable() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::is_writable
 */
class Tests_Filesystem_WpFilesystemDirect_IsWritable extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::is_writable()` determines that
	 * a path is writable.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_determine_that_a_path_is_writable( $path ) {
		$this->assertTrue( self::$filesystem->is_writable( self::$file_structure['test_dir']['path'] . $path ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::is_writable()` determines that
	 * a path is not writable.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_determine_that_a_path_is_not_writable( $path ) {
		$this->assertFalse( self::$filesystem->is_writable( self::$file_structure['test_dir']['path'] . $path ) );
	}
}
