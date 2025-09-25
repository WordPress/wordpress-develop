<?php
/**
 * Tests for the WP_Filesystem_Direct::dirlist() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::dirlist
 */
class Tests_Filesystem_WpFilesystemDirect_Dirlist extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::dirlist()` returns
	 * the expected result for a path.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_should_get_dirlist
	 *
	 * @param string      $path           The path.
	 * @param bool        $include_hidden Whether to include hidden files.
	 * @param bool        $recursive      Whether to recursive into subdirectories.
	 * @param array|false $expected       The expected result.
	 */
	public function test_should_get_dirlist( $path, $include_hidden, $recursive, $expected ) {
		$actual = self::$filesystem->dirlist( self::$file_structure['test_dir']['path'] . $path, $include_hidden, $recursive );

		if ( is_array( $expected ) ) {
			$this->assertSameSets(
				$expected,
				array_keys( $actual ),
				'The array keys do not match.'
			);
		} else {
			$this->assertFalse(
				$actual,
				'`WP_Filesystem_Direct::dirlist()` did not return false.'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_get_dirlist() {
		return array(
			'a directory that exists excluding hidden files' => array(
				'path'           => '',
				'include_hidden' => false,
				'recursive'      => false,
				'expected'       => array(
					'a_file_that_exists.txt',
					'subdir',
				),
			),
			'a directory that exists including hidden files' => array(
				'path'           => '',
				'include_hidden' => true,
				'recursive'      => false,
				'expected'       => array(
					'a_file_that_exists.txt',
					'.a_hidden_file',
					'subdir',
				),
			),
			'a directory that does not exist' => array(
				'path'           => 'a_directory_that_does_not_exist/',
				'include_hidden' => true,
				'recursive'      => false,
				'expected'       => false,
			),
			'a file that exists'              => array(
				'path'           => 'a_file_that_exists.txt',
				'include_hidden' => true,
				'recursive'      => false,
				'expected'       => array(
					'a_file_that_exists.txt',
				),
			),
			'a file that does not exist'      => array(
				'path'           => 'a_file_that_does_not_exist.txt',
				'include_hidden' => true,
				'recursive'      => false,
				'expected'       => false,
			),
		);
	}

	/**
	 * Tests that `WP_Filesystem_Direct::dirlist()` recurses
	 * into a subdirectory.
	 *
	 * @ticket 57774
	 */
	public function test_should_recurse_into_subdirectory() {
		$actual = self::$filesystem->dirlist( self::$file_structure['test_dir']['path'], true, true );

		$this->assertIsArray( $actual, 'Did not return an array.' );
		$this->assertArrayHasKey( 'subdir', $actual, 'The subdirectory was not detected.' );
		$this->assertArrayHasKey( 'files', $actual['subdir'], 'The subdirectory does not have a "files" key.' );
		$this->assertNotEmpty( $actual['subdir']['files'], "The subdirectory's contents were not retrieved." );
		$this->assertArrayHasKey( 'subfile.txt', $actual['subdir']['files'], 'The subfile was not detected.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::dirlist()` should not recurse
	 * into a subdirectory.
	 *
	 * @ticket 57774
	 */
	public function test_should_not_recurse_into_subdirectory() {

		$actual = self::$filesystem->dirlist( self::$file_structure['test_dir']['path'], true, false );

		$this->assertIsArray( $actual, 'Did not return an array.' );
		$this->assertArrayHasKey( 'subdir', $actual, 'The subdirectory was not detected.' );
		$this->assertArrayHasKey( 'files', $actual['subdir'], 'The "files" key was not set.' );
		$this->assertIsArray( $actual['subdir']['files'], 'The "files" key was not set to an array.' );
		$this->assertEmpty( $actual['subdir']['files'], 'The "files" array was not empty.' );
	}
}
