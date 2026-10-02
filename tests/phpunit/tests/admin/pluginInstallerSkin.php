<?php
/**
 * Tests for the Plugin_Installer_Skin class.
 *
 * @package WordPress
 */

require_once __DIR__ . '/plugin-dependencies/base.php';

/**
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Installer_Skin::after
 */
class Tests_Admin_PluginInstallerSkin extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Path to the test plugin's main file, relative to the plugins directory.
	 *
	 * @var string
	 */
	private $plugin_file = '';

	/**
	 * The action links passed to the 'install_plugin_complete_actions' filter.
	 *
	 * @var string[]|null
	 */
	private $install_actions = null;

	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Sets up each test.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		add_filter( 'install_plugin_complete_actions', array( $this, 'capture_install_actions' ) );
	}

	/**
	 * Removes the test plugin after each test.
	 */
	public function tear_down() {
		if ( $this->plugin_file ) {
			$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_file );
			unlink( WP_PLUGIN_DIR . '/' . $this->plugin_file );
			rmdir( $plugin_dir );
		}

		wp_clean_plugins_cache( false );

		parent::tear_down();
	}

	/**
	 * Stores the action links from the 'install_plugin_complete_actions' filter.
	 *
	 * @param string[] $install_actions Array of plugin action links.
	 * @return string[] The unmodified action links.
	 */
	public function capture_install_actions( $install_actions ) {
		$this->install_actions = $install_actions;
		return $install_actions;
	}

	/**
	 * Creates a plugin in the plugins directory, as an upload would.
	 *
	 * @param string $requires_plugins The value of the 'Requires Plugins' header.
	 */
	private function create_plugin( $requires_plugins = '' ) {
		$slug = 'plugin-installer-skin-test';

		mkdir( WP_PLUGIN_DIR . '/' . $slug );

		$this->plugin_file = $slug . '/' . $slug . '.php';

		$headers = "<?php\n/**\n * Plugin Name: Plugin Installer Skin Test\n";
		if ( '' !== $requires_plugins ) {
			$headers .= " * Requires Plugins: {$requires_plugins}\n";
		}
		$headers .= " */\n";

		file_put_contents( WP_PLUGIN_DIR . '/' . $this->plugin_file, $headers );
	}

	/**
	 * Runs Plugin_Installer_Skin::after() for a successful upload of the test plugin.
	 *
	 * @return string The output of the method, excluding the action links.
	 */
	private function run_after() {
		$upgrader = $this->createMock( Plugin_Upgrader::class );
		$upgrader->method( 'plugin_info' )->willReturn( $this->plugin_file );

		// Stub feedback(), as show_message() flushes all output buffers.
		$skin = new class( array( 'type' => 'upload' ) ) extends Plugin_Installer_Skin {
			public function feedback( $feedback, ...$args ) {}
		};

		$skin->upgrader = $upgrader;
		$skin->result   = true;

		return get_echo( array( $skin, 'after' ) );
	}

	/**
	 * Tests that activation links are not offered for a plugin with unmet dependencies.
	 *
	 * @ticket 66184
	 */
	public function test_should_not_offer_activation_when_plugin_has_unmet_dependencies() {
		$this->create_plugin( 'plugin-installer-skin-missing-dependency' );

		$output = $this->run_after();

		$this->assertIsArray( $this->install_actions, 'The install actions filter did not run.' );
		$this->assertArrayNotHasKey( 'activate_plugin', $this->install_actions, 'The Activate Plugin link should not be offered.' );
		$this->assertArrayNotHasKey( 'network_activate', $this->install_actions, 'The Network Activate link should not be offered.' );
		$this->assertStringContainsString( 'notice-error', $output, 'An error notice should be displayed.' );
		$this->assertStringContainsString( 'plugin-installer-skin-missing-dependency', $output, 'The error notice should name the missing plugin.' );
	}

	/**
	 * Tests that an activation link is still offered for a plugin without dependencies.
	 *
	 * @ticket 66184
	 */
	public function test_should_offer_activation_when_plugin_has_no_dependencies() {
		$this->create_plugin();

		$output = $this->run_after();

		$this->assertIsArray( $this->install_actions, 'The install actions filter did not run.' );
		$this->assertArrayHasKey(
			is_multisite() ? 'network_activate' : 'activate_plugin',
			$this->install_actions,
			'An activation link should be offered.'
		);
		$this->assertStringNotContainsString( 'notice-error', $output, 'No error notice should be displayed.' );
	}
}
