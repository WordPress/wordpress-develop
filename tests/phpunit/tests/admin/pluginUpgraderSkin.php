<?php

/**
 * Tests for Plugin_Upgrader_Skin.
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Upgrader_Skin
 */
class Tests_Admin_PluginUpgraderSkin extends WP_UnitTestCase {

	/**
	 * Plugin file of a plugin that is installed in the test data.
	 *
	 * @var string
	 */
	const PLUGIN = 'custom-internationalized-plugin/custom-internationalized-plugin.php';

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Loads the upgrader classes and sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Revokes the super admin fixture.
	 */
	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$admin_id );
		}
	}

	/**
	 * Clears the plugins cache.
	 */
	public function tear_down() {
		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Tests the defaults of the skin.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::__construct
	 */
	public function test_constructor_should_use_defaults() {
		$skin = new Plugin_Upgrader_Skin();

		$this->assertSame( 'Update Plugin', $skin->options['title'], 'The title should be set.' );
		$this->assertSame( '', $skin->options['url'], 'The URL should be empty.' );
		$this->assertSame( '', $skin->options['nonce'], 'The nonce should be empty.' );
		$this->assertSame( '', $skin->plugin, 'The plugin should be empty.' );
		$this->assertFalse( $skin->plugin_active, 'The plugin should not be active.' );
		$this->assertFalse( $skin->plugin_network_active, 'The plugin should not be network active.' );
	}

	/**
	 * Tests that the arguments override the defaults.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::__construct
	 */
	public function test_constructor_should_use_arguments() {
		$skin = new Plugin_Upgrader_Skin(
			array(
				'url'    => 'update.php?action=upgrade-plugin',
				'plugin' => self::PLUGIN,
				'nonce'  => 'upgrade-plugin_' . self::PLUGIN,
				'title'  => 'My title',
			)
		);

		$this->assertSame( 'update.php?action=upgrade-plugin', $skin->options['url'] );
		$this->assertSame( 'upgrade-plugin_' . self::PLUGIN, $skin->options['nonce'] );
		$this->assertSame( 'My title', $skin->options['title'] );
		$this->assertSame( self::PLUGIN, $skin->plugin );
	}

	/**
	 * Tests that the skin knows when the plugin is active.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::__construct
	 */
	public function test_constructor_should_detect_an_active_plugin() {
		update_option( 'active_plugins', array( self::PLUGIN ) );

		$skin = new Plugin_Upgrader_Skin( array( 'plugin' => self::PLUGIN ) );

		$this->assertTrue( $skin->plugin_active, 'The plugin should be active.' );
		$this->assertFalse( $skin->plugin_network_active, 'The plugin should not be network active.' );
	}

	/**
	 * Tests that the skin knows when the plugin is active for the network.
	 *
	 * @ticket 66551
	 *
	 * @group ms-required
	 *
	 * @covers Plugin_Upgrader_Skin::__construct
	 */
	public function test_constructor_should_detect_a_network_active_plugin() {
		update_site_option( 'active_sitewide_plugins', array( self::PLUGIN => time() ) );

		$skin = new Plugin_Upgrader_Skin( array( 'plugin' => self::PLUGIN ) );

		$this->assertTrue( $skin->plugin_active, 'A network active plugin should be active.' );
		$this->assertTrue( $skin->plugin_network_active, 'The plugin should be network active.' );
	}

	/**
	 * Tests that an active plugin is activated again in a frame after it is updated.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_reactivate_an_active_plugin_in_a_frame() {
		wp_set_current_user( self::$admin_id );
		update_option( 'active_plugins', array( self::PLUGIN ) );

		$output = $this->run_after( $this->get_skin( true ) );

		$this->assertStringContainsString( '<iframe title="Update progress"', $output, 'The frame should be output.' );
		$this->assertStringContainsString( 'src="update.php?action=activate-plugin&amp;', $output, 'The frame should activate the plugin.' );
		$this->assertStringContainsString( '&amp;plugin=custom-internationalized-plugin%2Fcustom-internationalized-plugin.php&amp;_wpnonce=', $output, 'The frame should activate the updated plugin.' );
		$this->assertStringNotContainsString( 'Activate Plugin', $output, 'An active plugin should not get a link to activate it.' );
		$this->assertStringContainsString( 'Go to Plugins page', $output, 'The Plugins page should be linked.' );
	}

	/**
	 * Tests that a plugin that was not active is not activated in a frame.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_not_output_a_frame_for_an_inactive_plugin() {
		wp_set_current_user( self::$admin_id );

		$output = $this->run_after( $this->get_skin( true ) );

		$this->assertStringNotContainsString( '<iframe', $output );
	}

	/**
	 * Tests that no frame is output without a plugin, or when the update failed.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 *
	 * @dataProvider data_results_without_a_frame
	 *
	 * @param mixed $result      The result of the update.
	 * @param bool  $has_plugin  Whether the update result has an installed plugin.
	 */
	public function test_after_should_not_output_a_frame_without_a_plugin_or_after_an_error( $result, $has_plugin ) {
		wp_set_current_user( self::$admin_id );
		update_option( 'active_plugins', array( self::PLUGIN ) );

		$output = $this->run_after( $this->get_skin( $result, $has_plugin ) );

		$this->assertStringNotContainsString( '<iframe', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_results_without_a_frame() {
		return array(
			'no plugin was installed' => array( true, false ),
			'an error'                => array( new WP_Error( 'failed', 'Failed.' ), true ),
		);
	}

	/**
	 * Tests the links after a successful update of a plugin that is not active.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_link_to_activate_the_plugin_and_to_the_plugins_page() {
		wp_set_current_user( self::$admin_id );

		$output = $this->run_after( $this->get_skin( true ) );

		$this->assertStringContainsString( '<a href="', $output );
		$this->assertStringContainsString( 'plugins.php?action=activate&amp;plugin=custom-internationalized-plugin%2Fcustom-internationalized-plugin.php&amp;_wpnonce=', $output, 'The link to activate the plugin should be output.' );
		$this->assertStringContainsString( 'target="_parent">Activate Plugin</a> | <a href="', $output, 'The links should be separated.' );
		$this->assertStringContainsString( '<a href="' . self_admin_url( 'plugins.php' ) . '" target="_parent">Go to Plugins page</a>', $output, 'The link to the Plugins page should be output.' );
	}

	/**
	 * Tests that the link to activate the plugin is not output.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 *
	 * @dataProvider data_results_without_an_activate_link
	 *
	 * @param mixed $result The result of the update.
	 */
	public function test_after_should_not_link_to_activate_the_plugin_after_a_failure( $result ) {
		wp_set_current_user( self::$admin_id );

		$output = $this->run_after( $this->get_skin( $result ) );

		$this->assertStringNotContainsString( 'Activate Plugin', $output );
		$this->assertStringContainsString( 'Go to Plugins page', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_results_without_an_activate_link() {
		return array(
			'no result' => array( false ),
			'an error'  => array( new WP_Error( 'failed', 'Failed.' ) ),
		);
	}

	/**
	 * Tests that the plugin is not offered for activation to a user who cannot activate it.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_not_link_to_activate_the_plugin_for_a_user_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$output = $this->run_after( $this->get_skin( true ) );

		$this->assertStringNotContainsString( 'Activate Plugin', $output );
	}

	/**
	 * Tests that the links can be filtered.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_filter_the_links() {
		wp_set_current_user( self::$admin_id );

		$filter = new MockAction();
		add_filter( 'update_plugin_complete_actions', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'update_plugin_complete_actions',
			static function ( $actions ) {
				$actions['custom'] = '<a href="#custom">Custom link</a>';

				return $actions;
			},
			11
		);

		$output = $this->run_after( $this->get_skin( true ) );
		$args   = $filter->get_args();

		$this->assertSame( array( 'activate_plugin', 'plugins_page' ), array_keys( $args[0][0] ), 'The filter should receive both links.' );
		$this->assertSame( self::PLUGIN, $args[0][1], 'The filter should receive the plugin file.' );
		$this->assertStringContainsString( 'Go to Plugins page</a> | <a href="#custom">Custom link</a>', $output, 'The added link should be output.' );
	}

	/**
	 * Tests that nothing is output about the links when they are removed.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_not_output_links_when_they_are_removed() {
		wp_set_current_user( self::$admin_id );

		add_filter( 'update_plugin_complete_actions', '__return_empty_array' );

		$skin = $this->get_skin( true );

		get_echo( array( $skin, 'after' ) );

		$this->assertSame( array(), $skin->messages, 'No feedback should be given without links.' );
	}

	/**
	 * Tests that the update count is decremented after a successful update only.
	 *
	 * @ticket 66551
	 *
	 * @covers Plugin_Upgrader_Skin::after
	 */
	public function test_after_should_decrement_the_update_count_after_a_successful_update() {
		wp_set_current_user( self::$admin_id );

		$output = $this->run_after( $this->get_skin( true ) );

		$this->assertStringContainsString( '<script', $output, 'A script should be output.' );
		$this->assertStringContainsString( '"plugin"', $output, 'The script should decrement the count of plugins.' );

		$output = $this->run_after( $this->get_skin( false ) );

		$this->assertStringNotContainsString( '<script', $output, 'No script should be output without a result.' );
	}

	/**
	 * Returns a plugin upgrader skin attached to an upgrader.
	 *
	 * @param mixed $result     The result of the update, which the skin gets.
	 * @param bool  $has_plugin Optional. Whether the upgrader result has the folder of an installed plugin.
	 *                          Default true.
	 * @return Plugin_Upgrader_Skin The skin, with the feedback it gets in `$messages`.
	 */
	private function get_skin( $result, $has_plugin = true ) {
		// Collects the feedback, because show_message() ends all output buffers.
		$skin     = new class( array( 'plugin' => self::PLUGIN ) ) extends Plugin_Upgrader_Skin {
			/**
			 * Feedback given to the skin.
			 *
			 * @var string[]
			 */
			public $messages = array();

			/**
			 * Collects the feedback.
			 *
			 * @param string|array|WP_Error $feedback Message data.
			 * @param mixed                 ...$args  Optional text replacements.
			 */
			public function feedback( $feedback, ...$args ) {
				$this->messages[] = $feedback;
			}
		};
		$upgrader = new Plugin_Upgrader( $skin );

		$upgrader->result = $has_plugin ? array( 'destination_name' => 'custom-internationalized-plugin' ) : array( 'destination_name' => '' );

		// Done by WP_Upgrader::init(), which also schedules a backup cleanup.
		$skin->set_upgrader( $upgrader );
		$skin->result = $result;

		return $skin;
	}

	/**
	 * Runs the skin's after() method.
	 *
	 * @param Plugin_Upgrader_Skin $skin The skin.
	 * @return string The output, followed by the feedback of the skin.
	 */
	private function run_after( $skin ) {
		return get_echo( array( $skin, 'after' ) ) . implode( '', $skin->messages );
	}
}
