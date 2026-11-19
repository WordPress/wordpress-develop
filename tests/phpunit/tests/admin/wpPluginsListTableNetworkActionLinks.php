<?php

require_once __DIR__ . '/plugin-dependencies/base.php';

/**
 * Tests for the action links WP_Plugins_List_Table::single_row() adds on the network admin Plugins screen.
 *
 * @group admin
 * @group plugins
 * @group ms-required
 *
 * @covers WP_Plugins_List_Table::single_row
 */
class Tests_Admin_wpPluginsListTableNetworkActionLinks extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Super admin user ID.
	 *
	 * @var int
	 */
	private static $super_admin_id;

	/**
	 * Site administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original globals the list table reads.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * The actions passed to the network_admin_plugin_action_links filter.
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
		self::$super_admin_id = $factory->user->create();
		self::$admin_id       = $factory->user->create( array( 'role' => 'administrator' ) );

		grant_super_admin( self::$super_admin_id );
	}

	/**
	 * Revokes the super admin grant before the fixture users are deleted.
	 */
	public static function wpTearDownAfterClass() {
		revoke_super_admin( self::$super_admin_id );
	}

	/**
	 * Sets up the network admin Plugins screen, the list table globals and the dependency state.
	 */
	public function set_up() {
		parent::set_up();

		$this->original_globals = array();
		foreach ( array( 'status', 'page', 's', 'totals' ) as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->original_globals[ $name ] = $GLOBALS[ $name ];
			}
		}

		set_current_screen( 'plugins-network' );

		$GLOBALS['status'] = 'all';
		$GLOBALS['page']   = 1;
		$GLOBALS['s']      = '';
		$GLOBALS['totals'] = array();

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
		$this->slug       = 'network-action-links-plugin-' . wp_generate_password( 8, false );

		add_filter( 'network_admin_plugin_action_links', array( $this, 'capture_actions' ), PHP_INT_MAX );

		wp_set_current_user( self::$super_admin_id );
	}

	/**
	 * Restores the globals and removes the test plugin.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		foreach ( array( 'status', 'page', 's', 'totals' ) as $name ) {
			if ( ! array_key_exists( $name, $this->original_globals ) ) {
				unset( $GLOBALS[ $name ] );
			}
		}

		$this->remove_plugin();

		parent::tear_down();
	}

	/**
	 * Tests the Network Deactivate link of a network-active plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_network_deactivate_for_network_active_plugin() {
		$this->network_activate();

		$this->assertSame(
			array(
				'deactivate' => '<a href="' . $this->get_url( 'deactivate', 'deactivate-plugin_' . $this->get_file() ) . '" id="deactivate-' . $this->slug . '" aria-label="Network Deactivate My Plugin">Network Deactivate</a>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests that a network-active plugin that other active plugins require cannot be deactivated.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_link_network_deactivate_for_plugin_with_active_dependents() {
		$this->set_dependent();
		$this->network_activate();

		$this->assertSame(
			array(
				'deactivate' => 'Network Deactivate<span class="screen-reader-text">You cannot deactivate this plugin as other plugins require it.</span>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests that an inactive dependent does not block Network Deactivate.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_network_deactivate_when_dependent_is_inactive() {
		$this->set_dependent( false );
		$this->network_activate();

		$this->assertSame(
			array(
				'deactivate' => '<a href="' . $this->get_url( 'deactivate', 'deactivate-plugin_' . $this->get_file() ) . '" id="deactivate-' . $this->slug . '" aria-label="Network Deactivate My Plugin">Network Deactivate</a>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests the Network Activate and Delete links of a plugin that is not network active.
	 *
	 * @ticket 65819
	 */
	public function test_should_link_network_activate_and_delete() {
		$this->assertSame(
			array(
				'activate' => '<a href="' . $this->get_url( 'activate', 'activate-plugin_' . $this->get_file() ) . '" id="activate-' . $this->slug . '" class="edit" aria-label="Network Activate My Plugin">Network Activate</a>',
				'delete'   => '<a href="' . $this->get_delete_url() . '" id="delete-' . $this->slug . '" class="delete" aria-label="Delete My Plugin">Delete</a>',
			),
			$this->get_actions()
		);
	}

	/**
	 * Tests that a plugin that is active on the current site cannot be deleted.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_link_delete_for_plugin_active_on_site() {
		update_option( 'active_plugins', array( $this->get_file() ) );

		$this->assertSame( array( 'activate' ), array_keys( $this->get_actions() ) );
	}

	/**
	 * Tests that a plugin whose requirements are not met cannot be network activated.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unmet_requirements
	 *
	 * @param array $plugin_data The plugin's requirement headers.
	 */
	public function test_should_not_network_activate_plugin_with_unmet_requirements( $plugin_data ) {
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
	 * Tests that a plugin with unmet dependencies cannot be network activated.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unmet_dependencies
	 *
	 * @param string|false $filepath The filepath of the dependency.
	 */
	public function test_should_not_network_activate_plugin_with_unmet_dependencies( $filepath ) {
		if ( false !== $filepath ) {
			$this->set_property_value( 'plugins', array( $filepath => array( 'Name' => 'Dependency' ) ) );
		}

		$this->set_property_value( 'dependencies', array( $this->get_file() => array( 'dependency' ) ) );
		$this->set_property_value( 'dependency_filepaths', array( 'dependency' => $filepath ) );

		$this->assertSame(
			'Network Activate<span class="screen-reader-text">You cannot activate this plugin as it has unmet requirements.</span>',
			$this->get_actions()['activate']
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unmet_dependencies() {
		return array(
			'missing dependency'             => array( false ),
			'installed, inactive dependency' => array( 'dependency/dependency.php' ),
		);
	}

	/**
	 * Tests that a plugin that other plugins require cannot be deleted, unless the dependency is circular.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_circular_dependencies
	 *
	 * @param bool $circular Whether the dependency is circular.
	 */
	public function test_delete_link_for_plugin_with_dependents( $circular ) {
		$this->set_dependent( false );

		if ( $circular ) {
			$this->set_property_value(
				'dependencies',
				array(
					'dependent/dependent.php' => array( $this->slug ),
					$this->get_file()         => array( 'dependent' ),
				)
			);
			$this->set_property_value( 'circular_dependencies_slugs', array( $this->slug, 'dependent' ) );
		}

		$this->assertSame(
			$circular
				? '<a href="' . $this->get_delete_url() . '" id="delete-' . $this->slug . '" class="delete" aria-label="Delete My Plugin">Delete</a>'
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
	 * Tests that a site administrator gets no action links on the network screen.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_network_active_states
	 *
	 * @param bool $network_active Whether the plugin is network active.
	 */
	public function test_should_not_add_links_for_user_without_network_capabilities( $network_active ) {
		wp_set_current_user( self::$admin_id );

		if ( $network_active ) {
			$this->network_activate();
		}

		$this->assertSame( array(), $this->get_actions() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_network_active_states() {
		return array(
			'network-active plugin' => array( true ),
			'inactive plugin'       => array( false ),
		);
	}

	/**
	 * Tests that only the network filters receive the links, with the plugin file, data and context.
	 *
	 * @ticket 65819
	 */
	public function test_should_pass_plugin_and_context_to_network_filters() {
		$network_filter = new MockAction();
		$file_filter    = new MockAction();
		$site_filter    = new MockAction();
		add_filter( 'network_admin_plugin_action_links', array( $network_filter, 'filter' ), 10, 4 );
		add_filter( "network_admin_plugin_action_links_{$this->get_file()}", array( $file_filter, 'filter' ), 10, 4 );
		add_filter( 'plugin_action_links', array( $site_filter, 'filter' ) );

		$distinctive_link = '<a href="https://example.org/distinctive-network-action">Distinctive Network Link</a>';
		add_filter(
			"network_admin_plugin_action_links_{$this->get_file()}",
			static function ( $actions ) use ( $distinctive_link ) {
				$actions['distinctive'] = $distinctive_link;
				return $actions;
			},
			20
		);

		$this->get_actions();

		$args = $network_filter->get_args();

		$this->assertCount( 1, $args, 'The general network filter should run once.' );

		$this->assertSame( $this->get_file(), $args[0][1], 'The filter should receive the plugin file.' );
		$this->assertSame( 'My Plugin', $args[0][2]['Name'], 'The filter should receive the plugin data.' );
		$this->assertSame( 'all', $args[0][3], 'The filter should receive the context.' );
		$this->assertSame( $args, $file_filter->get_args(), 'The plugin-specific filter should receive the same actions, plugin file, plugin data and context.' );
		$this->assertSame( 0, $site_filter->get_call_count(), 'The site filter should not run.' );

		$this->assertStringContainsString(
			$distinctive_link,
			$this->row_output,
			'The rendered row should contain action links added by filters.'
		);
	}

	/**
	 * Captures the actions passed to the network_admin_plugin_action_links filter.
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
	 * @param array $plugin_data The plugin data to add.
	 * @return array The action links.
	 */
	private function get_actions( $plugin_data = array() ) {
		if ( ! is_writable( WP_PLUGIN_DIR ) ) {
			$this->markTestSkipped( 'WP_PLUGIN_DIR is not writable.' );
		}

		$dir = WP_PLUGIN_DIR . '/' . $this->slug;
		$this->remove_plugin();
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse( file_put_contents( $dir . '/' . $this->slug . '.php', "<?php\n/**\n * Plugin Name: My Plugin\n */\n" ), 'The test plugin should be written.' );

		wp_clean_plugins_cache( false );

		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins-network' ) );

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

		$this->assertIsArray( $this->actions, 'The action links filter should run.' );

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
	 * Activates the test plugin network-wide.
	 */
	private function network_activate() {
		$active                      = get_site_option( 'active_sitewide_plugins', array() );
		$active[ $this->get_file() ] = time();
		update_site_option( 'active_sitewide_plugins', $active );
	}

	/**
	 * Sets a dependent plugin for the test plugin.
	 *
	 * @param bool $active Whether the dependent plugin should be network active. Default true.
	 */
	private function set_dependent( $active = true ) {
		$this->set_property_value( 'plugins', array( 'dependent/dependent.php' => array( 'Name' => 'Dependent' ) ) );
		$this->set_property_value( 'dependencies', array( 'dependent/dependent.php' => array( $this->slug ) ) );
		$this->set_property_value( 'dependency_slugs', array( $this->slug ) );

		if ( $active ) {
			$active_plugins                            = get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins['dependent/dependent.php'] = time();
			update_site_option( 'active_sitewide_plugins', $active_plugins );
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
