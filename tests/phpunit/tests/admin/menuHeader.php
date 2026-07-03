<?php
/**
 * @group admin
 */
class Tests_Admin_MenuHeader extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	public static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_top_level_url() {
		wp_set_current_user( self::$admin_id );
		update_option( 'siteurl', 'http://example.com' );

		$menu = array(
			array( 'Dashboard', 'read', 'index.php', 'Dashboard', 'menu-top', 'menu-dashboard', 'dashicons-dashboard' ),
		);

		$actual = $this->render_admin_menu( $menu, array() );

		$this->assertStringContainsString( "href='http://example.com/wp-admin/index.php'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_submenu_parent_url() {
		wp_set_current_user( self::$admin_id );
		update_option( 'siteurl', 'http://example.com' );

		$menu    = array(
			array( 'Tools', 'read', 'tools.php', 'Tools', 'menu-top', 'menu-tools', 'dashicons-admin-tools' ),
		);
		$submenu = array(
			'tools.php' => array(
				array( 'Available Tools', 'read', 'tools.php', 'Available Tools' ),
			),
		);

		$actual = $this->render_admin_menu( $menu, $submenu );

		$this->assertStringContainsString( "href='http://example.com/wp-admin/tools.php'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_submenu_urls() {
		wp_set_current_user( self::$admin_id );
		update_option( 'siteurl', 'http://example.com' );

		$menu    = array(
			array( 'Posts', 'read', 'edit.php', 'Posts', 'menu-top', 'menu-posts', 'dashicons-admin-post' ),
		);
		$submenu = array(
			'edit.php' => array(
				array( 'All Posts', 'read', 'edit.php', 'All Posts' ),
				array( 'Add New', 'read', 'post-new.php?post_type=post', 'Add New' ),
			),
		);

		$actual = $this->render_admin_menu( $menu, $submenu );

		$this->assertStringContainsString( "href='http://example.com/wp-admin/edit.php'", $actual );
		$this->assertStringContainsString( "href='http://example.com/wp-admin/post-new.php?post_type=post'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_plugin_page_url() {
		wp_set_current_user( self::$admin_id );
		update_option( 'siteurl', 'http://example.com' );

		add_menu_page( 'Test Page', 'Test Page', 'read', 'test-page', '__return_null' );

		global $menu;

		$actual = $this->render_admin_menu( $menu, array(), false );

		$this->assertStringContainsString( "href='http://example.com/wp-admin/admin.php?page=test-page'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_preserves_external_urls() {
		wp_set_current_user( self::$admin_id );
		update_option( 'siteurl', 'http://example.com' );

		$menu = array(
			array( 'External', 'read', 'https://wordpress.org/news/', 'External', 'menu-top', 'menu-external', 'dashicons-admin-site' ),
		);

		$actual = $this->render_admin_menu( $menu, array() );

		$this->assertStringContainsString( "href='https://wordpress.org/news/'", $actual );
	}

	private function render_admin_menu( $menu, $submenu, $submenu_as_parent = true ) {
		$this->require_menu_header();

		global $self, $parent_file, $submenu_file, $plugin_page, $typenow;

		$self         = 'index.php';
		$parent_file  = '';
		$submenu_file = '';
		$plugin_page  = null;
		$typenow      = '';

		ob_start();
		_wp_menu_output( $menu, $submenu, $submenu_as_parent );
		return ob_get_clean();
	}

	private function require_menu_header() {
		if ( function_exists( '_wp_menu_output' ) ) {
			return;
		}

		global $menu, $submenu, $parent_file, $submenu_file;

		$menu         = array();
		$submenu      = array();
		$parent_file  = '';
		$submenu_file = '';

		$_SERVER['PHP_SELF'] = '/wp-admin/index.php';

		ob_start();
		require ABSPATH . 'wp-admin/menu-header.php';
		ob_end_clean();
	}
}
