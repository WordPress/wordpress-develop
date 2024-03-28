<?php

require_once __DIR__ . '/Admin_WpPluginsListTable_TestCase.php';


/**
 * @group admin
 *
 * @covers WP_Plugins_List_Table::SINGLE_ROW
 */
class Admin_WpPluginsListTable_SingleRow_Test extends Admin_WpPluginsListTable_TestCase {

	/**
	 * Tests that WP_Plugins_List_Table::single_row() does not output the
	 * 'Auto-updates' column for Must-Use or Drop-in plugins.
	 *
	 * @ticket 54309
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
}
