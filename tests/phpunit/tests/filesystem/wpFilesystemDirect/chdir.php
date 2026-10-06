<?php
/**
 * Tests for the WP_Filesystem_Direct::chdir() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::chdir
 */
class Tests_Filesystem_WpFilesystemDirect_Chdir extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::chdir()`
	 * returns false for a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_should_fail_to_change_directory
	 *
	 * @param string $path The path.
	 */
	public function test_should_fail_to_change_directory( $path ) {
		$original_cwd = self::$filesystem->cwd();
		$path         = wp_normalize_path( realpath( self::$file_structure['test_dir']['path'] ) ) . $path;
		$chdir_result = self::$filesystem->chdir( $path );
		$cwd_result   = self::$filesystem->cwd();

		// Reset the current working directory.
		self::$filesystem->chdir( $original_cwd );

		$this->assertFalse(
			$chdir_result,
			'Changing working directory succeeded.'
		);

		$this->assertSame(
			$original_cwd,
			$cwd_result,
			'The current working directory was changed.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_fail_to_change_directory() {
		return array(
			'a file that exists'              => array(
				'path' => 'a_file_that_exists.txt',
			),
			'a file that does not exist'      => array(
				'path' => 'a_file_that_does_not_exist.txt',
			),
			'a directory that does not exist' => array(
				'path' => 'a_directory_that_does_not_exist',
			),
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::chdir()` changes to
	 * an existing directory.
	 *
	 * @ticket 57774
	 */
	public function test_should_change_directory() {
		$original_cwd = self::$filesystem->cwd();
		$path         = wp_normalize_path( realpath( self::$file_structure['test_dir']['path'] ) );
		$chdir_result = self::$filesystem->chdir( $path );
		$cwd_result   = self::$filesystem->cwd();

		// Reset the current working directory.
		self::$filesystem->chdir( $original_cwd );

		$this->assertTrue(
			$chdir_result,
			'Changing working directory failed.'
		);

		$this->assertSamePathIgnoringDirectorySeparators(
			$path,
			$cwd_result,
			'The current working directory was incorrect.'
		);
	}
}
