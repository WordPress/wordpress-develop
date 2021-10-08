<?php
/**
 * Test the `WP_Upgrader` class.
 *
 * @package WordPress\UnitTests
 *
 * @since x.x.x
 */

/**
 * Tests_Admin_wpUpgrader class.
 *
 * @group admin
 * @group upgrading
 *
 * @covers WP_Upgrader
 *
 * @since x.x.x
 */
class Tests_Admin_wpUpgrader extends WP_UnitTestCase {

	/**
	 * The WP_Upgrade class we're going to test.
	 *
	 * @var WP_Upgrader
	 */
	private $instance;

	/**
	 * @var WP_Upgrader_Skin&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $upgrader_skin_mock;

	/**
	 * Filesystem mock.
	 *
	 * @var \PHPUnit\Framework\MockObject\MockObject&WP_Filesystem_Base
	 */
	private $wp_filesystem_mock;

	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	}

	/**
	 * Set up the class instance for each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->upgrader_skin_mock = $this->getMockBuilder( 'WP_Upgrader_Skin' )->getMock();

		$this->instance = new WP_Upgrader( $this->upgrader_skin_mock );

		$this->wp_filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Base' )->getMock();

		$GLOBALS['wp_filesystem'] = $this->wp_filesystem_mock;
	}

	/**
	 * Tear down the class instance after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['wp_filesystem'] );

		parent::tear_down();
	}

	/**
	 * Constructing the class without a skin should create a skin itself.
	 */
	public function test_constructor() {
		$instance = new WP_Upgrader();

		$this->assertInstanceOf( WP_Upgrader_Skin::class, $instance->skin );
	}

	/**
	 * The init method should set up the class and skin.
	 */
	public function test_init() {
		$this->upgrader_skin_mock
			->expects( $this->once() )
			->method( 'set_upgrader' )
			->with( $this->instance );

		$this->instance->init();

		$this->assertNotEmpty( $this->instance->strings['bad_request'] );
		$this->assertNotEmpty( $this->instance->strings['fs_unavailable'] );
		$this->assertNotEmpty( $this->instance->strings['fs_error'] );
		$this->assertNotEmpty( $this->instance->strings['fs_no_root_dir'] );
		$this->assertNotEmpty( $this->instance->strings['fs_no_content_dir'] );
		$this->assertNotEmpty( $this->instance->strings['fs_no_plugins_dir'] );
		$this->assertNotEmpty( $this->instance->strings['fs_no_themes_dir'] );
		$this->assertNotEmpty( $this->instance->strings['fs_no_folder'] );
		$this->assertNotEmpty( $this->instance->strings['download_failed'] );
		$this->assertNotEmpty( $this->instance->strings['installing_package'] );
		$this->assertNotEmpty( $this->instance->strings['no_files'] );
		$this->assertNotEmpty( $this->instance->strings['folder_exists'] );
		$this->assertNotEmpty( $this->instance->strings['mkdir_failed'] );
		$this->assertNotEmpty( $this->instance->strings['incompatible_archive'] );
		$this->assertNotEmpty( $this->instance->strings['files_not_writable'] );
		$this->assertNotEmpty( $this->instance->strings['maintenance_start'] );
		$this->assertNotEmpty( $this->instance->strings['maintenance_end'] );
		$this->assertNotEmpty( $this->instance->strings['temp_backup_mkdir_failed'] );
		$this->assertNotEmpty( $this->instance->strings['temp_backup_move_failed'] );
		$this->assertNotEmpty( $this->instance->strings['temp_backup_restore_failed'] );

		// The backup cleanup is scheduled.
		$this->assertIsInt( wp_next_scheduled( 'delete_temp_updater_backups' ) );
		$this->assertIsInt(
			has_action(
				'delete_temp_updater_backups',
				array(
					$this->instance,
					'delete_all_temp_backups',
				)
			)
		);
	}

	/**
	 * Disabling maintenance mode should remove the .maintenance file.
	 */
	public function test_maintenance_mode_disable() {
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'abspath' )->willReturn( '/' );
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'exists' )->with( '/.maintenance' )->willReturn( true );
		$this->upgrader_skin_mock->expects( $this->once() )->method( 'feedback' )->with( 'maintenance_end' );

		$this->wp_filesystem_mock->expects( $this->once() )->method( 'delete' )->with( '/.maintenance' );

		$this->instance->maintenance_mode();
	}

	/**
	 * Disabling maintenance mode do nothing if the .maintenance file does not exist.
	 */
	public function test_maintenance_mode_disable_no_file_exists() {
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'abspath' )->willReturn( '/' );
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'exists' )->with( '/.maintenance' )->willReturn( false );
		$this->upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		$this->instance->maintenance_mode();
	}

	/**
	 * Enabling maintenance mode should create a .maintenance file.
	 */
	public function test_maintenance_mode_enable() {
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'abspath' )->willReturn( '/' );
		$this->upgrader_skin_mock->expects( $this->once() )->method( 'feedback' )->with( 'maintenance_start' );
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'delete' )->with( '/.maintenance' );
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'put_contents' )->with( '/.maintenance', $this->stringContains( '<?php $upgrading =' ), FS_CHMOD_FILE );

		$this->instance->maintenance_mode( true );
	}

	/**
	 * Enabling maintenance mode should create a .maintenance file.
	 */
	public function test_maintenance_mode_non_boolean() {
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'abspath' );
		$this->upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'delete' );
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'put_contents' );

		$this->instance->maintenance_mode( new stdClass() );
	}

	/**
	 * Release lock should remove the option.
	 */
	public function test_release_lock() {
		update_option( 'lock.lock', 'content' );

		WP_Upgrader::release_lock( 'lock' );

		$this->assertNotSame( 'content', get_option( 'lock.lock' ) );
	}

	/**
	 * Delete temp backup should call delete on the filesystem.
	 */
	public function test_delete_temp_backup() {
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'wp_content_dir' )->willReturn( '/' );
		$this->wp_filesystem_mock->expects( $this->once() )->method( 'delete' )->with( '/upgrade/temp-backup/dir/slug', true );

		$this->instance->delete_temp_backup(
			array(
				'slug' => 'slug',
				'dir'  => 'dir',
			)
		);
	}

	/**
	 * Delete temp backup should return early with invalid arguments.
	 *
	 * @param array $arguments The arguments to test against.
	 *
	 * @dataProvider delete_temp_backup_invalid_arguments_dataprovider
	 */
	public function test_delete_temp_backup_invalid_arguments( $arguments ) {
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'wp_content_dir' );
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'delete' );

		$this->instance->delete_temp_backup( $arguments );
	}

	/**
	 * Invalid arguments for the delete_temp_backup method call.
	 *
	 * @return array[][]
	 */
	public function delete_temp_backup_invalid_arguments_dataprovider() {
		return array(
			'missing slug and dir'              => array(
				'arguments' => array(
					'slug' => '',
					'dir'  => '',
				),
			),
			'missing dir'                       => array(
				'arguments' => array(
					'slug' => 'slug',
					'dir'  => '',
				),
			),
			'missing slug'                      => array(
				'arguments' => array(
					'slug' => '',
					'dir'  => 'dir',
				),
			),
			'boolean - non-string slug and dir' => array(
				'arguments' => array(
					'slug' => true,
					'dir'  => true,
				),
			),
			'object - non-string slug and dir'  => array(
				'arguments' => array(
					'slug' => new stdClass(),
					'dir'  => new stdClass(),
				),
			),
		);
	}

	/**
	 * Download package should exit early when filter returns non-false value.
	 */
	public function test_download_package_pre_download_exit() {
		$callback = function ( $reply ) {
			return ! $reply;
		};

		add_filter( 'upgrader_pre_download', $callback, 10, 1 );

		$this->upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );

		$result = $this->instance->download_package( 'package' );

		$this->assertTrue( $result );

		remove_filter( 'upgrader_pre_download', $callback );
	}

	/**
	 * Download package should call the upgrader_pre_download with expected arguments.
	 */
	public function test_download_package_pre_download_arguments() {
		$callback = function ( $reply, $package, $upgrader, $hook_extra ) {
			$this->assertFalse( $reply );
			$this->assertSame( 'package', $package );
			$this->assertSame( $this->instance, $upgrader );
			$this->assertSame( array( 'hook_extra' ), $hook_extra );

			return ! $reply;
		};

		add_filter( 'upgrader_pre_download', $callback, 10, 4 );

		$this->upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );

		$result = $this->instance->download_package( 'package', false, array( 'hook_extra' ) );

		$this->assertTrue( $result );

		remove_filter( 'upgrader_pre_download', $callback );
	}

	/**
	 * Download package for an existing file should return the file.
	 */
	public function test_download_package_package_constraints() {
		$result = $this->instance->download_package( __FILE__ );

		$this->assertSame( __FILE__, $result );
	}

	/**
	 * Download package should trigger an error for an empty package.
	 */
	public function test_download_package_empty_package() {
		$this->instance->generic_strings();

		$result = $this->instance->download_package( '' );

		$this->assertWPError( $result );
		$this->assertSame( 'no_package', $result->get_error_code() );
	}

	/**
	 * Download package should return a file with the package name in it.
	 */
	public function test_download_package() {
		$callback = function () {
			return array( 'response' => array( 'code' => 200 ) );
		};

		add_filter( 'pre_http_request', $callback );

		$result = $this->instance->download_package( 'wordpress-seo' );

		$this->assertStringContainsString( '/wordpress-seo-', $result );

		remove_filter( 'pre_http_request', $callback );
	}

	/**
	 * Downloads the package URL error should be returned as WP Error.
	 */
	public function test_download_package_wp_error() {
		$this->instance->generic_strings();

		$callback = function () {
			return array(
				'response' => array(
					'code'    => 400,
					'message' => 'error',
				),
			);
		};

		add_filter( 'pre_http_request', $callback );

		$result = $this->instance->download_package( 'wordpress-seo' );

		$this->assertWPError( $result );
		$this->assertSame( 'download_failed', $result->get_error_code() );

		remove_filter( 'pre_http_request', $callback );
	}

	/**
	 * Restore temp backup should return early if not all arguments are provided.
	 *
	 * @param array $arguments The arguments to test against.
	 *
	 * @dataProvider restore_temp_backup_invalid_arguments_dataprovider
	 */
	public function test_restore_temp_backup_invalid_arguments( $arguments ) {
		$this->assertFalse( $this->instance->restore_temp_backup( $arguments ) );
		$this->wp_filesystem_mock->expects( $this->never() )->method( 'wp_content_dir' );
	}

	/**
	 * Provides arguments for the temp backup invalid arguments.
	 *
	 * @return string[][][]
	 */
	public function restore_temp_backup_invalid_arguments_dataprovider() {
		return array(
			'missing slug'              => array(
				'arguments' => array(
					'slug' => '',
					'src'  => 'src',
					'dir'  => 'dir',
				),
			),
			'missing src'               => array(
				'arguments' => array(
					'slug' => 'slug',
					'src'  => '',
					'dir'  => 'dir',
				),
			),
			'missing dir'               => array(
				'arguments' => array(
					'slug' => 'dir',
					'src'  => 'src',
					'dir'  => '',
				),
			),
			'missing slug and dir'      => array(
				'arguments' => array(
					'slug' => '',
					'src'  => 'src',
					'dir'  => '',
				),
			),
			'missing slug and src'      => array(
				'arguments' => array(
					'slug' => '',
					'src'  => '',
					'dir'  => 'dir',
				),
			),
			'missing src and dir'       => array(
				'arguments' => array(
					'slug' => 'slug',
					'src'  => '',
					'dir'  => '',
				),
			),
			'missing all'               => array(
				'arguments' => array(
					'slug' => '',
					'src'  => '',
					'dir'  => '',
				),
			),
			'not string - all'          => array(
				'arguments' => array(
					'slug' => true,
					'src'  => true,
					'dir'  => true,
				),
			),
			'not string - only src'     => array(
				'arguments' => array(
					'slug' => 'true',
					'src'  => true,
					'dir'  => 'true',
				),
			),
			'not string - only dir'     => array(
				'arguments' => array(
					'slug' => 'true',
					'src'  => 'true',
					'dir'  => true,
				),
			),
			'not string - only slug'    => array(
				'arguments' => array(
					'slug' => true,
					'src'  => 'true',
					'dir'  => 'true',
				),
			),
			'not string - src and dir'  => array(
				'arguments' => array(
					'slug' => 'true',
					'src'  => true,
					'dir'  => true,
				),
			),
			'not string - slug and dir' => array(
				'arguments' => array(
					'slug' => true,
					'src'  => 'true',
					'dir'  => true,
				),
			),
			'not string - slug and src' => array(
				'arguments' => array(
					'slug' => true,
					'src'  => true,
					'dir'  => 'true',
				),
			),
		);
	}
}
