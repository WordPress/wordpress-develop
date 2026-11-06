<?php

/**
 * @group admin
 *
 * @covers WP_Plugins_List_Table
 */
class Tests_Admin_wpPluginsListTable extends WP_UnitTestCase {
	/**
	 * @var WP_Plugins_List_Table
	 */
	public $table = false;

	/**
	 * An admin user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original value of the `$s` global.
	 *
	 * @var string|null
	 */
	private static $original_s;

	/**
	 * @var array
	 */
	public $fake_plugin = array(
		'fake-plugin.php' => array(
			'Name'        => 'Fake Plugin',
			'PluginURI'   => 'https://wordpress.org/',
			'Version'     => '1.0.0',
			'Description' => 'A fake plugin for testing.',
			'Author'      => 'WordPress',
			'AuthorURI'   => 'https://wordpress.org/',
			'TextDomain'  => 'fake-plugin',
			'DomainPath'  => '/languages',
			'Network'     => false,
			'Title'       => 'Fake Plugin',
			'AuthorName'  => 'WordPress',
		),
	);

	/**
	 * The original values of the globals that a test changed, by name.
	 *
	 * @var array
	 */
	private $original_globals = array();

	/**
	 * Creates an admin user before any tests run and backs up the `$s` global.
	 */
	public static function set_up_before_class() {
		global $s;

		parent::set_up_before_class();

		self::$admin_id   = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_wp_plugins_list_table',
				'user_pass'  => 'password',
				'user_email' => 'testadmin@example.com',
			)
		);
		self::$original_s = $s;
	}

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
	}

	/**
	 * Restores the `$s` global after each test.
	 */
	public function tear_down() {
		global $s;

		$s = self::$original_s;

		foreach ( $this->original_globals as $name => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $name ] );
			} else {
				$GLOBALS[ $name ] = $value;
			}
		}

		parent::tear_down();
	}

	/**
	 * Sets a global that the list table reads, and restores it after the test.
	 *
	 * @param string $name  The name of the global.
	 * @param mixed  $value The value of the global.
	 */
	private function set_global( $name, $value ) {
		if ( ! array_key_exists( $name, $this->original_globals ) ) {
			$this->original_globals[ $name ] = $GLOBALS[ $name ] ?? null;
		}

		$GLOBALS[ $name ] = $value;
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Plugins_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		global $totals;

		$totals_backup = $totals;
		$totals        = array(
			'all'                  => 45,
			'active'               => 1,
			'recently_activated'   => 2,
			'inactive'             => 3,
			'mustuse'              => 4,
			'dropins'              => 5,
			'paused'               => 6,
			'upgrade'              => 7,
			'auto-update-enabled'  => 8,
			'auto-update-disabled' => 9,
		);

		$expected = array(
			'all'                  => '<a href="plugins.php?plugin_status=all" class="current" aria-current="page">All <span class="count">(45)</span></a>',
			'active'               => '<a href="plugins.php?plugin_status=active">Active <span class="count">(1)</span></a>',
			'recently_activated'   => '<a href="plugins.php?plugin_status=recently_activated">Recently Active <span class="count">(2)</span></a>',
			'inactive'             => '<a href="plugins.php?plugin_status=inactive">Inactive <span class="count">(3)</span></a>',
			'mustuse'              => '<a href="plugins.php?plugin_status=mustuse">Must-Use <span class="count">(4)</span></a>',
			'dropins'              => '<a href="plugins.php?plugin_status=dropins">Drop-ins <span class="count">(5)</span></a>',
			'paused'               => '<a href="plugins.php?plugin_status=paused">Paused <span class="count">(6)</span></a>',
			'upgrade'              => '<a href="plugins.php?plugin_status=upgrade">Update Available <span class="count">(7)</span></a>',
			'auto-update-enabled'  => '<a href="plugins.php?plugin_status=auto-update-enabled">Auto-updates Enabled <span class="count">(8)</span></a>',
			'auto-update-disabled' => '<a href="plugins.php?plugin_status=auto-update-disabled">Auto-updates Disabled <span class="count">(9)</span></a>',
		);

		$actual = $this->table->get_views();
		$totals = $totals_backup;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that WP_Plugins_List_Table::__construct() does not set
	 * the 'show_autoupdates' property to false for Must-Use and Drop-in
	 * plugins.
	 *
	 * The 'ms-excluded' group is added as $this->show_autoupdates is already set to false for multisite.
	 *
	 * @ticket 54309
	 * @group ms-excluded
	 *
	 * @covers WP_Plugins_List_Table::__construct()
	 *
	 * @dataProvider data_status_mustuse_and_dropins
	 *
	 * @param string $status The value for $_REQUEST['plugin_status'].
	 */
	public function test_construct_should_not_set_show_autoupdates_to_false_for_mustuse_and_dropins( $status ) {
		$original_status           = $_REQUEST['plugin_status'] ?? null;
		$_REQUEST['plugin_status'] = $status;

		// Enable plugin auto-updates.
		add_filter( 'plugins_auto_update_enabled', '__return_true' );

		// Use a user with the 'manage_plugins' capability.
		wp_set_current_user( self::$admin_id );

		$list_table       = new WP_Plugins_List_Table();
		$show_autoupdates = new ReflectionProperty( $list_table, 'show_autoupdates' );

		if ( PHP_VERSION_ID < 80100 ) {
			$show_autoupdates->setAccessible( true );
		}
		$actual = $show_autoupdates->getValue( $list_table );
		if ( PHP_VERSION_ID < 80100 ) {
			$show_autoupdates->setAccessible( false );
		}

		$_REQUEST['plugin_status'] = $original_status;

		$this->assertTrue( $actual );
	}

	/**
	 * Tests that WP_Plugins_List_Table::get_columns() does not add
	 * the auto-update column when not viewing Must-Use or Drop-in plugins.
	 *
	 * @ticket 54309
	 *
	 * @covers WP_Plugins_List_Table::get_columns
	 *
	 * @dataProvider data_status_mustuse_and_dropins
	 *
	 * @param string $test_status The value for the global $status variable.
	 */
	public function test_get_columns_should_not_add_the_autoupdates_column_when_viewing_mustuse_or_dropins( $test_status ) {
		global $status;

		$original_status = $status;

		// Enable plugin auto-updates.
		add_filter( 'plugins_auto_update_enabled', '__return_true' );

		// Use a user with the 'manage_plugins' capability.
		wp_set_current_user( self::$admin_id );

		$status = $test_status;
		$actual = $this->table->get_columns();
		$status = $original_status;

		$this->assertArrayNotHasKey( 'auto-updates', $actual );
	}

	/**
	 * Tests that WP_Plugins_List_Table::get_columns() does not add
	 * the auto-update column when the 'plugins_auto_update_enabled'
	 * filter returns false.
	 *
	 * @ticket 54309
	 *
	 * @covers WP_Plugins_List_Table::get_columns
	 */
	public function test_get_columns_should_not_add_the_autoupdates_column_when_plugin_auto_update_is_disabled() {
		global $status;

		$original_status = $status;

		// Enable plugin auto-updates.
		add_filter( 'plugins_auto_update_enabled', '__return_false' );

		// Use a user with the 'manage_plugins' capability.
		wp_set_current_user( self::$admin_id );

		$status = 'all';
		$actual = $this->table->get_columns();
		$status = $original_status;

		$this->assertArrayNotHasKey( 'auto-updates', $actual );
	}

	/**
	 * Tests that WP_Plugins_List_Table::single_row() does not output the
	 * 'Auto-updates' column for Must-Use or Drop-in plugins.
	 *
	 * @ticket 54309
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 *
	 * @dataProvider data_status_mustuse_and_dropins
	 *
	 * @param string $test_status The value for the global $status variable.
	 */
	public function test_single_row_should_not_add_the_autoupdates_column_for_mustuse_or_dropins( $test_status ) {
		global $status;

		$original_status = $status;

		// Enable plugin auto-updates.
		add_filter( 'plugins_auto_update_enabled', '__return_true' );

		// Use a user with the 'manage_plugins' capability.
		wp_set_current_user( self::$admin_id );

		$column_info = array(
			array(
				'name'         => 'Plugin',
				'description'  => 'Description',
				'auto-updates' => 'Auto-updates',
			),
			array(),
			array(),
			'name',
		);

		// Mock WP_Plugins_List_Table
		$list_table_mock = $this->getMockBuilder( 'WP_Plugins_List_Table' )
			// Note: setMethods() is deprecated in PHPUnit 9, but still supported.
			->setMethods( array( 'get_column_info' ) )
			->getMock();

		// Force the return value of the get_column_info() method.
		$list_table_mock->expects( $this->once() )->method( 'get_column_info' )->willReturn( $column_info );

		$single_row_args = array(
			'advanced-cache.php',
			array(
				'Name'        => 'Advanced caching plugin',
				'slug'        => 'advanced-cache',
				'Description' => 'An advanced caching plugin.',
				'Author'      => 'A plugin author',
				'Version'     => '1.0.0',
				'Author URI'  => 'http://example.org',
				'Text Domain' => 'advanced-cache',
			),
		);

		$status = $test_status;
		ob_start();
		$list_table_mock->single_row( $single_row_args );
		$actual = ob_get_clean();
		$status = $original_status;

		$this->assertIsString( $actual, 'Output was not captured.' );
		$this->assertNotEmpty( $actual, 'The output string was empty.' );
		$this->assertStringNotContainsString( 'column-auto-updates', $actual, 'The auto-updates column was output.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::single_row() determines the auto-update
	 * message once, rather than for each plugin with an update.
	 *
	 * The 'ms-excluded' group is added as the auto-updates column is only shown in the network admin.
	 *
	 * @ticket 27814
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 */
	public function test_single_row_should_determine_the_auto_update_message_once() {
		add_filter( 'plugins_auto_update_enabled', '__return_true' );
		add_filter( 'request_filesystem_credentials', '__return_true' );

		$vcs_checks = 0;
		add_filter(
			'automatic_updates_is_vcs_checkout',
			static function () use ( &$vcs_checks ) {
				++$vcs_checks;
				return true;
			}
		);

		wp_set_current_user( self::$admin_id );

		$list_table_mock = $this->getMockBuilder( 'WP_Plugins_List_Table' )
			->setMethods( array( 'get_column_info' ) )
			->getMock();

		$list_table_mock->method( 'get_column_info' )->willReturn(
			array(
				array(
					'name'         => 'Plugin',
					'auto-updates' => 'Auto-updates',
				),
				array(),
				array(),
				'name',
			)
		);

		$output = '';
		foreach ( array( 'plugin-one', 'plugin-two' ) as $slug ) {
			ob_start();
			$list_table_mock->single_row(
				array(
					"$slug/$slug.php",
					array(
						'Name'             => $slug,
						'slug'             => $slug,
						'Description'      => 'A plugin with an update available.',
						'Author'           => 'A plugin author',
						'Version'          => '1.0.0',
						'Author URI'       => 'http://example.org',
						'Text Domain'      => $slug,
						'update'           => true,
						'update-supported' => true,
					),
				)
			);
			$output .= ob_get_clean();
		}

		$this->assertSame( 1, $vcs_checks, 'Version control was not checked exactly once.' );
		$this->assertSame(
			2,
			substr_count( $output, 'appears to be under version control' ),
			'The auto-update message was not output for both plugins.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_status_mustuse_and_dropins() {
		return array(
			'Must-Use' => array( 'mustuse' ),
			'Drop-ins' => array( 'dropins' ),
		);
	}

	/**
	 * Tests that WP_Plugins_List_Table::prepare_items()
	 * applies 'plugins_list' filters.
	 *
	 * @ticket 57278
	 *
	 * @covers WP_Plugins_List_Table::prepare_items
	 */
	public function test_plugins_list_filter() {
		global $status, $s;

		$old_status = $status;
		$status     = 'mustuse';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'plugins_list_filter' ), 10, 1 );
		$this->table->prepare_items();
		$plugins = $this->table->items;
		remove_filter( 'plugins_list', array( $this, 'plugins_list_filter' ), 10 );

		// Restore to default.
		$status = $old_status;
		$this->table->prepare_items();

		$this->assertSame( $plugins, $this->fake_plugin );
	}

	/**
	 * Adds a fake plugin to an array of plugins.
	 *
	 * Used as a callback for the 'plugins_list' hook.
	 *
	 * @return array
	 */
	public function plugins_list_filter( $plugins_list ) {
		$plugins_list['mustuse'] = $this->fake_plugin;

		return $plugins_list;
	}

	/**
	 * Calls a protected method of the list table.
	 *
	 * @param string $method The name of the method.
	 * @param array  $args   Optional. The arguments. Default empty array.
	 * @return mixed The return value of the method.
	 */
	private function call_protected( $method, array $args = array() ) {
		$reflection = new ReflectionMethod( $this->table, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $this->table, $args );
	}

	/**
	 * Returns the output of the extra table navigation.
	 *
	 * @param string $which The position of the navigation, `top` or `bottom`.
	 * @return string The output.
	 */
	private function get_extra_tablenav( $which ) {
		ob_start();
		$this->call_protected( 'extra_tablenav', array( $which ) );

		return ob_get_clean();
	}

	/**
	 * Renders a row of a plugin with the given columns.
	 *
	 * @param string $plugin_file The plugin file.
	 * @param array  $plugin_data The data of the plugin.
	 * @param array  $columns     Optional. The columns, by name. Default `name` only.
	 * @param array  $hidden      Optional. The names of the hidden columns. Default none.
	 * @return string The output of the row.
	 */
	private function render_row( $plugin_file, array $plugin_data, array $columns = array( 'name' => 'Plugin' ), array $hidden = array() ) {
		$status = $GLOBALS['status'] ?? 'all';
		$page   = $GLOBALS['page'] ?? 1;

		$list_table = $this->getMockBuilder( 'WP_Plugins_List_Table' )
			->setMethods( array( 'get_column_info' ) )
			->getMock();

		// The constructor sets these globals from the request.
		$GLOBALS['status'] = $status;
		$GLOBALS['page']   = $page;

		$list_table->method( 'get_column_info' )->willReturn( array( $columns, $hidden, array(), 'name' ) );

		return get_echo(
			array( $list_table, 'single_row' ),
			array(
				array(
					$plugin_file,
					array_merge(
						array(
							'Name'        => 'Row Plugin',
							'Description' => 'A plugin.',
							'Version'     => '1.0.0',
							'Author'      => 'An author',
							'AuthorURI'   => '',
							'PluginURI'   => '',
						),
						$plugin_data
					),
				),
			)
		);
	}

	/**
	 * Tests that the current search term is added to the request URI.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::__construct
	 */
	public function test_construct_should_add_the_search_term_to_the_request_uri() {
		$request_uri = $_SERVER['REQUEST_URI'];
		$search      = $_REQUEST['s'] ?? null;

		$_SERVER['REQUEST_URI'] = '/wp-admin/plugins.php';
		$_REQUEST['s']          = 'my \\"plugin\\"';

		try {
			_get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
			$query = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
		} finally {
			$_SERVER['REQUEST_URI'] = $request_uri;

			if ( null === $search ) {
				unset( $_REQUEST['s'] );
			} else {
				$_REQUEST['s'] = $search;
			}
		}

		parse_str( $query, $args );

		$this->assertSame( 'my "plugin"', $args['s'], 'The search term should be added without slashes.' );
	}

	/**
	 * Tests that the capability to activate plugins is required for Ajax requests.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::ajax_user_can
	 */
	public function test_ajax_user_can_should_require_the_activate_plugins_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( $this->table->ajax_user_can(), 'A user without the capability should not be allowed.' );

		// Administrators cannot activate plugins on a network, but super admins can.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		wp_set_current_user( $admin_id );

		try {
			$this->assertTrue( $this->table->ajax_user_can(), 'A user with the capability should be allowed.' );
		} finally {
			if ( is_multisite() ) {
				revoke_super_admin( $admin_id );
			}
		}
	}

	/**
	 * Tests the classes of the table.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_table_classes
	 */
	public function test_get_table_classes_should_return_the_classes_of_the_plugins_table() {
		$this->assertSame( array( 'widefat', 'plugins' ), $this->call_protected( 'get_table_classes' ) );
	}

	/**
	 * Tests the primary column.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_primary_column_name
	 */
	public function test_get_primary_column_name_should_be_the_plugin_name() {
		$this->assertSame( 'name', $this->call_protected( 'get_primary_column_name' ) );
	}

	/**
	 * Tests the default columns.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_columns
	 */
	public function test_get_columns_should_return_the_default_columns() {
		add_filter( 'plugins_auto_update_enabled', '__return_false' );

		$this->assertSame(
			array(
				'cb'          => '<input type="checkbox" />',
				'name'        => 'Plugin',
				'description' => 'Description',
			),
			_get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) )->get_columns()
		);
	}

	/**
	 * Tests that the column for the automatic updates is added after the description.
	 *
	 * The 'ms-excluded' group is added as the column is only shown in the network admin on multisite.
	 *
	 * @ticket 66552
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Plugins_List_Table::get_columns
	 */
	public function test_get_columns_should_add_the_autoupdates_column() {
		add_filter( 'plugins_auto_update_enabled', '__return_true' );
		wp_set_current_user( self::$admin_id );

		$columns = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) )->get_columns();

		$this->assertSame( array( 'cb', 'name', 'description', 'auto-updates' ), array_keys( $columns ), 'The column should be added last.' );
		$this->assertSame( 'Automatic Updates', $columns['auto-updates'] );
	}

	/**
	 * Tests which plugins match a search term.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::_search_callback
	 *
	 * @dataProvider data_search_callback
	 *
	 * @param string $search   The search term, as the `$s` global holds it.
	 * @param array  $plugin   The data of the plugin.
	 * @param bool   $expected Whether the plugin should match.
	 */
	public function test_search_callback_should_match_the_values_of_the_plugin( $search, array $plugin, $expected ) {
		$this->set_global( 's', $search );

		$this->assertSame( $expected, $this->table->_search_callback( $plugin ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_search_callback() {
		$plugin = array(
			'Name'        => 'Contact Forms',
			'Description' => 'Build <strong>simple</strong> forms.',
			'Author'      => 'Jane Doe',
			'Tags'        => array( 'seo' ),
			'Network'     => false,
		);

		return array(
			'the name'                       => array( 'Contact', $plugin, true ),
			'a part of the name'             => array( 'tact for', $plugin, true ),
			'any case'                       => array( 'CONTACT forms', $plugin, true ),
			'the description without markup' => array( 'simple forms', $plugin, true ),
			'the author'                     => array( 'jane', $plugin, true ),
			'an encoded term'                => array( 'jane+doe', $plugin, true ),
			'an encoded space'               => array( 'contact%20forms', $plugin, true ),
			'the markup of the description'  => array( 'strong', $plugin, false ),
			'a value that is not a string'   => array( 'seo', $plugin, false ),
			'a term that is not in the data' => array( 'newsletter', $plugin, false ),
			'a plugin without data'          => array( 'contact', array(), false ),
		);
	}

	/**
	 * Tests the views of a status that has no label of its own.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_views
	 */
	public function test_get_views_should_use_the_status_as_the_label_of_other_statuses() {
		$this->set_global( 'totals', array( 'custom' => 1234 ) );
		$this->set_global( 'status', 'all' );

		$this->assertSame(
			array( 'custom' => '<a href="plugins.php?plugin_status=custom">custom <span class="count">(1,234)</span></a>' ),
			$this->table->get_views()
		);
	}

	/**
	 * Tests that the label of a status can be filtered.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_views
	 */
	public function test_get_views_should_use_the_label_of_the_plugins_list_status_text_filter() {
		$this->set_global( 'totals', array( 'custom' => 3 ) );
		$this->set_global( 'status', 'custom' );

		$filter = new MockAction();
		add_filter( 'plugins_list_status_text', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'plugins_list_status_text',
			static function () {
				return 'My <b>status</b>';
			},
			11
		);

		$views = $this->table->get_views();
		$args  = $filter->get_args();

		$this->assertSame( array( '', 3, 'custom' ), $args[0], 'The filter should receive the label, the count and the status.' );
		$this->assertSame(
			array( 'custom' => '<a href="plugins.php?plugin_status=custom" class="current" aria-current="page">My &lt;b&gt;status&lt;/b&gt; <span class="count">(3)</span></a>' ),
			$views,
			'The label should be escaped.'
		);
	}

	/**
	 * Tests that a label that is not a string is replaced with the status.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_views
	 */
	public function test_get_views_should_ignore_a_label_that_is_not_a_string() {
		$this->set_global( 'totals', array( 'custom' => 3 ) );
		$this->set_global( 'status', 'all' );

		add_filter(
			'plugins_list_status_text',
			static function () {
				return array( 'not a string' );
			}
		);

		$this->assertSame(
			array( 'custom' => '<a href="plugins.php?plugin_status=custom">custom <span class="count">(3)</span></a>' ),
			$this->table->get_views()
		);
	}

	/**
	 * Tests that statuses without plugins and the search are not listed.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::get_views
	 */
	public function test_get_views_should_skip_statuses_without_plugins_and_the_search() {
		$this->set_global(
			'totals',
			array(
				'all'      => 2,
				'active'   => 0,
				'search'   => 1,
				'inactive' => 2,
			)
		);
		$this->set_global( 'status', 'all' );

		$this->assertSame( array( 'all', 'inactive' ), array_keys( $this->table->get_views() ) );
	}

	/**
	 * Tests the bulk actions that are not shown for Must-Use plugins and drop-ins.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::bulk_actions
	 *
	 * @dataProvider data_status_mustuse_and_dropins
	 *
	 * @param string $status The plugin status.
	 */
	public function test_bulk_actions_should_output_nothing_for_mustuse_and_dropins( $status ) {
		$this->set_global( 'status', $status );

		$this->assertSame( '', get_echo( array( $this->table, 'bulk_actions' ), array( 'top' ) ) );
	}

	/**
	 * Tests that the bulk actions are shown for the other statuses.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::bulk_actions
	 */
	public function test_bulk_actions_should_output_the_actions_for_other_statuses() {
		$this->set_global( 'status', 'all' );
		wp_set_current_user( self::$admin_id );

		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );

		$output = get_echo( array( $table, 'bulk_actions' ), array( 'top' ) );

		$this->assertStringContainsString( '<select name="action" id="bulk-action-selector-top">', $output );
		$this->assertStringContainsString( 'Bulk actions', $output );
		$this->assertStringContainsString( '<option value="activate-selected">Activate</option>', $output );
	}

	/**
	 * Tests the extra table navigation of the statuses that have none.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::extra_tablenav
	 *
	 * @dataProvider data_statuses_without_extra_tablenav
	 *
	 * @param string $status The plugin status.
	 */
	public function test_extra_tablenav_should_output_nothing_for_other_statuses( $status ) {
		$this->set_global( 'status', $status );

		$this->assertSame( '', $this->get_extra_tablenav( 'top' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_statuses_without_extra_tablenav() {
		return array(
			'all'      => array( 'all' ),
			'active'   => array( 'active' ),
			'inactive' => array( 'inactive' ),
			'paused'   => array( 'paused' ),
			'upgrade'  => array( 'upgrade' ),
		);
	}

	/**
	 * Tests that recently active plugins can be cleared above and below the list.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::extra_tablenav
	 *
	 * @dataProvider data_tablenav_positions
	 *
	 * @param string $which The position of the navigation.
	 */
	public function test_extra_tablenav_should_output_a_button_to_clear_the_recently_active_plugins( $which ) {
		$this->set_global( 'status', 'recently_activated' );

		$output = $this->get_extra_tablenav( $which );

		$this->assertStringStartsWith( '<div class="alignleft actions">', $output );
		$this->assertStringContainsString( 'name="clear-recent-list"', $output, 'The button should be output.' );
		$this->assertStringContainsString( 'value="Clear List"', $output );
		$this->assertStringEndsWith( '</div>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tablenav_positions() {
		return array(
			'top'    => array( 'top' ),
			'bottom' => array( 'bottom' ),
		);
	}

	/**
	 * Tests the description of the Must-Use plugins above the list only.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::extra_tablenav
	 */
	public function test_extra_tablenav_should_describe_the_mustuse_plugins_above_the_list() {
		$this->set_global( 'status', 'mustuse' );

		$top    = $this->get_extra_tablenav( 'top' );
		$bottom = $this->get_extra_tablenav( 'bottom' );

		$this->assertSame(
			'<div class="alignleft actions"><p>Files in the <code>' . str_replace( ABSPATH, '/', WPMU_PLUGIN_DIR ) . '</code> directory are executed automatically.</p></div>',
			$top
		);
		$this->assertSame( '<div class="alignleft actions"></div>', $bottom, 'Nothing should be described below the list.' );
	}

	/**
	 * Tests the description of the drop-ins above the list only.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::extra_tablenav
	 */
	public function test_extra_tablenav_should_describe_the_dropins_above_the_list() {
		$this->set_global( 'status', 'dropins' );

		$top    = $this->get_extra_tablenav( 'top' );
		$bottom = $this->get_extra_tablenav( 'bottom' );

		$this->assertStringStartsWith( '<div class="alignleft actions"><p>Drop-ins are single files, found in the <code>' . str_replace( ABSPATH, '', WP_CONTENT_DIR ) . '</code> directory,', $top );
		$this->assertSame( '<div class="alignleft actions"></div>', $bottom, 'Nothing should be described below the list.' );
	}

	/**
	 * Tests the action of the button that clears the recently active plugins.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::current_action
	 */
	public function test_current_action_should_be_the_clear_button() {
		$_POST['clear-recent-list'] = 'Clear List';
		$_REQUEST['action']         = 'activate-selected';

		try {
			$this->assertSame( 'clear-recent-list', $this->table->current_action() );
		} finally {
			unset( $_POST['clear-recent-list'], $_REQUEST['action'] );
		}
	}

	/**
	 * Tests the action of the bulk actions.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::current_action
	 *
	 * @dataProvider data_current_action
	 *
	 * @param array       $request  The request.
	 * @param string|bool $expected The expected action.
	 */
	public function test_current_action_should_use_the_bulk_action_of_the_request( array $request, $expected ) {
		$original = $_REQUEST;

		$_REQUEST = $request;

		try {
			$this->assertSame( $expected, $this->table->current_action() );
		} finally {
			$_REQUEST = $original;
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_current_action() {
		return array(
			'an action'            => array( array( 'action' => 'activate-selected' ), 'activate-selected' ),
			'no action'            => array( array( 'action' => '-1' ), false ),
			'no request'           => array( array(), false ),
			'a filter was applied' => array(
				array(
					'action'        => 'activate-selected',
					'filter_action' => 'Filter',
				),
				false,
			),
		);
	}

	/**
	 * Tests that a row is displayed for each plugin.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::display_rows
	 */
	public function test_display_rows_should_display_a_row_for_each_plugin() {
		$this->set_global( 'status', 'all' );

		$list_table = $this->getMockBuilder( 'WP_Plugins_List_Table' )
			->setConstructorArgs( array( array( 'screen' => 'plugins' ) ) )
			->setMethods( array( 'single_row' ) )
			->getMock();

		$list_table->items = array(
			'one/one.php' => array( 'Name' => 'One' ),
			'two.php'     => array( 'Name' => 'Two' ),
		);

		$list_table->expects( $this->exactly( 2 ) )
			->method( 'single_row' )
			->withConsecutive(
				array( array( 'one/one.php', array( 'Name' => 'One' ) ) ),
				array( array( 'two.php', array( 'Name' => 'Two' ) ) )
			);

		$list_table->display_rows();
	}

	/**
	 * Tests that the Must-Use plugins and the drop-ins are not displayed on the sites of a network.
	 *
	 * @ticket 66552
	 *
	 * @group ms-required
	 *
	 * @covers WP_Plugins_List_Table::display_rows
	 *
	 * @dataProvider data_status_mustuse_and_dropins
	 *
	 * @param string $status The plugin status.
	 */
	public function test_display_rows_should_not_display_mustuse_plugins_and_dropins_on_a_site_of_a_network( $status ) {
		$list_table = $this->getMockBuilder( 'WP_Plugins_List_Table' )
			->setConstructorArgs( array( array( 'screen' => 'plugins' ) ) )
			->setMethods( array( 'single_row' ) )
			->getMock();

		// The constructor sets the status from the request.
		$this->set_global( 'status', $status );

		$list_table->items = array( 'one.php' => array( 'Name' => 'One' ) );

		$list_table->expects( $this->never() )->method( 'single_row' );

		$list_table->display_rows();
	}

	/**
	 * Tests the setting for the automatic updates of a plugin.
	 *
	 * The 'ms-excluded' group is added as the column is only shown in the network admin on multisite.
	 *
	 * @ticket 66552
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 *
	 * @dataProvider data_autoupdate_settings
	 *
	 * @param array  $plugin_data The data of the plugin.
	 * @param bool   $enabled     Whether the automatic updates of the plugin are on.
	 * @param string $label       The expected label.
	 * @param string $action      The expected action, or an empty string when there is no link.
	 */
	public function test_single_row_should_output_the_setting_for_the_automatic_updates( array $plugin_data, $enabled, $label, $action ) {
		add_filter( 'plugins_auto_update_enabled', '__return_true' );
		wp_set_current_user( self::$admin_id );
		$this->set_global( 'status', 'all' );
		$this->set_global( 'page', 1 );
		update_site_option( 'auto_update_plugins', $enabled ? array( 'auto/auto.php' ) : array() );

		$output = $this->render_row( 'auto/auto.php', $plugin_data, array( 'auto-updates' => 'Automatic Updates' ) );

		$this->assertStringContainsString( "<td class='column-auto-updates'>", $output );
		$this->assertStringContainsString( '<span class="label">' . $label . '</span>', $output );

		if ( '' === $action ) {
			$this->assertStringNotContainsString( 'toggle-auto-update', $output, 'There should be no link.' );
		} else {
			$this->assertStringContainsString( 'data-wp-action="' . $action . '"', $output );
			$this->assertStringContainsString( 'plugin=auto%2Fauto.php', $output );
			$this->assertStringContainsString( 'action=' . $action . '-auto-update', $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_autoupdate_settings() {
		return array(
			'forced on'             => array( array( 'auto-update-forced' => true ), false, 'Auto-updates enabled', '' ),
			'forced off'            => array( array( 'auto-update-forced' => false ), true, 'Auto-updates disabled', '' ),
			'not supported'         => array( array(), true, '', '' ),
			'supported and enabled' => array( array( 'update-supported' => true ), true, 'Disable auto-updates', 'disable' ),
			'supported and not on'  => array( array( 'update-supported' => true ), false, 'Enable auto-updates', 'enable' ),
		);
	}

	/**
	 * Tests that the time of the next update is shown for a plugin with an update that updates automatically.
	 *
	 * @ticket 66552
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 *
	 * @dataProvider data_autoupdate_time
	 *
	 * @param bool   $enabled Whether the automatic updates of the plugin are on.
	 * @param string $time_class The expected class of the message.
	 */
	public function test_single_row_should_show_the_auto_update_message_only_when_updates_are_on( $enabled, $time_class ) {
		add_filter( 'plugins_auto_update_enabled', '__return_true' );
		wp_set_current_user( self::$admin_id );
		$this->set_global( 'status', 'all' );
		$this->set_global( 'page', 1 );
		update_site_option( 'auto_update_plugins', $enabled ? array( 'auto/auto.php' ) : array() );

		$output = $this->render_row(
			'auto/auto.php',
			array(
				'update'           => true,
				'update-supported' => true,
			),
			array( 'auto-updates' => 'Automatic Updates' )
		);

		$this->assertStringContainsString( '<div class="auto-update-time' . $time_class . '">', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_autoupdate_time() {
		return array(
			'on'  => array( true, '' ),
			'off' => array( false, ' hidden' ),
		);
	}

	/**
	 * Tests that the setting for the automatic updates can be filtered, and that its column can be hidden.
	 *
	 * @ticket 66552
	 *
	 * @group ms-excluded
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 */
	public function test_single_row_should_filter_the_setting_for_the_automatic_updates_and_hide_the_column() {
		add_filter( 'plugins_auto_update_enabled', '__return_true' );
		wp_set_current_user( self::$admin_id );
		$this->set_global( 'status', 'all' );
		$this->set_global( 'page', 1 );

		$filter = new MockAction();
		add_filter( 'plugin_auto_update_setting_html', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'plugin_auto_update_setting_html',
			static function () {
				return '<b>Custom setting</b>';
			},
			11
		);

		$output = $this->render_row( 'auto/auto.php', array( 'update-supported' => true ), array( 'auto-updates' => 'Automatic Updates' ), array( 'auto-updates' ) );
		$args   = $filter->get_args();

		$this->assertStringContainsString( "<td class='column-auto-updates hidden'><b>Custom setting</b>", $output, 'The setting should be replaced, in a hidden column.' );
		$this->assertStringContainsString( 'notice notice-error notice-alt inline hidden', $output, 'A hidden notice should be output for errors.' );
		$this->assertStringContainsString( 'toggle-auto-update', $args[0][0], 'The filter should receive the setting.' );
		$this->assertSame( 'auto/auto.php', $args[0][1], 'The filter should receive the plugin file.' );
		$this->assertSame( 'Row Plugin', $args[0][2]['Name'], 'The filter should receive the plugin data.' );
	}

	/**
	 * Tests that a column that is not one of the plugin columns is filled by an action.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 */
	public function test_single_row_should_let_an_action_fill_a_custom_column() {
		$this->set_global( 'status', 'all' );
		$this->set_global( 'page', 1 );

		$action = new MockAction();
		add_action( 'manage_plugins_custom_column', array( $action, 'action' ), 10, 3 );
		add_action(
			'manage_plugins_custom_column',
			static function ( $column_name ) {
				echo 'Value of ' . $column_name;
			}
		);

		$output = $this->render_row(
			'hello.php',
			array(),
			array(
				'name'   => 'Plugin',
				'custom' => 'Custom',
				'hidden' => 'Hidden',
			),
			array( 'hidden' )
		);

		$args = $action->get_args();

		$this->assertStringContainsString( "<td class='custom column-custom inactive'>Value of custom</td>", $output, 'The column should be filled by the action.' );
		$this->assertStringContainsString( "<td class='hidden column-hidden inactive hidden'>Value of hidden</td>", $output, 'A hidden column should be hidden.' );
		$this->assertSame( array( 'custom', 'hello.php' ), array_slice( $args[0], 0, 2 ), 'The action should receive the column and the plugin file.' );
		$this->assertSame( 'Row Plugin', $args[0][2]['Name'], 'The action should receive the plugin data.' );
	}

	/**
	 * Tests the row of a drop-in that is always active.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 */
	public function test_single_row_should_describe_a_dropin_that_is_always_active() {
		$this->set_global( 'status', 'dropins' );
		$this->set_global( 'page', 1 );

		$output = $this->render_row(
			'db.php',
			array(
				'Name'        => 'Custom Database',
				'Description' => 'My database class.',
			),
			array(
				'name'        => 'Plugin',
				'description' => 'Description',
			)
		);

		$this->assertStringContainsString( 'db.php<br />Custom Database', $output, 'The file and the name should be output.' );
		$this->assertStringContainsString( '<p><strong>Custom database class.</strong></p><p>My database class.</p>', $output, 'The description of the drop-in should be output.' );
		$this->assertStringContainsString( 'class="active', $output, 'The drop-in should be active.' );
		$this->assertStringNotContainsString( 'Inactive:', $output );
	}

	/**
	 * Tests the row of a drop-in that needs a constant that is not defined.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 */
	public function test_single_row_should_describe_a_dropin_that_needs_a_constant_as_inactive() {
		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			$this->markTestSkipped( 'The constant WP_CACHE is defined.' );
		}

		$this->set_global( 'status', 'dropins' );
		$this->set_global( 'page', 1 );

		$output = $this->render_row(
			'advanced-cache.php',
			array( 'Name' => 'advanced-cache.php' ),
			array(
				'name'        => 'Plugin',
				'description' => 'Description',
			)
		);

		$this->assertStringNotContainsString( 'advanced-cache.php<br />', $output, 'The name should not be repeated.' );
		$this->assertStringContainsString( 'Advanced caching plugin. <span class="error-message">Inactive:</span>', $output, 'The drop-in should be inactive.' );
		$this->assertStringContainsString( "<code>define('WP_CACHE', true);</code>", $output, 'The constant should be output.' );
		$this->assertStringContainsString( 'class="inactive', $output, 'The drop-in should be inactive.' );
	}

	/**
	 * Tests the message for a plugin that is not compatible, by what the user can update.
	 *
	 * Updating PHP needs the capability to update WordPress, so a filter separates them.
	 *
	 * @ticket 66552
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 *
	 * @dataProvider data_incompatible_message
	 *
	 * @param bool     $can_update_core Whether the user can update WordPress.
	 * @param bool     $can_update_php  Whether the user can learn about updating PHP.
	 * @param string[] $expected        The strings that the message should contain.
	 * @param string[] $unexpected      The strings that the message should not contain.
	 */
	public function test_single_row_should_explain_that_a_plugin_is_not_compatible( $can_update_core, $can_update_php, array $expected, array $unexpected ) {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) use ( $can_update_core, $can_update_php ) {
				if ( 'update_core' === $cap ) {
					return $can_update_core ? array( 'exist' ) : array( 'do_not_allow' );
				}

				if ( 'update_php' === $cap ) {
					return $can_update_php ? array( 'exist' ) : array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			2
		);

		$this->set_global( 'status', 'all' );
		$this->set_global( 'page', 1 );

		$output = $this->render_row(
			'hello.php',
			array(
				'RequiresPHP' => '99.0',
				'RequiresWP'  => '99.0',
			)
		);

		$this->assertStringContainsString( '<tr class="plugin-update-tr"><td colspan="', $output );
		$this->assertStringContainsString( 'This plugin does not work with your versions of WordPress and PHP.', $output );

		foreach ( $expected as $string ) {
			$this->assertStringContainsString( $string, $output );
		}

		foreach ( $unexpected as $string ) {
			$this->assertStringNotContainsString( $string, $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incompatible_message() {
		return array(
			'WordPress and PHP' => array(
				true,
				true,
				array( '/update-core.php">Please update WordPress</a>, and then <a href=', 'learn more about updating PHP</a>.' ),
				array(),
			),
			'WordPress only'    => array(
				true,
				false,
				array( '/update-core.php">Please update WordPress</a>.' ),
				array( 'pdating PHP' ),
			),
			'PHP only'          => array(
				false,
				true,
				array( 'Learn more about updating PHP</a>.' ),
				array( 'Please update WordPress' ),
			),
			'neither'           => array(
				false,
				false,
				array(),
				array( 'Please update WordPress', 'pdating PHP' ),
			),
		);
	}
}
