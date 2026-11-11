<?php

/**
 * Tests for Plugin_Upgrader::bulk_upgrade().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Upgrader::bulk_upgrade
 */
class Tests_Admin_PluginUpgraderBulkUpgrade extends WP_UnitTestCase {

	/**
	 * A plugin of the test data, which is installed and inactive.
	 *
	 * @var string
	 */
	const PLUGIN = 'hello.php';

	/**
	 * The options that the upgrader ran with, one list for each run.
	 *
	 * @var array[]
	 */
	private $run_options = array();

	/**
	 * Whether the hooks of the upgrader were added when it ran.
	 *
	 * @var array[]
	 */
	private $hooks_during_run = array();

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

		update_option( 'active_plugins', array() );
		update_option( 'auto_plugin_theme_update_emails', array() );
		delete_site_transient( 'update_plugins' );

		// Keep the update checks that run when an upgrade completes from asking WordPress.org.
		remove_all_actions( 'upgrader_process_complete' );
	}

	/**
	 * Makes sure that the maintenance mode is off.
	 */
	public function tear_down() {
		$file = ABSPATH . '.maintenance';

		if ( file_exists( $file ) ) {
			unlink( $file );
		}

		parent::tear_down();
	}

	/**
	 * Returns an upgrader with a skin that keeps what it is told.
	 *
	 * @param bool $filesystem Optional. Whether the skin gives access to the filesystem. Default true.
	 * @return Plugin_Upgrader The upgrader.
	 */
	private function get_upgrader( $filesystem = true ) {
		$skin = new class( array(), $filesystem ) extends WP_Upgrader_Skin {
			/**
			 * What the skin was told, in order.
			 *
			 * @var array[]
			 */
			public $events = array();

			/**
			 * The data of the plugin that is upgraded.
			 *
			 * @var array
			 */
			public $plugin_info = array();

			/**
			 * Whether the plugin that is upgraded is active.
			 *
			 * @var bool
			 */
			public $plugin_active = false;

			/**
			 * Whether the skin gives access to the filesystem.
			 *
			 * @var bool
			 */
			private $filesystem;

			/**
			 * The number of the requests for the credentials of the filesystem.
			 *
			 * @var int
			 */
			public $credential_requests = 0;

			/**
			 * Whether the requests after the first one fail.
			 *
			 * @var bool
			 */
			public $fail_after_first_request = false;

			public function __construct( $args = array(), $filesystem = true ) {
				$this->filesystem = $filesystem;

				parent::__construct( $args );
			}

			public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) {
				++$this->credential_requests;

				if ( $this->fail_after_first_request && $this->credential_requests > 1 ) {
					return false;
				}

				return $this->filesystem ? true : false;
			}

			public function header() {
				$this->events[] = array( 'header' );
			}

			public function footer() {
				$this->events[] = array( 'footer' );
			}

			public function bulk_header() {
				$this->events[] = array( 'bulk_header' );
			}

			public function bulk_footer() {
				$this->events[] = array( 'bulk_footer' );
			}

			public function before( $title = '' ) {
				$this->events[] = array( 'before' );
			}

			public function after( $title = '' ) {
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

		return new Plugin_Upgrader( $skin );
	}

	/**
	 * Returns what the skin of an upgrader was told, in order.
	 *
	 * The messages about the maintenance mode are left out, because they are only given on a network.
	 *
	 * @param Plugin_Upgrader $upgrader The upgrader.
	 * @return array[] The events.
	 */
	private function get_skin_events( $upgrader ) {
		return array_values(
			array_filter(
				$upgrader->skin->events,
				static function ( $event ) {
					return ! ( 'feedback' === $event[0] && in_array( $event[1], array( 'maintenance_start', 'maintenance_end' ), true ) );
				}
			)
		);
	}

	/**
	 * Returns the names of what the skin of an upgrader was told, in order.
	 *
	 * @param Plugin_Upgrader $upgrader The upgrader.
	 * @return string[] The names.
	 */
	private function get_events( $upgrader ) {
		return wp_list_pluck( $this->get_skin_events( $upgrader ), 0 );
	}

	/**
	 * Sets the plugins that have an update.
	 *
	 * @param array $updates The updates, by plugin file.
	 */
	private function set_updates( array $updates ) {
		$response = array();

		foreach ( $updates as $plugin => $data ) {
			$response[ $plugin ] = (object) array_merge( array( 'package' => 'https://downloads.example.org/package.zip' ), $data );
		}

		set_site_transient( 'update_plugins', (object) array( 'response' => $response ) );
	}

	/**
	 * Records the options that the upgrader runs with, then stops the update.
	 */
	private function stop_the_upgrade() {
		add_filter(
			'upgrader_package_options',
			function ( $options ) {
				$this->run_options[]      = $options;
				$this->hooks_during_run[] = array(
					'check_package'     => has_filter( 'upgrader_source_selection' ),
					'clear_destination' => has_filter( 'upgrader_clear_destination' ),
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
	 * Tests the order in which the skin is told about the update of no plugins.
	 *
	 * @ticket 66563
	 */
	public function test_should_tell_the_skin_about_the_start_and_the_end_of_the_update() {
		$upgrader = $this->get_upgrader();

		$this->assertSame( array(), $upgrader->bulk_upgrade( array() ) );
		$this->assertSame( array( 'header', 'bulk_header', 'bulk_footer', 'footer' ), $this->get_events( $upgrader ) );
		$this->assertTrue( $upgrader->bulk, 'The upgrader should be a bulk upgrader.' );
	}

	/**
	 * Tests that the update does not start without access to the filesystem.
	 *
	 * @ticket 66563
	 */
	public function test_should_return_false_without_access_to_the_filesystem() {
		$upgrader = $this->get_upgrader( false );

		$this->assertFalse( $upgrader->bulk_upgrade( array( self::PLUGIN ) ) );
		$this->assertSame( array( 'header', 'footer' ), $this->get_events( $upgrader ), 'The skin should get the header and the footer.' );
	}

	/**
	 * Tests a plugin that has no update.
	 *
	 * @ticket 66563
	 */
	public function test_should_report_a_plugin_that_has_no_update_as_up_to_date() {
		$upgrader = $this->get_upgrader();

		$this->assertSame( array( self::PLUGIN => true ), $upgrader->bulk_upgrade( array( self::PLUGIN ) ) );
		$this->assertSame(
			array( 'header', 'bulk_header', 'set_result', 'before', 'feedback', 'after', 'bulk_footer', 'footer' ),
			$this->get_events( $upgrader )
		);
		$this->assertSame( array( 'set_result', 'up_to_date' ), $this->get_skin_events( $upgrader )[2] );
		$this->assertSame( array( 'feedback', 'up_to_date' ), $this->get_skin_events( $upgrader )[4] );
		$this->assertSame( 'Hello Dolly', $upgrader->skin->plugin_info['Name'], 'The skin should get the data of the plugin.' );
	}

	/**
	 * Tests the counts of the plugins.
	 *
	 * @ticket 66563
	 */
	public function test_should_count_the_plugins() {
		$upgrader = $this->get_upgrader();

		$upgrader->bulk_upgrade( array( self::PLUGIN, 'internationalized-plugin.php' ) );

		$this->assertSame( 2, $upgrader->update_count );
		$this->assertSame( 2, $upgrader->update_current );
	}

	/**
	 * Tests that a plugin that needs a newer version of WordPress is not updated.
	 *
	 * @ticket 66563
	 */
	public function test_should_not_update_a_plugin_that_needs_a_newer_version_of_wordpress() {
		$this->set_updates( array( self::PLUGIN => array( 'requires' => '99.0' ) ) );
		$this->stop_the_upgrade();

		$upgrader = $this->get_upgrader();
		$results  = $upgrader->bulk_upgrade( array( self::PLUGIN ) );

		$this->assertWPError( $results[ self::PLUGIN ] );
		$this->assertSame( 'incompatible_wp_required_version', $results[ self::PLUGIN ]->get_error_code() );
		$this->assertStringContainsString( 'the new plugin version requires 99.0', $results[ self::PLUGIN ]->get_error_message() );
		$this->assertSame( array(), $this->run_options, 'The update should not run.' );
		$this->assertSame( array( 'header', 'bulk_header', 'before', 'error', 'after', 'bulk_footer', 'footer' ), $this->get_events( $upgrader ) );
		$this->assertSame( $results[ self::PLUGIN ], $this->get_skin_events( $upgrader )[3][1], 'The skin should get the error.' );
	}

	/**
	 * Tests that a plugin that needs a newer version of PHP is not updated.
	 *
	 * @ticket 66563
	 */
	public function test_should_not_update_a_plugin_that_needs_a_newer_version_of_php() {
		$this->set_updates( array( self::PLUGIN => array( 'requires_php' => '99.0' ) ) );
		$this->stop_the_upgrade();

		$upgrader = $this->get_upgrader();
		$results  = $upgrader->bulk_upgrade( array( self::PLUGIN ) );

		$this->assertWPError( $results[ self::PLUGIN ] );
		$this->assertSame( 'incompatible_php_required_version', $results[ self::PLUGIN ]->get_error_code() );
		$this->assertStringContainsString( 'the new plugin version requires 99.0', $results[ self::PLUGIN ]->get_error_message() );
		$this->assertSame( array(), $this->run_options, 'The update should not run.' );
	}

	/**
	 * Tests that the update runs with the package of the update, and the options of a bulk update.
	 *
	 * @ticket 66563
	 */
	public function test_should_run_with_the_package_and_the_options_of_a_bulk_update() {
		$this->set_updates( array( 'custom-internationalized-plugin/custom-internationalized-plugin.php' => array( 'package' => 'https://downloads.example.org/custom.zip' ) ) );
		$this->stop_the_upgrade();

		$upgrader = $this->get_upgrader();
		$results  = $upgrader->bulk_upgrade( array( 'custom-internationalized-plugin/custom-internationalized-plugin.php' ) );

		$this->assertCount( 1, $this->run_options );
		$this->assertSame( 'https://downloads.example.org/custom.zip', $this->run_options[0]['package'] );
		$this->assertSame( WP_PLUGIN_DIR, $this->run_options[0]['destination'] );
		$this->assertTrue( $this->run_options[0]['clear_destination'], 'The old plugin should be removed.' );
		$this->assertTrue( $this->run_options[0]['clear_working'] );
		$this->assertTrue( $this->run_options[0]['is_multi'], 'The update should be one of several.' );
		$this->assertSame(
			array(
				'plugin'      => 'custom-internationalized-plugin/custom-internationalized-plugin.php',
				'temp_backup' => array(
					'slug' => 'custom-internationalized-plugin',
					'src'  => WP_PLUGIN_DIR,
					'dir'  => 'plugins',
				),
			),
			$this->run_options[0]['hook_extra']
		);
		$this->assertWPError( $results['custom-internationalized-plugin/custom-internationalized-plugin.php'], 'The error of the download should be the result.' );
		$this->assertSame( 'stopped', $results['custom-internationalized-plugin/custom-internationalized-plugin.php']->get_error_code() );
	}

	/**
	 * Tests the hooks that are added while the plugins are updated only.
	 *
	 * @ticket 66563
	 */
	public function test_should_add_its_hooks_only_while_it_runs() {
		$this->set_updates( array( self::PLUGIN => array() ) );
		$this->stop_the_upgrade();

		$upgrader = $this->get_upgrader();
		$upgrader->bulk_upgrade( array( self::PLUGIN ) );

		$this->assertNotFalse( $this->hooks_during_run[0]['check_package'], 'The package should be checked while the plugin is updated.' );
		$this->assertNotFalse( $this->hooks_during_run[0]['clear_destination'], 'The old plugin should be deleted while the plugin is updated.' );
		$this->assertFalse( has_filter( 'upgrader_source_selection' ), 'The package should not be checked afterwards.' );
		$this->assertFalse( has_filter( 'upgrader_clear_destination' ), 'The old plugins should not be deleted afterwards.' );
	}

	/**
	 * Tests that the update stops when the filesystem is no longer available for a plugin.
	 *
	 * @ticket 66563
	 */
	public function test_should_stop_when_the_update_of_a_plugin_returns_false() {
		$this->set_updates(
			array(
				self::PLUGIN                   => array(),
				'internationalized-plugin.php' => array(),
			)
		);
		$this->stop_the_upgrade();

		$upgrader                                 = $this->get_upgrader();
		$upgrader->skin->fail_after_first_request = true;

		$results = $upgrader->bulk_upgrade( array( self::PLUGIN, 'internationalized-plugin.php' ) );

		$this->assertSame( array( self::PLUGIN => false ), $results, 'The second plugin should not be updated.' );
	}

	/**
	 * Tests that the strings of the update are set.
	 *
	 * @ticket 66563
	 */
	public function test_should_set_the_strings_of_the_update() {
		$upgrader = $this->get_upgrader();

		$upgrader->bulk_upgrade( array() );

		$this->assertSame( 'The plugin is at the latest version.', $upgrader->strings['up_to_date'] );
		$this->assertSame( 'Plugins updated successfully.', $upgrader->strings['process_bulk_success'] );
	}

	/**
	 * Tests that the update of the plugins continues after a plugin failed.
	 *
	 * @ticket 66563
	 */
	public function test_should_continue_with_the_next_plugin_after_a_plugin_failed() {
		$this->set_updates(
			array(
				self::PLUGIN                   => array( 'requires' => '99.0' ),
				'internationalized-plugin.php' => array( 'requires_php' => '99.0' ),
			)
		);

		$upgrader = $this->get_upgrader();
		$results  = $upgrader->bulk_upgrade( array( self::PLUGIN, 'internationalized-plugin.php' ) );

		$this->assertSame( array( self::PLUGIN, 'internationalized-plugin.php' ), array_keys( $results ) );
		$this->assertSame( 'incompatible_wp_required_version', $results[ self::PLUGIN ]->get_error_code() );
		$this->assertSame( 'incompatible_php_required_version', $results['internationalized-plugin.php']->get_error_code() );
	}

	/**
	 * Tests the action that fires when the update is complete.
	 *
	 * @ticket 66563
	 */
	public function test_should_fire_the_upgrader_process_complete_action() {
		$received = array();
		add_action(
			'upgrader_process_complete',
			static function ( $upgrader, $options ) use ( &$received ) {
				$received = array( $upgrader, $options );
			},
			10,
			2
		);

		$upgrader = $this->get_upgrader();
		$upgrader->bulk_upgrade( array( self::PLUGIN ) );

		$this->assertSame( $upgrader, $received[0], 'The action should receive the upgrader.' );
		$this->assertSame(
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'bulk'    => true,
				'plugins' => array( self::PLUGIN ),
			),
			$received[1]
		);
	}

	/**
	 * Tests that the update information is cleaned.
	 *
	 * @ticket 66563
	 */
	public function test_should_clean_the_update_information() {
		set_site_transient( 'update_plugins', (object) array( 'response' => array() ) );

		$this->get_upgrader()->bulk_upgrade( array( self::PLUGIN ) );

		$this->assertFalse( get_site_transient( 'update_plugins' ) );
	}

	/**
	 * Tests that the update information is kept when it is not asked to clean it.
	 *
	 * @ticket 66563
	 */
	public function test_should_keep_the_update_information_when_it_is_not_asked_to_clean_it() {
		set_site_transient( 'update_plugins', (object) array( 'response' => array() ) );

		$this->get_upgrader()->bulk_upgrade( array( self::PLUGIN ), array( 'clear_update_cache' => false ) );

		$this->assertNotFalse( get_site_transient( 'update_plugins' ) );
	}

	/**
	 * Tests that the failure emails of the plugins that are up to date are forgotten, and the others are kept.
	 *
	 * @ticket 66563
	 */
	public function test_should_forget_the_failure_emails_of_the_plugins_that_were_updated_only() {
		update_option(
			'auto_plugin_theme_update_emails',
			array(
				self::PLUGIN                   => array( 'sent' => 1 ),
				'internationalized-plugin.php' => array( 'sent' => 2 ),
				'other/other.php'              => array( 'sent' => 3 ),
			)
		);
		$this->set_updates( array( 'internationalized-plugin.php' => array( 'requires' => '99.0' ) ) );

		$this->get_upgrader()->bulk_upgrade( array( self::PLUGIN, 'internationalized-plugin.php' ) );

		$this->assertSame(
			array(
				'internationalized-plugin.php' => array( 'sent' => 2 ),
				'other/other.php'              => array( 'sent' => 3 ),
			),
			get_option( 'auto_plugin_theme_update_emails' ),
			'Only the plugin that is up to date should be forgotten.'
		);
	}

	/**
	 * Tests that the skin knows when the plugin that is updated is active.
	 *
	 * @ticket 66563
	 */
	public function test_should_tell_the_skin_that_the_plugin_is_active() {
		update_option( 'active_plugins', array( self::PLUGIN ) );
		$this->set_updates( array( self::PLUGIN => array( 'requires' => '99.0' ) ) );

		$upgrader = $this->get_upgrader();
		$upgrader->bulk_upgrade( array( self::PLUGIN ) );

		$this->assertTrue( $upgrader->skin->plugin_active );
		$this->assertFileDoesNotExist( ABSPATH . '.maintenance', 'The maintenance mode should be off afterwards.' );
	}
}
