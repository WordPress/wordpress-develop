<?php
/**
 * Tests for the WP_Filesystem_Direct::rmdir() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group filesystem
 * @group filesystem-direct
 *
 * @covers WP_Filesystem_Direct::rmdir
 */
class Tests_Filesystem_WpFilesystemDirect_Rmdir extends WP_Filesystem_Direct_UnitTestCase {

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()` returns false
	 * for an empty path.
	 *
	 * @ticket 57774
	 */
	public function test_should_return_false_for_empty_path() {
		$this->assertFalse( self::$filesystem->rmdir( '' ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()` deletes an empty directory.
	 *
	 * @ticket 57774
	 */
	public function test_should_delete_an_empty_directory() {
		$dir = self::$file_structure['test_dir']['path'] . 'directory-to-delete/';

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir );
		}

		$actual = self::$filesystem->rmdir( $dir );

		if ( ! $actual ) {
			rmdir( $dir );
		}

		$this->assertTrue( $actual, 'The directory was not deleted.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()` recursively deletes
	 * a directory with contents.
	 *
	 * @ticket 57774
	 */
	public function test_should_recursively_delete_a_directory() {
		$dir     = self::$file_structure['test_dir']['path'] . 'directory-to-delete/';
		$file    = $dir . 'file-to-delete.txt';
		$subdir  = $dir . 'subdirectory-to-delete/';
		$subfile = $subdir . 'subfile-to-delete.txt';

		mkdir( $dir, 0755 );
		mkdir( $subdir, 0755 );
		touch( $file, 0644 );
		touch( $subfile, 0644 );

		$actual = self::$filesystem->rmdir( self::$file_structure['test_dir']['path'], true );

		if ( ! $actual ) {
			unlink( $file );
			unlink( $subfile );
			rmdir( $subdir );
			rmdir( $dir );
		}

		$this->assertTrue( $actual, 'The directory was deleted.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()` deletes a file.
	 *
	 * @ticket 57774
	 */
	public function test_should_delete_a_file() {
		$file = self::$file_structure['test_dir']['path'] . 'file-to-delete.txt';

		touch( $file );

		$actual = self::$filesystem->rmdir( $file );

		if ( ! $actual ) {
			unlink( $file );
		}

		$this->assertTrue( $actual, 'The directory was not deleted.' );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()`
	 * returns true when deleting a path that does not exist.
	 *
	 * @ticket 57774
	 *
	 * @dataProvider data_paths_that_do_not_exist
	 *
	 * @param string $path The path.
	 */
	public function test_should_return_true_when_deleting_path_that_does_not_exist( $path ) {
		if (
			'' === $path
			|| str_starts_with( $path, '.' )
			|| str_starts_with( $path, '/' )
		) {
			$this->markTestSkipped( 'Dangerous delete path.' );
		}

		$this->assertTrue( self::$filesystem->rmdir( self::$file_structure['test_dir']['path'] . $path ) );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()`
	 * returns false when a directory's contents cannot be deleted.
	 *
	 * @ticket 57774
	 */
	public function test_should_return_false_when_contents_cannot_be_deleted() {

		global $wp_filesystem;

		$wp_filesystem = new WP_Filesystem_Direct( array() );

		$path = self::$file_structure['test_dir']['path'] . 'dir-to-delete/';

		if ( ! is_dir( $path ) ) {
			mkdir( $path );
		}

		// Set up mock filesystem.
		$filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Direct' )
								->setConstructorArgs( array( null ) )
								// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
								->setMethods( array( 'dirlist' ) )
								->getMock();

		$filesystem_mock->expects( $this->once() )
						->method( 'dirlist' )
						->willReturn(
							array( 'a_file_that_does_not_exist.txt' => array( 'type' => 'f' ) )
						);

		$wp_filesystem_backup = $wp_filesystem;
		$wp_filesystem        = $filesystem_mock;

		$actual = $filesystem_mock->rmdir( $path, true );

		if ( $actual ) {
			rmdir( $path );
		}

		$wp_filesystem = $wp_filesystem_backup;

		$this->assertFalse( $actual );
	}

	/**
	 * Tests that `WP_Filesystem_Direct::rmdir()`
	 * returns false when the path is not a file or directory, but exists.
	 *
	 * @ticket 57774
	 */
	public function test_should_return_false_when_path_exists_but_is_not_a_file_or_directory() {
		global $wp_filesystem;

		$wp_filesystem = new WP_Filesystem_Direct( array() );

		// Set up mock filesystem.
		$filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Direct' )
								->setConstructorArgs( array( null ) )
								// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
								->setMethods( array( 'is_file', 'dirlist' ) )
								->getMock();

		$filesystem_mock->expects( $this->once() )
						->method( 'is_file' )
						->willReturn( false );

		$filesystem_mock->expects( $this->once() )
						->method( 'dirlist' )
						->willReturn( false );

		$wp_filesystem_backup = $wp_filesystem;
		$wp_filesystem        = $filesystem_mock;

		$actual = $filesystem_mock->rmdir( self::$file_structure['subdir']['path'], true );

		$wp_filesystem = $wp_filesystem_backup;

		$this->assertFalse( $actual );
	}
}
