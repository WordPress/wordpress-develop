<?php

/**
 * Tests for the strings, Plugin_Upgrader::install() and Plugin_Upgrader::upgrade().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 */
class Tests_Admin_PluginUpgraderInstallUpgrade extends WP_UnitTestCase {

	/**
	 * The plugin file of the plugin that the updates of the tests are for.
	 *
	 * @var string
	 */
	const PLUGIN = 'my-plugin/my-plugin.php';

	/**
	 * The options that the upgrader ran with, one list for each run.
	 *
	 * @var array[]
	 */
	private $run_options = array();

	/**
	 * The hooks that were added while the upgrader ran, for each run.
	 *
	 * @var array[]
	 */
	private $hooks_during_run = array();

	/**
	 * The path of a copy of a package that the test made, if any.
	 *
	 * @var string|null
	 */
	private $package_copy;

	/**
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Resets the records of the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->run_options      = array();
		$this->hooks_during_run = array();
		$this->package_copy     = null;

		update_option( 'active_plugins', array() );
		delete_site_transient( 'update_plugins' );

		// Keep the update checks that run when an upgrade completes from asking WordPress.org.
		remove_all_actions( 'upgrader_process_complete' );
	}

	/**
	 * Removes the files that the tests made.
	 */
	public function tear_down() {
		if ( $this->package_copy && file_exists( $this->package_copy ) ) {
			unlink( $this->package_copy );
		}

		$dir = WP_PLUGIN_DIR . '/link-manager';

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}

		wp_clean_plugins_cache();

