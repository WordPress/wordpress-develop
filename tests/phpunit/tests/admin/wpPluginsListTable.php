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
				'user_email' => 'testadmin@test.com',
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
		$original_status           = isset( $_REQUEST['plugin_status'] ) ? $_REQUEST['plugin_status'] : null;
		$_REQUEST['plugin_status'] = $status;

		// Enable plugin auto-updates.
		add_filter( 'plugins_auto_update_enabled', '__return_true' );

		// Use a user with the 'manage_plugins' capability.
		wp_set_current_user( self::$admin_id );

		$list_table       = new WP_Plugins_List_Table();
		$show_autoupdates = new ReflectionProperty( $list_table, 'show_autoupdates' );

		$show_autoupdates->setAccessible( true );
		$actual = $show_autoupdates->getValue( $list_table );
		$show_autoupdates->setAccessible( false );

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
}
