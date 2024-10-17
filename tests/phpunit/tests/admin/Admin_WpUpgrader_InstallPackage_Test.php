<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::install_package()
 */
class Admin_WpUpgrader_InstallPackage_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error object
	 * when an invalid source is passed.
	 *
	 * @ticket 54245
	 *
	 * @dataProvider data_install_package_invalid_paths
	 *
	 * @param mixed $path The path to test.
	 */
	public function test_install_package_should_return_wp_error_with_invalid_source( $path ) {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'dirlist' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'find_folder' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'is_dir' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'exists' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'mkdir' );

		$args = array(
			'source'      => $path,
			'destination' => '/',
		);

		$actual = self::$instance->install_package( $args );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::install_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'bad_request',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error object
	 * when an invalid destination is passed.
	 *
	 * @ticket 54245
	 *
	 * @dataProvider data_install_package_invalid_paths
	 *
	 * @param mixed $path The path to test.
	 */
	public function test_install_package_should_return_wp_error_with_invalid_destination( $path ) {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'dirlist' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'find_folder' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'is_dir' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'exists' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'mkdir' );

		$args = array(
			'source'      => '/',
			'destination' => $path,
		);

		$actual = self::$instance->install_package( $args );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::install_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'bad_request',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_install_package_invalid_paths() {
		return array(
			'empty string'                   => array( 'path' => '' ),

			// Type checks.
			'empty array'                    => array( 'path' => array() ),
			'populated array'                => array( 'path' => array( '/' ) ),
			'(int) 0'                        => array( 'path' => 0 ),
			'(int) -0'                       => array( 'path' => -0 ),
			'(int) -1'                       => array( 'path' => -1 ),
			'(int) 1'                        => array( 'path' => 1 ),
			'(float) 0.0'                    => array( 'path' => 0.0 ),
			'(float) -0.0'                   => array( 'path' => -0.0 ),
			'(float) 1.0'                    => array( 'path' => 1.0 ),
			'(float) -1.0'                   => array( 'path' => -1.0 ),
			'(bool) false'                   => array( 'path' => false ),
			'(bool) true'                    => array( 'path' => true ),
			'null'                           => array( 'path' => null ),
			'empty object'                   => array( 'path' => new stdClass() ),
			'populated object'               => array( 'path' => (object) array( '/' ) ),

			// Ensures that `trim()` is run triggering an empty array.
			'a string with spaces'           => array( 'path' => '   ' ),
			'a string with tabs'             => array( 'path' => "\t\t" ),
			'a string with new lines'        => array( 'path' => "\n\n" ),
			'a string with carriage returns' => array( 'path' => "\r\r" ),

			// Ensure that strings with leading/trailing whitespace are invalid.
			'a path with a leading space'    => array( 'path' => ' /path' ),
			'a path with a trailing space'   => array( 'path' => '/path ' ),
			'a path with a leading tab'      => array( 'path' => "\t/path" ),
			'a path with a trailing tab'     => array( 'path' => "/path\t" ),
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error object
	 * when the 'upgrader_pre_install' filter returns a WP_Error object.
	 *
	 * @ticket 54245
	 */
	public function test_install_package_should_return_wp_error_when_pre_install_filter_returns_wp_error() {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'installing_package' );

		add_filter(
			'upgrader_pre_install',
			static function () {
				return new WP_Error( 'from_upgrader_pre_install' );
			}
		);

		$args = array(
			'source'      => '/',
			'destination' => '/',
		);

		$actual = self::$instance->install_package( $args );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::install_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'from_upgrader_pre_install',
			$actual->get_error_code(),
			'The WP_Error object was not returned from the filter'
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` adds a trailing slash to
	 * the source directory and a single subdirectory.
	 *
	 * @ticket 54245
	 */
	public function test_install_package_should_add_trailing_slash_to_source_and_subdirectory() {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'installing_package' );

		$dirlist = array(
			'subdir' => array(
				'name'  => 'subdir',
				'type'  => 'd',
				'files' => array( 'subfile.php' ),
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( '/source_dir' )
				->willReturn( $dirlist );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'is_dir' )
				->with( '/source_dir/subdir/' )
				->willReturn( true );

		add_filter(
			'upgrader_source_selection',
			function ( $source ) {
				$this->assertSame( '/source_dir/subdir/', $source );

				// Return a WP_Error to exit before `move_dir()/copy_dir()`.
				return new WP_Error();
			}
		);

		$args = array(
			'source'      => '/source_dir',
			'destination' => '/dest_dir',
		);

		self::$instance->install_package( $args );
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error object
	 * when no source files exist.
	 *
	 * @ticket 54245
	 */
	public function test_install_package_should_return_wp_error_when_no_source_files_exist() {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'installing_package' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( '/' )
				->willReturn( array() );

		$args = array(
			'source'      => '/',
			'destination' => '/',
		);

		$actual = self::$instance->install_package( $args );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::install_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'incompatible_archive_empty',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` adds a trailing slash to
	 * the source directory of a single file.
	 *
	 * @ticket 54245
	 */
	public function test_install_package_should_add_trailing_slash_to_the_source_directory_of_single_file() {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'installing_package' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( '/source_dir' )
				->willReturn( array( 'file1.php' ) );

		add_filter(
			'upgrader_source_selection',
			function ( $source ) {
				$this->assertSame( '/source_dir/', $source );

				// Return a WP_Error to exit before `move_dir()/copy_dir()`.
				return new WP_Error();
			}
		);

		$args = array(
			'source'      => '/source_dir',
			'destination' => '/dest_dir',
		);

		self::$instance->install_package( $args );
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` applies
	 * 'upgrader_clear_destination' filters with arguments.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 54245
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_install_package_should_clear_destination_when_clear_destination_is_true() {
		define( 'FS_CHMOD_FILE', 0644 );

		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->exactly( 2 ) )
				->method( 'feedback' )
				->withConsecutive(
					array( 'installing_package' ),
					array( 'remove_old' )
				);

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'find_folder' )
				->with( '/dest_dir' )
				->willReturn( '/dest_dir/' );

		$dirlist_args = array(
			array( '/source_dir' ),
			array( '/source_dir/' ),
			array( '/dest_dir/' ),
		);

		$dirlist_results = array(
			'file1.php' => array(
				'name' => 'file1.php',
				'type' => 'f',
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->exactly( 3 ) )
				->method( 'dirlist' )
				->withConsecutive( ...$dirlist_args )
				->willReturn( $dirlist_results );

		add_filter(
			'upgrader_clear_destination',
			function ( $removed, $local_destination, $remote_destination, $hook_extra ) {
				$this->assertTrue(
					is_bool( $removed ) || is_wp_error( $removed ),
					'The "removed" argument is not a bool or WP_Error'
				);

				$this->assertIsString(
					$local_destination,
					'The "local_destination" argument is not a string'
				);

				$this->assertIsString(
					$remote_destination,
					'The "remote_destination" argument is not a string'
				);

				$this->assertIsArray(
					$hook_extra,
					'The "hook_extra" argument is not an array'
				);

				return new WP_Error( 'exit_early' );
			},
			10,
			4
		);

		$args = array(
			'source'            => '/source_dir',
			'destination'       => '/dest_dir',
			'clear_destination' => true,
		);

		self::$instance->install_package( $args );
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` makes the
	 * remote destination safe when set to a protected directory.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 54245
	 *
	 * @dataProvider data_install_package_should_make_remote_destination_safe_when_set_to_a_protected_directory
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param string $protected_directory The path to a protected directory.
	 * @param string $expected            The expected safe remote destination.
	 */
	public function test_install_package_should_make_remote_destination_safe_when_set_to_a_protected_directory( $protected_directory, $expected ) {
		define( 'FS_CHMOD_FILE', 0644 );

		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->exactly( 2 ) )
				->method( 'feedback' )
				->withConsecutive(
					array( 'installing_package' ),
					array( 'remove_old' )
				);

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'find_folder' )
				->with( $protected_directory )
				->willReturn( trailingslashit( $protected_directory ) );

		$dirlist_args = array(
			array( '/source_dir' ),
			array( '/source_dir/' ),
			array( $expected ),
		);

		$dirlist_results = array(
			'file1.php' => array(
				'name' => 'file1.php',
				'type' => 'f',
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->exactly( 3 ) )
				->method( 'dirlist' )
				->withConsecutive( ...$dirlist_args )
				->willReturn( $dirlist_results );

		add_filter(
			'upgrader_clear_destination',
			function ( $removed, $local_destination, $remote_destination ) use ( $expected ) {
				$this->assertSame( $expected, $remote_destination );
				return new WP_Error( 'exit_early' );
			},
			10,
			3
		);

		$args = array(
			'source'            => '/source_dir',
			'destination'       => $protected_directory,
			'clear_destination' => true,
		);

		self::$instance->install_package( $args );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_install_package_should_make_remote_destination_safe_when_set_to_a_protected_directory() {
		return array(
			'ABSPATH'               => array(
				'protected_directory' => ABSPATH,
				'expected'            => ABSPATH . 'source_dir/',
			),
			'WP_CONTENT_DIR'        => array(
				'protected_directory' => WP_CONTENT_DIR,
				'expected'            => WP_CONTENT_DIR . '/source_dir/',
			),
			'WP_PLUGIN_DIR'         => array(
				'protected_directory' => WP_PLUGIN_DIR,
				'expected'            => WP_PLUGIN_DIR . '/source_dir/',
			),
			'WP_CONTENT_DIR/themes' => array(
				'protected_directory' => WP_CONTENT_DIR . '/themes',
				'expected'            => WP_CONTENT_DIR . '/themes/source_dir/',
			),
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error object
	 * if the destination directory exists.
	 *
	 * @ticket 54245
	 */
	public function test_install_package_should_abort_if_the_destination_directory_exists() {
		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'installing_package' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'find_folder' )
				->with( '/dest_dir' )
				->willReturn( '/dest_dir/' );

		$dirlist_args = array(
			array( '/source_dir' ),
			array( '/source_dir/' ),
			array( '/dest_dir/' ),
		);

		$dirlist_results = array(
			'file1.php' => array(
				'name' => 'file1.php',
				'type' => 'f',
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->exactly( 3 ) )
				->method( 'dirlist' )
				->withConsecutive( ...$dirlist_args )
				->willReturn( $dirlist_results );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'exists' )
				->with( '/dest_dir/' )
				->willReturn( true );

		$args = array(
			'source'      => '/source_dir',
			'destination' => '/dest_dir',
		);

		$actual = self::$instance->install_package( $args );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::install_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'folder_exists',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error
	 * if the destination directory cannot be created.
	 *
	 * This test runs in a separate process so that it can define
	 * constants without impacting other tests.
	 *
	 * This test does not preserve global state to prevent the exception
	 * "Serialization of 'Closure' is not allowed." when running in a
	 * separate process.
	 *
	 * @ticket 54245
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_install_package_should_return_wp_error_if_destination_cannot_be_created() {
		define( 'FS_CHMOD_DIR', 0755 );

		self::$instance->generic_strings();

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'installing_package' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'find_folder' )
				->with( '/dest_dir' )
				->willReturn( '/dest_dir/' );

		$dirlist_args = array(
			array( '/source_dir' ),
			array( '/source_dir/' ),
		);

		$dirlist_results = array(
			'file1.php' => array(
				'name' => 'file1.php',
				'type' => 'f',
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->exactly( 2 ) )
				->method( 'dirlist' )
				->withConsecutive( ...$dirlist_args )
				->willReturn( $dirlist_results );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'exists' )
				->with( '/dest_dir/' )
				->willReturn( false );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'mkdir' )
				->with( '/dest_dir/' )
				->willReturn( false );

		$args = array(
			'source'                      => '/source_dir',
			'destination'                 => '/dest_dir',
			'abort_if_destination_exists' => false,
		);

		$actual = self::$instance->install_package( $args );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::install_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'mkdir_failed_destination',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);
	}
}