		parent::tear_down();
	}

	/**
	 * Returns an upgrader with a skin that keeps its messages.
	 *
	 * @param string $overwrite Optional. The overwrite mode of the skin. Default empty.
	 * @return Plugin_Upgrader The upgrader.
	 */
	private function get_upgrader( $overwrite = '' ) {
		$skin = new class() extends WP_Upgrader_Skin {
			/**
			 * What the skin was told, in order.
			 *
			 * @var array[]
			 */
			public $events = array();

			/**
			 * The overwrite mode.
			 *
			 * @var string
			 */
			public $overwrite = '';

			public function header() {}

			public function footer() {}

			public function before() {
				$this->events[] = array( 'before' );
			}

			public function after() {
				$this->events[] = array( 'after' );
			}

			public function set_result( $result ) {
				$this->events[] = array( 'set_result', $result );
				parent::set_result( $result );
			}

			public function error( $errors ) {
				$this->events[] = array( 'error', $errors );
			}

			public function feedback( $feedback, ...$args ) {
				$this->events[] = array( 'feedback', $feedback );
			}
		};

		$skin->overwrite = $overwrite;

		return new Plugin_Upgrader( $skin );
	}

	/**
	 * Records the options that the upgrader runs with, and the hooks it added, then stops the upgrade.
	 */
	private function stop_the_upgrade() {
		add_filter(
			'upgrader_package_options',
			function ( $options ) {
				$this->run_options[]      = $options;
				$this->hooks_during_run[] = array(
					'check_package'     => has_filter( 'upgrader_source_selection' ),
					'clean_cache'       => has_action( 'upgrader_process_complete', 'wp_clean_plugins_cache' ),
					'pre_install'       => count( $GLOBALS['wp_filter']['upgrader_pre_install']->callbacks[10] ?? array() ),
					'clear_destination' => has_filter( 'upgrader_clear_destination' ),
					'post_install'      => has_filter( 'upgrader_post_install' ),
				);

				return $options;
			}
		);
		add_filter(
			'upgrader_pre_download',
			static function () {
				return new WP_Error( 'stopped', 'The test stopped the upgrade.' );
			}
		);
	}

	/**
	 * Tests the strings for updating a plugin.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::upgrade_strings
	 */
	public function test_upgrade_strings_should_describe_the_update() {
		$upgrader = $this->get_upgrader();
		$upgrader->upgrade_strings();

		$this->assertSame( 'The plugin is at the latest version.', $upgrader->strings['up_to_date'] );
		$this->assertSame( 'Update package not available.', $upgrader->strings['no_package'] );
		$this->assertSame( 'Downloading update from <span class="code pre">%s</span>&#8230;', $upgrader->strings['downloading_package'] );
		$this->assertSame( 'Unpacking the update&#8230;', $upgrader->strings['unpack_package'] );
		$this->assertSame( 'Removing the old version of the plugin&#8230;', $upgrader->strings['remove_old'] );
		$this->assertSame( 'Could not remove the old plugin.', $upgrader->strings['remove_old_failed'] );
		$this->assertSame( 'Plugin update failed.', $upgrader->strings['process_failed'] );
		$this->assertSame( 'Plugin updated successfully.', $upgrader->strings['process_success'] );
		$this->assertSame( 'Plugins updated successfully.', $upgrader->strings['process_bulk_success'] );
	}

	/**
	 * Tests the strings for installing a plugin.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install_strings
	 */
	public function test_install_strings_should_describe_the_installation() {
		$upgrader = $this->get_upgrader();
		$upgrader->install_strings();

		$this->assertSame( 'Installation package not available.', $upgrader->strings['no_package'] );
		$this->assertSame( 'Downloading installation package from <span class="code pre">%s</span>&#8230;', $upgrader->strings['downloading_package'] );
		$this->assertSame( 'Unpacking the package&#8230;', $upgrader->strings['unpack_package'] );
		$this->assertSame( 'Installing the plugin&#8230;', $upgrader->strings['installing_package'] );
		$this->assertSame( 'Removing the current plugin&#8230;', $upgrader->strings['remove_old'] );
		$this->assertSame( 'Could not remove the current plugin.', $upgrader->strings['remove_old_failed'] );
		$this->assertSame( 'The plugin contains no files.', $upgrader->strings['no_files'] );
		$this->assertSame( 'Plugin installation failed.', $upgrader->strings['process_failed'] );
		$this->assertSame( 'Plugin installed successfully.', $upgrader->strings['process_success'] );
		$this->assertSame( 'Successfully installed the plugin <strong>%1$s %2$s</strong>.', $upgrader->strings['process_success_specific'] );
	}

	/**
	 * Tests the strings for installing a plugin over an installed one.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install_strings
	 *
	 * @dataProvider data_overwrite
	 *
	 * @param string $overwrite The overwrite mode of the skin.
	 * @param array  $expected  The strings that the mode changes.
	 */
	public function test_install_strings_should_describe_the_overwrite( $overwrite, array $expected ) {
		$upgrader = $this->get_upgrader( $overwrite );
		$upgrader->install_strings();

		foreach ( $expected as $key => $string ) {
			$this->assertSame( $string, $upgrader->strings[ $key ] );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_overwrite() {
		return array(
			'an update'    => array(
				'update-plugin',
				array(
					'installing_package' => 'Updating the plugin&#8230;',
					'process_failed'     => 'Plugin update failed.',
					'process_success'    => 'Plugin updated successfully.',
				),
			),
			'a downgrade'  => array(
				'downgrade-plugin',
				array(
					'installing_package' => 'Downgrading the plugin&#8230;',
					'process_failed'     => 'Plugin downgrade failed.',
					'process_success'    => 'Plugin downgraded successfully.',
				),
			),
			'another mode' => array(
				'something-else',
				array(
					'installing_package' => 'Installing the plugin&#8230;',
					'process_failed'     => 'Plugin installation failed.',
					'process_success'    => 'Plugin installed successfully.',
				),
			),
			'no overwrite' => array(
				'',
				array(
					'installing_package' => 'Installing the plugin&#8230;',
					'process_success'    => 'Plugin installed successfully.',
				),
			),
		);
	}

	/**
	 * Tests that the installation runs with the package and the plugins directory.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_install_should_run_with_the_package_and_the_plugins_directory() {
		$this->stop_the_upgrade();

		$upgrader = $this->get_upgrader();

		$this->assertNotTrue( $upgrader->install( 'https://example.org/my-plugin.zip' ), 'The installation should not succeed.' );
		$this->assertSame( 'stopped', $this->get_error_code( $upgrader ), 'The skin should get the error of the download.' );
		$this->assertCount( 1, $this->run_options );
		$this->assertSame( 'https://example.org/my-plugin.zip', $this->run_options[0]['package'] );
		$this->assertSame( WP_PLUGIN_DIR, $this->run_options[0]['destination'] );
		$this->assertFalse( $this->run_options[0]['clear_destination'], 'Files should not be overwritten by default.' );
		$this->assertTrue( $this->run_options[0]['clear_working'] );
		$this->assertSame(
			array(
				'type'   => 'plugin',
				'action' => 'install',
			),
			$this->run_options[0]['hook_extra']
		);
	}

	/**
	 * Tests that the files are overwritten when the package is.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_install_should_clear_the_destination_to_overwrite_the_package() {
		$this->stop_the_upgrade();

		$this->get_upgrader()->install( 'https://example.org/my-plugin.zip', array( 'overwrite_package' => true ) );

		$this->assertTrue( $this->run_options[0]['clear_destination'] );
	}

	/**
	 * Tests the hooks that are added only while the installation runs.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_install_should_add_its_hooks_only_while_it_runs() {
		$this->stop_the_upgrade();

		$this->get_upgrader()->install( 'https://example.org/my-plugin.zip' );

		$this->assertNotFalse( $this->hooks_during_run[0]['check_package'], 'The package should be checked while the installation runs.' );
		$this->assertSame( 9, $this->hooks_during_run[0]['clean_cache'], 'The cache should be cleaned when the installation completes.' );
		$this->assertFalse( has_filter( 'upgrader_source_selection' ), 'The package should not be checked afterwards.' );
		$this->assertFalse( has_action( 'upgrader_process_complete', 'wp_clean_plugins_cache' ), 'The cache should not be cleaned afterwards.' );
	}

	/**
	 * Tests that the cache is not cleaned when it is not asked for.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_install_should_not_clean_the_cache_when_it_is_not_asked_for() {
		$this->stop_the_upgrade();

		$this->get_upgrader()->install( 'https://example.org/my-plugin.zip', array( 'clear_update_cache' => false ) );

		$this->assertFalse( $this->hooks_during_run[0]['clean_cache'] );
	}

	/**
	 * Tests that a plugin is installed from a package.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_install_should_install_the_plugin_of_the_package() {
		$this->package_copy = $this->copy_package();

		add_filter(
			'upgrader_pre_download',
			function () {
				return $this->package_copy;
			}
		);

		set_site_transient( 'update_plugins', (object) array( 'response' => array( 'stale/stale.php' => (object) array() ) ) );

		$upgrader = $this->get_upgrader();

		$this->assertTrue( $upgrader->install( 'https://example.org/link-manager.zip' ), 'The installation should succeed.' );
		$this->assertFileExists( WP_PLUGIN_DIR . '/link-manager/link-manager.php', 'The plugin should be installed.' );
		$this->assertFalse( get_site_transient( 'update_plugins' ), 'The update information should be cleaned.' );
	}

	/**
	 * Tests that overwriting a package is announced.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_install_should_announce_that_it_overwrote_the_package() {
		$this->package_copy = $this->copy_package();

		add_filter(
			'upgrader_pre_download',
			function () {
				return $this->package_copy;
			}
		);

		$announced = array();
		add_action(
			'upgrader_overwrote_package',
			static function ( $package, $new_plugin_data, $type ) use ( &$announced ) {
				$announced[] = array( $package, $new_plugin_data, $type );
			},
			10,
			3
		);

		$this->assertTrue( $this->get_upgrader()->install( 'https://example.org/link-manager.zip' ), 'The first installation should succeed.' );
		$this->assertSame( array(), $announced, 'Nothing should be announced without overwriting.' );

		$this->package_copy = $this->copy_package();

		$this->assertTrue( $this->get_upgrader()->install( 'https://example.org/link-manager.zip', array( 'overwrite_package' => true ) ), 'The second installation should succeed.' );
		$this->assertCount( 1, $announced, 'The overwrite should be announced.' );
		$this->assertSame( 'https://example.org/link-manager.zip', $announced[0][0] );
		$this->assertSame( 'plugin', $announced[0][2] );
	}

	/**
	 * Tests that an update that is not available is reported.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::upgrade
	 */
	public function test_upgrade_should_report_a_plugin_that_has_no_update() {
		$upgrader = $this->get_upgrader();

		$this->assertFalse( $upgrader->upgrade( self::PLUGIN ) );
		$this->assertSame(
			array( array( 'before' ), array( 'set_result', false ), array( 'error', 'up_to_date' ), array( 'after' ) ),
			$upgrader->skin->events,
			'The skin should be told that the plugin is up to date.'
		);
		$this->assertSame( 'The plugin is at the latest version.', $upgrader->strings['up_to_date'] );
	}

	/**
	 * Tests that an update that is for another plugin is not used.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::upgrade
	 */
	public function test_upgrade_should_report_a_plugin_when_the_update_is_for_another_plugin() {
		set_site_transient( 'update_plugins', (object) array( 'response' => array( 'other/other.php' => (object) array( 'package' => 'https://example.org/other.zip' ) ) ) );

		$this->assertFalse( $this->get_upgrader()->upgrade( self::PLUGIN ) );
	}

	/**
	 * Tests that the update runs with the package of the update.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::upgrade
	 */
	public function test_upgrade_should_run_with_the_package_of_the_update() {
		set_site_transient( 'update_plugins', (object) array( 'response' => array( self::PLUGIN => (object) array( 'package' => 'https://example.org/my-plugin.zip' ) ) ) );
		$this->stop_the_upgrade();

		$upgrader = $this->get_upgrader();

		$this->assertNotTrue( $upgrader->upgrade( self::PLUGIN ), 'The update should not succeed.' );
		$this->assertSame( 'stopped', $this->get_error_code( $upgrader ), 'The skin should get the error of the download.' );
		$this->assertCount( 1, $this->run_options );
		$this->assertSame( 'https://example.org/my-plugin.zip', $this->run_options[0]['package'] );
		$this->assertSame( WP_PLUGIN_DIR, $this->run_options[0]['destination'] );
		$this->assertTrue( $this->run_options[0]['clear_destination'], 'The old plugin should be removed.' );
		$this->assertTrue( $this->run_options[0]['clear_working'] );
		$this->assertSame(
			array(
				'plugin'      => self::PLUGIN,
				'type'        => 'plugin',
				'action'      => 'update',
				'temp_backup' => array(
					'slug' => 'my-plugin',
					'src'  => WP_PLUGIN_DIR,
					'dir'  => 'plugins',
				),
			),
			$this->run_options[0]['hook_extra']
		);
	}

	/**
	 * Tests the hooks that are added only while the update runs.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::upgrade
	 */
	public function test_upgrade_should_add_its_hooks_only_while_it_runs() {
		set_site_transient( 'update_plugins', (object) array( 'response' => array( self::PLUGIN => (object) array( 'package' => 'https://example.org/my-plugin.zip' ) ) ) );
		$this->stop_the_upgrade();

		$this->get_upgrader()->upgrade( self::PLUGIN );

		$this->assertSame( 9, $this->hooks_during_run[0]['clean_cache'] );
		$this->assertSame( 2, $this->hooks_during_run[0]['pre_install'], 'The plugin should be deactivated and the maintenance mode should start.' );
		$this->assertNotFalse( $this->hooks_during_run[0]['clear_destination'] );
		$this->assertNotFalse( $this->hooks_during_run[0]['post_install'] );
		$this->assertFalse( has_filter( 'upgrader_pre_install' ), 'The plugin should not be changed before later installations.' );
		$this->assertFalse( has_filter( 'upgrader_clear_destination' ), 'The old plugin should not be deleted by later installations.' );
		$this->assertFalse( has_filter( 'upgrader_post_install' ), 'The plugin should not be changed after later installations.' );
		$this->assertFalse( has_action( 'upgrader_process_complete', 'wp_clean_plugins_cache' ) );
	}

	/**
	 * Tests that the cache is not cleaned when it is not asked for.
	 *
	 * @ticket 66559
	 *
	 * @covers Plugin_Upgrader::upgrade
	 */
	public function test_upgrade_should_not_clean_the_cache_when_it_is_not_asked_for() {
		set_site_transient( 'update_plugins', (object) array( 'response' => array( self::PLUGIN => (object) array( 'package' => 'https://example.org/my-plugin.zip' ) ) ) );
		$this->stop_the_upgrade();

		$this->get_upgrader()->upgrade( self::PLUGIN, array( 'clear_update_cache' => false ) );

		$this->assertFalse( $this->hooks_during_run[0]['clean_cache'] );
	}

	/**
	 * Returns the code of the error that the skin of an upgrader got.
	 *
	 * @param Plugin_Upgrader $upgrader The upgrader.
	 * @return string|null The code of the error, or null when the skin got none.
	 */
	private function get_error_code( $upgrader ) {
		foreach ( $upgrader->skin->events as $event ) {
			if ( 'error' === $event[0] && is_wp_error( $event[1] ) ) {
				return $event[1]->get_error_code();
			}
		}

		return null;
	}

	/**
	 * Makes a copy of the package of a plugin that can be installed and deleted.
	 *
	 * @return string The path of the copy.
	 */
	private function copy_package() {
		$copy = wp_tempnam( 'link-manager.zip' );

		$this->assertTrue( copy( DIR_TESTDATA . '/plugins/link-manager.zip', $copy ), 'The package should be copied.' );

		return $copy;
	}
}
