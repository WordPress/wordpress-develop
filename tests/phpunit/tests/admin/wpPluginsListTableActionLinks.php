<?php

require_once __DIR__ . '/plugin-dependencies/base.php';

/**
 * Tests for the action links WP_Plugins_List_Table::single_row() adds on a site's Plugins screen.
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::single_row
 */
class Tests_Admin_wpPluginsListTableActionLinks extends WP_PluginDependencies_UnitTestCase {

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
	 * The original globals the list table reads.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * The original request parameters.
	 *
	 * @var array
	 */
	private $original_request = array();

	/**
	 * The actions passed to the plugin_action_links filter.
	 *
	 * @var array|null
	 */
	private $actions;

	/**
	 * The rendered HTML output of the plugin row.
	 *
	 * @var string
	 */
	private $row_output = '';

	/**
	 * The slug of the plugin rendered by the current test.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
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
	 * Sets up the Plugins screen, the list table globals and the dependency state.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_globals = array();
		foreach ( array( 's', 'totals', '_paused_plugins' ) as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->original_globals[ $name ] = $GLOBALS[ $name ];
			}
		}

		$this->original_request = array(
			'plugin_status' => $_REQUEST['plugin_status'] ?? null,
			'paged'         => $_REQUEST['paged'] ?? null,
		);

		set_current_screen( 'plugins' );

		$_REQUEST['plugin_status'] = 'all';
		$_REQUEST['paged']         = 1;

		$GLOBALS['s']               = '';
		$GLOBALS['totals']          = array();
		$GLOBALS['_paused_plugins'] = array();

		$this->set_property_value( 'plugins', array() );
		$this->set_property_value( 'dependencies', array() );
		$this->set_property_value( 'dependency_slugs', array() );
		$this->set_property_value( 'dependency_filepaths', array() );
		$this->set_property_value( 'circular_dependencies_slugs', array() );
		$this->set_property_value( 'circular_dependencies_pairs', array() );
		$this->set_property_value( 'initialized', true );

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array() );

		$this->actions    = null;
		$this->row_output = '';
		$this->slug       = 'action-links-plugin-' . wp_generate_password( 8, false );

		add_filter( 'plugin_action_links', array( $this, 'capture_actions' ), PHP_INT_MAX );

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Restores the globals and removes the test plugin.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		foreach ( array( 's', 'totals', '_paused_plugins' ) as $name ) {
			if ( ! array_key_exists( $name, $this->original_globals ) ) {
				unset( $GLOBALS[ $name ] );
			}
		}

		foreach ( $this->original_request as $key => $value ) {
			if ( null !== $value ) {
				$_REQUEST[ $key ] = $value;
			} else {
				unset( $_REQUEST[ $key ] );
			}
		}

		$this->remove_plugin();

		parent::tear_down();
	}

	/**
	 * Tests the Deactivate link of an active plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_deactivate_for_active_plugin() {
		$this->activate();

		$this->assertSame(
			array(
				'deactivate' => '<a href="' . $this->get_url( 'deactivate', 'deactivate-plugin_' . $this->get_file() ) . '" id="deactivate-' . $this->slug . '" aria-label="Deactivate My Plugin">Deactivate</a>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests that an active plugin that other active plugins require cannot be deactivated.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_link_deactivate_for_plugin_with_active_dependents() {
		$this->set_dependent( true );
		$this->activate();

		$this->assertSame(
			array(
				'deactivate' => 'Deactivate<span class="screen-reader-text">You cannot deactivate this plugin as other plugins depend on it.</span>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests the Resume link of an active plugin that was paused.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_resume_for_paused_plugin() {
		$this->activate();
		$this->pause();

		$this->assertSame(
			array(
				'deactivate' => '<a href="' . $this->get_url( 'deactivate', 'deactivate-plugin_' . $this->get_file() ) . '" id="deactivate-' . $this->slug . '" aria-label="Deactivate My Plugin">Deactivate</a>',
				'resume'     => '<a href="' . $this->get_url( 'resume', 'resume-plugin_' . $this->get_file() ) . '" id="resume-' . $this->slug . '" class="resume-link" aria-label="Resume My Plugin">Resume</a>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests the Activate and Delete links of an inactive plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_activate_and_delete_for_inactive_plugin() {
		$expected_actions = array(
			'activate' => '<a href="' . $this->get_url( 'activate', 'activate-plugin_' . $this->get_file() ) . '" id="activate-' . $this->slug . '" class="edit" aria-label="Activate My Plugin">Activate</a>',
		);

		if ( ! is_multisite() ) {
			$expected_actions['delete'] = $this->get_delete_link();
		}

		$this->assertSame( $expected_actions, $this->get_actions() );
	}

	/**
	 * Tests that a plugin whose requirements are not met cannot be activated.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unmet_requirements
	 *
	 * @param array $plugin_data The plugin's requirement headers.
	 */
	public function test_should_not_activate_plugin_with_unmet_requirements( $plugin_data ) {
		$this->assertSame( '<span>Cannot Activate</span>', $this->get_actions( $plugin_data )['activate'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unmet_requirements() {
		return array(
			'PHP version'       => array( array( 'RequiresPHP' => '99.0' ) ),
			'WordPress version' => array( array( 'RequiresWP' => '99.0' ) ),
		);
	}

	/**
	 * Tests that a plugin with unmet dependencies cannot be activated.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_activate_plugin_with_unmet_dependencies() {
		$this->set_property_value( 'dependencies', array( $this->get_file() => array( 'missing' ) ) );
		$this->set_property_value( 'dependency_filepaths', array( 'missing' => false ) );

		$this->assertSame(
			'Activate<span class="screen-reader-text">You cannot activate this plugin as it has unmet requirements.</span>',
			$this->get_actions()['activate']
		);
	}

	/**
	 * Tests that a plugin that other plugins require cannot be deleted, unless the dependency is circular.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_circular_dependencies
	 *
	 * @param bool $circular Whether the dependency is circular.
	 */
	public function test_delete_link_for_plugin_with_dependents( $circular ) {
		$this->set_dependent( false );

		if ( $circular ) {
			$this->set_property_value( 'circular_dependencies_slugs', array( $this->slug ) );
		}

		$this->assertSame(
			$circular
				? $this->get_delete_link()
				: 'Delete<span class="screen-reader-text">You cannot delete this plugin as other plugins require it.</span>',
			$this->get_actions()['delete']
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_circular_dependencies() {
		return array(
			'regular dependency'  => array( false ),
			'circular dependency' => array( true ),
		);
	}

	/**
	 * Tests that a user who cannot manage plugins gets no action links.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_active_states
	 *
	 * @param bool $active Whether the plugin is active.
	 */
	public function test_should_not_add_links_for_user_without_capabilities( $active ) {
		wp_set_current_user( self::$subscriber_id );

		if ( $active ) {
			$this->activate();
			$this->pause();
		}

		$this->assertSame( array(), $this->get_actions() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_active_states() {
		return array(
			'active plugin'   => array( true ),
			'inactive plugin' => array( false ),
		);
	}

	/**
	 * Tests that a network-active plugin only shows that it is network active on a site.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_only_show_network_active_on_site_screen() {
		update_site_option( 'active_sitewide_plugins', array( $this->get_file() => time() ) );

		$this->assertSame( array( 'network_active' => 'Network Active' ), $this->get_actions() );
	}

	/**
	 * Tests that an inactive network-only plugin shows "Network Only" on a site's Plugins screen.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 */
	public function test_should_show_network_only_for_inactive_network_only_plugin() {
		$this->assertSame(
			array( 'network_only' => 'Network Only' ),
			$this->get_actions( array(), " * Network: true\n" )
		);
	}

	/**
	 * Tests that the filter receives the plugin file, data and context.
	 *
	 * @ticket 65819
	 */
	public function test_should_pass_plugin_and_context_to_filter() {
		$filter = new MockAction();
		add_filter( 'plugin_action_links', array( $filter, 'filter' ), 10, 4 );

		$distinctive_link = '<a href="https://example.org/distinctive-action">Distinctive Site Link</a>';
		add_filter(
			'plugin_action_links',
			static function ( $actions ) use ( $distinctive_link ) {
				$actions['distinctive'] = $distinctive_link;
				return $actions;
			},
			20
		);

		$this->get_actions();

		$args = $filter->get_args();

		$this->assertSame( $this->get_file(), $args[0][1], 'The filter should receive the plugin file.' );
		$this->assertSame( 'My Plugin', $args[0][2]['Name'], 'The filter should receive the plugin data.' );
		$this->assertSame( 'all', $args[0][3], 'The filter should receive the context.' );

		$this->assertStringContainsString(
			$distinctive_link,
			$this->row_output,
			'The rendered row should contain action links added by filters.'
		);
	}

	/**
	 * Captures the actions passed to the plugin_action_links filter.
	 *
	 * @param array $actions The action links.
	 * @return array The unchanged action links.
	 */
	public function capture_actions( $actions ) {
		$this->actions = $actions;

		return $actions;
	}

	/**
	 * Renders the row of the test plugin and returns its action links.
	 *
	 * @param array  $plugin_data The plugin data to add.
	 * @param string $headers     Additional plugin headers for the file on multisite.
	 * @return array The action links.
	 */
	private function get_actions( $plugin_data = array(), $headers = '' ) {
		// is_network_only_plugin() reads the plugin headers on multisite.
		if ( is_multisite() ) {
			if ( ! is_writable( WP_PLUGIN_DIR ) ) {
				$this->markTestSkipped( 'WP_PLUGIN_DIR is not writable.' );
			}

			$dir = WP_PLUGIN_DIR . '/' . $this->slug;
			$this->remove_plugin();
			$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
			$this->assertNotFalse(
				file_put_contents(
					$dir . '/' . $this->slug . '.php',
					"<?php\n/**\n * Plugin Name: My Plugin\n{$headers} */\n"
				),
				'The test plugin should be written.'
			);

			wp_clean_plugins_cache( false );
		}

		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );

		$this->row_output = get_echo(
			array( $table, 'single_row' ),
			array(
				array(
					$this->get_file(),
					array_merge(
						array(
							'Name'        => 'My Plugin',
							'slug'        => $this->slug,
							'Description' => '',
							'Version'     => '1.0',
							'Author'      => '',
							'AuthorURI'   => '',
							'PluginURI'   => '',
						),
						$plugin_data
					),
				),
			)
		);

		return $this->actions;
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
	 * Returns the expected URL of an action.
	 *
	 * @param string $action The action.
	 * @param string $nonce  The nonce action.
	 * @return string The escaped URL.
	 */
	private function get_url( $action, $nonce ) {
		return "plugins.php?action={$action}&amp;plugin={$this->slug}%2F{$this->slug}.php&amp;plugin_status=all&amp;paged=1&amp;s&amp;_wpnonce=" . wp_create_nonce( $nonce );
	}

	/**
	 * Returns the expected Delete URL.
	 *
	 * @return string The escaped URL.
	 */
	private function get_delete_url() {
		return "plugins.php?action=delete-selected&amp;checked%5B0%5D={$this->slug}%2F{$this->slug}.php&amp;plugin_status=all&amp;paged=1&amp;s&amp;_wpnonce=" . wp_create_nonce( 'bulk-plugins' );
	}

	/**
	 * Returns the expected Delete link markup.
	 *
	 * @return string The Delete link markup.
	 */
	private function get_delete_link() {
		return '<a href="' . $this->get_delete_url() . '" id="delete-' . $this->slug . '" class="delete" aria-label="Delete My Plugin">Delete</a>';
	}

	/**
	 * Activates the test plugin.
	 */
	private function activate() {
		update_option( 'active_plugins', array_merge( get_option( 'active_plugins' ), array( $this->get_file() ) ) );
	}

	/**
	 * Pauses the test plugin.
	 */
	private function pause() {
		$GLOBALS['_paused_plugins'] = array(
			$this->slug => array(
				'type'    => E_ERROR,
				'line'    => 1,
				'file'    => WP_PLUGIN_DIR . '/' . $this->get_file(),
				'message' => 'Error.',
			),
		);
	}

	/**
	 * Makes another plugin require the test plugin.
	 *
	 * @param bool $active Whether the other plugin is active.
	 */
	private function set_dependent( $active ) {
		$this->set_property_value( 'plugins', array( 'dependent/dependent.php' => array( 'Name' => 'Dependent' ) ) );
		$this->set_property_value( 'dependencies', array( 'dependent/dependent.php' => array( $this->slug ) ) );
		$this->set_property_value( 'dependency_slugs', array( $this->slug ) );

		if ( $active ) {
			update_option( 'active_plugins', array_merge( (array) get_option( 'active_plugins', array() ), array( 'dependent/dependent.php' ) ) );
		}
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
