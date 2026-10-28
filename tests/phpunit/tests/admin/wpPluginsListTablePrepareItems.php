<?php

/**
 * Tests for how WP_Plugins_List_Table::prepare_items() sorts plugins into views.
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::prepare_items
 */
class Tests_Admin_wpPluginsListTablePrepareItems extends WP_UnitTestCase {

	/**
	 * Plugin files that exist in the test plugins directory.
	 *
	 * @var string[]
	 */
	private static $files = array( 'hello.php', 'internationalized-plugin.php', 'internationalized-plugin-2.php', 'custom-internationalized-plugin/custom-internationalized-plugin.php' );

	/**
	 * Administrator user ID, a super admin on multisite.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original globals the list table sets.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * The plugin lists passed to the plugins_list filter.
	 *
	 * @var array|null
	 */
	private $lists;

	/**
	 * Sets up the user.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

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
	 * Sets up the Plugins screen, the plugin list and the options.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'status', 'plugins', 'totals', 'page', 'orderby', 'order', 's', '_paused_plugins', 'current_screen' ) as $name ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
		}

		set_current_screen( 'plugins' );
		$GLOBALS['s']               = '';
		$GLOBALS['_paused_plugins'] = array();

		update_option( 'active_plugins', array() );
		update_option( 'recently_activated', array() );
		update_site_option( 'active_sitewide_plugins', array() );
		update_site_option( 'auto_update_plugins', array() );
		delete_site_transient( 'update_plugins' );

		add_filter( 'all_plugins', array( $this, 'get_all_plugins' ) );
		add_filter( 'show_advanced_plugins', '__return_false' );
		add_filter( 'plugins_auto_update_enabled', '__return_false' );

		$this->lists = null;
		add_filter( 'plugins_list', array( $this, 'capture_lists' ), PHP_INT_MAX );

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Restores the globals and the request.
	 */
	public function tear_down() {
		foreach ( $this->original_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		unset( $_REQUEST['plugin_status'] );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that plugins are sorted into the active and inactive views.
	 *
	 * @ticket 65819
	 */
	public function test_should_sort_plugins_into_active_and_inactive() {
		update_option( 'active_plugins', array( 'hello.php', 'internationalized-plugin.php' ) );

		$this->prepare();

		$this->assertSame( array( 'hello.php', 'internationalized-plugin.php' ), array_keys( $this->lists['active'] ), 'The active plugins should be listed.' );
		$this->assertSame( array( 'internationalized-plugin-2.php', self::$files[3] ), array_keys( $this->lists['inactive'] ), 'The inactive plugins should be listed.' );
		$this->assertSame( 4, $GLOBALS['totals']['all'], 'All plugins should be counted.' );
		$this->assertSame( 2, $GLOBALS['totals']['active'], 'The active plugins should be counted.' );
	}

	/**
	 * Tests that old recently activated entries are removed and recent ones are listed.
	 *
	 * @ticket 65819
	 */
	public function test_should_prune_and_list_recently_activated_plugins() {
		update_option(
			'recently_activated',
			array(
				'hello.php'                      => time() - DAY_IN_SECONDS,
				'internationalized-plugin.php'   => time() - 2 * WEEK_IN_SECONDS,
				'internationalized-plugin-2.php' => (string) time(),
			)
		);

		$this->prepare();

		$this->assertSame( array( 'hello.php' ), array_keys( get_option( 'recently_activated' ) ), 'Old and invalid entries should be removed.' );
		$this->assertSame( array( 'hello.php' ), array_keys( $this->lists['recently_activated'] ), 'Recent inactive plugins should be listed.' );
	}

	/**
	 * Tests the update information added to each plugin.
	 *
	 * On a site in a network, only the network admin screen lists updates.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_add_update_information() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'response'  => array(
					'hello.php' => (object) array(
						'new_version' => '9.9',
						'Name'        => 'Not Used',
					),
				),
				'no_update' => array(
					'internationalized-plugin.php' => (object) array( 'new_version' => '1.0.0' ),
				),
			)
		);

		$this->prepare();

		$this->assertSame( array( 'hello.php' ), array_keys( $this->lists['upgrade'] ), 'Plugins with an update should be listed.' );
		$this->assertTrue( $this->lists['all']['hello.php']['update'], 'The plugin should be flagged as having an update.' );
		$this->assertSame( '9.9', $this->lists['upgrade']['hello.php']['new_version'], 'The update details should be added.' );
		$this->assertSame( 'Hello Dolly', $this->lists['all']['hello.php']['Name'], 'The plugin data should take precedence.' );
		$this->assertTrue( $this->lists['all']['hello.php']['update-supported'], 'A plugin with an update should support updates.' );
		$this->assertTrue( $this->lists['all']['internationalized-plugin.php']['update-supported'], 'A plugin without an update should support updates.' );
		$this->assertFalse( $this->lists['all']['internationalized-plugin-2.php']['update-supported'], 'An unknown plugin should not support updates.' );
	}

	/**
	 * Tests that paused active plugins are listed.
	 *
	 * @ticket 65819
	 */
	public function test_should_list_paused_plugins() {
		update_option( 'active_plugins', array( 'hello.php', 'internationalized-plugin.php' ) );
		$GLOBALS['_paused_plugins'] = array(
			'hello.php' => array(
				'type'    => E_ERROR,
				'line'    => 1,
				'file'    => WP_PLUGIN_DIR . '/hello.php',
				'message' => 'Error.',
			),
		);

		$this->prepare();

		$this->assertSame( array( 'hello.php' ), array_keys( $this->lists['paused'] ) );
	}

	/**
	 * Tests the auto-update views.
	 *
	 * On a site in a network, the auto-update views are only on the network admin screen.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_sort_plugins_into_auto_update_views() {
		add_filter( 'plugins_auto_update_enabled', '__return_true', 11 );
		update_site_option( 'auto_update_plugins', array( 'hello.php', 'internationalized-plugin.php' ) );
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'hello.php'                      => (object) array( 'new_version' => '1.7.2' ),
					'internationalized-plugin-2.php' => (object) array( 'new_version' => '1.0.0' ),
				),
			)
		);
		add_filter(
			'auto_update_plugin',
			static function ( $update, $item ) {
				return self::$files[3] === $item->plugin ? true : $update;
			},
			10,
			2
		);

		$this->prepare();

		$this->assertSame( array( 'hello.php', self::$files[3] ), array_keys( $this->lists['auto-update-enabled'] ), 'Enabled and forced plugins should be listed.' );
		$this->assertSame( array( 'internationalized-plugin.php', 'internationalized-plugin-2.php' ), array_keys( $this->lists['auto-update-disabled'] ), 'Unsupported and disabled plugins should be listed.' );
	}

	/**
	 * Tests that a search term switches to the search view.
	 *
	 * @ticket 65819
	 */
	public function test_should_switch_to_search_view() {
		$GLOBALS['s'] = 'Hello';

		$table = $this->prepare();

		$this->assertSame( 'search', $GLOBALS['status'] );
		$this->assertSame( array( 'hello.php' ), array_keys( $table->items ), 'Only the matching plugin should be listed.' );
	}

	/**
	 * Tests that an empty view falls back to all plugins.
	 *
	 * @ticket 65819
	 */
	public function test_should_fall_back_to_all_plugins_for_empty_view() {
		$_REQUEST['plugin_status'] = 'active';

		$table = $this->prepare();

		$this->assertSame( 'all', $GLOBALS['status'], 'The status should fall back to all.' );
		$this->assertSameSets( self::$files, array_keys( $table->items ), 'All plugins should be listed.' );
	}

	/**
	 * Tests that the must-use and drop-in views can be shown.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 */
	public function test_should_respect_show_advanced_plugins_filter() {
		$filter = new MockAction();
		add_filter( 'show_advanced_plugins', array( $filter, 'filter' ), 10, 2 );

		$this->prepare();

		$this->assertSame( array( array( false, 'mustuse' ), array( false, 'dropins' ) ), $filter->get_args() );
	}

	/**
	 * Tests that network-active plugins are only listed on a site when allowed.
	 *
	 * @ticket 65819
	 *
	 * @group ms-required
	 *
	 * @dataProvider data_show_network_active
	 *
	 * @param bool $show Whether network-active plugins are shown.
	 */
	public function test_should_list_network_active_plugins_on_site_when_allowed( $show ) {
		update_site_option( 'active_sitewide_plugins', array( 'hello.php' => time() ) );
		add_filter( 'show_network_active_plugins', $show ? '__return_true' : '__return_false' );

		$this->prepare();

		$this->assertSame( $show, isset( $this->lists['all']['hello.php'] ), 'The plugin should only be listed when allowed.' );
		$this->assertSame( $show, isset( $this->lists['active']['hello.php'] ), 'The plugin should only be active when allowed.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_show_network_active() {
		return array(
			'shown'  => array( true ),
			'hidden' => array( false ),
		);
	}

	/**
	 * Returns the plugins of the test plugins directory.
	 *
	 * @return array The plugins.
	 */
	public function get_all_plugins() {
		$plugins = array();
		foreach ( self::$files as $file ) {
			$plugins[ $file ] = array(
				'Name'        => 'hello.php' === $file ? 'Hello Dolly' : $file,
				'Description' => '',
				'Version'     => '1.0',
				'Author'      => '',
				'AuthorURI'   => '',
				'PluginURI'   => '',
				'TextDomain'  => '',
				'DomainPath'  => '',
			);
		}

		return $plugins;
	}

	/**
	 * Captures the plugin lists.
	 *
	 * @param array $lists The plugin lists.
	 * @return array The unchanged lists.
	 */
	public function capture_lists( $lists ) {
		$this->lists = $lists;

		return $lists;
	}

	/**
	 * Prepares a Plugins list table.
	 *
	 * @return WP_Plugins_List_Table The list table.
	 */
	private function prepare() {
		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
		$table->prepare_items();

		return $table;
	}
}
