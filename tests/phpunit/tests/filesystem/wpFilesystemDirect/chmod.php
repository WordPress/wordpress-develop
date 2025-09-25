<?php
/**
 * Tests for the WP_Filesystem_Direct::chmod() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::chmod
 */
class Tests_Filesystem_WpFilesystemDirect_Chmod extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::chmod()`
	 * returns false for a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_return_false( $path ) {
		$this->assertFalse( self::$filesystem->chmod( $path ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::chmod()` should set
	 * $mode when it is not passed.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_should_set_mode_when_not_passed
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param string $path The path.
	 * @param string $type The type of path. "FILE" for file, "DIR" for directory.
	 */
	public function test_should_handle_set_mode_when_not_passed( $path, $type ) {
		define( 'FS_CHMOD_' . $type, ( 'FILE' === $type ? 0644 : 0755 ) );

		$this->assertTrue( self::$filesystem->chmod( self::$file_structure['test_dir']['path'] . $path, false ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_set_mode_when_not_passed() {
		return array(
			'a file'      => array(
				'path' => 'a_file_that_exists.txt',
				'type' => 'FILE',
			),
			'a directory' => array(
				'path' => '',
				'type' => 'DIR',
			),
		);
	}
}
