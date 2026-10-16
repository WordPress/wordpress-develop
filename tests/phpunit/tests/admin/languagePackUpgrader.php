<?php

/**
 * @group admin
 * @group upgrade
 *
 * @covers Language_Pack_Upgrader
 */
class Tests_Admin_LanguagePackUpgrader extends WP_UnitTestCase {
	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	public function tear_down() {
		delete_site_option( 'deferred_translation_updates' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that deferred translation updates are not passed to the bulk upgrader.
	 *
	 * @ticket 42281
	 *
	 * @covers Language_Pack_Upgrader::async_upgrade
	 */
	public function test_async_upgrade_should_skip_deferred_translation_updates() {
		$deferred_update = (object) array(
			'type'       => 'plugin',
			'slug'       => 'deferred-plugin',
			'language'   => 'de_DE',
			'version'    => '1.0.0',
			'autoupdate' => true,
			'package'    => 'https://example.org/deferred-plugin-de_DE.zip',
		);

		set_site_transient(
			'update_plugins',
			(object) array(
				'translations' => array( $deferred_update ),
			)
		);

		wp_set_deferred_translation_updates( array( $deferred_update ) );

		$async_update_results = array();
		$bulk_upgrade_started = false;

		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );
		add_filter(
			'async_update_translation',
			static function ( $update ) use ( &$async_update_results ) {
				$async_update_results[] = $update;
				return $update;
			}
		);
		add_filter(
			'request_filesystem_credentials',
			static function () use ( &$bulk_upgrade_started ) {
				$bulk_upgrade_started = true;
				return false;
			}
		);

		Language_Pack_Upgrader::async_upgrade();

		$this->assertSame( array( false ), $async_update_results );
		$this->assertFalse( $bulk_upgrade_started );
	}

	/**
	 * Tests that only successfully installed translations lose their deferrals.
	 *
	 * @ticket 42281
	 * @dataProvider data_bulk_upgrade_results
	 * @covers Language_Pack_Upgrader::bulk_upgrade
	 *
	 * @param string[] $outcomes  Results returned by successive package attempts.
	 * @param string[] $remaining Keys of translations that should remain deferred.
	 */
	public function test_bulk_upgrade_should_clear_only_successful_deferrals( $outcomes, $remaining ) {
		$updates = $this->get_translation_updates();
		wp_set_deferred_translation_updates( $updates );

		$this->run_bulk_upgrade(
			array( $updates['core'], $updates['plugin'], $updates['theme'] ),
			$outcomes
		);

		$expected = array_intersect_key( $updates, array_flip( $remaining ) );
		$this->assertSame(
			array_keys( wp_get_translation_updates_by_id( $expected ) ),
			array_keys( wp_get_deferred_translation_updates() )
		);
	}

	/**
	 * Provides successful, failed, and interrupted package attempts.
	 *
	 * @return array[]
	 */
	public function data_bulk_upgrade_results() {
		return array(
			'all package types succeed'             => array(
				array( 'success', 'success', 'success' ),
				array( 'unrelated', 'other_version' ),
			),
			'errors preserve deferrals'             => array(
				array( 'error', 'error', 'error' ),
				array( 'core', 'plugin', 'theme', 'unrelated', 'other_version' ),
			),
			'error does not stop later success'     => array(
				array( 'success', 'error', 'success' ),
				array( 'plugin', 'unrelated', 'other_version' ),
			),
			'false preserves unattempted deferrals' => array(
				array( 'false' ),
				array( 'core', 'plugin', 'theme', 'unrelated', 'other_version' ),
			),
			'false after success preserves remaining deferrals' => array(
				array( 'success', 'false' ),
				array( 'plugin', 'theme', 'unrelated', 'other_version' ),
			),
		);
	}

	/**
	 * Tests that cleanup uses deferrals saved during the final package attempt.
	 *
	 * @ticket 42281
	 * @covers Language_Pack_Upgrader::bulk_upgrade
	 */
	public function test_bulk_upgrade_should_read_latest_deferrals_after_run() {
		$updates = $this->get_translation_updates();
		wp_set_deferred_translation_updates( array( $updates['unrelated'] ) );

		$this->run_bulk_upgrade(
			array( $updates['core'], $updates['plugin'] ),
			array( 'success', 'false' ),
			static function ( $attempt ) use ( $updates ) {
				if ( 1 === $attempt ) {
					// Add deferrals and remove an old one after the first success.
					wp_set_deferred_translation_updates( array( $updates['core'], $updates['plugin'], $updates['theme'] ) );
				}
			}
		);

		$this->assertSame(
			array_keys( wp_get_translation_updates_by_id( array( $updates['plugin'], $updates['theme'] ) ) ),
			array_keys( wp_get_deferred_translation_updates() )
		);
	}

	/**
	 * Tests that an identical offer is no longer deferred after installation.
	 *
	 * @ticket 42281
	 * @covers Language_Pack_Upgrader::bulk_upgrade
	 */
	public function test_bulk_upgrade_should_clear_deferral_for_same_version_reoffer() {
		$updates = $this->get_translation_updates();
		$update  = $updates['plugin'];
		wp_set_deferred_translation_updates( array( $update ) );

		$this->run_bulk_upgrade( array( $update ), array( 'success' ) );

		$this->assertFalse( get_site_option( 'deferred_translation_updates' ), 'The empty deferral option should be deleted.' );

		$reoffer          = clone $update;
		$reoffer->package = 'https://example.org/reoffered-package.zip';
		set_site_transient( 'update_plugins', (object) array( 'translations' => array( $reoffer ) ) );

		$available = wp_get_translation_updates_by_id();
		$id        = wp_get_translation_update_id( $update );
		$this->assertArrayHasKey( $id, $available );
		$this->assertFalse( wp_is_translation_update_deferred( $available[ $id ] ) );
	}

	/**
	 * Tests that successful packages without matching deferrals cause no writes.
	 *
	 * @ticket 42281
	 * @covers Language_Pack_Upgrader::bulk_upgrade
	 */
	public function test_bulk_upgrade_should_not_write_unmatched_deferrals() {
		$updates = $this->get_translation_updates();
		wp_set_deferred_translation_updates( array( $updates['unrelated'] ) );
		$before = get_site_option( 'deferred_translation_updates' );
		$writes = 0;
		$filter = static function ( $value ) use ( &$writes ) {
			++$writes;
			return $value;
		};
		$action = static function () use ( &$writes ) {
			++$writes;
		};

		add_filter( 'pre_update_site_option_deferred_translation_updates', $filter );
		add_action( 'pre_delete_site_option_deferred_translation_updates', $action );
		try {
			$this->run_bulk_upgrade( array( $updates['core'] ), array( 'success' ) );
		} finally {
			remove_filter( 'pre_update_site_option_deferred_translation_updates', $filter );
			remove_action( 'pre_delete_site_option_deferred_translation_updates', $action );
		}

		$this->assertSame( 0, $writes );
		$this->assertSame( $before, get_site_option( 'deferred_translation_updates' ) );
	}

	/**
	 * Creates package fixtures, including an unrelated offer and another version.
	 *
	 * @return object[] Translation updates keyed by fixture name.
	 */
	private function get_translation_updates() {
		$updates = array();
		foreach ( array( 'core', 'plugin', 'theme', 'unrelated' ) as $key ) {
			$updates[ $key ] = (object) array(
				'type'     => 'unrelated' === $key ? 'plugin' : $key,
				'slug'     => 'core' === $key ? 'default' : $key,
				'language' => 'de_DE',
				'version'  => '1.0.0',
				'package'  => "https://example.org/$key.zip",
			);
		}
		$updates['other_version']          = clone $updates['plugin'];
		$updates['other_version']->version = '2.0.0';

		return $updates;
	}

	/**
	 * Runs the real bulk loop with mocked installation and filesystem access.
	 *
	 * @param object[]      $updates  Packages to attempt.
	 * @param string[]      $outcomes Expected attempt outcomes: success, error, or false.
	 * @param callable|null $on_run   Optional callback receiving the zero-based attempt index.
	 */
	private function run_bulk_upgrade( $updates, $outcomes, $on_run = null ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';

		$had_filesystem = array_key_exists( 'wp_filesystem', $GLOBALS );
		$filesystem     = $GLOBALS['wp_filesystem'] ?? null;
		$mock           = $this->getMockBuilder( 'WP_Filesystem_Base' )->getMock();
		$mock->method( 'find_folder' )->willReturn( WP_LANG_DIR );
		$mock->method( 'exists' )->willReturn( true );
		$mock->expects( $this->never() )->method( 'mkdir' );

		$skin     = $this->getMockBuilder( 'Language_Pack_Upgrader_Skin' )->getMock();
		$upgrader = $this->getMockBuilder( 'Language_Pack_Upgrader' )
			->setConstructorArgs( array( $skin ) )
			->onlyMethods( array( 'fs_connect', 'run' ) )
			->getMock();
		$upgrader->expects( $this->once() )->method( 'fs_connect' )->willReturn( true );
		$attempt = 0;
		$upgrader->expects( $this->exactly( count( $outcomes ) ) )
			->method( 'run' )
			->willReturnCallback(
				function ( $options ) use ( $upgrader, $updates, $outcomes, $on_run, &$attempt ) {
					$this->assertSame( $updates[ $attempt ], $options['hook_extra']['language_update'] );
					if ( null !== $on_run ) {
						$on_run( $attempt );
					}
					$outcome = $outcomes[ $attempt++ ];
					$result  = array( 'destination' => $options['destination'] );
					if ( 'error' === $outcome ) {
						$result = new WP_Error( 'installation_failed', 'Installation failed.' );
					} elseif ( 'false' === $outcome ) {
						$result = false;
					}
					$upgrader->result = $result;
					return $result;
				}
			);

		$GLOBALS['wp_filesystem'] = $mock;
		try {
			$upgrader->bulk_upgrade( $updates, array( 'clear_update_cache' => false ) );
		} finally {
			if ( $had_filesystem ) {
				$GLOBALS['wp_filesystem'] = $filesystem;
			} else {
				unset( $GLOBALS['wp_filesystem'] );
			}
		}
	}
}
