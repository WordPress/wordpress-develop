<?php
/**
 * Tests the `WP_Upgrader` class.
 *
 * @group admin
 * @group upgrade
 */
class Tests_Admin_WpUpgrader extends WP_UnitTestCase {

	/**
	 * An instance of the WP_Upgrader class being tested.
	 *
	 * @var WP_Upgrader
	 */
	private static $instance;

	/**
	 * @var WP_Upgrader_Skin&PHPUnit\Framework\MockObject\MockObject
	 */
	private static $upgrader_skin_mock;

	/**
	 * Filesystem mock.
	 *
	 * @var WP_Filesystem_Base&PHPUnit\Framework\MockObject\MockObject
	 */
	private static $wp_filesystem_mock;

	/**
	 * A backup of the existing 'wp_filesystem' global.
	 *
	 * @var mixed|null
	 */
	private static $wp_filesystem_backup = null;

	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	}

	/**
	 * Sets up the class instance and mocks needed for each test.
	 */
	public function set_up() {
		parent::set_up();

		self::$upgrader_skin_mock = $this->getMockBuilder( 'WP_Upgrader_Skin' )->getMock();

		self::$instance = new WP_Upgrader( self::$upgrader_skin_mock );

		self::$wp_filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Base' )->getMock();

		if ( array_key_exists( 'wp_filesystem', $GLOBALS ) ) {
			self::$wp_filesystem_backup = $GLOBALS['wp_filesystem'];
		}

		$GLOBALS['wp_filesystem'] = self::$wp_filesystem_mock;
	}

	/**
	 * Cleans up after each test.
	 */
	public function tear_down() {
		if ( null !== self::$wp_filesystem_backup ) {
			$GLOBALS['wp_filesystem'] = self::$wp_filesystem_backup;
		} else {
			unset( $GLOBALS['wp_filesystem'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that `WP_Upgrader::__construct()` creates a skin when one is not
	 * passed to the constructor.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::__construct
	 */
	public function test_constructor_should_create_skin_when_one_is_not_provided() {
		$instance = new WP_Upgrader();

		$this->assertInstanceOf( WP_Upgrader_Skin::class, $instance->skin );
	}

	/**
	 * Tests that `WP_Upgrader::init()` calls `WP_Upgrader::set_upgrader()`.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::init
	 */
	public function test_init_should_call_set_upgrader() {
		self::$upgrader_skin_mock->expects( $this->once() )->method( 'set_upgrader' )->with( self::$instance );
		self::$instance->init();
	}

	/**
	 * Tests that `WP_Upgrader::init()` initializes the `$strings` property.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::init
	 * @covers WP_Upgrader::generic_strings
	 *
	 * @dataProvider data_init_should_initialize_strings
	 *
	 * @param string $key The key to check.
	 */
	public function test_init_should_initialize_strings( $key ) {
		$this->assertEmpty( self::$instance->strings, '"$strings" has already been initialized' );

		self::$instance->init();

		$this->assertArrayHasKey( $key, self::$instance->strings, "The '$key' key was not created" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_init_should_initialize_strings() {
		return self::text_array_to_dataprovider(
			array(
				'bad_request',
				'fs_unavailable',
				'fs_error',
				'fs_no_root_dir',
				'fs_no_content_dir',
				'fs_no_plugins_dir',
				'fs_no_themes_dir',
				'fs_no_folder',
				'no_package',
				'download_failed',
				'installing_package',
				'no_files',
				'folder_exists',
				'mkdir_failed',
				'incompatible_archive',
				'files_not_writable',
				'maintenance_start',
				'maintenance_end',
				'temp_backup_mkdir_failed',
				'temp_backup_move_failed',
				'temp_backup_restore_failed',
				'temp_backup_delete_failed',
			)
		);
	}

	/**
	 * Tests that `WP_Upgrader::flatten_dirlist()` returns the expected file list.
	 *
	 * @ticket 54245
	 *
	 * @dataProvider data_should_flatten_dirlist
	 *
	 * @covers WP_Upgrader::flatten_dirlist
	 *
	 * @param array  $expected     The expected flattened dirlist.
	 * @param array  $nested_files Array of files as returned by WP_Filesystem_Base::dirlist().
	 * @param string $path         Optional. Relative path to prepend to child nodes. Default empty string.
	 */
	public function test_flatten_dirlist_should_flatten_the_provided_directory_list( $expected, $nested_files, $path = '' ) {
		$flatten_dirlist = new ReflectionMethod( self::$instance, 'flatten_dirlist' );
		$flatten_dirlist->setAccessible( true );
		$actual = $flatten_dirlist->invoke( self::$instance, $nested_files, $path );
		$flatten_dirlist->setAccessible( false );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_flatten_dirlist() {
		return array(
			'empty array, default path'       => array(
				'expected'     => array(),
				'nested_files' => array(),
			),
			'root only'                       => array(
				'expected'     => array(
					'file1.php' => array( 'name' => 'file1.php' ),
					'file2.php' => array( 'name' => 'file2.php' ),
				),
				'nested_files' => array(
					'file1.php' => array( 'name' => 'file1.php' ),
					'file2.php' => array( 'name' => 'file2.php' ),
				),
			),
			'root only and custom path'       => array(
				'expected'     => array(
					'custom_path/file1.php' => array( 'name' => 'file1.php' ),
					'custom_path/file2.php' => array( 'name' => 'file2.php' ),
				),
				'nested_files' => array(
					'file1.php' => array( 'name' => 'file1.php' ),
					'file2.php' => array( 'name' => 'file2.php' ),
				),
				'path'         => 'custom_path/',
			),
			'one level deep'                  => array(
				'expected'     => array(
					'subdir1'              => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'subdir2'              => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
					'subdir1/subfile1.php' => array( 'name' => 'subfile1.php' ),
					'subdir1/subfile2.php' => array( 'name' => 'subfile2.php' ),
					'subdir2/subfile3.php' => array( 'name' => 'subfile3.php' ),
					'subdir2/subfile4.php' => array( 'name' => 'subfile4.php' ),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
				),
			),
			'one level deep and numeric keys' => array(
				'expected'     => array(
					'subdir1'   => array(
						'files' => array(
							0 => array( 'name' => '0' ),
							1 => array( 'name' => '1' ),
						),
					),
					'subdir2'   => array(
						'files' => array(
							2 => array( 'name' => '2' ),
							3 => array( 'name' => '3' ),
						),
					),
					'subdir1/0' => array( 'name' => '0' ),
					'subdir1/1' => array( 'name' => '1' ),
					'subdir2/2' => array( 'name' => '2' ),
					'subdir2/3' => array( 'name' => '3' ),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'0' => array( 'name' => '0' ),
							'1' => array( 'name' => '1' ),
						),
					),
					'subdir2' => array(
						'files' => array(
							'2' => array( 'name' => '2' ),
							'3' => array( 'name' => '3' ),
						),
					),
				),
			),
			'one level deep and custom path'  => array(
				'expected'     => array(
					'custom_path/subdir1'              => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'custom_path/subdir2'              => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
					'custom_path/subdir1/subfile1.php' => array(
						'name' => 'subfile1.php',
					),
					'custom_path/subdir1/subfile2.php' => array(
						'name' => 'subfile2.php',
					),
					'custom_path/subdir2/subfile3.php' => array(
						'name' => 'subfile3.php',
					),
					'custom_path/subdir2/subfile4.php' => array(
						'name' => 'subfile4.php',
					),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
						),
					),
				),
				'path'         => 'custom_path/',
			),
			'two levels deep'                 => array(
				'expected'     => array(
					'subdir1'                            => array(
						'files' => array(
							'subfile1.php' => array(
								'name' => 'subfile1.php',
							),
							'subfile2.php' => array(
								'name' => 'subfile2.php',
							),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'subdir1/subfile1.php'               => array(
						'name' => 'subfile1.php',
					),
					'subdir1/subfile2.php'               => array(
						'name' => 'subfile2.php',
					),
					'subdir1/subsubdir1'                 => array(
						'files' => array(
							'subsubfile1.php' => array(
								'name' => 'subsubfile1.php',
							),
							'subsubfile2.php' => array(
								'name' => 'subsubfile2.php',
							),
						),
					),
					'subdir1/subsubdir1/subsubfile1.php' => array(
						'name' => 'subsubfile1.php',
					),
					'subdir1/subsubdir1/subsubfile2.php' => array(
						'name' => 'subsubfile2.php',
					),
					'subdir2'                            => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
					'subdir2/subfile3.php'               => array(
						'name' => 'subfile3.php',
					),
					'subdir2/subfile4.php'               => array(
						'name' => 'subfile4.php',
					),
					'subdir2/subsubdir2'                 => array(
						'files' => array(
							'subsubfile3.php' => array(
								'name' => 'subsubfile3.php',
							),
							'subsubfile4.php' => array(
								'name' => 'subsubfile4.php',
							),
						),
					),
					'subdir2/subsubdir2/subsubfile3.php' => array(
						'name' => 'subsubfile3.php',
					),
					'subdir2/subsubdir2/subsubfile4.php' => array(
						'name' => 'subsubfile4.php',
					),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
				),
			),
			'two levels deep and custom path' => array(
				'expected'     => array(
					'custom_path/subdir1'              => array(
						'files' => array(
							'subfile1.php' => array(
								'name' => 'subfile1.php',
							),
							'subfile2.php' => array(
								'name' => 'subfile2.php',
							),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'custom_path/subdir1/subfile1.php' => array(
						'name' => 'subfile1.php',
					),
					'custom_path/subdir1/subfile2.php' => array(
						'name' => 'subfile2.php',
					),
					'custom_path/subdir1/subsubdir1'   => array(
						'files' => array(
							'subsubfile1.php' => array(
								'name' => 'subsubfile1.php',
							),
							'subsubfile2.php' => array(
								'name' => 'subsubfile2.php',
							),
						),
					),
					'custom_path/subdir1/subsubdir1/subsubfile1.php' => array(
						'name' => 'subsubfile1.php',
					),
					'custom_path/subdir1/subsubdir1/subsubfile2.php' => array(
						'name' => 'subsubfile2.php',
					),
					'custom_path/subdir2'              => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
					'custom_path/subdir2/subfile3.php' => array(
						'name' => 'subfile3.php',
					),
					'custom_path/subdir2/subfile4.php' => array(
						'name' => 'subfile4.php',
					),
					'custom_path/subdir2/subsubdir2'   => array(
						'files' => array(
							'subsubfile3.php' => array(
								'name' => 'subsubfile3.php',
							),
							'subsubfile4.php' => array(
								'name' => 'subsubfile4.php',
							),
						),
					),
					'custom_path/subdir2/subsubdir2/subsubfile3.php' => array(
						'name' => 'subsubfile3.php',
					),
					'custom_path/subdir2/subsubdir2/subsubfile4.php' => array(
						'name' => 'subsubfile4.php',
					),
				),
				'nested_files' => array(
					'subdir1' => array(
						'files' => array(
							'subfile1.php' => array( 'name' => 'subfile1.php' ),
							'subfile2.php' => array( 'name' => 'subfile2.php' ),
							'subsubdir1'   => array(
								'files' => array(
									'subsubfile1.php' => array(
										'name' => 'subsubfile1.php',
									),
									'subsubfile2.php' => array(
										'name' => 'subsubfile2.php',
									),
								),
							),
						),
					),
					'subdir2' => array(
						'files' => array(
							'subfile3.php' => array( 'name' => 'subfile3.php' ),
							'subfile4.php' => array( 'name' => 'subfile4.php' ),
							'subsubdir2'   => array(
								'files' => array(
									'subsubfile3.php' => array(
										'name' => 'subsubfile3.php',
									),
									'subsubfile4.php' => array(
										'name' => 'subsubfile4.php',
									),
								),
							),
						),
					),
				),
				'path'         => 'custom_path/',
			),
		);
	}

	/**
	 * Tests that `WP_Upgrader::clear_destination()` returns early with `true`
	 * when the destination does not exist.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::clear_destination
	 */
	public function test_clear_destination_should_return_early_when_the_destination_does_not_exist() {
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'is_writable' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'chmod' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		$destination = DIR_TESTDATA . '/upgrade/';

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( $destination )
				->willReturn( false );

		$this->assertTrue( self::$instance->clear_destination( $destination ) );
	}

	/**
	 * Tests that `WP_Upgrader::clear_destination()` clears
	 * the destination directory.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::clear_destination
	 */
	public function test_clear_destination_should_clear_the_destination_directory() {
		$destination = DIR_TESTDATA . '/upgrade/';

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( $destination )
				->willReturn( array() );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'delete' )
				->with( $destination )
				->willReturn( true );

		$this->assertTrue( self::$instance->clear_destination( $destination ) );
	}

	/**
	 * Tests that `WP_Upgrader::clear_destination()` returns a WP_Error object
	 * if files are not writable.
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
	 * @covers WP_Upgrader::clear_destination
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_clear_destination_should_return_wp_error_if_files_are_not_writable() {
		define( 'FS_CHMOD_FILE', 0644 );
		define( 'FS_CHMOD_DIR', 0755 );

		self::$instance->generic_strings();

		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		$destination = DIR_TESTDATA . '/upgrade/';
		$dirlist     = array(
			'file1.php' => array(
				'name' => 'file1.php',
				'type' => 'f',
			),
			'subdir'    => array(
				'name' => 'subdir',
				'type' => 'd',
			),
		);

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'dirlist' )
				->with( $destination )
				->willReturn( $dirlist );

		$unwritable_checks = array(
			array( $destination . 'file1.php' ),
			array( $destination . 'file1.php' ),
			array( $destination . 'subdir' ),
			array( $destination . 'subdir' ),
		);

		self::$wp_filesystem_mock
				->expects( $this->exactly( 4 ) )
				->method( 'is_writable' )
				->withConsecutive( ...$unwritable_checks )
				->willReturn( false );

		$actual = self::$instance->clear_destination( $destination );

		$this->assertWPError(
			$actual,
			'WP_Upgrader::clear_destination() did not return a WP_Error object'
		);

		$this->assertSame(
			'files_not_writable',
			$actual->get_error_code(),
			'Unexpected WP_Error code'
		);

		$this->assertSameSets(
			array( 'file1.php, subdir' ),
			$actual->get_all_error_data(),
			'Unexpected WP_Error data'
		);
	}

	/**
	 * Tests that `WP_Upgrader::install_package()` returns a WP_Error object
	 * when an invalid source is passed.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::install_package
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
	 * @covers WP_Upgrader::install_package
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
	 *
	 * @covers WP_Upgrader::install_package
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
	 *
	 * @covers WP_Upgrader::install_package
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
	 *
	 * @covers WP_Upgrader::install_package
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
	 *
	 * @covers WP_Upgrader::install_package
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
	 * @covers WP_Upgrader::install_package
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
	 * @covers WP_Upgrader::install_package
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
	 *
	 * @covers WP_Upgrader::install_package
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
	 * @covers WP_Upgrader::install_package
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

	/**
	 * Tests that `WP_Upgrader::run()` returns `false` when
	 * requesting filesystem credentials fails.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::run
	 */
	public function test_run_should_return_false_when_requesting_filesystem_credentials_fails() {
		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'request_filesystem_credentials' )
				->willReturn( false );

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'footer' );

		$this->assertFalse( self::$instance->run( array() ) );
	}

	/**
	 * Tests that `WP_Upgrader::maintenance_mode()` removes the `.maintenance` file.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::maintenance_mode
	 */
	public function test_maintenance_mode_should_disable_maintenance_mode_if_maintenance_file_exists() {
		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'abspath' )
				->willReturn( '/' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'exists' )
				->with( '/.maintenance' )
				->willReturn( true );

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'maintenance_end' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'delete' )
				->with( '/.maintenance' );

		self::$instance->maintenance_mode();
	}

	/**
	 * Tests that `WP_Upgrader::maintenance_mode()` does nothing if
	 * the `.maintenance` file does not exist.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::maintenance_mode
	 */
	public function test_maintenance_mode_should_not_disable_maintenance_mode_if_no_maintenance_file_exists() {
		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );
		self::$wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'abspath' )
				->willReturn( '/' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'exists' )
				->with( '/.maintenance' )
				->willReturn( false );

		self::$instance->maintenance_mode();
	}

	/**
	 * Tests that `WP_Upgrader::maintenance_mode()` creates
	 * a `.maintenance` file with a boolean `$enable` argument.
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
	 * @covers WP_Upgrader::maintenance_mode
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_maintenance_mode_should_create_maintenance_file_with_boolean() {
		define( 'FS_CHMOD_FILE', 0644 );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'abspath' )
				->willReturn( '/' );

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'feedback' )
				->with( 'maintenance_start' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'delete' )
				->with( '/.maintenance' );

		self::$wp_filesystem_mock
				->expects( $this->once() )
				->method( 'put_contents' )
				->with(
					'/.maintenance',
					$this->stringContains( '<?php $upgrading =' ),
					FS_CHMOD_FILE
				);

		self::$instance->maintenance_mode( true );
	}

	/**
	 * Tests that `WP_Upgrader::release_lock()` removes the 'lock' option.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::release_lock
	 */
	public function test_release_lock_should_remove_lock_option() {
		global $wpdb;

		$this->assertSame(
			1,
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => 'lock.lock',
					'option_value' => 'content',
				),
				'%s'
			),
			'The initial lock was not created.'
		);

		WP_Upgrader::release_lock( 'lock' );

		$this->assertNotSame( 'content', get_option( 'lock.lock' ) );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns early when
	 * the 'upgrader_pre_download' filter returns a non-false value.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_exit_early_when_the_upgrader_pre_download_filter_returns_non_false() {
		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );

		add_filter(
			'upgrader_pre_download',
			static function () {
				return 'a non-false value';
			}
		);

		$result = self::$instance->download_package( 'package' );

		$this->assertSame( 'a non-false value', $result );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` should apply
	 * 'upgrader_pre_download' filters with expected arguments.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_apply_upgrader_pre_download_filter_with_arguments() {
		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );

		add_filter(
			'upgrader_pre_download',
			function ( $reply, $package, $upgrader, $hook_extra ) {
				$this->assertFalse( $reply, '"$reply" was not false' );

				$this->assertSame(
					'package',
					$package,
					'The package file name was not "package"'
				);

				$this->assertSame(
					self::$instance,
					$upgrader,
					'The wrong WP_Upgrader instance was passed'
				);

				$this->assertSameSets(
					array( 'hook_extra' ),
					$hook_extra,
					'The "$hook_extra" array was not the expected array'
				);

				return ! $reply;
			},
			10,
			4
		);

		$result = self::$instance->download_package( 'package', false, array( 'hook_extra' ) );

		$this->assertTrue(
			$result,
			'WP_Upgrader::download_package() did not return true'
		);
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns an existing file.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_return_an_existing_file() {
		$result = self::$instance->download_package( __FILE__ );

		$this->assertSame( __FILE__, $result );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns a WP_Error object
	 * for an empty package.
	 *
	 * @ticket 59712
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_return_a_wp_error_object_for_an_empty_package() {
		self::$instance->init();

		$result = self::$instance->download_package( '' );

		$this->assertWPError(
			$result,
			'WP_Upgrader::download_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'no_package',
			$result->get_error_code(),
			'Unexpected WP_Error code'
		);
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns a file with the
	 * package name in it.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_return_a_file_with_the_package_name() {
		add_filter(
			'pre_http_request',
			static function () {
				return array( 'response' => array( 'code' => 200 ) );
			}
		);

		$result = self::$instance->download_package( 'wordpress-seo' );

		$this->assertStringContainsString( '/wordpress-seo-', $result );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns a package URL error
	 * as a `WP_Error` object.
	 *
	 * @ticket 54245
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_return_a_wp_error_object() {
		self::$instance->generic_strings();

		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array(
						'code'    => 400,
						'message' => 'error',
					),
				);
			}
		);

		$result = self::$instance->download_package( 'wordpress-seo' );

		$this->assertWPError(
			$result,
			'WP_Upgrader::download_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'download_failed',
			$result->get_error_code(),
			'Unexpected WP_Error code'
		);
	}
}
