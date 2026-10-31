<?php

/**
 * Tests for validate_active_plugins().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::validate_active_plugins
 * @covers ::validate_plugin
 */
class Tests_Admin_Includes_Plugin_ValidateActivePlugins extends WP_UnitTestCase {

	/**
	 * Unique directory name for the test plugin without a header.
	 *
	 * @var string
	 */
	private $no_header_dir;

	/**
	 * Super admin user ID.
	 *
	 * @var int
	 */
	private static $super_admin_id;

	/**
	 * Sets up the user.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$super_admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$super_admin_id );
		}
	}

	/**
	 * Revokes the super admin grant before the fixture users are deleted.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$super_admin_id );
		}
	}

	/**
	 * Removes stale plugin files and resets the active plugins.
	 */
	public function set_up() {
		parent::set_up();

		$this->no_header_dir = 'validate-test-no-header-' . wp_generate_password( 6, false );
		$this->remove_plugin();

		wp_clean_plugins_cache( false );
		update_site_option( 'active_sitewide_plugins', array() );
	}

	/**
	 * Removes the plugin file without a header.
	 */
	public function tear_down() {
		$this->remove_plugin();

		wp_clean_plugins_cache( false );

		parent::tear_down();
	}

	/**
	 * Tests that an active plugins option that is not an array is reset.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_options
	 *
	 * @param mixed $value The option value.
	 */
	public function test_should_reset_option_that_is_not_an_array( $value ) {
		update_option( 'active_plugins', $value );

		$this->assertSame( array(), validate_active_plugins(), 'No invalid plugins should be returned.' );
		$this->assertSame( array(), get_option( 'active_plugins' ), 'The option should be reset.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_options() {
		return array(
			'string'  => array( 'hello.php' ),
			'integer' => array( 1 ),
			'boolean' => array( false ),
			'null'    => array( null ),
			'object'  => array( new stdClass() ),
		);
	}

	/**
	 * Tests that an empty array is returned when no plugins are active.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_empty_array_when_no_active_plugins() {
		update_option( 'active_plugins', array() );

		$this->assertSame( array(), validate_active_plugins() );
	}

	/**
	 * Tests that valid active plugins are kept.
	 *
	 * @ticket 65819
	 */
	public function test_should_keep_valid_plugins() {
		update_option( 'active_plugins', array( 'hello.php', 'internationalized-plugin.php' ) );

		$this->assertSame( array(), validate_active_plugins(), 'No invalid plugins should be returned.' );
		$this->assertSame( array( 'hello.php', 'internationalized-plugin.php' ), get_option( 'active_plugins' ), 'The plugins should stay active.' );
	}

	/**
	 * Tests that invalid active plugins are reported and deactivated silently.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_and_deactivate_invalid_plugins() {
		if ( ! is_writable( WP_PLUGIN_DIR ) ) {
			$this->markTestSkipped( 'WP_PLUGIN_DIR is not writable.' );
		}

		$dir = WP_PLUGIN_DIR . '/' . $this->no_header_dir;
		$this->assertTrue( mkdir( $dir ), 'The test directory should be created.' );
		$this->assertNotFalse( file_put_contents( $dir . '/functions.php', "<?php\n// No plugin header.\n" ), 'The file should be written.' );

		wp_clean_plugins_cache( false );

		$no_header = $this->no_header_dir . '/functions.php';
		update_option( 'active_plugins', array( 'hello.php', '../outside.php', 'missing/missing.php', $no_header ) );

		$deactivate = new MockAction();
		add_action( 'deactivate_plugin', array( $deactivate, 'action' ) );
		add_action( 'deactivated_plugin', array( $deactivate, 'action' ) );
		add_action( "deactivate_{$no_header}", array( $deactivate, 'action' ) );

		$invalid = validate_active_plugins();

		$this->assertSame( array( '../outside.php', 'missing/missing.php', $no_header ), array_keys( $invalid ), 'Each invalid plugin should be reported.' );
		$this->assertSame(
			array(
				'../outside.php'      => 'plugin_invalid',
				'missing/missing.php' => 'plugin_not_found',
				$no_header            => 'no_plugin_header',
			),
			array_map(
				static function ( $error ) {
					return $error->get_error_code();
				},
				$invalid
			),
			'Each invalid plugin should have its expected error code.'
		);
		$this->assertSame( array( 'hello.php' ), array_values( get_option( 'active_plugins' ) ), 'Only the valid plugin should stay active.' );
		$this->assertSame( 0, $deactivate->get_call_count(), 'Invalid plugins should be deactivated silently.' );
	}

	/**
	 * Tests that network-active plugins are validated for a user who can manage network plugins,
	 * and that a missing plugin present in both active_plugins and active_sitewide_plugins is removed from both.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_validate_network_plugins_for_network_admin() {
		wp_set_current_user( self::$super_admin_id );

		update_option( 'active_plugins', array( 'missing/missing.php' ) );
		update_site_option(
			'active_sitewide_plugins',
			array(
				'hello.php'           => 1000,
				'missing/missing.php' => 1000,
			)
		);

		$this->assertSame( array( 'missing/missing.php' ), array_keys( validate_active_plugins() ), 'The invalid network plugin should be reported.' );
		$this->assertSame( array( 'hello.php' => 1000 ), get_site_option( 'active_sitewide_plugins' ), 'The invalid network plugin should be deactivated from network.' );
		$this->assertNotContains( 'missing/missing.php', get_option( 'active_plugins' ), 'The invalid plugin should also be deactivated from site active plugins.' );
	}

	/**
	 * Tests that network-active plugins are not validated for other users.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_not_validate_network_plugins_for_other_users() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( 'missing/missing.php' => 1000 ) );

		$this->assertSame( array(), validate_active_plugins(), 'No plugins should be validated.' );
		$this->assertSame( array( 'missing/missing.php' => 1000 ), get_site_option( 'active_sitewide_plugins' ), 'The network plugins should not change.' );
	}

	/**
	 * Removes the plugin file without a header.
	 */
	private function remove_plugin() {
		if ( $this->no_header_dir ) {
			$dir = WP_PLUGIN_DIR . '/' . $this->no_header_dir;
			if ( is_dir( $dir ) ) {
				$this->rmdir( $dir );
				$this->delete_folders( $dir );
			}
		}
	}
}
