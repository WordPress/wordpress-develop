<?php

/**
 * Tests for wp_get_plugin_action_button().
 *
 * @group plugins
 * @group admin
 *
 * @covers ::wp_get_plugin_action_button
 */
class Tests_Admin_Includes_WpGetPluginActionButton extends WP_UnitTestCase {

	/**
	 * User role.
	 *
	 * @var WP_Role
	 */
	private static $role;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private static $user_id;

	/**
	 * Test plugin data.
	 *
	 * @var stdClass
	 */
	private static $test_plugin;

	/**
	 * Sets up properties and adds a test plugin before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$role_name = 'wp_get_plugin_action_button-test-role';
		add_role( $role_name, 'Test Role' );

		self::$role        = get_role( $role_name );
		self::$user_id     = self::factory()->user->create( array( 'role' => $role_name ) );
		self::$test_plugin = (object) array(
			'name'    => 'My Plugin',
			'slug'    => 'my-plugin',
			'version' => '1.0.0',
		);

		mkdir( WP_PLUGIN_DIR . '/' . self::$test_plugin->slug );
		file_put_contents(
			WP_PLUGIN_DIR . '/' . self::$test_plugin->slug . '/my_plugin.php',
			"<?php\n/**\n* Plugin Name: " . self::$test_plugin->name . "\n* Version: " . self::$test_plugin->version . "\n*/"
		);
	}

	/**
	 * Removes the test plugin and its directory after all tests run.
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		remove_role( self::$role->name );

		unlink( WP_PLUGIN_DIR . '/' . self::$test_plugin->slug . '/my_plugin.php' );
		rmdir( WP_PLUGIN_DIR . '/' . self::$test_plugin->slug );
	}

	/**
	 * Tests that an empty string is returned when the user does not have the correct capabilities.
	 *
	 * @ticket 61400
	 */
	public function test_should_return_empty_string_without_proper_capabilities() {
		wp_set_current_user( self::$user_id );

		$actual = wp_get_plugin_action_button(
			self::$test_plugin->name,
			self::$test_plugin,
			true,
			true
		);

		$this->assertIsString( $actual, 'A string should be returned.' );
		$this->assertEmpty( $actual, 'An empty string should be returned.' );
	}

	/**
	 * Tests that an empty string is not returned when the user
	 * has the correct capabilities on single site.
	 *
	 * @ticket 61400
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_capabilities
	 *
	 * @param string $capability The name of the capability.
	 */
	public function test_should_not_return_empty_string_with_proper_capabilities_single_site( $capability ) {
		self::$role->add_cap( $capability );

		wp_set_current_user( self::$user_id );

		$actual = wp_get_plugin_action_button(
			self::$test_plugin->name,
			self::$test_plugin,
			true,
			true
		);

		self::$role->remove_cap( $capability );

		$this->assertIsString( $actual, 'A string should be returned.' );
		$this->assertNotEmpty( $actual, 'An empty string should not be returned.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_capabilities() {
		return self::text_array_to_dataprovider( array( 'install_plugins', 'update_plugins' ) );
	}

	/**
	 * Tests that an empty string is not returned when the user
	 * has the correct capabilities on multisite.
	 *
	 * @ticket 61400
	 *
	 * @group ms-required
	 */
	public function test_should_not_return_empty_string_with_proper_capabilities_multisite() {
		wp_set_current_user( self::$user_id );

		grant_super_admin( self::$user_id );

		$actual = wp_get_plugin_action_button(
			self::$test_plugin->name,
			self::$test_plugin,
			true,
			true
		);

		revoke_super_admin( self::$user_id );

		$this->assertIsString( $actual, 'A string should be returned.' );
		$this->assertNotEmpty( $actual, 'An empty string should not be returned.' );
	}
}
