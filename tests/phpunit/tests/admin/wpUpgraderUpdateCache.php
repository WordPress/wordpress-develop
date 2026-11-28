<?php
/**
 * Tests that plugin and theme installs/updates keep the update transients
 * rebuilt during 'upgrader_process_complete'.
 *
 * @group admin
 * @group upgrade
 */
class Tests_Admin_WpUpgraderUpdateCache extends WP_UnitTestCase {

	/**
	 * Slug of the test theme built for these tests.
	 *
	 * @var string
	 */
	const THEME_SLUG = 'wp-upgrader-update-cache-theme';

	/**
	 * Path to the test theme package.
	 *
	 * @var string
	 */
	private static $theme_package;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	public function set_up() {
		parent::set_up();

		// Serve the test packages instead of downloading them.
		copy( DIR_TESTDATA . '/plugins/link-manager.zip', DIR_TESTDATA . '/link-manager.zip' );
		add_filter( 'upgrader_pre_download', array( $this, 'filter_upgrader_pre_download' ), 10, 3 );

		/*
		 * Replace the real update checks with stand-ins that rebuild the transients
		 * at the same priority, without making HTTP requests.
		 */
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );
		remove_action( 'upgrader_process_complete', 'wp_version_check' );
		remove_action( 'upgrader_process_complete', 'wp_update_plugins' );
		remove_action( 'upgrader_process_complete', 'wp_update_themes' );
		add_action( 'upgrader_process_complete', array( $this, 'rebuild_update_transients' ), 10, 0 );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down() {
		if ( file_exists( DIR_TESTDATA . '/link-manager.zip' ) ) {
			unlink( DIR_TESTDATA . '/link-manager.zip' );
		}

		if ( file_exists( WP_PLUGIN_DIR . '/link-manager/link-manager.php' ) ) {
			$this->rmdir( WP_PLUGIN_DIR . '/link-manager' );
			rmdir( WP_PLUGIN_DIR . '/link-manager' );
		}

		// Plugin_Upgrader::upgrade() and Theme_Upgrader::upgrade() keep temporary backups.
		foreach ( array( 'plugins/link-manager', 'themes/' . self::THEME_SLUG ) as $backup ) {
			$backup_dir = WP_CONTENT_DIR . '/upgrade-temp-backup/' . $backup;
			if ( is_dir( $backup_dir ) ) {
				$this->rmdir( $backup_dir );
				rmdir( $backup_dir );
			}
		}

		$theme_dir = get_theme_root() . '/' . self::THEME_SLUG;
		if ( is_dir( $theme_dir ) ) {
			$this->rmdir( $theme_dir );
			rmdir( $theme_dir );
		}

		if ( self::$theme_package && file_exists( self::$theme_package ) ) {
			unlink( self::$theme_package );
		}

		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		wp_clean_plugins_cache();
		wp_clean_themes_cache();

		parent::tear_down();
	}

	/**
	 * Tests that installing a plugin keeps the `update_plugins` transient rebuilt on `upgrader_process_complete`.
	 *
	 * @ticket 65543
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_plugin_install_should_keep_rebuilt_update_plugins_transient() {
		set_site_transient( 'update_plugins', $this->get_stale_transient() );

		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( 'https://downloads.wordpress.org/plugin/link-manager.zip' );

		$this->assertTrue( $result, 'The plugin should install.' );
		$this->assertSame( 1, did_action( 'test_update_transients_rebuilt' ), 'The transients should be rebuilt once.' );
		$this->assertEquals( $this->get_rebuilt_transient(), get_site_transient( 'update_plugins' ), 'The rebuilt update_plugins transient should be kept.' );
	}

	/**
	 * Tests that updating a plugin keeps the `update_plugins` transient rebuilt on `upgrader_process_complete`.
	 *
	 * @ticket 65543
	 *
	 * @covers Plugin_Upgrader::upgrade
	 */
	public function test_plugin_upgrade_should_keep_rebuilt_update_plugins_transient() {
		$this->install_plugin();
		set_site_transient( 'update_plugins', $this->get_stale_transient( 'link-manager/link-manager.php' ) );

		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->upgrade( 'link-manager/link-manager.php' );

		$this->assertTrue( $result, 'The plugin should update.' );
		$this->assertEquals( $this->get_rebuilt_transient(), get_site_transient( 'update_plugins' ), 'The rebuilt update_plugins transient should be kept.' );
	}

	/**
	 * Tests that installing a theme keeps the `update_themes` transient rebuilt on `upgrader_process_complete`.
	 *
	 * @ticket 65543
	 *
	 * @covers Theme_Upgrader::install
	 */
	public function test_theme_install_should_keep_rebuilt_update_themes_transient() {
		set_site_transient( 'update_themes', $this->get_stale_transient() );

		$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $this->get_theme_package() );

