<?php

/**
 * @group admin
 *
 * @covers WP_Plugin_Install_List_Table
 */
class Tests_Admin_wpPluginInstallListTable extends WP_UnitTestCase {
	/**
	 * @var WP_Plugin_Install_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$this->table = _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Plugin_Install_List_Table::get_views
	 */
	public function test_get_views_should_return_no_views_by_default() {
		$this->assertSame( array(), $this->table->get_views() );
	}

	/**
	 * @ticket 61211
	 *
	 * @covers WP_Plugin_Install_List_Table::display_rows
	 */
	public function test_display_rows_incompatible_wp_displays_required_version() {
		$this->table->items = array(
			array(
				'slug'              => 'incompatible-test-plugin',
				'name'              => 'Incompatible Test Plugin',
				'version'           => '1.0.0',
				'author'            => 'Test Author',
				'requires'          => '99.0',
				'requires_php'      => '7.0',
				'last_updated'      => '2026-01-01 00:00:00',
				'icons'             => array( 'default' => 'http://example.org/icon.png' ),
				'short_description' => 'A test plugin.',
				'rating'            => 100,
				'num_ratings'       => 1,
				'active_installs'   => 1000,
			),
		);

		ob_start();
		$this->table->display_rows();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'This plugin requires WordPress 99.0 or higher.', $output );
	}
}
