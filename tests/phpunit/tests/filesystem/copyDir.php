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
}
