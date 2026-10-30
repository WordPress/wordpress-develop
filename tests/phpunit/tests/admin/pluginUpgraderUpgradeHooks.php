<?php

/**
 * Tests for the Plugin_Upgrader callbacks that run around a plugin upgrade.
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Upgrader::deactivate_plugin_before_upgrade
 * @covers Plugin_Upgrader::active_before
 * @covers Plugin_Upgrader::active_after
 * @covers Plugin_Upgrader::delete_old_plugin
 */
class Tests_Admin_PluginUpgraderUpgradeHooks extends WP_UnitTestCase {

	/**
	 * The plugin file of the test plugin.
	 *
	 * @var string
	 */
	const FILE = 'my-plugin/my-plugin.php';

	/**
	 * The plugins directory of the mocked filesystem.
	 *
	 * @var string
	 */
	const PLUGINS_DIR = '/remote/wp-content/plugins/';

	/**
	 * The mocked filesystem.
	 *
	 * @var WP_Filesystem_Base&PHPUnit\Framework\MockObject\MockObject
	 */
	private $filesystem;

	/**
	 * The upgrader.
	 *
	 * @var Plugin_Upgrader
	 */
	private $upgrader;

	/**
	 * Whether the wp_filesystem global was set before the test.
	 *
	 * @var bool
	 */
	private $had_filesystem;

	/**
	 * The original wp_filesystem global.
	 *
	 * @var mixed
	 */
	private $original_filesystem;

