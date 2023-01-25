<?php

/**
 * Tests move_dir().
 *
 * @group functions.php
 * @covers ::move_dir
 */
class Tests_Functions_MoveDir extends WP_UnitTestCase {

	/**
	 * Filesystem backup.
	 *
	 * @var null|WP_Filesystem_Base $wp_filesystem_backup
	 */
	private static $wp_filesystem_backup;

	/**
	 * The test directory.
	 *
	 * @var string $test_dir
	 */
	private static $test_dir;

	/**
	 * The existing 'from' directory path.
	 *
	 * @var string $existing_from
	 */
	private static $existing_from;

	/**
	 * The existing 'from' sub-directory path.
	 *
	 * @var string $existing_from_subdir
	 */
	private static $existing_from_subdir;

	/**
	 * The existing 'from' file path.
	 *
	 * @var string $existing_from_file
	 */
	private static $existing_from_file;

	/**
	 * The existing 'from' sub-directory file path.
	 *
	 * @var string $existing_from_subdir_file
	 */
	private static $existing_from_subdir_file;

	/**
	 * The existing 'to' directory file path.
	 *
	 * @var string $existing_to
	 */
	private static $existing_to;

	/**
	 * The existing 'to' file path.
	 *
	 * @var string $existing_to_file
	 */
	private static $existing_to_file;

	/**
	 * Sets up the filesystem and directory structure properties
	 * before any tests run.
	 */
	public static function set_up_before_class() {
		global $wp_filesystem;

		self::$wp_filesystem_backup = $wp_filesystem;

		parent::set_up_before_class();

		/*
		 * WP_Filesystem_MockFS has a bug that appears in CI.
		 *
		 * Until this is resolved, use WP_Filesystem_Direct and restore
		 * $wp_filesystem to its original state after the tests.
		 */
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem( array( 'method' => 'direct' ) );

		self::$test_dir                  = get_temp_dir() . 'move_dir/';
		self::$existing_from             = self::$test_dir . 'existing_from/';
		self::$existing_from_subdir      = self::$existing_from . 'existing_from_subdir/';
		self::$existing_from_file        = self::$existing_from . 'existing_from_file.txt';
		self::$existing_from_subdir_file = self::$existing_from_subdir . 'existing_from_subdir_file.txt';
		self::$existing_to               = self::$test_dir . 'existing_to/';
		self::$existing_to_file          = self::$existing_to . 'existing_to_file.txt';
	}

	/**
	 * Sets up the directory structure before each test.
	 */
	public function set_up() {
		global $wp_filesystem;

		parent::set_up();

		// Create the root directory.
		$wp_filesystem->mkdir( self::$test_dir );

		// Create the "from" directory structure.
		$wp_filesystem->mkdir( self::$existing_from );
		$wp_filesystem->touch( self::$existing_from_file );
		$wp_filesystem->mkdir( self::$existing_from_subdir );
		$wp_filesystem->touch( self::$existing_from_subdir_file );

		// Create the "to" directory structure.
		$wp_filesystem->mkdir( self::$existing_to );
		$wp_filesystem->touch( self::$existing_to_file );
	}

	/**
	 * Removes the test directory structure after each test.
	 */
	public function tear_down() {
		global $wp_filesystem;

		// Delete the root directory and its contents.
		$wp_filesystem->delete( self::$test_dir, true );

		parent::tear_down();
	}

	/**
	 * Restores the filesystem after all tests have run.
	 */
	public static function tear_down_after_class() {
		global $wp_filesystem;

		// Restore the filesystem.
		$wp_filesystem = self::$wp_filesystem_backup;

		parent::tear_down_after_class();
	}

	/**
	 * Tests that move_dir() returns a WP_Error object.
	 *
	 * @ticket 57375
	 *
	 * @dataProvider data_should_return_wp_error
	 *
	 * @param string $from      The source directory path.
	 * @param string $to        The destination directory path.
	 * @param bool   $overwrite Whether to overwrite the destination directory.
	 * @param string $expected  The expected WP_Error code.
	 */
	public function test_should_return_wp_error( $from, $to, $overwrite, $expected ) {
		global $wp_filesystem;

		$from   = self::$test_dir . $from;
		$to     = self::$test_dir . $to;
		$result = move_dir( $from, $to, $overwrite );

		$this->assertWPError(
			$result,
			'move_dir() did not return a WP_Error object.'
		);

		$this->assertSame(
			$expected,
			$result->get_error_code(),
			'The expected error code was not returned.'
		);

		$this->assertTrue(
			$wp_filesystem->exists( $from ),
			'The $from directory does not exist anymore.'
		);

		if ( false === $overwrite && 'existing_to' === untrailingslashit( $to ) ) {
			$this->assertTrue(
				$wp_filesystem->exists( $to ),
				'The $to directory does not exist anymore.'
			);
		}
	}

	/**
	 * Data provider for test_should_return_wp_error().
	 *
	 * @return array[]
	 */
	public function data_should_return_wp_error() {
		return array(
			'$overwrite is false and $to exists' => array(
				'from'      => 'existing_from',
				'to'        => 'existing_to',
				'overwrite' => false,
				'expected'  => 'to_directory_already_exists_move_dir',
			),
		);
	}

	/**
	 * Tests that move_dir() successfully moves a directory.
	 *
	 * @ticket 57375
	 *
	 * @dataProvider data_should_move_directory
	 *
	 * @param string $from      The source directory path.
	 * @param string $to        The destination directory path.
	 * @param bool   $overwrite Whether to overwrite the destination directory.
	 */
	public function test_should_move_directory( $from, $to, $overwrite ) {
		global $wp_filesystem;

		$from   = self::$test_dir . $from;
		$to     = self::$test_dir . $to;
		$result = move_dir( $from, $to, $overwrite );

		$this->assertTrue(
			$result,
			'The directory was not moved.'
		);

		$this->assertFalse(
			$wp_filesystem->exists( $from ),
			'The source directory still exists.'
		);

		$this->assertTrue(
			$wp_filesystem->exists( $to ),
			'The destination directory does not exist.'
		);

		$dirlist = $wp_filesystem->dirlist( $to, true, true );

		$this->assertIsArray(
			$dirlist,
			'The directory listing of the destination directory could not be retrieved.'
		);

		// Prevent PHP array sorting bugs from breaking tests.
		$to_contents = array_keys( $dirlist );
		sort( $to_contents );

		$this->assertSame(
			array(
				'existing_from_file.txt',
				'existing_from_subdir',
			),
			$to_contents,
			'The expected files were not moved.'
		);

		$this->assertSame(
			array( 'existing_from_subdir_file.txt' ),
			array_keys( $dirlist['existing_from_subdir']['files'] ),
			'Sub-directory files failed to move.'
		);
	}

	/**
	 * Data provider for test_should_move_directory().
	 *
	 * @return array[]
	 */
	public function data_should_move_directory() {
		return array(
			'$overwrite is false and $to does not exist' => array(
				'from'      => 'existing_from',
				'to'        => 'non_existing_to',
				'overwrite' => false,
			),
			'$overwrite is true and $to exists'          => array(
				'from'      => 'existing_from',
				'to'        => 'existing_to',
				'overwrite' => true,
			),
		);
	}

}
