<?php
/**
 * Tests for the WP_Filesystem_Direct::size() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::size
 */
class Tests_Filesystem_WpFilesystemDirect_Size extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::size()` determines
	 * the file size of a path that exists.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_determine_file_size( $path ) {
		$result       = self::$filesystem->size( self::$file_structure['test_dir']['path'] . $path );
		$has_filesize = false !== $result;

		$this->assertTrue(
			$has_filesize,
			'The file size was not determined.'
		);

		$this->assertIsInt(
			$result,
			'The file size is not an integer.'
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::size()` does not determine
	 * the filesize of a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_not_determine_file_size( $path ) {
		$result       = self::$filesystem->size( self::$file_structure['test_dir']['path'] . $path );
		$has_filesize = false !== $result;

		$this->assertFalse(
			$has_filesize,
			'A file size was determined.'
		);
	}
}
