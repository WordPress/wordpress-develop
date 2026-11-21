<?php

/**
 * Tests for activate_plugin().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::activate_plugin
 */
class Tests_Admin_Includes_Plugin_ActivatePlugin extends WP_UnitTestCase {

	/**
	 * The slug of the test plugin.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * The original value of $_GET['networkwide'] before the test.
	 *
	 * @var mixed
	 */
	private $old_networkwide;

	/**
	 * The redirect locations passed to wp_redirect().
	 *
	 * @var string[]
	 */
	private $redirects = array();

	/**
	 * Resets the active plugins and prepares a unique test plugin.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'active_plugins', array( 'zzz/zzz.php' ) );
		update_site_option( 'active_sitewide_plugins', array() );

		$this->slug            = 'activation-test-plugin-' . wp_generate_password( 8, false );
		$this->old_networkwide = array_key_exists( 'networkwide', $_GET ) ? $_GET['networkwide'] : null;
		$this->redirects       = array();

		add_filter( 'wp_redirect', array( $this, 'record_redirect' ) );
	}

	/**
	 * Removes the test plugin and restores global state.
	 */
	public function tear_down() {
		global $wp_plugin_paths;

		$this->remove_plugin();
		wp_cache_delete( 'plugins', 'plugins' );

		if ( is_array( $wp_plugin_paths ) ) {
			unset( $wp_plugin_paths[ WP_PLUGIN_DIR . '/' . $this->get_file() ] );
		}

		if ( null !== $this->old_networkwide ) {
			$_GET['networkwide'] = $this->old_networkwide;
		} else {
			unset( $_GET['networkwide'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that a plugin that is not installed is not activated.
	 *
	 * On multisite, is_network_only_plugin() reads the missing file before the plugin is validated.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_reject_plugin_that_is_not_installed() {
		$result = activate_plugin( 'missing/missing.php' );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'plugin_not_found', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( array( 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'The active plugins should not change.' );
	}

	/**
	 * Tests that a plugin whose requirements are not met is not activated.
	 *
	 * @ticket 65819
	 */
	public function test_should_reject_plugin_with_unmet_requirements() {
		$this->create_plugin( " * Requires PHP: 99.0\n" );

		$result = activate_plugin( $this->get_file() );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'plugin_php_incompatible', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( array( 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'The active plugins should not change.' );

		if ( is_multisite() ) {
			$this->assertSame( array(), get_site_option( 'active_sitewide_plugins' ), 'The active sitewide plugins should not change.' );
		}
	}

	/**
	 * Tests that a plugin is activated, sorted into the active plugins, and the activation hooks fire.
	 *
	 * @ticket 65819
	 */
	public function test_should_activate_plugin_and_fire_hooks() {
		$this->create_plugin();

		$actions = $this->watch_activation_hooks();

		$this->assertNull( activate_plugin( $this->get_file() ), 'Nothing should be returned.' );
		$this->assertSame( array( $this->get_file(), 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'The plugin should be added in order.' );
		$this->assertSame(
			array( 'activate_plugin', "activate_{$this->get_file()}", 'activated_plugin' ),
			$actions->get_hook_names(),
			'The activation hooks should fire in order.'
		);
		$this->assertSame(
			array(
				array( $this->get_file(), false ),
				array( false ),
				array( $this->get_file(), false ),
			),
			$actions->get_args(),
			'The activation hooks should receive the expected arguments.'
		);
	}

	/**
	 * Tests that the plugin path is reduced to its basename.
	 *
	 * @ticket 65819
	 */
	public function test_should_accept_full_path_with_whitespace() {
		$this->create_plugin();

		$this->assertNull( activate_plugin( '  ' . WP_PLUGIN_DIR . '/' . $this->get_file() . ' ' ), 'Nothing should be returned.' );
		$this->assertContains( $this->get_file(), get_option( 'active_plugins' ), 'The plugin should be active.' );
	}

	/**
	 * Tests that no hooks fire in silent mode.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_fire_hooks_in_silent_mode() {
		$this->create_plugin();

		$actions = $this->watch_activation_hooks();

		$this->assertNull( activate_plugin( $this->get_file(), '', false, true ), 'Nothing should be returned.' );
		$this->assertContains( $this->get_file(), get_option( 'active_plugins' ), 'The plugin should be active.' );
		$this->assertSame( array(), $actions->get_events(), 'No activation hooks should fire.' );
	}

	/**
	 * Tests that an active plugin is not activated again.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_activate_active_plugin_again() {
		$this->create_plugin();
		update_option( 'active_plugins', array( $this->get_file() ) );

		$actions = $this->watch_activation_hooks();

		$this->assertNull( activate_plugin( $this->get_file(), 'plugins.php' ), 'Nothing should be returned.' );
		$this->assertSame( array( $this->get_file() ), get_option( 'active_plugins' ), 'The active plugins should not change.' );
		$this->assertSame( array(), $actions->get_events(), 'No activation hooks should fire.' );
		$this->assertSame( array(), $this->redirects, 'No redirect should be sent.' );
	}

	/**
	 * Tests that output while loading the plugin is reported, after the plugin is activated.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_unexpected_output() {
		$this->create_plugin( '', "echo 'Unexpected!';\n" );

		$result = activate_plugin( $this->get_file() );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'unexpected_output', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'The plugin generated unexpected output.', $result->get_error_message(), 'The error message should match.' );
		$this->assertSame( 'Unexpected!', $result->get_error_data(), 'The output should be the error data.' );
		$this->assertContains( $this->get_file(), get_option( 'active_plugins' ), 'The plugin should still be activated.' );
	}

	/**
	 * Tests that a redirect to the error page is sent before the plugin is loaded.
	 *
	 * @ticket 65819
	 */
	public function test_should_redirect_with_error_nonce() {
		$this->create_plugin();

		activate_plugin( $this->get_file(), 'plugins.php?error=true&plugin=' . $this->get_file() );

		$this->assertSame(
			array( "plugins.php?error=true&plugin={$this->slug}%2F{$this->slug}.php&_error_nonce=" . wp_create_nonce( 'plugin-activation-error_' . $this->get_file() ) ),
			$this->redirects
		);
	}

	/**
	 * Tests that a plugin is activated network-wide on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_activate_plugin_network_wide() {
		$this->create_plugin();

		$actions = $this->watch_activation_hooks();

		$this->assertNull( activate_plugin( $this->get_file(), '', true ), 'Nothing should be returned.' );
		$this->assertSame( array( $this->get_file() ), array_keys( get_site_option( 'active_sitewide_plugins' ) ), 'The plugin should be network active.' );
		$this->assertSame( array( 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'The site plugins should not change.' );
		$this->assertSame( 1, $_GET['networkwide'], 'The back-compat request value should be set.' );
		$this->assertSame(
			array( 'activate_plugin', "activate_{$this->get_file()}", 'activated_plugin' ),
			$actions->get_hook_names(),
			'The activation hooks should fire in order.'
		);
		$this->assertSame(
			array(
				array( $this->get_file(), true ),
				array( true ),
				array( $this->get_file(), true ),
			),
			$actions->get_args(),
			'The activation hooks should receive the expected arguments.'
		);
	}

	/**
	 * Tests that a network-only plugin is always activated network-wide on multisite.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_activate_network_only_plugin_network_wide() {
		$this->create_plugin( " * Network: true\n" );

		$actions = $this->watch_activation_hooks();

		$this->assertNull( activate_plugin( $this->get_file() ), 'Nothing should be returned.' );
		$this->assertArrayHasKey( $this->get_file(), get_site_option( 'active_sitewide_plugins' ), 'The plugin should be network active.' );
		$this->assertSame( array( 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'The site plugins should not change.' );
		$this->assertSame( 1, $_GET['networkwide'], 'The back-compat request value should be set.' );
		$this->assertSame(
			array( 'activate_plugin', "activate_{$this->get_file()}", 'activated_plugin' ),
			$actions->get_hook_names(),
			'The activation hooks should fire in order.'
		);
		$this->assertSame(
			array(
				array( $this->get_file(), true ),
				array( true ),
				array( $this->get_file(), true ),
			),
			$actions->get_args(),
			'The activation hooks should receive the expected arguments.'
		);
	}

	/**
	 * Tests that a network-active plugin is not activated again.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_not_activate_network_active_plugin_again() {
		$this->create_plugin();
		update_site_option( 'active_sitewide_plugins', array( $this->get_file() => 1000 ) );

		$this->assertNull( activate_plugin( $this->get_file(), '', true ), 'Nothing should be returned.' );
		$this->assertSame( array( $this->get_file() => 1000 ), get_site_option( 'active_sitewide_plugins' ), 'The activation time should not change.' );
	}

	/**
	 * Records redirect locations and stops the redirect.
	 *
	 * @param string $location The redirect location.
	 * @return false False, so that no header is sent.
	 */
	public function record_redirect( $location ) {
		$this->redirects[] = $location;

		return false;
	}

	/**
	 * Records the activation hooks using MockAction.
	 *
	 * @return MockAction The mock action recording hook calls.
	 */
	private function watch_activation_hooks() {
		$mock = new MockAction();

		foreach ( array( 'activate_plugin', "activate_{$this->get_file()}", 'activated_plugin' ) as $hook ) {
			add_action( $hook, array( $mock, 'action' ), 10, 2 );
		}

		return $mock;
	}

	/**
	 * Returns the plugin file of the test plugin.
	 *
	 * @return string The plugin file.
	 */
	private function get_file() {
		return "{$this->slug}/{$this->slug}.php";
	}

	/**
	 * Creates the test plugin.
	 *
	 * @param string $headers Additional plugin headers.
	 * @param string $code    Code after the headers.
	 */
	private function create_plugin( $headers = '', $code = '' ) {
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;

		$this->remove_plugin();
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse(
			file_put_contents( "{$dir}/{$this->slug}.php", "<?php\n/**\n * Plugin Name: Activation Test Plugin\n{$headers} */\n{$code}" ),
			'The test plugin should be written.'
		);

		wp_cache_delete( 'plugins', 'plugins' );
	}

	/**
	 * Removes the test plugin, including one left over by an interrupted run.
	 */
	private function remove_plugin() {
		$dir = WP_PLUGIN_DIR . '/' . $this->slug;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}
	}
}
