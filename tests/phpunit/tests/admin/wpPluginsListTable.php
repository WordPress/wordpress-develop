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

		parent::tear_down();
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
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_status_mustuse_and_dropins() {
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
	 * Tests that WP_Plugins_List_Table::get_plugin_author_key() derives the
	 * grouping key from the plugin's AuthorName header.
	 *
	 * @covers WP_Plugins_List_Table::get_plugin_author_key
	 *
	 * @dataProvider data_plugin_author_key
	 *
	 * @param array  $plugin_data The plugin data passed to the method.
	 * @param string $expected    The expected author key.
	 */
	public function test_get_plugin_author_key( $plugin_data, $expected ) {
		$method = new ReflectionMethod( $this->table, 'get_plugin_author_key' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$this->assertSame( $expected, $method->invoke( $this->table, 'fake/fake.php', $plugin_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_plugin_author_key() {
		return array(
			'an author name'   => array( array( 'AuthorName' => 'Team Yoast' ), 'team-yoast' ),
			'a missing author' => array( array(), '' ),
			'an empty author'  => array( array( 'AuthorName' => '' ), '' ),
		);
	}

	/**
	 * Tests that WP_Plugins_List_Table::prepare_items() builds the list of
	 * plugin authors with a per-author plugin count.
	 *
	 * @covers WP_Plugins_List_Table::prepare_items
	 */
	public function test_prepare_items_builds_plugin_authors_with_counts() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );
		$this->table->prepare_items();
		remove_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );

		$authors = $this->get_plugin_authors();

		$status = $old_status;

		$this->assertArrayHasKey( 'author-one', $authors, 'The first author is missing.' );
		$this->assertArrayHasKey( 'author-two', $authors, 'The second author is missing.' );
		$this->assertSame( 2, $authors['author-one']['count'], 'The first author count is wrong.' );
		$this->assertSame( 1, $authors['author-two']['count'], 'The second author count is wrong.' );
		$this->assertSame( 'Author One', $authors['author-one']['label'], 'The first author label is wrong.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::prepare_items() narrows the list to a
	 * single author when the 'plugin_author' request variable is set.
	 *
	 * @covers WP_Plugins_List_Table::prepare_items
	 */
	public function test_prepare_items_filters_items_by_author() {
		global $status, $s;

		$old_status                = $status;
		$old_author                = $_REQUEST['plugin_author'] ?? null;
		$status                    = 'all';
		$s                         = '';
		$_REQUEST['plugin_author'] = 'author-one';

		add_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );
		$this->table->prepare_items();
		remove_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );

		$items = array_keys( $this->table->items );

		$status = $old_status;
		if ( null === $old_author ) {
			unset( $_REQUEST['plugin_author'] );
		} else {
			$_REQUEST['plugin_author'] = $old_author;
		}

		$this->assertEqualSets(
			array( 'plugin-a/plugin-a.php', 'plugin-c/plugin-c.php' ),
			$items
		);
	}

	/**
	 * Tests that the 'plugins_list_plugin_author' filter can merge author-name
	 * variants under a single author key.
	 *
	 * @covers WP_Plugins_List_Table::get_plugin_author_key
	 */
	public function test_plugins_list_plugin_author_filter_merges_author_variants() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_yoast_variants' ) );
		add_filter( 'plugins_list_plugin_author', array( $this, 'merge_yoast_author_key' ), 10, 3 );
		$this->table->prepare_items();
		remove_filter( 'plugins_list_plugin_author', array( $this, 'merge_yoast_author_key' ), 10 );
		remove_filter( 'plugins_list', array( $this, 'inject_yoast_variants' ) );

		$authors = $this->get_plugin_authors();

		$status = $old_status;

		$this->assertSame( array( 'yoast' ), array_keys( $authors ), 'The author variants were not merged.' );
		$this->assertSame( 2, $authors['yoast']['count'], 'The merged author count is wrong.' );
	}

	/**
	 * Tests that the 'plugins_list_authors' filter can remove an author from
	 * the "Filter by author" control.
	 *
	 * @covers WP_Plugins_List_Table::prepare_items
	 */
	public function test_plugins_list_authors_filter_can_remove_an_author() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );
		add_filter( 'plugins_list_authors', array( $this, 'remove_second_author' ) );
		$this->table->prepare_items();
		remove_filter( 'plugins_list_authors', array( $this, 'remove_second_author' ) );
		remove_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );

		$authors = $this->get_plugin_authors();

		$status = $old_status;

		$this->assertArrayHasKey( 'author-one', $authors, 'The retained author is missing.' );
		$this->assertArrayNotHasKey( 'author-two', $authors, 'The removed author is still present.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::extra_tablenav() renders the author
	 * filter control when more than one author is installed.
	 *
	 * @covers WP_Plugins_List_Table::extra_tablenav
	 */
	public function test_extra_tablenav_renders_author_filter_for_multiple_authors() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );
		$this->table->prepare_items();
		$output = $this->get_extra_tablenav_output();
		remove_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );

		$status = $old_status;

		$this->assertStringContainsString( 'id="plugin-author-filter"', $output, 'The author filter select was not rendered.' );
		$this->assertStringContainsString( 'name="plugin_author"', $output, 'The select is missing its name attribute.' );
		$this->assertStringContainsString( 'value="author-one"', $output, 'The author option was not rendered.' );
		$this->assertStringContainsString( 'id="plugin-author-filter-submit"', $output, 'The Filter submit button was not rendered.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::extra_tablenav() does not render the
	 * author filter control when only one author is installed.
	 *
	 * @covers WP_Plugins_List_Table::extra_tablenav
	 */
	public function test_extra_tablenav_does_not_render_author_filter_for_single_author() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_single_author_plugin' ) );
		$this->table->prepare_items();
		$output = $this->get_extra_tablenav_output();
		remove_filter( 'plugins_list', array( $this, 'inject_single_author_plugin' ) );

		$status = $old_status;

		$this->assertStringNotContainsString( 'plugin-author-filter', $output, 'The author filter control should not be rendered for a single author.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::author_filter_form() prints a GET form
	 * when more than one author is installed.
	 *
	 * @covers WP_Plugins_List_Table::author_filter_form
	 */
	public function test_author_filter_form_renders_for_multiple_authors() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );
		$this->table->prepare_items();
		ob_start();
		$this->table->author_filter_form();
		$output = ob_get_clean();
		remove_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );

		$status = $old_status;

		$this->assertStringContainsString( 'id="plugin-author-filter-form"', $output, 'The filter form was not rendered.' );
		$this->assertStringContainsString( 'method="get"', $output, 'The filter form should submit via GET.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::author_filter_form() preserves the current
	 * status view in a hidden field.
	 *
	 * @covers WP_Plugins_List_Table::author_filter_form
	 */
	public function test_author_filter_form_preserves_current_status() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );
		$this->table->prepare_items();
		// Set the status after prepare_items() so the empty-bucket fallback does not reset it.
		$status = 'inactive';
		ob_start();
		$this->table->author_filter_form();
		$output = ob_get_clean();
		remove_filter( 'plugins_list', array( $this, 'inject_multi_author_plugins' ) );

		$status = $old_status;

		$this->assertStringContainsString( 'name="plugin_status"', $output, 'The status field is missing.' );
		$this->assertStringContainsString( 'value="inactive"', $output, 'The current status was not preserved.' );
	}

	/**
	 * Tests that WP_Plugins_List_Table::author_filter_form() prints nothing when
	 * only one author is installed.
	 *
	 * @covers WP_Plugins_List_Table::author_filter_form
	 */
	public function test_author_filter_form_is_empty_for_single_author() {
		global $status, $s;

		$old_status = $status;
		$status     = 'all';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'inject_single_author_plugin' ) );
		$this->table->prepare_items();
		ob_start();
		$this->table->author_filter_form();
		$output = ob_get_clean();
		remove_filter( 'plugins_list', array( $this, 'inject_single_author_plugin' ) );

		$status = $old_status;

		$this->assertSame( '', $output, 'The filter form should not be rendered for a single author.' );
	}

	/**
	 * Returns the protected 'plugin_authors' property of the list table.
	 *
	 * @return array The plugin authors map.
	 */
	private function get_plugin_authors() {
		$property = new ReflectionProperty( $this->table, 'plugin_authors' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue( $this->table );
	}

	/**
	 * Captures the output of the protected extra_tablenav() method for the top nav.
	 *
	 * @return string The captured output.
	 */
	private function get_extra_tablenav_output() {
		$method = new ReflectionMethod( $this->table, 'extra_tablenav' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invoke( $this->table, 'top' );

		return ob_get_clean();
	}

	/**
	 * Builds a fake plugin data array for the given name and author.
	 *
	 * @param string $name   The plugin name.
	 * @param string $author The plugin author name.
	 * @return array The fake plugin data.
	 */
	private function fake_plugin_data( $name, $author ) {
		return array(
			'Name'        => $name,
			'PluginURI'   => 'https://example.org/',
			'Version'     => '1.0.0',
			'Description' => $name . '.',
			'Author'      => $author,
			'AuthorURI'   => 'https://example.org/',
			'TextDomain'  => sanitize_title( $name ),
			'DomainPath'  => '/languages',
			'Network'     => false,
			'Title'       => $name,
			'AuthorName'  => $author,
		);
	}

	/**
	 * Replaces the 'all' plugins with a set from two distinct authors.
	 *
	 * Used as a callback for the 'plugins_list' hook.
	 *
	 * @param array $plugins_list The plugins list keyed by status.
	 * @return array The filtered plugins list.
	 */
	public function inject_multi_author_plugins( $plugins_list ) {
		$plugins_list['all'] = array(
			'plugin-a/plugin-a.php' => $this->fake_plugin_data( 'Plugin A', 'Author One' ),
			'plugin-b/plugin-b.php' => $this->fake_plugin_data( 'Plugin B', 'Author Two' ),
			'plugin-c/plugin-c.php' => $this->fake_plugin_data( 'Plugin C', 'Author One' ),
		);

		return $plugins_list;
	}

	/**
	 * Replaces the 'all' plugins with a single plugin from one author.
	 *
	 * Used as a callback for the 'plugins_list' hook.
	 *
	 * @param array $plugins_list The plugins list keyed by status.
	 * @return array The filtered plugins list.
	 */
	public function inject_single_author_plugin( $plugins_list ) {
		$plugins_list['all'] = array(
			'plugin-a/plugin-a.php' => $this->fake_plugin_data( 'Plugin A', 'Author One' ),
		);

		return $plugins_list;
	}

	/**
	 * Replaces the 'all' plugins with two plugins using author-name variants.
	 *
	 * Used as a callback for the 'plugins_list' hook.
	 *
	 * @param array $plugins_list The plugins list keyed by status.
	 * @return array The filtered plugins list.
	 */
	public function inject_yoast_variants( $plugins_list ) {
		$plugins_list['all'] = array(
			'wordpress-seo/wp-seo.php'    => $this->fake_plugin_data( 'Yoast SEO', 'Team Yoast' ),
			'wpseo-local/wpseo-local.php' => $this->fake_plugin_data( 'Yoast Local SEO', 'Yoast' ),
		);

		return $plugins_list;
	}

	/**
	 * Merges Yoast author-name variants under a single 'yoast' key.
	 *
	 * Used as a callback for the 'plugins_list_plugin_author' hook.
	 *
	 * @param string $author_key  The derived author key.
	 * @param string $plugin_file The plugin file.
	 * @param array  $plugin_data The plugin data.
	 * @return string The author key, merged to 'yoast' for Yoast plugins.
	 */
	public function merge_yoast_author_key( $author_key, $plugin_file, $plugin_data ) {
		if ( isset( $plugin_data['AuthorName'] ) && false !== stripos( $plugin_data['AuthorName'], 'yoast' ) ) {
			return 'yoast';
		}

		return $author_key;
	}

	/**
	 * Removes the second test author from the authors list.
	 *
	 * Used as a callback for the 'plugins_list_authors' hook.
	 *
	 * @param array $authors The authors map.
	 * @return array The filtered authors map.
	 */
	public function remove_second_author( $authors ) {
		unset( $authors['author-two'] );

		return $authors;
	}
}
