<?php

/**
 * Base class for WP_Filesystem_Direct tests.
 *
 * @package WordPress
 */
abstract class WP_Filesystem_Direct_UnitTestCase extends WP_UnitTestCase {

	/**
	 * The filesystem object.
	 *
	 * @var WP_Filesystem_Direct
	 */
	protected static $filesystem;

	/**
	 * The file structure for tests.
	 *
	 * @var array
	 */
	protected static $file_structure = array();

	/**
	 * Sets up test assets before the class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		self::$filesystem = new WP_Filesystem_Direct( null );

		$filesystem_data_dir = wp_normalize_path( DIR_TESTDATA . '/filesystem/' );
		if ( ! file_exists( $filesystem_data_dir ) ) {
			mkdir( $filesystem_data_dir );
		}

		/*
		 * These must be created during the tests as they may be modified or deleted
		 * during testing, either intentionally or accidentally as a result of test failure.
		 */
		$test_data_root_dir = $filesystem_data_dir . 'filesystem_api/';
		$test_data_dir      = $test_data_root_dir . 'wpFilesystemDirect/';

		self::$file_structure = array(
			// Directories first.
			'test_dir_root' => array(
				'type' => 'd',
				'path' => $test_data_root_dir,
			),
			'test_dir'      => array(
				'type' => 'd',
				'path' => $test_data_dir,
			),
			'subdir'        => array(
				'type' => 'd',
				'path' => $test_data_dir . 'subdir/',
			),

			// Then files.
			'visible_file'  => array(
				'type'     => 'f',
				'path'     => $test_data_dir . 'a_file_that_exists.txt',
				'contents' => "Contents of a file.\r\nNext line of a file.\r\n",
			),
			'hidden_file'   => array(
				'type'     => 'f',
				'path'     => $test_data_dir . '.a_hidden_file',
				'contents' => "A hidden file.\r\n",
			),
			'subfile'       => array(
				'type'     => 'f',
				'path'     => $test_data_dir . 'subdir/subfile.txt',
				'contents' => "A file in a subdirectory.\r\n",
			),
		);
	}

	/**
	 * Creates any missing test assets before each test.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( self::$file_structure as $entry ) {
			if ( 'd' === $entry['type'] ) {
				$this->create_directory_if_needed( $entry['path'] );
			} elseif ( 'f' === $entry['type'] ) {
				$this->create_file_if_needed(
					$entry['path'],
					isset( $entry['contents'] ) ? $entry['contents'] : ''
				);
			}
		}
	}

	/**
	 * Removes any existing test assets after each test.
	 */
	public function tear_down() {
		foreach ( array_reverse( self::$file_structure ) as $entry ) {
			if ( ! file_exists( $entry['path'] ) ) {
				continue;
			}

			if ( 'f' === $entry['type'] ) {
				unlink( $entry['path'] );
			} elseif ( 'd' === $entry['type'] ) {
				rmdir( $entry['path'] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Creates a directory if it doesn't already exist.
	 *
	 * @throws Exception If the path already exists as a file.
	 *
	 * @param string $path The path to the directory.
	 */
	public function create_directory_if_needed( $path ) {
		if ( file_exists( $path ) ) {
			if ( is_file( $path ) ) {
				throw new Exception( "$path already exists as a file." );
			}

			return;
		}

		mkdir( $path );
	}

	/**
	 * Creates a file if it doesn't already exist.
	 *
	 * @throws Exception If the path already exists as a directory.
	 *
	 * @param string $path     The path to the file.
	 * @param string $contents Optional. The contents of the file. Default empty string.
	 */
	public function create_file_if_needed( $path, $contents = '' ) {
		if ( file_exists( $path ) ) {
			if ( is_dir( $path ) ) {
				throw new Exception( "$path already exists as a directory." );
			}

			return;
		}

		file_put_contents( $path, $contents );
	}

	/**
	 * Determines whether the operating system is Windows.
	 *
	 * @return bool Whether the operating system is Windows.
	 */
	public static function is_windows() {
		return 'Windows' === PHP_OS_FAMILY;
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_paths_that_exist() {
		return array(
			'a file that exists'      => array(
				'path' => 'a_file_that_exists.txt',
			),
			'a directory that exists' => array(
				'path' => '',
			),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_paths_that_do_not_exist() {
		return array(
			'a file that does not exist'      => array(
				'path' => 'a_file_that_does_not_exist.txt',
			),
			'a directory that does not exist' => array(
				'path' => 'a_directory_that_does_not_exist',
			),
		);
	}
}
