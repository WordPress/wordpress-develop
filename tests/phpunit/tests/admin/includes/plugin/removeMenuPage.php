<?php

/**
 * Tests for remove_menu_page() and remove_submenu_page().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_RemoveMenuPage extends WP_UnitTestCase {

	/**
	 * Whether the $menu global was set before the test.
	 *
	 * @var bool
	 */
	private $had_menu;

	/**
	 * Original $menu global value.
	 *
	 * @var mixed
	 */
	private $orig_menu;

	/**
	 * Whether the $submenu global was set before the test.
	 *
	 * @var bool
	 */
	private $had_submenu;

	/**
	 * Original $submenu global value.
	 *
	 * @var mixed
	 */
	private $orig_submenu;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->had_menu     = array_key_exists( 'menu', $GLOBALS );
		$this->orig_menu    = $this->had_menu ? $GLOBALS['menu'] : null;
		$this->had_submenu  = array_key_exists( 'submenu', $GLOBALS );
		$this->orig_submenu = $this->had_submenu ? $GLOBALS['submenu'] : null;

		$GLOBALS['menu'] = array(
			2  => array( 'Dashboard', 'read', 'index.php', '', 'menu-top menu-icon-dashboard', 'menu-dashboard', 'dashicons-dashboard' ),
			5  => array( 'Posts', 'edit_posts', 'edit.php', '', 'menu-top menu-icon-post', 'menu-posts', 'dashicons-admin-post' ),
			25 => array( 'My Plugin', 'manage_options', 'my-plugin', 'My Plugin', 'menu-top', 'toplevel_page_my-plugin', 'dashicons-admin-generic' ),
			26 => array( 'My Plugin Copy', 'manage_options', 'my-plugin', 'My Plugin Copy', 'menu-top', 'toplevel_page_my-plugin', 'dashicons-admin-generic' ),
			30 => array( 'Numeric', 'manage_options', '123', 'Numeric', 'menu-top', 'toplevel_page_123', 'dashicons-admin-generic' ),
		);

		$GLOBALS['submenu'] = array(
			'edit.php'  => array(
				5  => array( 'All Posts', 'edit_posts', 'edit.php' ),
				10 => array( 'Add Post', 'edit_posts', 'post-new.php' ),
				15 => array( 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=category' ),
				16 => array( 'Categories Copy', 'manage_categories', 'edit-tags.php?taxonomy=category' ),
			),
			'my-plugin' => array(
				0 => array( 'Settings', 'manage_options', 'my-plugin' ),
				1 => array( 'Numeric', 'manage_options', '123' ),
			),
		);
	}

	public function tear_down() {
		if ( $this->had_menu ) {
			$GLOBALS['menu'] = $this->orig_menu;
		} else {
			unset( $GLOBALS['menu'] );
		}

		if ( $this->had_submenu ) {
			$GLOBALS['submenu'] = $this->orig_submenu;
		} else {
			unset( $GLOBALS['submenu'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests that remove_menu_page() removes the menu item and returns it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_menu_page
	 */
	public function test_remove_menu_page_removes_and_returns_menu_item() {
		$removed = remove_menu_page( 'edit.php' );

		$this->assertSame(
			array( 'Posts', 'edit_posts', 'edit.php', '', 'menu-top menu-icon-post', 'menu-posts', 'dashicons-admin-post' ),
			$removed,
			'The removed menu item should be returned.'
		);
		$this->assertSame( array( 2, 25, 26, 30 ), array_keys( $GLOBALS['menu'] ), 'Only the matching menu item should be removed, keeping the other positions.' );
	}

	/**
	 * Tests that remove_menu_page() only removes the first menu item with a matching slug.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_menu_page
	 */
	public function test_remove_menu_page_only_removes_first_matching_menu_item() {
		$removed = remove_menu_page( 'my-plugin' );

		$this->assertSame( 'My Plugin', $removed[0], 'The first matching menu item should be returned.' );
		$this->assertSame( array( 2, 5, 26, 30 ), array_keys( $GLOBALS['menu'] ), 'The second matching menu item should be kept.' );
	}

	/**
	 * Tests that remove_menu_page() does not remove the submenu of the removed menu item.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_menu_page
	 */
	public function test_remove_menu_page_keeps_submenu_items() {
		$submenu = $GLOBALS['submenu'];

		remove_menu_page( 'edit.php' );

		$this->assertSame( $submenu, $GLOBALS['submenu'] );
	}

	/**
	 * Tests that remove_menu_page() returns false and keeps the menu when no item matches.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_menu_page
	 *
	 * @dataProvider data_menu_slugs_without_match
	 *
	 * @param mixed $menu_slug Menu slug.
	 */
	public function test_remove_menu_page_returns_false_without_match( $menu_slug ) {
		$menu = $GLOBALS['menu'];

		$this->assertFalse( remove_menu_page( $menu_slug ) );
		$this->assertSame( $menu, $GLOBALS['menu'], 'The menu should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_menu_slugs_without_match() {
		return array(
			'unknown slug'            => array( 'does-not-exist' ),
			'empty slug'              => array( '' ),
			'partial slug'            => array( 'edit' ),
			'different case'          => array( 'EDIT.PHP' ),
			'submenu slug'            => array( 'post-new.php' ),
			'integer for string slug' => array( 123 ),
			'null'                    => array( null ),
			'false'                   => array( false ),
		);
	}

	/**
	 * Tests that remove_menu_page() returns false when the menu is empty.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_menu_page
	 */
	public function test_remove_menu_page_returns_false_for_empty_menu() {
		$GLOBALS['menu'] = array();

		$this->assertFalse( remove_menu_page( 'edit.php' ) );
		$this->assertSame( array(), $GLOBALS['menu'] );
	}

	/**
	 * Tests that remove_submenu_page() removes the submenu item and returns it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_submenu_page
	 */
	public function test_remove_submenu_page_removes_and_returns_submenu_item() {
		$removed = remove_submenu_page( 'edit.php', 'post-new.php' );

		$this->assertSame( array( 'Add Post', 'edit_posts', 'post-new.php' ), $removed, 'The removed submenu item should be returned.' );
		$this->assertSame( array( 5, 15, 16 ), array_keys( $GLOBALS['submenu']['edit.php'] ), 'Only the matching submenu item should be removed, keeping the other positions.' );
		$this->assertSame( array( 0, 1 ), array_keys( $GLOBALS['submenu']['my-plugin'] ), 'Other submenus should not change.' );
	}

	/**
	 * Tests that remove_submenu_page() only removes the first submenu item with a matching slug.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_submenu_page
	 */
	public function test_remove_submenu_page_only_removes_first_matching_submenu_item() {
		$removed = remove_submenu_page( 'edit.php', 'edit-tags.php?taxonomy=category' );

		$this->assertSame( 'Categories', $removed[0], 'The first matching submenu item should be returned.' );
		$this->assertSame( array( 5, 10, 16 ), array_keys( $GLOBALS['submenu']['edit.php'] ), 'The second matching submenu item should be kept.' );
	}

	/**
	 * Tests that remove_submenu_page() keeps an empty submenu after removing its last item.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_submenu_page
	 */
	public function test_remove_submenu_page_keeps_empty_submenu() {
		remove_submenu_page( 'my-plugin', 'my-plugin' );
		remove_submenu_page( 'my-plugin', '123' );

		$this->assertSame( array(), $GLOBALS['submenu']['my-plugin'] );
	}

	/**
	 * Tests that remove_submenu_page() does not remove the parent menu item.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_submenu_page
	 */
	public function test_remove_submenu_page_keeps_menu_items() {
		$menu = $GLOBALS['menu'];

		remove_submenu_page( 'my-plugin', 'my-plugin' );

		$this->assertSame( $menu, $GLOBALS['menu'] );
	}

	/**
	 * Tests that remove_submenu_page() returns false and keeps the submenus when no item matches.
	 *
	 * @ticket 65819
	 *
	 * @covers ::remove_submenu_page
	 *
	 * @dataProvider data_submenu_slugs_without_match
	 *
	 * @param mixed $menu_slug    Parent menu slug.
	 * @param mixed $submenu_slug Submenu slug.
	 */
	public function test_remove_submenu_page_returns_false_without_match( $menu_slug, $submenu_slug ) {
		$submenu = $GLOBALS['submenu'];

		$this->assertFalse( remove_submenu_page( $menu_slug, $submenu_slug ) );
		$this->assertSame( $submenu, $GLOBALS['submenu'], 'The submenus should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_submenu_slugs_without_match() {
		return array(
			'unknown parent slug'       => array( 'does-not-exist', 'post-new.php' ),
			'parent without a submenu'  => array( 'index.php', 'index.php' ),
			'empty parent slug'         => array( '', 'post-new.php' ),
			'unknown submenu slug'      => array( 'edit.php', 'does-not-exist' ),
			'empty submenu slug'        => array( 'edit.php', '' ),
			'submenu of another parent' => array( 'my-plugin', 'post-new.php' ),
			'different case'            => array( 'edit.php', 'POST-NEW.PHP' ),
			'integer for string slug'   => array( 'my-plugin', 123 ),
			'null submenu slug'         => array( 'edit.php', null ),
		);
	}
}
