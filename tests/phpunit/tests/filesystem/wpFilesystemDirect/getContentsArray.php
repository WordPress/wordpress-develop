<?php
/**
 * Tests for the WP_Filesystem_Direct::get_contents_array() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::get_contents_array
 */
class Tests_Filesystem_WpFilesystemDirect_GetContentsArray extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::get_contents_array()` gets
	 * the contents of the provided file.
	 *
	 * @ticket 57774
	 */
	public function test_should_get_the_contents_of_a_file_as_an_array() {
		$file     = self::$file_structure['visible_file']['path'];
		$contents = self::$filesystem->get_contents_array( $file );

		$this->assertIsArray(
			$contents,
			'The file contents are not an array.'
		);

		$this->assertSameSetsWithIndex(
			array(
				"Contents of a file.\r\n",
				"Next line of a file.\r\n",
			),
			$contents,
			'The file contents do not match the expected value.'
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::get_contents_array()`
	 * returns false for a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_return_false( $path ) {
		$this->assertFalse( self::$filesystem->get_contents_array( self::$file_structure['test_dir']['path'] . $path ) );
	}
}