	/**
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	}

	/**
	 * Sets up the upgrader and a mocked filesystem.
	 */
	public function set_up() {
		parent::set_up();

		$this->had_filesystem      = array_key_exists( 'wp_filesystem', $GLOBALS );
		$this->original_filesystem = $this->had_filesystem ? $GLOBALS['wp_filesystem'] : null;

		$this->filesystem = $this->createMock( 'WP_Filesystem_Base' );
		$this->filesystem->method( 'wp_plugins_dir' )->willReturn( self::PLUGINS_DIR );
		$this->filesystem->method( 'abspath' )->willReturn( '/remote/' );

		$GLOBALS['wp_filesystem'] = $this->filesystem;

		$this->upgrader = new Plugin_Upgrader( $this->createMock( 'WP_Upgrader_Skin' ) );
		$this->upgrader->generic_strings();
		$this->upgrader->upgrade_strings();

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );
	}

	/**
	 * Restores the filesystem global.
	 */
	public function tear_down() {
		if ( $this->had_filesystem ) {
			$GLOBALS['wp_filesystem'] = $this->original_filesystem;
		} else {
			unset( $GLOBALS['wp_filesystem'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that an error response is passed through by each callback.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_callbacks
	 *
	 * @param string $callback The callback name.
	 */
	public function test_should_pass_error_through( $callback ) {
		// Background upgrades only toggle maintenance mode, while manual ones deactivate the plugin.
		add_filter( 'wp_doing_cron', 'deactivate_plugin_before_upgrade' === $callback ? '__return_false' : '__return_true' );
		update_option( 'active_plugins', array( self::FILE ) );

		$this->filesystem->method( 'exists' )->willReturn( true );
		$this->filesystem->expects( $this->never() )->method( 'delete' );
		$this->filesystem->expects( $this->never() )->method( 'put_contents' );

		$error = new WP_Error( 'previous_error', 'Previous error.' );
		$args  = array( 'plugin' => self::FILE );

		if ( 'delete_old_plugin' === $callback ) {
			$result = $this->upgrader->delete_old_plugin( $error, '', '', $args );
		} else {
			$result = $this->upgrader->$callback( $error, $args );
		}

		$this->assertSame( $error, $result, 'The error should be returned.' );

		// The plugin should remain active when deactivation is bypassed; for other callbacks,
		// the never() expectations above verify that no filesystem operations were performed.
		if ( 'deactivate_plugin_before_upgrade' === $callback ) {
			$this->assertSame( array( self::FILE ), get_option( 'active_plugins' ), 'The plugin should stay active.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_callbacks() {
		return self::text_array_to_dataprovider( array( 'deactivate_plugin_before_upgrade', 'active_before', 'active_after', 'delete_old_plugin' ) );
	}

	/**
	 * Tests that an active plugin is deactivated silently before a manual upgrade.
	 *
	 * @ticket 65819
	 */
	public function test_deactivate_should_deactivate_active_plugin_silently() {
		update_option( 'active_plugins', array( 'other-plugin/other-plugin.php', self::FILE ) );

		$deactivate = new MockAction();
		add_action( 'deactivate_plugin', array( $deactivate, 'action' ) );
		add_action( 'deactivate_' . self::FILE, array( $deactivate, 'action' ) );
		add_action( 'deactivated_plugin', array( $deactivate, 'action' ) );

		$this->assertTrue( $this->upgrader->deactivate_plugin_before_upgrade( true, array( 'plugin' => self::FILE ) ), 'The response should be returned.' );
		$this->assertSame( array( 'other-plugin/other-plugin.php' ), array_values( get_option( 'active_plugins' ) ), 'Only the upgraded plugin should be deactivated.' );
		$this->assertSame( 0, $deactivate->get_call_count(), 'No deactivation hooks should run.' );
	}

	/**
	 * Tests that a network-active plugin is deactivated silently before a manual upgrade on multisite.
	 *
	 * @ticket 65819
	 * @group ms-required
	 */
	public function test_deactivate_should_deactivate_network_active_plugin_silently() {
		update_site_option( 'active_sitewide_plugins', array( self::FILE => time() ) );

		$deactivate = new MockAction();
		add_action( 'deactivate_plugin', array( $deactivate, 'action' ) );
		add_action( 'deactivate_' . self::FILE, array( $deactivate, 'action' ) );
		add_action( 'deactivated_plugin', array( $deactivate, 'action' ) );

		$this->assertTrue( $this->upgrader->deactivate_plugin_before_upgrade( true, array( 'plugin' => self::FILE ) ), 'The response should be returned.' );
		$this->assertSame( array(), get_site_option( 'active_sitewide_plugins' ), 'The network-active plugin should be deactivated.' );
		$this->assertSame( 0, $deactivate->get_call_count(), 'No deactivation hooks should run.' );
	}

	/**
	 * Tests that an inactive plugin is left alone before a manual upgrade.
	 *
	 * @ticket 65819
	 */
	public function test_deactivate_should_leave_inactive_plugin() {
		update_option( 'active_plugins', array( 'other-plugin/other-plugin.php' ) );

		$this->assertTrue( $this->upgrader->deactivate_plugin_before_upgrade( true, array( 'plugin' => self::FILE ) ), 'The response should be returned.' );
		$this->assertSame( array( 'other-plugin/other-plugin.php' ), get_option( 'active_plugins' ), 'The active plugins should not change.' );
	}

	/**
	 * Tests that a plugin is not deactivated during a background upgrade.
	 *
	 * @ticket 65819
	 */
	public function test_deactivate_should_not_deactivate_plugin_in_cron() {
		add_filter( 'wp_doing_cron', '__return_true' );
		update_option( 'active_plugins', array( self::FILE ) );

		$this->assertTrue( $this->upgrader->deactivate_plugin_before_upgrade( true, array( 'plugin' => self::FILE ) ), 'The response should be returned.' );
		$this->assertSame( array( self::FILE ), get_option( 'active_plugins' ), 'The plugin should stay active.' );
	}

	/**
	 * Tests that a missing plugin is a bad request.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_missing_plugins
	 *
	 * @param string $callback The callback name.
	 * @param array  $args     The hook extra arguments.
	 */
	public function test_should_return_bad_request_without_plugin( $callback, $args ) {
		if ( 'delete_old_plugin' === $callback ) {
			$result = $this->upgrader->delete_old_plugin( true, '', '', $args );
		} else {
			$result = $this->upgrader->$callback( true, $args );
		}

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'bad_request', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'Invalid data provided.', $result->get_error_message(), 'The error message should match.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_missing_plugins() {
		return array(
			'deactivate, no plugin key' => array( 'deactivate_plugin_before_upgrade', array() ),
			'deactivate, empty plugin'  => array( 'deactivate_plugin_before_upgrade', array( 'plugin' => '' ) ),
			'delete, no plugin key'     => array( 'delete_old_plugin', array() ),
			'delete, empty plugin'      => array( 'delete_old_plugin', array( 'plugin' => '' ) ),
		);
	}

	/**
	 * Tests that active_before() and active_after() do not toggle maintenance mode when the plugin key is missing or empty.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_empty_plugin_args
	 *
	 * @param array $args The hook arguments.
	 */
	public function test_active_before_and_after_should_not_toggle_maintenance_mode_without_plugin( $args ) {
		add_filter( 'wp_doing_cron', '__return_true' );

		$this->filesystem->expects( $this->never() )->method( 'put_contents' );
		$this->filesystem->expects( $this->never() )->method( 'delete' );

		$this->assertTrue( $this->upgrader->active_before( true, $args ), 'active_before() should return the response.' );
		$this->assertTrue( $this->upgrader->active_after( true, $args ), 'active_after() should return the response.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_plugin_args() {
		return array(
			'no plugin key' => array( array() ),
			'empty plugin'  => array( array( 'plugin' => '' ) ),
		);
	}

	/**
	 * Tests that maintenance mode is enabled and disabled for an active plugin during a background upgrade.
	 *
	 * The maintenance file is written with FS_CHMOD_FILE, which WP_Filesystem() defines.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_active_before_and_after_should_toggle_maintenance_mode_in_cron() {
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', 0644 );
		}

		add_filter( 'wp_doing_cron', '__return_true' );
		update_option( 'active_plugins', array( self::FILE ) );

		$maintenance_file_exists = false;

		$this->filesystem->method( 'exists' )
			->willReturnCallback(
				static function ( $file ) use ( &$maintenance_file_exists ) {
					return '/remote/.maintenance' === $file ? $maintenance_file_exists : true;
				}
			);

		$this->filesystem->expects( $this->once() )
			->method( 'put_contents' )
			->with( '/remote/.maintenance', $this->stringStartsWith( '<?php $upgrading = ' ), FS_CHMOD_FILE )
			->willReturnCallback(
				static function ( $file ) use ( &$maintenance_file_exists ) {
					if ( '/remote/.maintenance' === $file ) {
						$maintenance_file_exists = true;
					}
					return true;
				}
			);

		$this->filesystem->expects( $this->atLeastOnce() )
			->method( 'delete' )
			->with( '/remote/.maintenance' )
			->willReturnCallback(
				static function ( $file ) use ( &$maintenance_file_exists ) {
					if ( '/remote/.maintenance' === $file ) {
						$maintenance_file_exists = false;
					}
					return true;
				}
			);

		$this->assertTrue( $this->upgrader->active_before( true, array( 'plugin' => self::FILE ) ), 'active_before() should return the response.' );
		$this->assertTrue( $maintenance_file_exists, 'Maintenance mode should be active after active_before().' );

		$this->assertTrue( $this->upgrader->active_after( true, array( 'plugin' => self::FILE ) ), 'active_after() should return the response.' );
		$this->assertFalse( $maintenance_file_exists, 'Maintenance mode should be inactive after active_after().' );
	}

	/**
	 * Tests that maintenance mode is toggled for a network-active plugin during a background upgrade on multisite.
	 *
	 * @ticket 65819
	 * @group ms-required
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_active_before_and_after_should_toggle_maintenance_mode_for_network_active_plugin_in_cron() {
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', 0644 );
		}

		add_filter( 'wp_doing_cron', '__return_true' );
		update_site_option( 'active_sitewide_plugins', array( self::FILE => time() ) );

		$maintenance_file_exists = false;

		$this->filesystem->method( 'exists' )
			->willReturnCallback(
				static function ( $file ) use ( &$maintenance_file_exists ) {
					return '/remote/.maintenance' === $file ? $maintenance_file_exists : true;
				}
			);

		$this->filesystem->expects( $this->once() )
			->method( 'put_contents' )
			->with( '/remote/.maintenance', $this->stringStartsWith( '<?php $upgrading = ' ), FS_CHMOD_FILE )
			->willReturnCallback(
				static function ( $file ) use ( &$maintenance_file_exists ) {
					if ( '/remote/.maintenance' === $file ) {
						$maintenance_file_exists = true;
					}
					return true;
				}
			);

		$this->filesystem->expects( $this->atLeastOnce() )
			->method( 'delete' )
			->with( '/remote/.maintenance' )
			->willReturnCallback(
				static function ( $file ) use ( &$maintenance_file_exists ) {
					if ( '/remote/.maintenance' === $file ) {
						$maintenance_file_exists = false;
					}
					return true;
				}
			);

		$this->assertTrue( $this->upgrader->active_before( true, array( 'plugin' => self::FILE ) ), 'active_before() should return the response.' );
		$this->assertTrue( $maintenance_file_exists, 'Maintenance mode should be active after active_before().' );

		$this->assertTrue( $this->upgrader->active_after( true, array( 'plugin' => self::FILE ) ), 'active_after() should return the response.' );
		$this->assertFalse( $maintenance_file_exists, 'Maintenance mode should be inactive after active_after().' );
	}

	/**
	 * Tests that maintenance mode is left alone unless an active plugin is upgraded alone in the background.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_maintenance_mode
	 *
	 * @param bool $cron   Whether the upgrade runs in cron.
	 * @param bool $active Whether the plugin is active.
	 * @param bool $bulk   Whether it is a bulk upgrade.
	 */
	public function test_active_before_and_after_should_not_toggle_maintenance_mode( $cron, $active, $bulk ) {
		add_filter( 'wp_doing_cron', $cron ? '__return_true' : '__return_false' );
		update_option( 'active_plugins', $active ? array( self::FILE ) : array() );
		$this->upgrader->bulk = $bulk;

		$this->filesystem->method( 'exists' )->willReturn( true );
		$this->filesystem->expects( $this->never() )->method( 'put_contents' );
		$this->filesystem->expects( $this->never() )->method( 'delete' );

		$this->assertTrue( $this->upgrader->active_before( true, array( 'plugin' => self::FILE ) ), 'active_before() should return the response.' );
		$this->assertTrue( $this->upgrader->active_after( true, array( 'plugin' => self::FILE ) ), 'active_after() should return the response.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_maintenance_mode() {
		return array(
			'manual upgrade'  => array( false, true, false ),
			'inactive plugin' => array( true, false, false ),
			'bulk upgrade'    => array( true, true, true ),
		);
	}

	/**
	 * Tests how the old plugin files are deleted.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_old_plugin_files
	 *
	 * @param string $plugin    The plugin file.
	 * @param string $path      The expected deleted path.
	 * @param bool   $recursive Whether the deletion is expected to be recursive.
	 */
	public function test_delete_old_plugin_should_delete_plugin_files( $plugin, $path, $recursive ) {
		$this->filesystem->method( 'exists' )->willReturn( true );
		$this->filesystem->expects( $this->once() )
			->method( 'delete' )
			->with( $path, $recursive )
			->willReturn( true );

		$this->assertTrue( $this->upgrader->delete_old_plugin( true, '', '', array( 'plugin' => $plugin ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_old_plugin_files() {
		return array(
			'plugin in a directory'               => array( self::FILE, self::PLUGINS_DIR . 'my-plugin/', true ),
			'single-file plugin'                  => array( 'my-plugin.php', self::PLUGINS_DIR . 'my-plugin.php', false ),
			'directory trailing slash only guard' => array( 'my-plugin/', self::PLUGINS_DIR . 'my-plugin/', false ),
		);
	}

	/**
	 * Tests that a plugin directory that is already gone is not deleted.
	 *
	 * @ticket 65819
	 */
	public function test_delete_old_plugin_should_skip_missing_directory() {
		$this->filesystem->expects( $this->once() )
			->method( 'exists' )
			->with( self::PLUGINS_DIR . 'my-plugin/' )
			->willReturn( false );
		$this->filesystem->expects( $this->never() )->method( 'delete' );

		$this->assertFalse( $this->upgrader->delete_old_plugin( false, '', '', array( 'plugin' => self::FILE ) ) );
	}

	/**
	 * Tests that a failed deletion is reported.
	 *
	 * @ticket 65819
	 */
	public function test_delete_old_plugin_should_report_failed_deletion() {
		$this->filesystem->method( 'exists' )->willReturn( true );
		$this->filesystem->method( 'delete' )->willReturn( false );

		$result = $this->upgrader->delete_old_plugin( null, '', '', array( 'plugin' => self::FILE ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'remove_old_failed', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'Could not remove the old plugin.', $result->get_error_message(), 'The error message should match.' );
	}
}
