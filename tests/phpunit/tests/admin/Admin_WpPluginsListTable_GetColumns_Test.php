<?php

require_once __DIR__ . '/Admin_WpPluginsListTable_TestCase.php';

/**
 * @group admin
 *
 * @covers WP_Plugins_List_Table::get_columns
 */
class Admin_WpPluginsListTable_GetColumns_Test extends Admin_WpPluginsListTable_TestCase {
	/**
	 * Tests that WP_Plugins_List_Table::get_columns() does not add
	 * the auto-update column when not viewing Must-Use or Drop-in plugins.
	 *
	 * @ticket 54309
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
}
