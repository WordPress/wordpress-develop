<?php

/**
 * Tests for wp_plugin_update_row() and wp_plugin_update_rows().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers ::wp_plugin_update_row
 * @covers ::wp_plugin_update_rows
 */
class Tests_Admin_Includes_Update_WpPluginUpdateRow extends WP_UnitTestCase {

	/**
	 * The plugin file of the test plugin.
	 *
	 * @var string
	 */
	const FILE = 'my-plugin/my-plugin.php';

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
	 * The original current screen.
	 *
	 * @var WP_Screen|null
	 */
	private $original_screen;

	/**
	 * Sets up the users and loads the tested file.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/update.php';

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
	 * Switches to the Plugins screen and resets the update data.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_screen = $GLOBALS['current_screen'] ?? null;
		set_current_screen( is_multisite() ? 'plugins-network' : 'plugins' );

		// Keeps the column count fixed; auto-updates add a column.
		add_filter( 'plugins_auto_update_enabled', '__return_false' );

		delete_site_transient( 'update_plugins' );
		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );
	}

	/**
	 * Restores the current screen and the update data.
	 */
	public function tear_down() {
		$GLOBALS['current_screen'] = $this->original_screen;
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that nothing is output for a plugin without an update.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_without_update() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( 'other-plugin/other-plugin.php', array() );

		$this->assertSame( array( false, '' ), $this->render_row() );
	}

