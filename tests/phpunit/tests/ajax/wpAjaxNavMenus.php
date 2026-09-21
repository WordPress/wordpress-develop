<?php
/**
 * Admin ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests Ajax handling for navigation menus.
 *
 * @group ajax
 */
class Tests_Ajax_wpAjaxNavMenus extends WP_Ajax_UnitTestCase {

	/**
	 * Grants manage_nav_menus to users who can read.
	 *
	 * @param string[] $caps    Primitive capabilities required of the user.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @return string[] Primitive capabilities required of the user.
	 */
	public function grant_manage_nav_menus_to_users_who_can_read( $caps, $cap, $user_id ) {
		if ( 'manage_nav_menus' === $cap && user_can( $user_id, 'read' ) ) {
			return array( 'read' );
		}

		return $caps;
	}

	/**
	 * @ticket 29213
	 *
	 * @dataProvider data_nav_menu_ajax_actions
	 *
	 * @param string $action    Ajax action.
	 * @param array  $post_data Data to populate $_POST.
	 */
	public function test_nav_menu_ajax_actions_require_manage_nav_menus( $action, $post_data ) {
		$this->_setRole( 'subscriber' );
		$_POST = $post_data;

		if ( 'add-menu-item' === $action ) {
			$_POST['menu-settings-column-nonce'] = wp_create_nonce( 'add-menu_item' );
		}

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( $action );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_nav_menu_ajax_actions() {
		return array(
			'add menu item'       => array(
				'action'    => 'add-menu-item',
				'post_data' => array(),
			),
			'get metabox'         => array(
				'action'    => 'menu-get-metabox',
				'post_data' => array(),
			),
			'save menu locations' => array(
				'action'    => 'menu-locations-save',
				'post_data' => array(),
			),
			'quick search'        => array(
				'action'    => 'menu-quick-search',
				'post_data' => array(),
			),
		);
	}

	/**
	 * @ticket 29213
	 */
	public function test_menu_quick_search_allows_user_who_can_manage_nav_menus() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Nav Menu Ajax Test Page',
			)
		);

		$this->_setRole( 'subscriber' );
		add_filter( 'map_meta_cap', array( $this, 'grant_manage_nav_menus_to_users_who_can_read' ), 10, 3 );

		try {
			$this->assertFalse( current_user_can( 'edit_theme_options' ) );
			$this->assertTrue( current_user_can( 'manage_nav_menus' ) );

			$_POST = array(
				'type'            => 'quick-search-posttype-post',
				'q'               => 'Nav Menu Ajax Test Page',
				'response-format' => 'json',
			);

			try {
				$this->_handleAjax( 'menu-quick-search' );
			} catch ( WPAjaxDieContinueException $e ) {
				unset( $e );
			}

			$this->assertStringContainsString( 'Nav Menu Ajax Test Page', $this->_last_response );
			$this->assertStringContainsString( (string) $post_id, $this->_last_response );
		} finally {
			remove_filter( 'map_meta_cap', array( $this, 'grant_manage_nav_menus_to_users_who_can_read' ), 10 );
		}
	}
}
