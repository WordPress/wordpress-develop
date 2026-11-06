<?php

/**
 * Tests for WP_Plugin_Install_List_Table::prepare_items().
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Install_List_Table::prepare_items
 */
class Tests_Admin_wpPluginInstallListTablePrepareItems extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original values of the globals and the request data that the tests change.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * The arguments that the Plugin Installation API received.
	 *
	 * @var array[]
	 */
	private $api_requests = array();

	/**
	 * The response of the Plugin Installation API.
	 *
	 * @var object|WP_Error
	 */
	private $api_response;

	/**
	 * Creates the administrator, and loads the tested file.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

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
	 * Saves the globals and the request data, and answers the Plugin Installation API.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'tabs', 'tab', 'paged', 'type', 'term' ) as $name ) {
			$this->original['globals'][ $name ] = $GLOBALS[ $name ] ?? null;
		}

		$this->original['request'] = $_REQUEST;
		$this->original['get']     = $_GET;

		$_REQUEST = array();
		$_GET     = array();

		$this->api_requests = array();
		$this->api_response = (object) array(
			'plugins' => array(),
			'info'    => array( 'results' => 0 ),
		);

		add_filter( 'plugins_api', array( $this, 'answer_api' ), 10, 3 );

		// Use a version that is not a pre-release, so the beta tab is not listed.
		$this->original['wp_version'] = $GLOBALS['wp_version'];
		$this->original['wp_scripts'] = $GLOBALS['wp_scripts'] ?? null;
		$GLOBALS['wp_version']        = '7.0';
		$GLOBALS['wp_scripts']        = new WP_Scripts();
		wp_set_current_user( self::$admin_id );
		set_site_transient( 'update_plugins', (object) array() );
	}

	/**
	 * Restores the globals and the request data.
	 */
	public function tear_down() {
		foreach ( $this->original['globals'] as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		$_REQUEST = $this->original['request'];
		$_GET     = $this->original['get'];

		$GLOBALS['wp_version'] = $this->original['wp_version'];
		$GLOBALS['wp_scripts'] = $this->original['wp_scripts'];

		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Answers the Plugin Installation API, and records the request.
	 *
	 * @param false|object|array $result The result.
	 * @param string             $action  The action.
	 * @param object             $args    The arguments.
	 * @return object|WP_Error The response.
	 */
	public function answer_api( $result, $action, $args ) {
		$this->api_requests[] = array( $action, (array) $args );

		return $this->api_response;
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
	 * Tests the tab that is used when none is requested.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_first_tab_when_no_tab_is_requested() {
		global $tab;

		$this->get_table()->prepare_items();

		$this->assertSame( 'featured', $tab );
		$this->assertCount( 1, $this->api_requests, 'The API should be asked once.' );
		$this->assertSame( 'query_plugins', $this->api_requests[0][0] );
		$this->assertSame( 'featured', $this->api_requests[0][1]['browse'] );
	}

	/**
	 * Tests the tab that is used when the requested tab is not valid.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_first_tab_when_the_requested_tab_is_not_valid() {
		global $tab;

		$_REQUEST['tab'] = 'unknown';

		$this->get_table()->prepare_items();

		$this->assertSame( 'featured', $tab );
	}

	/**
	 * Tests that a tab that is not in the menu can be requested.
	 *
	 * @ticket 66554
	 */
	public function test_should_keep_a_tab_that_is_not_in_the_menu() {
		global $tab;

		$_REQUEST['tab'] = 'plugin-information';

		$this->get_table()->prepare_items();

		$this->assertSame( 'plugin-information', $tab );
		$this->assertSame( array(), $this->api_requests, 'The API should not be asked for a tab that is not in the menu.' );
	}

	/**
	 * Tests the tabs that the table lists.
	 *
	 * @ticket 66554
	 */
	public function test_should_list_the_tabs() {
		global $tabs;

		$this->get_table()->prepare_items();

		$this->assertSame( array( 'featured', 'popular', 'recommended', 'favorites', 'upload' ), array_keys( $tabs ), 'The tabs should be listed in order.' );
		$this->assertSame( 'Featured', $tabs['featured'] );
		$this->assertSame( 'Upload Plugin', $tabs['upload'] );
	}

	/**
	 * Tests that the tab to upload plugins is for users who can upload plugins.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_list_the_upload_tab_for_a_user_who_cannot_upload_plugins() {
		global $tabs;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->get_table()->prepare_items();

		$this->assertArrayNotHasKey( 'upload', $tabs );
	}

	/**
	 * Tests the tab of the search results.
	 *
	 * @ticket 66554
	 */
	public function test_should_list_the_search_results_tab_first_for_a_search() {
		global $tabs, $tab;

		$_REQUEST['tab'] = 'search';

		$this->get_table()->prepare_items();

		$this->assertSame( 'search', $tab );
		$this->assertSame( 'search', array_key_first( $tabs ) );
		$this->assertSame( 'Search Results', $tabs['search'] );
	}

	/**
	 * Tests that the beta tab is listed when it is requested.
	 *
	 * @ticket 66554
	 */
	public function test_should_list_the_beta_tab_when_it_is_requested() {
		global $tabs, $tab;

		$_REQUEST['tab'] = 'beta';

		$this->get_table()->prepare_items();

		$this->assertSame( 'beta', $tab );
		$this->assertSame( 'Beta Testing', $tabs['beta'] );
		$this->assertSame( 'beta', $this->api_requests[0][1]['browse'] );
	}

	/**
	 * Tests that the beta tab is listed for a pre-release version.
	 *
	 * @ticket 66554
	 */
	public function test_should_list_the_beta_tab_first_for_a_pre_release_version() {
		global $tabs, $tab;

		$GLOBALS['wp_version'] = '7.0-beta1';

		$this->get_table()->prepare_items();

		$this->assertSame( 'beta', array_key_first( $tabs ) );
		$this->assertSame( 'beta', $tab, 'The first tab should be used.' );
	}

	/**
	 * Tests the tabs that can be changed by a filter.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_tabs_of_the_install_plugins_tabs_filter() {
		global $tabs, $tab;

		add_filter(
			'install_plugins_tabs',
			static function () {
				return array( 'custom' => 'Custom' );
			}
		);
		add_filter(
			'install_plugins_table_api_args_custom',
			static function () {
				return array(
					'page'     => 1,
					'per_page' => 12,
					'custom'   => true,
				);
			}
		);

		$this->get_table()->prepare_items();

		$this->assertSame( array( 'custom' => 'Custom' ), $tabs );
		$this->assertSame( 'custom', $tab );
		$this->assertTrue( $this->api_requests[0][1]['custom'] );
	}

	/**
	 * Tests the tabs that are not in the menu, which can be changed by a filter.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_tabs_of_the_install_plugins_nonmenu_tabs_filter() {
		global $tab;

		add_filter(
			'install_plugins_nonmenu_tabs',
			static function ( $tabs ) {
				$tabs[] = 'my-details';

				return $tabs;
			}
		);

		$_REQUEST['tab'] = 'my-details';

		$this->get_table()->prepare_items();

		$this->assertSame( 'my-details', $tab );
	}

	/**
	 * Tests the arguments for the API for the tabs that browse plugins.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_browse_tabs
	 *
	 * @param string $requested The requested tab.
	 */
	public function test_should_browse_the_plugins_of_the_tab( $requested ) {
		$_REQUEST['tab'] = $requested;

		$this->get_table()->prepare_items();

		$args = $this->api_requests[0][1];

		$this->assertSame( $requested, $args['browse'] );
		$this->assertSame( 1, $args['page'] );
		$this->assertSame( 36, $args['per_page'] );
		$this->assertSame( get_user_locale(), $args['locale'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_browse_tabs() {
		return array(
			'popular'  => array( 'popular' ),
			'featured' => array( 'featured' ),
			'beta'     => array( 'beta' ),
		);
	}

	/**
	 * Tests the page that is requested.
	 *
	 * @ticket 66554
	 */
	public function test_should_request_the_page_of_the_request() {
		global $paged;

		$_REQUEST['tab']   = 'popular';
		$_REQUEST['paged'] = '3';

		$this->get_table()->prepare_items();

		$this->assertSame( 3, $paged );
		$this->assertSame( 3, $this->api_requests[0][1]['page'] );
	}

	/**
	 * Tests the installed plugins that are sent for the recommended plugins.
	 *
	 * @ticket 66554
	 */
	public function test_should_send_the_installed_plugins_for_the_recommended_tab() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'a/a.php' => (object) array(
						'slug'   => 'a',
						'plugin' => 'a/a.php',
					),
				),
				'response'  => array(
					'b/b.php' => (object) array(
						'slug'   => 'b',
						'plugin' => 'b/b.php',
					),
				),
			)
		);

		$_REQUEST['tab'] = 'recommended';

		$this->get_table()->prepare_items();

		$args = $this->api_requests[0][1];

		$this->assertSame( 'recommended', $args['browse'] );
		$this->assertSame( array( 'a', 'b' ), $args['installed_plugins'] );
	}

	/**
	 * Tests that the installed plugins are not sent for the other tabs.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_send_the_installed_plugins_for_the_other_tabs() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'a/a.php' => (object) array(
						'slug'   => 'a',
						'plugin' => 'a/a.php',
					),
				),
			)
		);

		$_REQUEST['tab'] = 'popular';

		$this->get_table()->prepare_items();

		$this->assertArrayNotHasKey( 'installed_plugins', $this->api_requests[0][1] );
	}

	/**
	 * Tests the search arguments, by type of search.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_searches
	 *
	 * @param array $request  The request data.
	 * @param array $expected The expected arguments that the search adds, and the ones that it does not.
	 */
	public function test_should_search_the_plugins( array $request, array $expected ) {
		$_REQUEST = array_merge( array( 'tab' => 'search' ), $request );

		$this->get_table()->prepare_items();

		$args = $this->api_requests[0][1];

		foreach ( array( 'search', 'tag', 'author' ) as $key ) {
			if ( isset( $expected[ $key ] ) ) {
				$this->assertSame( $expected[ $key ], $args[ $key ], "The $key should be sent." );
			} else {
				$this->assertArrayNotHasKey( $key, $args, "The $key should not be sent." );
			}
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_searches() {
		return array(
			'a term'                   => array( array( 's' => 'contact forms' ), array( 'search' => 'contact forms' ) ),
			'a term by its type'       => array(
				array(
					's'    => 'contact forms',
					'type' => 'term',
				),
				array( 'search' => 'contact forms' ),
			),
			'a tag'                    => array(
				array(
					's'    => 'Contact Forms',
					'type' => 'tag',
				),
				array( 'tag' => 'contact-forms' ),
			),
			'an author'                => array(
				array(
					's'    => 'jane',
					'type' => 'author',
				),
				array( 'author' => 'jane' ),
			),
			'a term with slashes'      => array( array( 's' => 'it\\\'s' ), array( 'search' => "it's" ) ),
			'a type that is not known' => array(
				array(
					's'    => 'forms',
					'type' => 'unknown',
				),
				array(),
			),
		);
	}

	/**
	 * Tests the globals for a search.
	 *
	 * @ticket 66554
	 */
	public function test_should_set_the_type_and_the_term_of_a_search() {
		global $type, $term;

		$_REQUEST = array(
			'tab'  => 'search',
			's'    => 'forms',
			'type' => 'author',
		);

		$this->get_table()->prepare_items();

		$this->assertSame( 'author', $type );
		$this->assertSame( 'forms', $term );
	}

	/**
	 * Tests the defaults of a search.
	 *
	 * @ticket 66554
	 */
	public function test_should_search_for_an_empty_term_by_default() {
		global $type, $term;

		$_REQUEST['tab'] = 'search';

		$this->get_table()->prepare_items();

		$this->assertSame( 'term', $type );
		$this->assertSame( '', $term );
		$this->assertSame( '', $this->api_requests[0][1]['search'] );
	}

	/**
	 * Tests the favorites of the current user.
	 *
	 * @ticket 66554
	 */
	public function test_should_request_the_favorites_of_the_user_option() {
		update_user_option( self::$admin_id, 'wporg_favorites', 'jane' );

		$_REQUEST['tab'] = 'favorites';

		$this->get_table()->prepare_items();

		$this->assertSame( 'jane', $this->api_requests[0][1]['user'] );
	}

	/**
	 * Tests that the form for the favorites is shown.
	 *
	 * @ticket 66554
	 */
	public function test_should_add_the_form_for_the_favorites() {
		$_REQUEST['tab'] = 'favorites';

		$this->get_table()->prepare_items();

		$this->assertSame( 9, has_action( 'install_plugins_favorites', 'install_plugins_favorites_form' ) );
	}

	/**
	 * Tests that the API is not asked for favorites without a user.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_ask_the_api_for_favorites_without_a_user() {
		delete_user_option( self::$admin_id, 'wporg_favorites' );

		$_REQUEST['tab'] = 'favorites';

		$table = $this->get_table();
		$table->prepare_items();

		$this->assertSame( array(), $this->api_requests );
		$this->assertEmpty( $table->items );
	}

	/**
	 * Tests that the user of the favorites is saved when the request has a nonce.
	 *
	 * @ticket 66554
	 */
	public function test_should_save_the_user_of_the_favorites_with_a_nonce() {
		$_REQUEST['tab']  = 'favorites';
		$_GET['_wpnonce'] = wp_create_nonce( 'save_wporg_username_' . self::$admin_id );
		$_GET['user']     = 'janedoe';

		$this->get_table()->prepare_items();

		$this->assertSame( 'janedoe', get_user_meta( self::$admin_id, 'wporg_favorites', true ), 'The user should be saved.' );
		$this->assertSame( 'janedoe', $this->api_requests[0][1]['user'] );
	}

	/**
	 * Tests that the user of the favorites is not saved when the request says not to.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_save_the_user_of_the_favorites_when_the_request_says_not_to() {
		$_REQUEST['tab']  = 'favorites';
		$_GET['_wpnonce'] = wp_create_nonce( 'save_wporg_username_' . self::$admin_id );
		$_GET['user']     = 'janedoe';
		$_GET['save']     = '0';

		$this->get_table()->prepare_items();

		$this->assertSame( '', get_user_meta( self::$admin_id, 'wporg_favorites', true ), 'The user should not be saved.' );
		$this->assertSame( 'janedoe', $this->api_requests[0][1]['user'], 'The user should still be used.' );
	}

	/**
	 * Tests that the user of the favorites is not saved without a valid nonce.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_save_the_user_of_the_favorites_without_a_valid_nonce() {
		update_user_option( self::$admin_id, 'wporg_favorites', 'jane' );

		$_REQUEST['tab']  = 'favorites';
		$_GET['_wpnonce'] = 'invalid';
		$_GET['user']     = 'janedoe';

		$this->get_table()->prepare_items();

		$this->assertSame( 'jane', $this->api_requests[0][1]['user'], 'The user of the option should be used.' );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'wporg_favorites', true ), 'The user should not be saved.' );
	}

	/**
	 * Tests that the arguments of the API can be filtered, and that the API is not asked without arguments.
	 *
	 * @ticket 66554
	 */
	public function test_should_use_the_arguments_of_the_table_api_args_filter() {
		$received = null;

		add_filter(
			'install_plugins_table_api_args_popular',
			static function ( $args ) use ( &$received ) {
				$received         = $args;
				$args['per_page'] = 5;

				return $args;
			}
		);

		$_REQUEST['tab'] = 'popular';

		$this->get_table()->prepare_items();

		$this->assertSame( 36, $received['per_page'], 'The filter should receive the arguments.' );
		$this->assertSame( 5, $this->api_requests[0][1]['per_page'], 'The API should receive the arguments of the filter.' );
	}

	/**
	 * Tests that the API is not asked when the filter returns no arguments.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_ask_the_api_when_the_filter_returns_no_arguments() {
		add_filter( 'install_plugins_table_api_args_popular', '__return_false' );

		$_REQUEST['tab'] = 'popular';

		$this->get_table()->prepare_items();

		$this->assertSame( array(), $this->api_requests );
	}

	/**
	 * Tests the items and the pagination.
	 *
	 * @ticket 66554
	 */
	public function test_should_set_the_items_and_the_pagination_from_the_response() {
		$this->api_response = (object) array(
			'plugins' => array( array( 'slug' => 'one' ), array( 'slug' => 'two' ) ),
			'info'    => array(
				'results' => 100,
				'groups'  => array( 'all' => 'All' ),
			),
		);

		$_REQUEST['tab'] = 'popular';

		$table = $this->get_table();
		$table->prepare_items();

		$this->assertSame( $this->api_response->plugins, $table->items );
		$this->assertSame( 100, $table->get_pagination_arg( 'total_items' ) );
		$this->assertSame( 36, $table->get_pagination_arg( 'per_page' ) );
		$this->assertSame( 3, $table->get_pagination_arg( 'total_pages' ) );
		$this->assertSame( array( 'all' => 'All' ), $table->groups );
	}

	/**
	 * Tests that the groups are empty when the response has none.
	 *
	 * @ticket 66554
	 */
	public function test_should_have_no_groups_when_the_response_has_none() {
		$_REQUEST['tab'] = 'popular';

		$table = $this->get_table();
		$table->prepare_items();

		$this->assertSame( array(), $table->groups );
	}

	/**
	 * Tests that the error of the API is kept, and that there are no items.
	 *
	 * @ticket 66554
	 */
	public function test_should_keep_the_error_of_the_api() {
		$this->api_response = new WP_Error( 'plugins_api_failed', 'The API failed.' );

		$_REQUEST['tab'] = 'popular';

		$table = $this->get_table();
		$table->prepare_items();

		$this->assertEmpty( $table->items );

		ob_start();
		$table->no_items();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'The API failed.', $output );
	}

	/**
	 * Tests that the plugins are sorted by a field of the plugins.
	 *
	 * @ticket 66554
	 *
	 * @dataProvider data_order
	 *
	 * @param string   $order    The order.
	 * @param string[] $expected The slugs in the expected order.
	 */
	public function test_should_sort_the_plugins_by_a_field( $order, array $expected ) {
		$this->api_response = (object) array(
			'plugins' => array(
				(object) array(
					'slug'   => 'b',
					'rating' => 80,
				),
				(object) array(
					'slug'   => 'c',
					'rating' => 100,
				),
				(object) array(
					'slug'   => 'a',
					'rating' => 60,
				),
			),
			'info'    => array( 'results' => 3 ),
		);

		$_REQUEST['tab'] = 'popular';

		$table          = $this->get_table();
		$table->orderby = 'rating';
		$table->order   = $order;
		$table->prepare_items();

		$this->assertSame( $expected, array_values( wp_list_pluck( $table->items, 'slug' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_order() {
		return array(
			'ascending'  => array( 'ASC', array( 'a', 'b', 'c' ) ),
			'descending' => array( 'DESC', array( 'c', 'b', 'a' ) ),
		);
	}

	/**
	 * Tests that a plugin without the field keeps its place.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_move_the_plugins_that_have_no_value_for_the_field() {
		$this->api_response = (object) array(
			'plugins' => array(
				(object) array(
					'slug'   => 'b',
					'rating' => 80,
				),
				(object) array( 'slug' => 'none' ),
				(object) array(
					'slug'   => 'a',
					'rating' => 60,
				),
			),
			'info'    => array( 'results' => 3 ),
		);

		$_REQUEST['tab'] = 'popular';

		$table          = $this->get_table();
		$table->orderby = 'rating';
		$table->prepare_items();

		$this->assertSame( array( 'b', 'none', 'a' ), array_values( wp_list_pluck( $table->items, 'slug' ) ) );
	}

	/**
	 * Tests that the plugins keep the order of the API without a field.
	 *
	 * @ticket 66554
	 */
	public function test_should_keep_the_order_of_the_api_without_a_field() {
		$this->api_response = (object) array(
			'plugins' => array( array( 'slug' => 'b' ), array( 'slug' => 'a' ) ),
			'info'    => array( 'results' => 2 ),
		);

		$_REQUEST['tab'] = 'popular';

		$table = $this->get_table();
		$table->prepare_items();

		$this->assertSame( array( 'b', 'a' ), wp_list_pluck( $table->items, 'slug' ) );
	}

	/**
	 * Tests the counts of the installed plugins that are sent to the script for the updates.
	 *
	 * @ticket 66554
	 */
	public function test_should_send_the_counts_of_the_installed_plugins_to_the_updates_script() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'a/a.php' => (object) array(
						'slug'   => 'a',
						'plugin' => 'a/a.php',
					),
				),
				'response'  => array(
					'b/b.php' => (object) array(
						'slug'   => 'b',
						'plugin' => 'b/b.php',
					),
				),
			)
		);

		wp_register_script( 'updates', '/updates.js' );

		$_REQUEST['tab'] = 'popular';

		$this->get_table()->prepare_items();

		$data = wp_scripts()->get_data( 'updates', 'data' );

		$this->assertStringContainsString( '_wpUpdatesItemCounts', $data );
		$this->assertStringContainsString( '"all":["a/a.php","b/b.php"]', $data, 'All the installed plugins should be sent.' );
		$this->assertStringContainsString( '"upgrade":["b/b.php"]', $data, 'The plugins with an update should be sent.' );
	}

	/**
	 * Tests that nothing is sent to the script for the updates without installed plugins.
	 *
	 * @ticket 66554
	 */
	public function test_should_not_send_counts_without_installed_plugins() {
		wp_register_script( 'updates', '/updates.js' );

		$_REQUEST['tab'] = 'popular';

		$this->get_table()->prepare_items();

		$data = wp_scripts()->get_data( 'updates', 'data' );

		$this->assertEmpty( $data );
	}
}
