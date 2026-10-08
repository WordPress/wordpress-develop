<?php

/**
 * Tests copy_dir().
 *
 * @group file
 * @group filesystem
 *
 * @covers ::copy_dir
 */
class Tests_Filesystem_CopyDir extends WP_UnitTestCase {

	/**
	 * The test directory.
	 *
	 * @var string $test_dir
	 */
	private static $test_dir;

	/**
	 * Sets up the filesystem and test directory before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		self::$test_dir = get_temp_dir() . 'copy_dir/';
	}

	/**
	 * Sets up the test directory before each test.
	 */
	public function set_up() {
		global $wp_filesystem;

		parent::set_up();

		// Create the root directory.
		$wp_filesystem->mkdir( self::$test_dir );
	}

	/**
	 * Removes the test directory after each test.
	 */
	public function tear_down() {
		global $wp_filesystem;

		// Delete the root directory and its contents.
		$wp_filesystem->delete( self::$test_dir, true );

		parent::tear_down();
	}

	/**
	 * Tests that the destination is created if it does not already exist.
	 *
	 * @ticket 41855
	 */
	public function test_should_create_destination_it_if_does_not_exist() {
		global $wp_filesystem;

		$from = self::$test_dir . 'folder1/folder2/';
		$to   = self::$test_dir . 'folder3/folder2/';

		// Create the file structure for the test.
		$wp_filesystem->mkdir( self::$test_dir . 'folder1' );
		$wp_filesystem->mkdir( self::$test_dir . 'folder3' );
		$wp_filesystem->mkdir( $from );
		$wp_filesystem->touch( $from . 'file1.txt' );
		$wp_filesystem->mkdir( $from . 'subfolder1' );
		$wp_filesystem->touch( $from . 'subfolder1/file2.txt' );

		$this->assertTrue( copy_dir( $from, $to ), 'copy_dir() failed.' );

		$this->assertDirectoryExists( $to, 'The destination was not created.' );
		$this->assertFileExists( $to . 'file1.txt', 'The destination file was not created.' );

		$this->assertDirectoryExists( $to . 'subfolder1/', 'The destination subfolder was not created.' );
		$this->assertFileExists( $to . 'subfolder1/file2.txt', 'The destination subfolder file was not created.' );
	}

	/**
	 * Tests that files and directories named in the skip list are not copied.
	 *
	 * @ticket 46581
	 */
	public function test_should_skip_files_and_directories_in_the_skip_list() {
		global $wp_filesystem;

		$from = self::$test_dir . 'folder1/';
		$to   = self::$test_dir . 'folder2/';

		$wp_filesystem->mkdir( $from );
		$wp_filesystem->touch( $from . 'keep-me.txt' );
		$wp_filesystem->touch( $from . 'skip-me.txt' );
		$wp_filesystem->mkdir( $from . 'keep-folder' );
		$wp_filesystem->touch( $from . 'keep-folder/file.txt' );
		$wp_filesystem->mkdir( $from . 'skip-folder' );
		$wp_filesystem->touch( $from . 'skip-folder/file.txt' );

		$this->assertTrue( copy_dir( $from, $to, array( 'skip-me.txt', 'skip-folder' ) ), 'copy_dir() failed.' );

		$this->assertFileExists( $to . 'keep-me.txt', 'The non-skip-listed file was not copied.' );
		$this->assertDirectoryExists( $to . 'keep-folder', 'The non-skip-listed directory was not copied.' );
		$this->assertFileExists( $to . 'keep-folder/file.txt', 'The contents of the non-skip-listed directory were not copied.' );

		$this->assertFileDoesNotExist( $to . 'skip-me.txt', 'The skip-listed file was copied.' );
		$this->assertDirectoryDoesNotExist( $to . 'skip-folder', 'The skip-listed directory was copied.' );
	}

	/**
	 * Tests that a numeric-looking directory name is not skipped just because
	 * it's loosely equal to an unrelated `$skip_list` entry.
	 *
	 * `in_array( $filename, $skip_list )` without strict type checking compares
	 * two numeric strings numerically, so a directory named `0019` would be
	 * considered equal to a `$skip_list` entry of `19` even though the two
	 * strings are different paths. `copy_dir()` must use a strict comparison
	 * so only an exact match is skipped.
	 *
	 * @ticket 46581
	 */
	public function test_should_not_skip_directory_loosely_equal_to_a_skip_list_entry() {
		global $wp_filesystem;

		$from = self::$test_dir . 'folder1/';
		$to   = self::$test_dir . 'folder2/';

		$wp_filesystem->mkdir( $from );
		$wp_filesystem->mkdir( $from . '0019' );
		$wp_filesystem->touch( $from . '0019/file.txt' );

		$this->assertTrue( copy_dir( $from, $to, array( '19' ) ), 'copy_dir() failed.' );

		$this->assertDirectoryExists( $to . '0019', 'The directory was incorrectly skipped due to a loose comparison with an unrelated skip list entry.' );
		$this->assertFileExists( $to . '0019/file.txt', 'The contents of the incorrectly skipped directory were not copied.' );
	}
}
