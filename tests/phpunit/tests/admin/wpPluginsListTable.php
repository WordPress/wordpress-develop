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

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
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
	 * Tests that WP_Plugins_List_Table::prepare_items() 
	 * applies 'plugins_list' filters.
	 *
	 * @ticket 57278
	 *
	 * @covers WP_Plugins_List_Table::prepare_items
	 */
	public function test_plugins_list_filter() {
		global $status;

		$old_status = $status;
		$status     = 'mustuse';

		add_filter( 'plugins_list', array( $this, 'plugins_list_filter' ), 10, 1 );
		$this->table->prepare_items();
		$plugins = $this->table->items;
		remove_filter( 'plugins_list', array( $this, 'plugins_list_filter' ), 10 );

		// Restore to default.
		$status = $old_status;
		$this->table->prepare_items();

		$this->assertSame( $plugins, $this->fake_plugin );
	}

	public function plugins_list_filter( $plugins_list ) {
		$plugins_list['mustuse'] = $this->fake_plugin;

		return $plugins_list;
	}
}
