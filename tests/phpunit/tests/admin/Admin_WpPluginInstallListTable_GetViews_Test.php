<?php

/**
 * @group admin
 *
 * @covers WP_Plugin_Install_List_Table::get_views
 */
class Admin_WpPluginInstallListTable_GetViews_Test extends WP_UnitTestCase {
	/**
	 * @var WP_Plugin_Install_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );
	}

	/**
	 * @ticket 42066
	 */
	public function test_get_views_should_return_no_views_by_default() {
		$this->assertSame( array(), $this->table->get_views() );
	}
}
