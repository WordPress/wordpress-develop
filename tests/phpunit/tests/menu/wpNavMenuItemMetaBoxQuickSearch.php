<?php

/**
 * Tests for the no-JS quick-search fallback in the classic Menus screen meta boxes.
 *
 * @group menu
 */
class Tests_Menu_WpNavMenuItemMetaBoxQuickSearch extends WP_UnitTestCase {

	/**
	 * Test that the post type meta box's search tab returns an exact slug match
	 * even when the title has no overlap with the query.
	 *
	 * @ticket 44803
	 */
	public function test_post_type_meta_box_search_returns_exact_slug_match() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$GLOBALS['_nav_menu_placeholder'] = 0;
		$GLOBALS['nav_menu_selected_id']  = 0;

		self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Completely Unrelated Title',
				'post_name'  => 'zzz-exact-slug-target',
			)
		);

		$_REQUEST['quick-search-posttype-post'] = 'zzz-exact-slug-target';

		$output = get_echo(
			'wp_nav_menu_item_post_type_meta_box',
			array( null, array( 'args' => get_post_type_object( 'post' ) ) )
		);

		unset( $_REQUEST['quick-search-posttype-post'] );

		$this->assertStringContainsString( 'value="Completely Unrelated Title"', $output );
	}

	/**
	 * Test that the taxonomy meta box's search tab returns an exact slug match
	 * even when the name has no overlap with the query.
	 *
	 * @ticket 44803
	 */
	public function test_taxonomy_meta_box_search_returns_exact_slug_match() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$GLOBALS['nav_menu_selected_id'] = 0;

		register_taxonomy( 'wptests_tax', 'post' );

		self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'Something Else Entirely',
				'slug'     => 'zzz-term-slug-target',
			)
		);

		$_REQUEST['quick-search-taxonomy-wptests_tax'] = 'zzz-term-slug-target';

		$output = get_echo(
			'wp_nav_menu_item_taxonomy_meta_box',
			array( null, array( 'args' => get_taxonomy( 'wptests_tax' ) ) )
		);

		unset( $_REQUEST['quick-search-taxonomy-wptests_tax'] );

		$this->assertStringContainsString( 'value="Something Else Entirely"', $output );
	}
}
