<?php

/**
 * Tests for activate_plugins().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::activate_plugins
 */
class Tests_Admin_Includes_Plugin_ActivatePlugins extends WP_UnitTestCase {

	/**
	 * The slugs of the test plugins.
	 *
	 * @var string[]
	 */
	private $slugs = array();

	/**
	 * The redirect locations passed to wp_redirect().
	 *
	 * @var string[]
	 */
	private $redirects = array();

	/**
	 * Resets the active plugins and prepares unique test plugins.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'active_plugins', array( 'zzz/zzz.php' ) );
		update_site_option( 'active_sitewide_plugins', array() );

		$suffix          = wp_generate_password( 8, false );
		$this->slugs     = array( "activate-plugins-one-$suffix", "activate-plugins-two-$suffix" );
		$this->redirects = array();

		add_filter( 'wp_redirect', array( $this, 'record_redirect' ) );
	}

	/**
	 * Removes the test plugins and restores global state.
	 */
	public function tear_down() {
		global $wp_plugin_paths;

		foreach ( $this->slugs as $slug ) {
			$this->remove_plugin( $slug );

			if ( is_array( $wp_plugin_paths ) ) {
				unset( $wp_plugin_paths[ WP_PLUGIN_DIR . "/$slug/$slug.php" ] );
			}
		}

		wp_cache_delete( 'plugins', 'plugins' );
		unset( $_GET['networkwide'] );

		parent::tear_down();
	}

	/**
	 * Tests that several plugins are activated.
	 *
	 * @ticket 66555
	 */
	public function test_should_activate_several_plugins() {
		$this->create_plugins();

		$this->assertTrue( activate_plugins( array( $this->get_file( 0 ), $this->get_file( 1 ) ) ) );
		$this->assertEqualSets(
			array( 'zzz/zzz.php', $this->get_file( 0 ), $this->get_file( 1 ) ),
			get_option( 'active_plugins' ),
			'Both plugins should be active.'
		);
	}

	/**
	 * Tests that a plugin that is not in an array is activated.
	 *
	 * @ticket 66555
	 */
	public function test_should_activate_a_single_plugin() {
		$this->create_plugins();

		$this->assertTrue( activate_plugins( $this->get_file( 0 ) ) );
		$this->assertContains( $this->get_file( 0 ), get_option( 'active_plugins' ), 'The plugin should be active.' );
		$this->assertNotContains( $this->get_file( 1 ), get_option( 'active_plugins' ), 'The other plugin should not be active.' );
	}

