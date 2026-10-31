<?php

/**
 * Tests for Bulk_Plugin_Upgrader_Skin.
 *
 * @group admin
 * @group plugins
 * @group upgrade
 */
class Tests_Admin_BulkPluginUpgraderSkin extends WP_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * ID of a user who can update but not activate plugins.
	 *
	 * @var int
	 */
	private static $updater_id;

	/**
	 * Loads the upgrader classes and sets up the users.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		self::$admin_id   = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$updater_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( self::$updater_id )->add_cap( 'update_plugins' );
	}

	/**
	 * Tests that the strings for updating plugins are added to the upgrader.
	 *
	 * @ticket 65819
	 *
	 * @covers Bulk_Plugin_Upgrader_Skin::add_strings
	 */
	public function test_add_strings_should_add_plugin_header_and_bulk_strings() {
		$skin = $this->get_skin();

		$this->assertSame( 'Updating Plugin %1$s (%2$d/%3$d)', $skin->upgrader->strings['skin_before_update_header'], 'The plugin header should be added.' );
		$this->assertSame( 'All updates have been completed.', $skin->upgrader->strings['skin_upgrade_end'], 'The bulk strings should be added.' );
	}

	/**
	 * Tests the footer links after updating plugins.
	 *
	 * Site administrators cannot activate plugins on multisite by default.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @covers Bulk_Plugin_Upgrader_Skin::bulk_footer
	 */
	public function test_bulk_footer_should_link_to_plugins_and_updates_pages() {
		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			"<p>All updates have been completed.</p>\n"
			. '<p><a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/plugins.php" target="_parent">Go to Plugins page</a>'
			. ' | <a href="http://' . WP_TESTS_DOMAIN . "/wp-admin/update-core.php\" target=\"_parent\">Go to WordPress Updates page</a></p>\n",
			get_echo( array( $this->get_skin(), 'bulk_footer' ) )
		);
	}

	/**
	 * Tests that the Plugins page is not linked for a user who cannot activate plugins.
	 *
	 * @ticket 65819
	 *
	 * @covers Bulk_Plugin_Upgrader_Skin::bulk_footer
	 */
	public function test_bulk_footer_should_not_link_plugins_page_for_user_without_capability() {
		wp_set_current_user( self::$updater_id );

		$this->assertSame(
			"<p>All updates have been completed.</p>\n"
			. '<p><a href="http://' . WP_TESTS_DOMAIN . "/wp-admin/update-core.php\" target=\"_parent\">Go to WordPress Updates page</a></p>\n",
			get_echo( array( $this->get_skin(), 'bulk_footer' ) )
		);
	}

	/**
	 * Tests that the footer links can be filtered, and none are output when they are removed.
	 *
	 * Site administrators cannot activate plugins on multisite by default.
	 *
	 * @ticket 65819
	 *
	 * @group ms-excluded
	 *
	 * @covers Bulk_Plugin_Upgrader_Skin::bulk_footer
	 */
	public function test_bulk_footer_should_filter_links() {
		wp_set_current_user( self::$admin_id );

		$filter = new MockAction();
		add_filter( 'update_bulk_plugins_complete_actions', array( $filter, 'filter' ), 10, 2 );
		add_filter( 'update_bulk_plugins_complete_actions', '__return_empty_array', 11 );

		$skin              = $this->get_skin();
		$skin->plugin_info = array( 'Title' => 'My Plugin' );

		$output = get_echo( array( $skin, 'bulk_footer' ) );
		$args   = $filter->get_args();

		$this->assertSame( "<p>All updates have been completed.</p>\n", $output, 'No links should be output.' );
		$this->assertSame( array( 'plugins_page', 'updates_page' ), array_keys( $args[0][0] ), 'The filter should receive both links.' );
		$this->assertSame( array( 'Title' => 'My Plugin' ), $args[0][1], 'The filter should receive the plugin data.' );
	}

	/**
	 * Returns a bulk plugin upgrader skin attached to an upgrader.
	 *
	 * @return Bulk_Plugin_Upgrader_Skin The skin.
	 */
	private function get_skin() {
		$skin     = new Bulk_Plugin_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		// Done by WP_Upgrader::init(), which also schedules a backup cleanup.
		$skin->set_upgrader( $upgrader );

		return $skin;
	}
}
