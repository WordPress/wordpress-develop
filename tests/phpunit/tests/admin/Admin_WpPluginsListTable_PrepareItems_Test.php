<?php

require_once __DIR__ . '/Admin_WpPluginsListTable_TestCase.php';

/**
 * @group admin
 *
 * @covers WP_Plugins_List_Table::prepare_items
 */
class Admin_WpPluginsListTable_PrepareItems_Test extends Admin_WpPluginsListTable_TestCase {
	/**
	 * Tests that WP_Plugins_List_Table::prepare_items()
	 * applies 'plugins_list' filters.
	 *
	 * @ticket 57278
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
}
