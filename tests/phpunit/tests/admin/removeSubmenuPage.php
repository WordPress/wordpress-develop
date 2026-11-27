<?php
/**
 * @group plugins
 * @group admin
 */
class Tests_Admin_RemoveSubmenuPage extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $_wp_submenu_nopriv;
		$_wp_submenu_nopriv = array();
	}

	/**
	 * Tests that removing and re-adding a submenu page with different capabilities
	 * correctly updates the $_wp_submenu_nopriv global and user access.
	 *
	 * @ticket 47690
	 */
	public function test_remove_and_readd_submenu_page_allows_access() {
		global $_wp_submenu_nopriv, $plugin_page, $pagenow;

		$parent_slug  = 'tools.php';
		$submenu_slug = 'my-plugin-page';
		$page_title   = 'My Plugin Page';
		$menu_title   = 'My Plugin';
		$denied_cap   = 'do_not_have_this_cap';
		$allowed_cap  = 'manage_options';

		// Create an administrator user.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Add submenu page with denied capability. Should add to nopriv.
		add_submenu_page( $parent_slug, $page_title, $menu_title, $denied_cap, $submenu_slug, '__return_null' );
		$this->assertArrayHasKey( $submenu_slug, $_wp_submenu_nopriv[ $parent_slug ] );

		// Remove submenu page. Should remove from nopriv.
		remove_submenu_page( $parent_slug, $submenu_slug );
		if ( isset( $_wp_submenu_nopriv[ $parent_slug ] ) ) {
			$this->assertArrayNotHasKey( $submenu_slug, $_wp_submenu_nopriv[ $parent_slug ] );
		}

		// Re-add submenu page with allowed capability. Should not add to nopriv.
		add_submenu_page( $parent_slug, $page_title, $menu_title, $allowed_cap, $submenu_slug, '__return_null' );
		if ( isset( $_wp_submenu_nopriv[ $parent_slug ] ) ) {
			$this->assertArrayNotHasKey( $submenu_slug, $_wp_submenu_nopriv[ $parent_slug ] );
		}

		// Simulate access check for this submenu page.
		$plugin_page = $submenu_slug;
		$pagenow     = $parent_slug;
		$this->assertTrue( user_can_access_admin_page() );

		// Clean up global state.
		unset( $plugin_page, $pagenow );
	}
}
