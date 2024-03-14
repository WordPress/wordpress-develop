<?php

require_once __DIR__ . '/Admin_WpPluginsListTable_TestCase.php';

/**
 * @group admin
 *
 * @covers WP_Plugins_List_Table::__construct()
 */
class Admin_WpPluginsListTable_Construct_Test extends Admin_WpPluginsListTable_TestCase {
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
}
