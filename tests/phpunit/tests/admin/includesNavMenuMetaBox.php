<?php
/**
 * Tests for wp-admin/includes/nav-menu.php meta box functionality
 *
 * @package WordPress
 */

/**
 * Class Test_Admin_includesNavMenuMetaBox
 *
 * @since 6.9.0
 *
 * @group admin
 * @group nav-menu
 */
class Test_Admin_IncludesNavMenuMetaBox extends WP_UnitTestCase {

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
		set_current_screen( 'nav-menus' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );

		parent::tear_down();
	}

	/**
	 * Test that view-all tab shows all pages including important pages when no suppression is needed.
	 *
	 * @covers ::wp_nav_menu_item_post_type_meta_box
	 */
	public function test_view_all_tab_displays_all_pages_with_important_pages() {
		$front_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Front Page',
				'post_status' => 'publish',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page );

		$old_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Old Page',
				'post_status' => 'publish',
				'post_date'   => '2000-01-01 00:00:00',
			)
		);

		$recent_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Recent Page',
				'post_status' => 'publish',
			)
		);

		// The right order should be
		// 1. Front Page
		// 2. Old Page
		// 3. Recent Page
		// First important pages, then strictly by title.

		$post_type = get_post_type_object( 'page' );
		$box       = array(
			'args' => $post_type,
		);

		ob_start();
		wp_nav_menu_item_post_type_meta_box( null, $box );
		$output = ob_get_clean();

		// Extract only the "View All" tab content to avoid interference from other tabs.
		$dom = new DOMDocument();
		$dom->loadHTML( $output );
		$xpath            = new DOMXPath( $dom );
		$page_all_div     = $xpath->query( '//div[@id="page-all"]' )->item( 0 );
		$view_all_content = $page_all_div ? $dom->saveHTML( $page_all_div ) : '';

		$this->assertNotEmpty( $view_all_content, 'Should find the View All tab content' );

		$this->assertStringContainsString( 'Recent Page', $view_all_content, 'Recent Page should be present in View All tab' );
		$this->assertStringContainsString( 'Front Page', $view_all_content, 'Front Page should be present in View All tab' );

		$front_page_position  = strpos( $view_all_content, 'Front Page' );
		$recent_page_position = strpos( $view_all_content, 'Recent Page' );
		$old_page_position    = strpos( $view_all_content, 'Old Page' );

		$this->assertLessThan( $recent_page_position, $front_page_position, 'Front Page should appear before Recent Page due to important pages being merged first' );
		$this->assertLessThan( $recent_page_position, $old_page_position, 'Old Page should appear before Recent Page according to the order of titles ' );
	}

	/**
	 * Test that most-recent tab shows recent pages including important pages.
	 *
	 * @ticket 63473
	 *
	 * @covers ::wp_nav_menu_item_post_type_meta_box
	 */
	public function test_most_recent_tab_displays_recent_pages_with_important_pages() {
		$front_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Front Page',
				'post_status' => 'publish',
				'post_date'   => '2010-01-01 00:00:00',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page );

		$old_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Old Page',
				'post_status' => 'publish',
				'post_date'   => '2000-01-01 00:00:00',
			)
		);

		$recent_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Recent Page',
				'post_status' => 'publish',
				'post_date'   => '2020-01-01 00:00:00',
			)
		);

		// The right order should be:
		// 1. Recent Page
		// 2. Front Page
		// 3. Old Page
		// Strictly by date.

		$post_type = get_post_type_object( 'page' );
		$box       = array(
			'args' => $post_type,
		);

		ob_start();
		wp_nav_menu_item_post_type_meta_box( null, $box );
		$output = ob_get_clean();

		// Extract only the "Most Recent" tab content to avoid interference from other tabs.
		$dom = new DOMDocument();
		$dom->loadHTML( $output );
		$xpath               = new DOMXPath( $dom );
		$page_all_div        = $xpath->query( '//div[@id="tabs-panel-posttype-page-most-recent"]' )->item( 0 );
		$most_recent_content = $page_all_div ? $dom->saveHTML( $page_all_div ) : '';

		$this->assertNotEmpty( $most_recent_content, 'Should find the Most Recent tab content' );

		$this->assertStringContainsString( 'Recent Page', $most_recent_content, 'Recent Page should be present in Most Recent tab' );
		$this->assertStringContainsString( 'Front Page', $most_recent_content, 'Front Page should be present in Most Recent tab' );
		$this->assertStringContainsString( 'Old Page', $most_recent_content, 'Old Page should be present in Most Recent tab' );

		$front_page_position  = strpos( $most_recent_content, 'Front Page' );
		$recent_page_position = strpos( $most_recent_content, 'Recent Page' );
		$old_page_position    = strpos( $most_recent_content, 'Old Page' );

		$this->assertLessThan( $front_page_position, $recent_page_position, 'Recent Page should appear before Front Page because its the last created page' );
		$this->assertLessThan( $old_page_position, $front_page_position, 'Front Page should appear before Old Page because its more recent' );
		$this->assertLessThan( $old_page_position, $recent_page_position, 'Old Page should appear before Recent Page according to the order of titles ' );
	}

	/**
	 * Test that when only important pages exist, they are displayed properly.
	 *
	 * @covers ::wp_nav_menu_item_post_type_meta_box
	 */
	public function test_view_all_tab_handles_only_important_pages_scenario() {
		$front_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Front Page Only',
				'post_status' => 'publish',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page );

		$post_type = get_post_type_object( 'page' );
		$box       = array(
			'args' => $post_type,
		);

		ob_start();
		wp_nav_menu_item_post_type_meta_box( null, $box );
		$output = ob_get_clean();

		// Extract only the "View All" tab content to avoid interference from other tabs.
		$dom = new DOMDocument();
		$dom->loadHTML( $output );
		$xpath            = new DOMXPath( $dom );
		$page_all_div     = $xpath->query( '//div[@id="page-all"]' )->item( 0 );
		$view_all_content = $page_all_div ? $dom->saveHTML( $page_all_div ) : '';

		$this->assertNotEmpty( $view_all_content, 'Should find the View All tab content' );

		$this->assertStringContainsString( 'Front Page Only', $view_all_content, 'Front Page Only should be present in View All tab' );
		$this->assertStringNotContainsString( 'No items.', $view_all_content, 'No items message should not be shown' );
	}

	/**
	 * Test that when no pages exist at all, "No items" message is shown.
	 *
	 * @covers ::wp_nav_menu_item_post_type_meta_box
	 */
	public function test_view_all_tab_shows_no_items_when_no_pages_exist() {

		update_option( 'show_on_front', 'posts' );
		$post_type = get_post_type_object( 'page' );
		$box       = array(
			'args' => $post_type,
		);
		ob_start();
		wp_nav_menu_item_post_type_meta_box( null, $box );
		$output = ob_get_clean();

		// Extract only the "View All" tab content to avoid interference from other tabs.
		$dom = new DOMDocument();
		$dom->loadHTML( $output );
		$xpath            = new DOMXPath( $dom );
		$page_all_div     = $xpath->query( '//div[@id="page-all"]' )->item( 0 );
		$view_all_content = $page_all_div ? $dom->saveHTML( $page_all_div ) : '';

		$this->assertNotEmpty( $view_all_content, 'Should find the View All tab content' );

		$this->assertStringContainsString( 'No items.', $view_all_content, 'No items message should be shown' );
	}

	/**
	 * Test tab navigation structure is rendered correctly.
	 */
	public function test_tab_navigation_structure() {
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$post_type = get_post_type_object( 'page' );
		$box       = array(
			'args' => $post_type,
		);

		ob_start();
		wp_nav_menu_item_post_type_meta_box( null, $box );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'posttype-tabs', $output );
		$this->assertStringContainsString( 'Most Recent', $output );
		$this->assertStringContainsString( 'View All', $output );
		$this->assertStringContainsString( 'Search', $output );
	}
}
