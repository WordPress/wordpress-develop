<?php

/**
 * Tests for the configuration, the views and the display of WP_Plugin_Install_List_Table.
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_wpPluginInstallListTableViews extends WP_UnitTestCase {

	/**
	 * The original values of the globals and the request URI that the tests change.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * Loads the tested files.
	 */
	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	/**
	 * Saves the globals and the request URI.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'tabs', 'tab' ) as $name ) {
			$this->original['globals'][ $name ] = $GLOBALS[ $name ] ?? null;
		}

		$this->original['request']     = $_REQUEST;
		$this->original['request_uri'] = $_SERVER['REQUEST_URI'] ?? null;

		$_REQUEST               = array();
		$_SERVER['REQUEST_URI'] = '/wp-admin/plugin-install.php';
	}

	/**
	 * Restores the globals and the request URI.
	 */
	public function tear_down() {
		foreach ( $this->original['globals'] as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		$_REQUEST = $this->original['request'];

		if ( null === $this->original['request_uri'] ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original['request_uri'];
		}

		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Returns a new table.
	 *
	 * @return WP_Plugin_Install_List_Table The table.
	 */
	private function get_table() {
		return _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );
	}

	/**
	 * Calls a protected method of a table.
	 *
	 * @param WP_Plugin_Install_List_Table $table  The table.
	 * @param string                       $method The name of the method.
	 * @return mixed The return value of the method.
	 */
	private function call_protected( $table, $method ) {
		$reflection = new ReflectionMethod( $table, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invoke( $table );
	}

	/**
	 * Tests that the capability to install plugins is required for Ajax requests.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::ajax_user_can
	 */
	public function test_ajax_user_can_should_require_the_install_plugins_capability() {
		$table = $this->get_table();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( $table->ajax_user_can(), 'A user without the capability should not be allowed.' );

		// Administrators cannot install plugins on a network, but super admins can.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		wp_set_current_user( $admin_id );

		try {
			$this->assertTrue( $table->ajax_user_can(), 'A user with the capability should be allowed.' );
		} finally {
			if ( is_multisite() ) {
				revoke_super_admin( $admin_id );
			}
		}
	}

	/**
	 * Tests the classes of the table.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::get_table_classes
	 */
	public function test_get_table_classes_should_return_the_classes_of_the_plugin_install_table() {
		$this->assertSame( array( 'widefat', 'plugin-install' ), $this->call_protected( $this->get_table(), 'get_table_classes' ) );
	}

	/**
	 * Tests that the table has no columns.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::get_columns
	 */
	public function test_get_columns_should_be_empty() {
		$this->assertSame( array(), $this->get_table()->get_columns() );
	}

	/**
	 * Tests the slugs of the installed plugins.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::get_installed_plugin_slugs
	 */
	public function test_get_installed_plugin_slugs_should_list_the_slugs_of_the_plugins_with_and_without_updates() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'a/a.php'             => (object) array( 'slug' => 'a' ),
					'no-slug/no-slug.php' => (object) array( 'plugin' => 'no-slug/no-slug.php' ),
				),
				'response'  => array( 'b/b.php' => (object) array( 'slug' => 'b' ) ),
			)
		);

		$this->assertSame( array( 'a', 'b' ), $this->call_protected( $this->get_table(), 'get_installed_plugin_slugs' ) );
	}

	/**
	 * Tests that there are no slugs without plugins.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::get_installed_plugin_slugs
	 */
	public function test_get_installed_plugin_slugs_should_be_empty_without_plugin_information() {
		$this->assertSame( array(), $this->call_protected( $this->get_table(), 'get_installed_plugin_slugs' ) );
	}

	/**
	 * Tests the views of the tabs.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::get_views
	 */
	public function test_get_views_should_link_the_tabs_and_leave_out_the_upload_tab() {
		$GLOBALS['tabs'] = array(
			'featured' => 'Featured',
			'popular'  => 'Popular',
			'upload'   => 'Upload Plugin',
		);
		$GLOBALS['tab']  = 'popular';

		$views = $this->get_table()->get_views();

		$this->assertSame( array( 'plugin-install-featured', 'plugin-install-popular' ), array_keys( $views ), 'The upload tab should not be listed.' );
		$this->assertSame( '<a href="' . self_admin_url( 'plugin-install.php?tab=featured' ) . '">Featured</a>', $views['plugin-install-featured'] );
		$this->assertSame( '<a href="' . self_admin_url( 'plugin-install.php?tab=popular' ) . '" class="current" aria-current="page">Popular</a>', $views['plugin-install-popular'], 'The current tab should be marked.' );
	}

	/**
	 * Tests the introduction, the tabs and the search form above the table.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::views
	 */
	public function test_views_should_output_the_introduction_the_tabs_and_the_search_form() {
		$GLOBALS['tabs'] = array(
			'featured' => 'Featured',
			'popular'  => 'Popular',
		);
		$GLOBALS['tab']  = 'featured';

		$output = get_echo( array( $this->get_table(), 'views' ) );

		$this->assertStringContainsString( '<p>Plugins extend and expand the functionality of WordPress. You may install plugins from the <a href="https://wordpress.org/plugins/">WordPress Plugin Directory</a>', $output, 'The introduction should be output.' );
		$this->assertStringContainsString( '<div class="wp-filter">', $output );
		$this->assertStringContainsString( "<li class='plugin-install-featured'><a href=\"" . self_admin_url( 'plugin-install.php?tab=featured' ) . '" class="current" aria-current="page">Featured</a> </li>', $output, 'The first tab should be listed.' );
		$this->assertStringContainsString( "<li class='plugin-install-popular'><a href=\"" . self_admin_url( 'plugin-install.php?tab=popular' ) . '">Popular</a></li>', $output, 'The last tab should be listed.' );
		$this->assertStringContainsString( '<form class="search-form search-plugins" method="get">', $output, 'The search form should be output.' );
	}

	/**
	 * Tests that the search form is not output for the favorites.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::views
	 */
	public function test_views_should_not_output_the_search_form_for_the_favorites() {
		$GLOBALS['tabs'] = array( 'favorites' => 'Favorites' );
		$GLOBALS['tab']  = 'favorites';

		$output = get_echo( array( $this->get_table(), 'views' ) );

		$this->assertStringContainsString( "<li class='plugin-install-favorites'>", $output );
		$this->assertStringNotContainsString( 'search-plugins', $output );
	}

	/**
	 * Tests that the views can be filtered.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::views
	 */
	public function test_views_should_use_the_views_of_the_filter() {
		$GLOBALS['tabs'] = array( 'featured' => 'Featured' );
		$GLOBALS['tab']  = 'featured';

		add_filter(
			'views_plugin-install',
			static function () {
				return array( 'custom' => '<a href="#custom">Custom</a>' );
			}
		);

		$output = get_echo( array( $this->get_table(), 'views' ) );

		$this->assertStringContainsString( "<li class='custom'><a href=\"#custom\">Custom</a></li>", $output );
		$this->assertStringNotContainsString( 'plugin-install-featured', $output );
	}

	/**
	 * Tests that no list of tabs is output without views.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::views
	 */
	public function test_views_should_output_an_empty_list_without_views() {
		$GLOBALS['tabs'] = array();
		$GLOBALS['tab']  = 'featured';

		$output = get_echo( array( $this->get_table(), 'views' ) );

		$this->assertMatchesRegularExpression( '/<ul class="filter-links">\s*<\/ul>/', $output );
	}

	/**
	 * Tests the table with its navigation and the list.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::display
	 */
	public function test_display_should_output_the_navigation_around_the_list() {
		$GLOBALS['tab'] = 'popular';

		$table = $this->get_table();
		$table->set_pagination_args(
			array(
				'total_items' => 100,
				'per_page'    => 36,
			)
		);

		$output = get_echo( array( $table, 'display' ) );

		$this->assertSame( 1, substr_count( $output, '<div class="tablenav top">' ), 'The navigation above the list should be output.' );
		$this->assertSame( 1, substr_count( $output, '<div class="tablenav bottom">' ), 'The navigation below the list should be output.' );
		$this->assertStringContainsString( '<div class="wp-list-table widefat plugin-install">', $output, 'The classes of the table should be used.' );
		$this->assertStringContainsString( '<div id="the-list">', $output, 'The list should be output.' );
		$this->assertLessThan( strpos( $output, 'id="the-list"' ), strpos( $output, 'tablenav top' ), 'The navigation should come before the list.' );
		$this->assertGreaterThan( strpos( $output, 'id="the-list"' ), strpos( $output, 'tablenav bottom' ), 'The navigation should come after the list.' );
	}

	/**
	 * Tests that the list is output without navigation for the featured plugins.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::display
	 */
	public function test_display_should_not_output_the_navigation_for_the_featured_plugins() {
		$GLOBALS['tab'] = 'featured';

		$output = get_echo( array( $this->get_table(), 'display' ) );

		$this->assertStringNotContainsString( 'tablenav', $output );
		$this->assertStringContainsString( '<div id="the-list"', $output );
	}

	/**
	 * Tests the placeholder of an empty list.
	 *
	 * @ticket 66554
	 *
	 * @covers WP_Plugin_Install_List_Table::display
	 */
	public function test_display_should_output_the_message_for_an_empty_list() {
		$GLOBALS['tab'] = 'featured';

		$output = get_echo( array( $this->get_table(), 'display' ) );

		$this->assertStringContainsString( 'No plugins found. Try a different search.', $output );
	}
}
