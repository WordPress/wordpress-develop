<?php

/**
 * Tests for the plugin sections of WP_Debug_Data.
 *
 * @group admin
 * @group site-health
 * @group plugins
 *
 * @covers WP_Debug_Data::get_wp_mu_plugins
 * @covers WP_Debug_Data::get_wp_plugins_raw_data
 */
class Tests_Admin_wpDebugDataPlugins extends WP_UnitTestCase {

	/**
	 * Must-use plugin files created by the current test.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * Loads the debug data class and backs up the must-use plugins.
	 */
	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		self::_back_up_mu_plugins();
	}

	/**
	 * Restores the must-use plugins.
	 */
	public static function wpTearDownAfterClass() {
		self::_restore_mu_plugins();
	}

	/**
	 * Resets the active plugins, the update transient and the auto-update settings.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );
		update_site_option( 'auto_update_plugins', array() );
		delete_site_transient( 'update_plugins' );

		remove_all_filters( 'auto_update_plugin' );
		add_filter( 'plugins_auto_update_enabled', '__return_false' );
	}

	/**
	 * Removes the must-use plugin files, the plugins cache and the update transient.
	 */
	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			unlink( $file );
		}
		$this->created_files = array();

		wp_cache_delete( 'plugins', 'plugins' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests the must-use plugins section without must-use plugins.
	 *
	 * @ticket 65819
	 */
	public function test_mu_plugins_should_return_empty_section() {
		$this->assertSame(
			array(
				'label'      => 'Must Use Plugins',
				'show_count' => true,
				'fields'     => array(),
			),
			$this->call( 'get_wp_mu_plugins' )
		);
	}

	/**
	 * Tests the version and author strings of a must-use plugin.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_version_strings
	 *
	 * @param string $version The Version header.
	 * @param string $author  The Author header.
	 * @param string $value   The expected value.
	 * @param string $debug   The expected debug value.
	 */
	public function test_mu_plugins_should_describe_version_and_author( $version, $author, $value, $debug ) {
		$headers = " * Plugin Name: My <b>MU</b> Plugin\n";
		if ( '' !== $version ) {
			$headers .= " * Version: {$version}\n";
		}
		if ( '' !== $author ) {
			$headers .= " * Author: {$author}\n";
		}

		$this->create_mu_plugin( 'my-mu-plugin.php', "<?php\n/**\n{$headers} */\n" );

		$this->assertSame(
			array(
				'My MU Plugin' => array(
					'label' => 'My <b>MU</b> Plugin',
					'value' => $value,
					'debug' => $debug,
				),
			),
			$this->call( 'get_wp_mu_plugins' )['fields']
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_version_strings() {
		return array(
			'version and author' => array( '1.0', 'Jane', 'Version 1.0 by Jane', 'version: 1.0, author: Jane' ),
			'author only'        => array( '', 'Jane', 'By Jane', 'author: Jane, version: (undefined)' ),
			'version only'       => array( '1.0', '', 'Version 1.0', 'author: (undefined), version: 1.0' ),
			'neither'            => array( '', '', 'No version or author information is available.', 'author: (undefined), version: (undefined)' ),
		);
	}

	/**
	 * Tests the version and author strings of a plugin, and its split into active and inactive.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_version_strings
	 *
	 * @param string $version The Version header.
	 * @param string $author  The Author header.
	 * @param string $value   The expected value.
	 * @param string $debug   The expected debug value.
	 */
	public function test_plugins_should_describe_version_and_author( $version, $author, $value, $debug ) {
		$this->set_plugins(
			array(
				'active/active.php'     => array( 'Active <b>Plugin</b>', $version, $author ),
				'inactive/inactive.php' => array( 'Inactive Plugin', $version, $author ),
			),
			array( 'active/active.php' )
		);

		$this->assertSame(
			array(
				'wp-plugins-active'   => array(
					'Active Plugin' => array(
						'label' => 'Active <b>Plugin</b>',
						'value' => $value,
						'debug' => $debug,
					),
				),
				'wp-plugins-inactive' => array(
					'Inactive Plugin' => array(
						'label' => 'Inactive Plugin',
						'value' => $value,
						'debug' => $debug,
					),
				),
			),
			$this->call( 'get_wp_plugins_raw_data' )
		);
	}

	/**
	 * Tests that an available update is mentioned.
	 *
	 * @ticket 65819
	 */
	public function test_plugins_should_mention_latest_version() {
		$this->set_plugins( array( 'my-plugin/my-plugin.php' => array( 'My Plugin', '1.0', 'Jane' ) ) );
		$this->set_update( 'response', 'my-plugin/my-plugin.php', '2.0' );

		$this->assertSame(
			array(
				'label' => 'My Plugin',
				'value' => 'Version 1.0 by Jane (Latest version: 2.0)',
				'debug' => 'version: 1.0, author: Jane (latest version: 2.0)',
			),
			$this->call( 'get_wp_plugins_raw_data' )['wp-plugins-inactive']['My Plugin']
		);
	}

	/**
	 * Tests the auto-update status when plugin auto-updates are enabled.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_auto_update_states
	 *
	 * @param bool      $in_setting Whether the plugin is in the auto_update_plugins setting.
	 * @param bool|null $forced     The value of the auto_update_plugin filter, or null for no filter.
	 * @param string    $status     The expected status string.
	 */
	public function test_plugins_should_add_auto_update_status( $in_setting, $forced, $status ) {
		add_filter( 'plugins_auto_update_enabled', '__return_true', 11 );

		$this->set_plugins( array( 'my-plugin/my-plugin.php' => array( 'My Plugin', '1.0', 'Jane' ) ) );

		if ( $in_setting ) {
			update_site_option( 'auto_update_plugins', array( 'my-plugin/my-plugin.php' ) );
		}

		if ( null !== $forced ) {
			add_filter( 'auto_update_plugin', $forced ? '__return_true' : '__return_false' );
		}

		$this->assertSame(
			array(
				'label' => 'My Plugin',
				'value' => 'Version 1.0 by Jane | ' . $status,
				'debug' => 'version: 1.0, author: Jane, ' . $status,
			),
			$this->call( 'get_wp_plugins_raw_data' )['wp-plugins-inactive']['My Plugin']
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_auto_update_states() {
		return array(
			'in setting'                => array( true, null, 'Auto-updates enabled' ),
			'not in setting'            => array( false, null, 'Auto-updates disabled' ),
			'forced on, not in setting' => array( false, true, 'Auto-updates enabled' ),
			'forced off, in setting'    => array( true, false, 'Auto-updates disabled' ),
		);
	}

	/**
	 * Tests that the auto-update setting is ignored when plugin auto-updates are disabled.
	 *
	 * @ticket 65819
	 */
	public function test_plugins_should_ignore_auto_update_setting_when_disabled() {
		$this->set_plugins( array( 'my-plugin/my-plugin.php' => array( 'My Plugin', '1.0', 'Jane' ) ) );
		update_site_option( 'auto_update_plugins', array( 'my-plugin/my-plugin.php' ) );

		$filter = new MockAction();
		add_filter( 'auto_update_plugin', array( $filter, 'filter' ) );

		$this->assertSame( 'Version 1.0 by Jane', $this->call( 'get_wp_plugins_raw_data' )['wp-plugins-inactive']['My Plugin']['value'], 'No auto-update status should be added.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The auto-update filter should not run.' );
	}

	/**
	 * Tests the update item passed to the auto-update filter.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_update_items
	 *
	 * @param string $transient_list The transient list holding the plugin, or an empty string for none.
	 * @param string $new_version The expected new version of the item.
	 * @param string $name        The expected plugin name in the item, or an empty string for none.
	 */
	public function test_plugins_should_pass_update_item_to_auto_update_filter( $transient_list, $new_version, $name ) {
		add_filter( 'plugins_auto_update_enabled', '__return_true', 11 );

		$this->set_plugins( array( 'my-plugin/my-plugin.php' => array( 'My Plugin', '1.0', 'Jane' ) ) );

		if ( '' !== $transient_list ) {
			$this->set_update( $transient_list, 'my-plugin/my-plugin.php', '2.0' );
		}

		$filter = new MockAction();
		add_filter( 'auto_update_plugin', array( $filter, 'filter' ), 10, 2 );

		$this->call( 'get_wp_plugins_raw_data' );

		$args = $filter->get_args();
		$item = $args[0][1];

		$this->assertNull( $args[0][0], 'The filter should receive null.' );
		$this->assertSame( 'my-plugin/my-plugin.php', $item->plugin, 'The item should be for the plugin.' );
		$this->assertSame( $new_version, $item->new_version, 'The item should hold the expected version.' );
		$this->assertSame( $name, $item->Name ?? '', 'The item should only include the plugin data when built from it.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_update_items() {
		return array(
			'available update' => array( 'response', '2.0', '' ),
			'no update'        => array( 'no_update', '2.0', '' ),
			'not checked yet'  => array( '', '', 'My Plugin' ),
		);
	}

	/**
	 * Tests that the auto-update status string can be filtered.
	 *
	 * @ticket 65819
	 */
	public function test_plugins_should_filter_auto_update_string() {
		add_filter( 'plugins_auto_update_enabled', '__return_true', 11 );

		$this->set_plugins( array( 'my-plugin/my-plugin.php' => array( 'My Plugin', '1.0', 'Jane' ) ) );
		update_site_option( 'auto_update_plugins', array( 'my-plugin/my-plugin.php' ) );

		$filter = new MockAction();
		add_filter( 'plugin_auto_update_debug_string', array( $filter, 'filter' ), 10, 4 );
		add_filter(
			'plugin_auto_update_debug_string',
			static function () {
				return 'Custom';
			},
			11
		);

		$field = $this->call( 'get_wp_plugins_raw_data' )['wp-plugins-inactive']['My Plugin'];
		$args  = $filter->get_args();

		$this->assertSame( 'Version 1.0 by Jane | Custom', $field['value'], 'The filtered string should be used.' );
		$this->assertSame( 'Auto-updates enabled', $args[0][0], 'The filter should receive the status string.' );
		$this->assertSame( 'my-plugin/my-plugin.php', $args[0][1], 'The filter should receive the plugin file.' );
		$this->assertSame( 'My Plugin', $args[0][2]['Name'], 'The filter should receive the plugin data.' );
		$this->assertTrue( $args[0][3], 'The filter should receive whether auto-updates are enabled.' );
	}

	/**
	 * Calls a private static method of WP_Debug_Data.
	 *
	 * @param string $method The method name.
	 * @return array The method's return value.
	 */
	private function call( $method ) {
		$reflection = new ReflectionMethod( 'WP_Debug_Data', $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invoke( null );
	}

	/**
	 * Creates a must-use plugin file.
	 *
	 * @param string $name     The file name.
	 * @param string $contents The file contents.
	 */
	private function create_mu_plugin( $name, $contents ) {
		$file = WPMU_PLUGIN_DIR . '/' . $name;

		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			$this->assertTrue( mkdir( WPMU_PLUGIN_DIR ), 'The must-use plugins directory should be created.' );
		}

		$this->assertFileDoesNotExist( $file, 'The must-use plugin should not exist yet.' );
		$this->assertNotFalse( file_put_contents( $file, $contents ), 'The must-use plugin should be written.' );

		$this->created_files[] = $file;
	}

	/**
	 * Primes the plugins cache with test plugins.
	 *
	 * @param array    $plugins Name, Version and Author of each plugin, keyed by plugin file.
	 * @param string[] $active  The active plugin files.
	 */
	private function set_plugins( $plugins, $active = array() ) {
		$data = array();
		foreach ( $plugins as $file => $headers ) {
			list( $name, $version, $author ) = $headers;

			$data[ $file ] = array(
				'Name'    => $name,
				'Version' => $version,
				'Author'  => $author,
			);
		}

		wp_cache_set( 'plugins', array( '' => $data ), 'plugins' );

		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', array_fill_keys( $active, time() ) );
		} else {
			update_option( 'active_plugins', $active );
		}
	}

	/**
	 * Adds a plugin to a list of the update transient.
	 *
	 * @param string $transient_list The transient list, 'response' or 'no_update'.
	 * @param string $file        The plugin file.
	 * @param string $new_version The new version.
	 */
	private function set_update( $transient_list, $file, $new_version ) {
		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = (object) array(
				'response'  => array(),
				'no_update' => array(),
			);
		}

		$transient->{$transient_list}[ $file ] = (object) array(
			'plugin'      => $file,
			'slug'        => dirname( $file ),
			'new_version' => $new_version,
		);

		set_site_transient( 'update_plugins', $transient );
	}

	/**
	 * Moves existing must-use plugins out of the way.
	 */
	private static function _back_up_mu_plugins() {
		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			rename( WPMU_PLUGIN_DIR, WP_CONTENT_DIR . '/mu-plugin-backup-debug-data' );
		}
	}

	/**
	 * Restores the backed-up must-use plugins.
	 */
	private static function _restore_mu_plugins() {
		$backup = WP_CONTENT_DIR . '/mu-plugin-backup-debug-data';

		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			rmdir( WPMU_PLUGIN_DIR );
		}

		if ( is_dir( $backup ) ) {
			rename( $backup, WPMU_PLUGIN_DIR );
		}
	}
}
