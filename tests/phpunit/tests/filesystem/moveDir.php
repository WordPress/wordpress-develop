<?php

/**
 * Tests move_dir().
 *
 * @group file.php
 *
 * @covers ::move_dir
 */
class Tests_Filesystem_MoveDir extends WP_UnitTestCase {

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
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

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

		if ( 'source_destination_same_move_dir' !== $expected ) {
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
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_return_wp_error() {
		return array(
			'$overwrite is false and $to exists' => array(
				'from'      => 'existing_from',
				'to'        => 'existing_to',
				'overwrite' => false,
				'expected'  => 'destination_already_exists_move_dir',
			),
			'same source and destination, source has trailing slash' => array(
				'from'      => 'existing_from/',
				'to'        => 'existing_from',
				'overwrite' => false,
				'expected'  => 'source_destination_same_move_dir',
			),
			'same source and destination, destination has trailing slash' => array(
				'from'      => 'existing_from',
				'to'        => 'existing_from/',
				'overwrite' => false,
				'expected'  => 'source_destination_same_move_dir',
			),
			'same source and destination, source lowercase, destination uppercase' => array(
				'from'      => 'existing_from',
				'to'        => 'EXISTING_FROM',
				'overwrite' => false,
				'expected'  => 'source_destination_same_move_dir',
			),
			'same source and destination, source uppercase, destination lowercase' => array(
				'from'      => 'EXISTING_FROM',
				'to'        => 'existing_from',
				'overwrite' => false,
				'expected'  => 'source_destination_same_move_dir',
			),
			'same source and destination, source and destination in inverted case' => array(
				'from'      => 'ExIsTiNg_FrOm',
				'to'        => 'eXiStInG_fRoM',
				'overwrite' => false,
				'expected'  => 'source_destination_same_move_dir',
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

		// Prevent PHP array sorting bugs from breaking tests.
		$to_contents = array_keys( $dirlist );

		$this->assertSameSets(
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
	 * Data provider.
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

	/**
	 * Tests that `move_dir()` returns a WP_Error object when overwriting
	 * is enabled, the destination exists, but cannot be deleted.
	 *
	 * @ticket 57375
	 */
	public function test_should_return_wp_error_when_overwriting_is_enabled_the_destination_exists_but_cannot_be_deleted() {
		global $wp_filesystem;
		$wpfilesystem_backup = $wp_filesystem;

		// Force failure conditions.
		$filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Direct' )->setConstructorArgs( array( null ) )->getMock();
		$filesystem_mock->expects( $this->once() )->method( 'exists' )->willReturn( true );
		$filesystem_mock->expects( $this->once() )->method( 'delete' )->willReturn( false );
		$wp_filesystem = $filesystem_mock;

		$actual = move_dir( self::$existing_from, self::$existing_from_subdir, true );

		// Restore the filesystem.
		$wp_filesystem = $wpfilesystem_backup;

		$this->assertWPError(
			$actual,
			'A WP_Error object was not returned.'
		);

		$this->assertSame(
			'destination_not_deleted_move_dir',
			$actual->get_error_code(),
			'An unexpected error code was returned.'
		);
	}

}
