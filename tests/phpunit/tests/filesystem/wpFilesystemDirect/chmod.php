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

	/**
	 * Tests that recursive {@see WP_Filesystem_Direct::chmod()} applies the mode to files in subdirectories.
	 *
	 * @ticket 65584
	 */
	public function test_should_change_mode_recursively(): void {
		if ( self::is_windows() ) {
			$this->markTestSkipped( 'chmod() does not support octal modes on Windows.' );
		}

		$directory   = untrailingslashit( self::$file_structure['test_dir']['path'] );
		$nested_file = self::$file_structure['subfile']['path'];

		$this->assertTrue(
			self::$filesystem->chmod( $directory, 0640, true ),
			'chmod() did not report success.'
		);

		clearstatcache();

		$this->assertSame(
			'640',
			self::$filesystem->getchmod( $nested_file ),
			'The mode was not applied to a file in a nested subdirectory.'
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::chmod()` uses the correct mask for comparing permissions.
	 *
	 * The `& 0777` mask should be used to strip the filetype bits from the `fileperms( $file )` value
	 * so that the current permission bits can be used for comparison with the requested mode.
	 *
	 * @ticket 65695
	 */
	public function test_should_use_correct_permission_mask_for_files(): void {
		if ( self::is_windows() ) {
			$this->markTestSkipped( 'chmod() does not support octal modes on Windows.' );
		}

		$file = self::$file_structure['visible_file']['path'];

		// Set the initial permissions.
		self::$filesystem->chmod( $file, 0600 );

		$this->assertTrue(
			self::$filesystem->chmod( $file, 0644 ),
			'chmod() did not report success.'
		);

		clearstatcache();

		$this->assertSame(
			'644',
			self::$filesystem->getchmod( $file ),
			'The requested mode was not applied to the file.'
		);
	}
}