	/**
	 * Tests that an error is returned when a plugin cannot be activated, and that the other plugins are activated.
	 *
	 * @ticket 66555
	 */
	public function test_should_return_the_errors_of_the_plugins_that_cannot_be_activated() {
		$this->create_plugins( array( 1 => " * Requires PHP: 99.0\n" ) );

		$result = activate_plugins( array( $this->get_file( 0 ), $this->get_file( 1 ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'plugins_invalid', $result->get_error_code() );
		$this->assertSame( 'One of the plugins is invalid.', $result->get_error_message() );

		$errors = $result->get_error_data();

		$this->assertSame( array( $this->get_file( 1 ) ), array_keys( $errors ), 'Only the plugin that failed should have an error.' );
		$this->assertWPError( $errors[ $this->get_file( 1 ) ] );
		$this->assertSame( 'plugin_php_incompatible', $errors[ $this->get_file( 1 ) ]->get_error_code() );
		$this->assertContains( $this->get_file( 0 ), get_option( 'active_plugins' ), 'The other plugin should be active.' );
		$this->assertNotContains( $this->get_file( 1 ), get_option( 'active_plugins' ), 'The plugin that failed should not be active.' );
	}

	/**
	 * Tests that the errors of all the plugins that cannot be activated are returned.
	 *
	 * @ticket 66555
	 */
	public function test_should_return_the_errors_of_all_the_plugins_that_cannot_be_activated() {
		$this->create_plugins(
			array(
				0 => " * Requires PHP: 99.0\n",
				1 => " * Requires PHP: 99.0\n",
			)
		);

		$result = activate_plugins( array( $this->get_file( 0 ), $this->get_file( 1 ) ) );

		$this->assertWPError( $result );
		$this->assertSame( array( $this->get_file( 0 ), $this->get_file( 1 ) ), array_keys( $result->get_error_data() ) );
		$this->assertSame( array( 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'No plugin should be activated.' );
	}

	/**
	 * Tests that the plugin is added to the redirect URL of each plugin.
	 *
	 * @ticket 66555
	 */
	public function test_should_add_each_plugin_to_the_redirect() {
		$this->create_plugins();

		activate_plugins( array( $this->get_file( 0 ), $this->get_file( 1 ) ), 'plugins.php?error=true' );

		$this->assertSame(
			array(
				'plugins.php?error=true&plugin=' . rawurlencode( $this->get_file( 0 ) ) . '&_error_nonce=' . wp_create_nonce( 'plugin-activation-error_' . $this->get_file( 0 ) ),
				'plugins.php?error=true&plugin=' . rawurlencode( $this->get_file( 1 ) ) . '&_error_nonce=' . wp_create_nonce( 'plugin-activation-error_' . $this->get_file( 1 ) ),
			),
			$this->redirects
		);
	}

	/**
	 * Tests that nothing is added to a redirect that is empty.
	 *
	 * @ticket 66555
	 */
	public function test_should_not_redirect_without_a_redirect_url() {
		$this->create_plugins();

		activate_plugins( array( $this->get_file( 0 ) ) );

		$this->assertSame( array(), $this->redirects );
	}

	/**
	 * Tests that the plugins are activated silently.
	 *
	 * @ticket 66555
	 */
	public function test_should_pass_the_silent_argument() {
		$this->create_plugins();

		$mock = new MockAction();
		add_action( 'activated_plugin', array( $mock, 'action' ) );

		$this->assertTrue( activate_plugins( array( $this->get_file( 0 ) ), '', false, true ) );
		$this->assertSame( array(), $mock->get_events(), 'The activation hooks should not fire.' );

		$this->assertTrue( activate_plugins( array( $this->get_file( 1 ) ) ) );
		$this->assertCount( 1, $mock->get_events(), 'The activation hook should fire when the plugin is not activated silently.' );
	}

	/**
	 * Tests that the plugins are activated for the network.
	 *
	 * @ticket 66555
	 *
	 * @group ms-required
	 */
	public function test_should_pass_the_network_wide_argument() {
		$this->create_plugins();

		$this->assertTrue( activate_plugins( array( $this->get_file( 0 ), $this->get_file( 1 ) ), '', true ) );
		$this->assertEqualSets(
			array( $this->get_file( 0 ), $this->get_file( 1 ) ),
			array_keys( get_site_option( 'active_sitewide_plugins' ) ),
			'Both plugins should be network active.'
		);
		$this->assertSame( array( 'zzz/zzz.php' ), get_option( 'active_plugins' ), 'The site plugins should not change.' );
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
	 * Returns the plugin file of a test plugin.
	 *
	 * @param int $index The index of the plugin.
	 * @return string The plugin file.
	 */
	private function get_file( $index ) {
		$slug = $this->slugs[ $index ];

		return "$slug/$slug.php";
	}

	/**
	 * Creates the test plugins.
	 *
	 * @param string[] $headers Optional. Additional plugin headers, by index of the plugin. Default none.
	 */
	private function create_plugins( array $headers = array() ) {
		foreach ( $this->slugs as $index => $slug ) {
			$dir = WP_PLUGIN_DIR . '/' . $slug;

			$this->remove_plugin( $slug );
			$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
			$this->assertNotFalse(
				file_put_contents( "{$dir}/{$slug}.php", "<?php\n/**\n * Plugin Name: Activate Plugins Test Plugin $index\n" . ( $headers[ $index ] ?? '' ) . " */\n" ),
				'The test plugin should be written.'
			);
		}

		wp_cache_delete( 'plugins', 'plugins' );
	}

	/**
	 * Removes a test plugin, including one left over by an interrupted run.
	 *
	 * @param string $slug The slug of the plugin.
	 */
	private function remove_plugin( $slug ) {
		$dir = WP_PLUGIN_DIR . '/' . $slug;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}
	}
}