	/**
	 * Tests the update notice with an update link.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_update_link() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array() );

		$this->assertSame(
			array(
				null,
				$this->get_row_start( '', 'notice-warning' )
				. 'There is a new version of My Plugin available. ' . $this->get_details_link( 'My Plugin' )
				. ' or <a href="' . $this->get_update_url() . '" class="update-link" aria-label="Update My Plugin now">update now</a>.'
				. '</p></div></td></tr>',
			),
			$this->render_row()
		);
	}

	/**
	 * Tests that the row is marked active for an active plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_mark_active_plugin() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array() );

		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', array( self::FILE => time() ) );
		} else {
			update_option( 'active_plugins', array( self::FILE ) );
		}

		list( , $output ) = $this->render_row();

		$this->assertStringStartsWith( '<tr class="plugin-update-tr active" id="my-plugin-update"', $output );
	}

	/**
	 * Tests the notice for a user who cannot update plugins.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_not_output_update_link_for_user_without_capability() {
		wp_set_current_user( self::$subscriber_id );

		$this->set_update( self::FILE, array() );

		list( , $output ) = $this->render_row();

		$this->assertSame(
			$this->get_row_start( '', 'notice-warning' )
			. 'There is a new version of My Plugin available. ' . $this->get_details_link( 'My Plugin' ) . '.'
			. '</p></div></td></tr>',
			$output
		);
	}

	/**
	 * Tests the notice for an update without a package.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_unavailable_notice_without_package() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array( 'package' => '' ) );

		list( , $output ) = $this->render_row();

		$this->assertSame(
			$this->get_row_start( '', 'notice-warning' )
			. 'There is a new version of My Plugin available. ' . $this->get_details_link( 'My Plugin' )
			. '. <em>Automatic update is unavailable for this plugin.</em>'
			. '</p></div></td></tr>',
			$output
		);
	}

	/**
	 * Tests the notice for an update that requires a newer PHP version.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_php_notice_for_incompatible_update() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array( 'requires_php' => '99.0' ) );

		list( , $output ) = $this->render_row();

		$this->assertSame(
			$this->get_row_start( '', 'notice-error' )
			. 'There is a new version of My Plugin available, but it does not work with your version of PHP. ' . $this->get_details_link( 'My Plugin' )
			. ' or <a href="https://wordpress.org/support/update-php/">learn more about updating PHP</a>.'
			. '</p></div></td></tr>',
			$output
		);
	}

	/**
	 * Tests that the plugin name is sanitized.
	 *
	 * @ticket 65819
	 */
	public function test_should_sanitize_plugin_name() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array() );

		list( , $output ) = $this->render_row( array( 'Name' => 'My <strong>Plugin</strong> & <i>Co</i><script>alert(1)</script>' ) );

		$this->assertStringContainsString(
			'There is a new version of My <strong>Plugin</strong> &amp; Co available.',
			$output,
			'The name should keep allowed tags only.'
		);
		$this->assertStringContainsString(
			'aria-label="Update My &lt;strong&gt;Plugin&lt;/strong&gt; &amp; Co now"',
			$output,
			'The name should be escaped in the update link label.'
		);
	}

	/**
	 * Tests where the details link points to without a plugin slug.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_details_urls
	 *
	 * @param array  $response    The update response fields.
	 * @param string $slug        The expected plugin slug.
	 * @param string $details_url The expected escaped details URL.
	 */
	public function test_should_choose_details_url( $response, $slug, $details_url ) {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, $response, false );

		list( , $output ) = $this->render_row( array( 'PluginURI' => 'https://example.org/plugin-uri/' ) );

		$this->assertStringStartsWith(
			'<tr class="plugin-update-tr" id="' . $slug . '-update" data-slug="' . $slug . '" data-plugin="my-plugin/my-plugin.php">',
			$output,
			'The row should use the expected slug.'
		);
		$this->assertStringContainsString( '<a href="' . $details_url . '" class="thickbox open-plugin-details-modal"', $output, 'The details link should use the expected URL.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_details_urls() {
		return array(
			'update URL' => array(
				array(
					'id'  => 'example.org/my-plugin',
					'url' => 'https://example.org/my-plugin/',
				),
				'example.org/my-plugin',
				'https://example.org/my-plugin/?TB_iframe=true&#038;width=600&#038;height=800',
			),
			'plugin URI' => array(
				array( 'id' => 'example.org/my-plugin' ),
				'example.org/my-plugin',
				'https://example.org/plugin-uri/?TB_iframe=true&#038;width=600&#038;height=800',
			),
		);
	}

	/**
	 * Tests that the update message action fires with the plugin data and the update response.
	 *
	 * @ticket 65819
	 */
	public function test_should_fire_update_message_action() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array() );

		add_action(
			'in_plugin_update_message-' . self::FILE,
			static function ( $plugin_data, $response ) {
				echo '[' . $plugin_data['Name'] . ' ' . $response->new_version . ']';
			},
			10,
			2
		);

		list( , $output ) = $this->render_row();

		$this->assertStringEndsWith( 'update now</a>.[My Plugin 2.0.0]</p></div></td></tr>', $output );
	}

	/**
	 * Tests that nothing is output on a site's Plugins screen in a network.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_not_output_row_on_site_screen_in_network() {
		wp_set_current_user( self::$admin_id );
		set_current_screen( 'plugins' );

		$this->set_update( self::FILE, array() );

		$this->assertSame( array( null, '' ), $this->render_row() );
	}

	/**
	 * Tests that a row is hooked for each plugin with an update.
	 *
	 * @ticket 65819
	 */
	public function test_update_rows_should_hook_row_for_each_update() {
		wp_set_current_user( self::$admin_id );

		$this->set_update( self::FILE, array() );
		$this->set_update( 'other-plugin.php', array() );

		wp_plugin_update_rows();

		$this->assertSame( 10, has_action( 'after_plugin_row_' . self::FILE, 'wp_plugin_update_row' ), 'The row should be hooked for the first plugin.' );
		$this->assertSame( 10, has_action( 'after_plugin_row_other-plugin.php', 'wp_plugin_update_row' ), 'The row should be hooked for the second plugin.' );
	}

	/**
	 * Tests that no row is hooked for a user who cannot update plugins or without updates.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_update_rows
	 *
	 * @param string $user     The user, 'admin' or 'subscriber'.
	 * @param mixed  $response The response of the update transient, or false for no transient.
	 */
	public function test_update_rows_should_not_hook_rows( $user, $response ) {
		wp_set_current_user( 'admin' === $user ? self::$admin_id : self::$subscriber_id );

		if ( false !== $response ) {
			set_site_transient( 'update_plugins', (object) array( 'response' => $response ) );
		}

		wp_plugin_update_rows();

		$this->assertFalse( has_action( 'after_plugin_row_' . self::FILE ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_update_rows() {
		return array(
			'user who cannot update plugins' => array( 'subscriber', array( self::FILE => (object) array( 'slug' => 'my-plugin' ) ) ),
			'no update transient'            => array( 'admin', false ),
			'response is not an array'       => array( 'admin', 'not an array' ),
		);
	}

	/**
	 * Returns the expected start of the update row.
	 *
	 * @param string $active_class The active class.
	 * @param string $notice_type  The notice type class.
	 * @return string The markup.
	 */
	private function get_row_start( $active_class, $notice_type ) {
		return '<tr class="plugin-update-tr' . $active_class . '" id="my-plugin-update" data-slug="my-plugin" data-plugin="my-plugin/my-plugin.php">'
			. '<td colspan="3" class="plugin-update colspanchange">'
			. '<div class="update-message notice inline ' . $notice_type . ' notice-alt"><p>';
	}

	/**
	 * Returns the expected details link.
	 *
	 * @param string $label_name The plugin name in the link label.
	 * @return string The markup.
	 */
	private function get_details_link( $label_name ) {
		$url = 'http://' . WP_TESTS_DOMAIN . '/wp-admin/' . ( is_multisite() ? 'network/' : '' )
			. 'plugin-install.php?tab=plugin-information&#038;plugin=my-plugin&#038;section=changelog&#038;TB_iframe=true&#038;width=600&#038;height=800';

		return '<a href="' . $url . '" class="thickbox open-plugin-details-modal" aria-label="View ' . $label_name . ' version 2.0.0 details">View version 2.0.0 details</a>';
	}

	/**
	 * Returns the expected update URL.
	 *
	 * @return string The URL.
	 */
	private function get_update_url() {
		return 'http://' . WP_TESTS_DOMAIN . '/wp-admin/' . ( is_multisite() ? 'network/' : '' )
			. 'update.php?action=upgrade-plugin&amp;plugin=my-plugin%2Fmy-plugin.php&amp;_wpnonce=' . wp_create_nonce( 'upgrade-plugin_' . self::FILE );
	}

	/**
	 * Adds an update for a plugin to the update transient.
	 *
	 * @param string $file     The plugin file.
	 * @param array  $fields   The response fields to add or replace.
	 * @param bool   $defaults Whether to start from the default response fields.
	 */
	private function set_update( $file, $fields, $defaults = true ) {
		$current = get_site_transient( 'update_plugins' );
		if ( ! is_object( $current ) ) {
			$current = (object) array( 'response' => array() );
		}

		$response = $defaults
			? array(
				'id'          => 'w.org/plugins/my-plugin',
				'slug'        => 'my-plugin',
				'new_version' => '2.0.0',
				'package'     => 'https://downloads.example.org/my-plugin.zip',
			)
			: array( 'new_version' => '2.0.0' );

		$current->response[ $file ] = (object) array_merge( $response, $fields );

		set_site_transient( 'update_plugins', $current );
	}

	/**
	 * Renders the update row of the test plugin.
	 *
	 * @param array $plugin_data The plugin data to add or replace.
	 * @return array The return value and the output.
	 */
	private function render_row( $plugin_data = array() ) {
		$plugin_data = array_merge(
			array(
				'Name'      => 'My Plugin',
				'PluginURI' => '',
			),
			$plugin_data
		);

		ob_start();
		$result = wp_plugin_update_row( self::FILE, $plugin_data );
		$output = ob_get_clean();

		return array( $result, $output );
	}
}