		$this->assertTrue( $result, 'The theme should install.' );
		$this->assertEquals( $this->get_rebuilt_transient(), get_site_transient( 'update_themes' ), 'The rebuilt update_themes transient should be kept.' );
	}

	/**
	 * Tests that updating a theme keeps the `update_themes` transient rebuilt on `upgrader_process_complete`.
	 *
	 * @ticket 65543
	 *
	 * @covers Theme_Upgrader::upgrade
	 */
	public function test_theme_upgrade_should_keep_rebuilt_update_themes_transient() {
		( new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() ) )->install( $this->get_theme_package() );
		set_site_transient( 'update_themes', $this->get_stale_transient( self::THEME_SLUG, 'theme' ) );

		$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->upgrade( self::THEME_SLUG );

		$this->assertTrue( $result, 'The theme should update.' );
		$this->assertEquals( $this->get_rebuilt_transient(), get_site_transient( 'update_themes' ), 'The rebuilt update_themes transient should be kept.' );
	}

	/**
	 * Tests that the transient is still cleared before it is rebuilt, so the update check includes the new plugin.
	 *
	 * @ticket 65543
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_plugin_install_should_clear_update_plugins_transient_before_it_is_rebuilt() {
		set_site_transient( 'update_plugins', $this->get_stale_transient() );

		// Observe the transient where the update check runs (after the priority 9 clear).
		remove_action( 'upgrader_process_complete', array( $this, 'rebuild_update_transients' ), 10 );
		$seen = 'not run';
		add_action(
			'upgrader_process_complete',
			static function () use ( &$seen ) {
				$seen = get_site_transient( 'update_plugins' );
			},
			10,
			0
		);

		( new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() ) )->install( 'https://downloads.wordpress.org/plugin/link-manager.zip' );

		$this->assertFalse( $seen, 'The update_plugins transient should be cleared before the update check runs.' );
	}

	/**
	 * Tests that installing a plugin with `clear_update_cache` set to false leaves the transient alone.
	 *
	 * @ticket 65543
	 *
	 * @covers Plugin_Upgrader::install
	 */
	public function test_plugin_install_without_clear_update_cache_should_not_clear_update_plugins_transient() {
		remove_action( 'upgrader_process_complete', array( $this, 'rebuild_update_transients' ), 10 );
		$stale = $this->get_stale_transient();
		set_site_transient( 'update_plugins', $stale );

		( new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() ) )->install(
			'https://downloads.wordpress.org/plugin/link-manager.zip',
			array( 'clear_update_cache' => false )
		);

		$this->assertEquals( $stale, get_site_transient( 'update_plugins' ), 'The update_plugins transient should not be cleared.' );
	}

	/**
	 * Stand-in for wp_update_plugins() and wp_update_themes() on 'upgrader_process_complete'.
	 */
	public function rebuild_update_transients() {
		do_action( 'test_update_transients_rebuilt' );
		set_site_transient( 'update_plugins', $this->get_rebuilt_transient() );
		set_site_transient( 'update_themes', $this->get_rebuilt_transient() );
	}

	/**
	 * Serves local test packages to the upgraders.
	 *
	 * @param bool|string|WP_Error $reply    Whether to bail without returning the package.
	 * @param string               $package  The package file name or URL.
	 * @param WP_Upgrader          $upgrader The WP_Upgrader instance.
	 * @return bool|string|WP_Error The local package path, or the original reply.
	 */
	public function filter_upgrader_pre_download( $reply, $package, $upgrader ) {
		if ( $upgrader instanceof Plugin_Upgrader ) {
			copy( DIR_TESTDATA . '/plugins/link-manager.zip', DIR_TESTDATA . '/link-manager.zip' );
			return DIR_TESTDATA . '/link-manager.zip';
		}

		if ( $upgrader instanceof Theme_Upgrader ) {
			return $this->get_theme_package();
		}

		return $reply;
	}

	/**
	 * Installs the link-manager test plugin.
	 */
	private function install_plugin() {
		remove_action( 'upgrader_process_complete', array( $this, 'rebuild_update_transients' ), 10 );
		( new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() ) )->install( 'https://downloads.wordpress.org/plugin/link-manager.zip' );
		add_action( 'upgrader_process_complete', array( $this, 'rebuild_update_transients' ), 10, 0 );
	}

	/**
	 * Builds a minimal theme package.
	 *
	 * @return string Path to the package.
	 */
	private function get_theme_package() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'The ZipArchive class is required.' );
		}

		self::$theme_package = get_temp_dir() . self::THEME_SLUG . '.zip';

		$zip = new ZipArchive();
		$zip->open( self::$theme_package, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( self::THEME_SLUG . '/style.css', "/*\nTheme Name: WP Upgrader Update Cache Test\nVersion: 1.0\n*/" );
		$zip->addFromString( self::THEME_SLUG . '/index.php', '<?php' );
		$zip->close();

		return self::$theme_package;
	}

	/**
	 * Returns a transient that pretends an update is available.
	 *
	 * @param string $item Optional. Plugin file or theme slug with an update. Default empty.
	 * @param string $type Optional. 'plugin' or 'theme'. Default 'plugin'.
	 * @return stdClass Transient value.
	 */
	private function get_stale_transient( $item = '', $type = 'plugin' ) {
		$transient               = new stdClass();
		$transient->last_checked = 1;
		$transient->checked      = array();
		$transient->response     = array();

		if ( $item ) {
			$transient->response[ $item ] = 'theme' === $type
				? array(
					'theme'       => $item,
					'new_version' => '2.0',
					'package'     => 'https://downloads.wordpress.org/theme/' . $item . '.zip',
				)
				: (object) array(
					'plugin'      => $item,
					'new_version' => '2.0',
					'package'     => 'https://downloads.wordpress.org/plugin/link-manager.zip',
				);
		}

		return $transient;
	}

	/**
	 * Returns the transient value set by the stand-in update checks.
	 *
	 * @return stdClass Transient value.
	 */
	private function get_rebuilt_transient() {
		$transient               = new stdClass();
		$transient->last_checked = 12345;
		$transient->checked      = array( 'rebuilt' => '1.0' );

		return $transient;
	}
}
