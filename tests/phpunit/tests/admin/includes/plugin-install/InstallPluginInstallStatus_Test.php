<?php

/**
 * Tests for install_plugin_install_status().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::install_plugin_install_status
 */
class Admin_Includes_Plugin_Install_InstallPluginInstallStatus_Test extends WP_UnitTestCase {

	/**
	 * The slug of the test plugin.
	 *
	 * @var string
	 */
	const SLUG = 'install-status-test-plugin';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	/**
	 * Update check requests made by the current test.
	 *
	 * @var string[]
	 */
	private $update_check_requests = array();

	/**
	 * Sets up the users and loads the tested file.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin grant before the fixture users are deleted.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Resets the update transient and the plugins cache before each test.
	 *
	 * Also removes a test plugin directory left behind by an interrupted run.
	 */
	public function set_up() {
		parent::set_up();

		$this->remove_plugin_directory();

		delete_site_transient( 'update_plugins' );
		wp_cache_delete( 'plugins', 'plugins' );
		unset( $_GET['from'] );
	}

	/**
	 * Removes the test plugin directory created by the test.
	 */
	public function tear_down() {
		$this->remove_plugin_directory();

		unset( $_GET['from'] );
		delete_site_transient( 'update_plugins' );
		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that a plugin with a pending update is reported as an available update.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_update_available_with_upgrade_url() {
		wp_set_current_user( self::$admin_id );

		$this->set_update_response( '3.0.0' );

		$file = self::SLUG . '/' . self::SLUG . '.php';

		$this->assertSame(
			array(
				'status'  => 'update_available',
				'url'     => 'http://' . WP_TESTS_DOMAIN . '/wp-admin/update.php?action=upgrade-plugin&amp;plugin=install-status-test-plugin%2Finstall-status-test-plugin.php&amp;_wpnonce=' . wp_create_nonce( 'upgrade-plugin_' . $file ),
				'version' => '3.0.0',
				'file'    => $file,
			),
			install_plugin_install_status( $this->get_api( '3.0.0' ) )
		);
	}

	/**
	 * Tests that the API data can be passed as an array.
	 *
	 * @ticket 65819
	 */
	public function test_should_accept_api_data_as_array() {
		wp_set_current_user( self::$admin_id );

		$this->set_update_response( '3.0.0' );

		$file = self::SLUG . '/' . self::SLUG . '.php';

		$this->assertSame(
			array(
				'status'  => 'update_available',
				'url'     => 'http://' . WP_TESTS_DOMAIN . '/wp-admin/update.php?action=upgrade-plugin&amp;plugin=install-status-test-plugin%2Finstall-status-test-plugin.php&amp;_wpnonce=' . wp_create_nonce( 'upgrade-plugin_' . $file ),
				'version' => '3.0.0',
				'file'    => $file,
			),
			install_plugin_install_status( (array) $this->get_api( '3.0.0' ) )
		);
	}

	/**
	 * Tests that no upgrade URL is returned to a user who cannot update plugins.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_update_available_without_url_for_user_without_update_capability() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_update_response( '3.0.0' );

		$this->assertSame(
			array(
				'status'  => 'update_available',
				'url'     => false,
				'version' => '3.0.0',
				'file'    => self::SLUG . '/' . self::SLUG . '.php',
			),
			install_plugin_install_status( $this->get_api( '3.0.0' ) )
		);
	}

	/**
	 * Tests that a plugin that is not installed is reported as installable.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_install_with_install_url_when_plugin_directory_is_missing() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			array(
				'status'  => 'install',
				'url'     => $this->get_install_url(),
				'version' => '',
				'file'    => false,
			),
			install_plugin_install_status( $this->get_api( '1.0.0' ) )
		);
	}

	/**
	 * Tests that no install URL is returned to a user who cannot install plugins.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_install_without_url_for_user_without_install_capability() {
		wp_set_current_user( self::$subscriber_id );

		$this->assertSame(
			array(
				'status'  => 'install',
				'url'     => false,
				'version' => '',
				'file'    => false,
			),
			install_plugin_install_status( $this->get_api( '1.0.0' ) )
		);
	}

	/**
	 * Tests that updates for other plugins are ignored.
	 *
	 * @ticket 65819
	 */
	public function test_should_ignore_updates_for_other_plugins() {
		wp_set_current_user( self::$admin_id );

		set_site_transient(
			'update_plugins',
			(object) array(
				'response' => array(
					'other-plugin/other-plugin.php' => (object) array(
						'slug'        => 'other-plugin',
						'new_version' => '9.0.0',
					),
				),
			)
		);

		$this->assertSame(
			array(
				'status'  => 'install',
				'url'     => $this->get_install_url(),
				'version' => '',
				'file'    => false,
			),
			install_plugin_install_status( $this->get_api( '1.0.0' ) )
		);
	}

	/**
	 * Tests that a directory without a plugin file is reported as installable.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_users_and_install_urls
	 *
	 * @param string $user        The user to test with, 'admin' or 'subscriber'.
	 * @param bool   $expects_url Whether an install URL is expected.
	 */
	public function test_should_return_install_when_plugin_directory_has_no_plugin( $user, $expects_url ) {
		wp_set_current_user( 'admin' === $user ? self::$admin_id : self::$subscriber_id );

		$this->create_plugin_file( 'readme.txt', "=== Install Status Test Plugin ===\n" );

		$this->assertSame(
			array(
				'status'  => 'install',
				'url'     => $expects_url ? $this->get_install_url() : false,
				'version' => '',
				'file'    => false,
			),
			install_plugin_install_status( $this->get_api( '1.0.0' ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_users_and_install_urls() {
		return array(
			'user who can install plugins'    => array( 'admin', true ),
			'user who cannot install plugins' => array( 'subscriber', false ),
		);
	}

	/**
	 * Tests the status of an installed plugin compared to the API version.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_installed_versions
	 *
	 * @param string $api_version      The version reported by the API.
	 * @param string $expected_status  The expected status.
	 * @param string $expected_version The expected version.
	 */
	public function test_should_compare_installed_version_with_api_version( $api_version, $expected_status, $expected_version ) {
		wp_set_current_user( self::$admin_id );

		$this->create_plugin( '2.0.0' );

		$this->assertSame(
			array(
				'status'  => $expected_status,
				'url'     => false,
				'version' => $expected_version,
				'file'    => self::SLUG . '/' . self::SLUG . '.php',
			),
			install_plugin_install_status( $this->get_api( $api_version ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_installed_versions() {
		return array(
			'same version'               => array( '2.0.0', 'latest_installed', '' ),
			'installed version is newer' => array( '1.5.0', 'newer_installed', '2.0.0' ),
		);
	}

	/**
	 * Tests that an outdated update check is not refreshed again during a recursive call.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_refresh_update_check_when_looping() {
		wp_set_current_user( self::$admin_id );

		$this->create_plugin( '2.0.0' );

		add_filter( 'pre_http_request', array( $this, 'mock_update_check' ), 10, 3 );

		$this->assertSame(
			array(
				'status'  => 'install',
				'url'     => false,
				'version' => '',
				'file'    => self::SLUG . '/' . self::SLUG . '.php',
			),
			install_plugin_install_status( $this->get_api( '3.0.0' ), true ),
			'The status should fall back to install.'
		);
		$this->assertSame( array(), $this->update_check_requests, 'No update check should be made.' );
	}

	/**
	 * Tests that a newer API version than the installed one refreshes the update check.
	 *
	 * @ticket 65819
	 */
	public function test_should_refresh_update_check_when_api_version_is_newer() {
		wp_set_current_user( self::$admin_id );

		$this->create_plugin( '2.0.0' );

		// A fresh update check that knows every installed plugin, so only deleting it forces a new check.
		set_site_transient(
			'update_plugins',
			(object) array(
				'last_checked' => time(),
				'checked'      => wp_list_pluck( get_plugins(), 'Version' ),
				'response'     => array(),
			)
		);

		add_filter( 'pre_http_request', array( $this, 'mock_update_check' ), 10, 3 );

		$file = self::SLUG . '/' . self::SLUG . '.php';

		$this->assertSame(
			array(
				'status'  => 'update_available',
				'url'     => 'http://' . WP_TESTS_DOMAIN . '/wp-admin/update.php?action=upgrade-plugin&amp;plugin=install-status-test-plugin%2Finstall-status-test-plugin.php&amp;_wpnonce=' . wp_create_nonce( 'upgrade-plugin_' . $file ),
				'version' => '3.0.0',
				'file'    => $file,
			),
			install_plugin_install_status( $this->get_api( '3.0.0' ) ),
			'The status should come from the refreshed update check.'
		);
		$this->assertCount( 1, $this->update_check_requests, 'One update check should be made.' );
		$this->assertStringEndsWith( '://api.wordpress.org/plugins/update-check/1.1/', $this->update_check_requests[0], 'The plugins update check should be requested.' );
	}

	/**
	 * Tests that the "from" query argument is appended to the URL.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_from_query_arg
	 *
	 * @param string $from     The "from" query argument, slashed as in a request.
	 * @param string $expected The expected suffix of the URL.
	 */
	public function test_should_append_from_query_arg_to_url( $from, $expected ) {
		wp_set_current_user( self::$admin_id );

		$_GET['from'] = $from;

		$result = install_plugin_install_status( $this->get_api( '1.0.0' ) );

		$this->assertSame( $this->get_install_url() . $expected, $result['url'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_from_query_arg() {
		return array(
			'value to encode' => array( 'a b&c=d', '&amp;from=a+b%26c%3Dd' ),
			'slashed value'   => array( "it\\'s", '&amp;from=it%27s' ),
			'empty string'    => array( '', '&amp;from=' ),
			'zero string'     => array( '0', '&amp;from=0' ),
		);
	}

	/**
	 * Returns plugin data as returned by the plugins API.
	 *
	 * @param string $version The plugin version.
	 * @return stdClass The plugin data.
	 */
	private function get_api( $version ) {
		return (object) array(
			'name'    => 'Install Status Test Plugin',
			'slug'    => self::SLUG,
			'version' => $version,
		);
	}

	/**
	 * Returns the expected install URL of the test plugin.
	 *
	 * @return string The install URL.
	 */
	private function get_install_url() {
		return 'http://' . WP_TESTS_DOMAIN . '/wp-admin/update.php?action=install-plugin&amp;plugin=' . self::SLUG . '&amp;_wpnonce=' . wp_create_nonce( 'install-plugin_' . self::SLUG );
	}

	/**
	 * Stores an update for the test plugin in the update transient.
	 *
	 * @param string $new_version The version of the update.
	 */
	private function set_update_response( $new_version ) {
		set_site_transient(
			'update_plugins',
			(object) array(
				'response' => array(
					'other-plugin/other-plugin.php'        => (object) array(
						'slug'        => 'other-plugin',
						'new_version' => '9.0.0',
					),
					self::SLUG . '/' . self::SLUG . '.php' => (object) array(
						'slug'        => self::SLUG,
						'new_version' => $new_version,
					),
				),
			)
		);
	}

	/**
	 * Creates the test plugin with the given version.
	 *
	 * @param string $version The plugin version.
	 */
	private function create_plugin( $version ) {
		$this->create_plugin_file(
			self::SLUG . '.php',
			"<?php\n/**\n * Plugin Name: Install Status Test Plugin\n * Version: {$version}\n */\n"
		);
	}

	/**
	 * Creates a file in the test plugin directory.
	 *
	 * @param string $name     The file name.
	 * @param string $contents The file contents.
	 */
	private function create_plugin_file( $name, $contents ) {
		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;

		if ( ! is_dir( $dir ) ) {
			$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		}

		$file = $dir . '/' . $name;

		$this->assertFileDoesNotExist( $file, 'The test plugin file should not exist yet.' );
		$this->assertNotFalse( file_put_contents( $file, $contents ), 'The test plugin file should be written.' );
	}

	/**
	 * Removes the test plugin directory and its files, if it exists.
	 */
	private function remove_plugin_directory() {
		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}
	}

	/**
	 * Returns an update for the test plugin from the plugins update check.
	 *
	 * @param false|array|WP_Error $response    A preemptive return value of an HTTP request.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array The mocked response for the update check, the original value otherwise.
	 */
	public function mock_update_check( $response, $parsed_args, $url ) {
		if ( ! str_contains( $url, 'api.wordpress.org/plugins/update-check/' ) ) {
			return $response;
		}

		$this->update_check_requests[] = $url;

		$file = self::SLUG . '/' . self::SLUG . '.php';

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'plugins'      => array(
						$file => array(
							'slug'        => self::SLUG,
							'plugin'      => $file,
							'new_version' => '3.0.0',
						),
					),
					'translations' => array(),
					'no_update'    => array(),
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
