<?php

/**
 * @group menu
 */
class Tests_Menu_WpAjaxMenuQuickSearch extends WP_UnitTestCase {

	/**
	 * Test search returns results for pages.
	 *
	 * @ticket 27042
	 */
	public function test_search_returns_results_for_pages() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		self::factory()->post->create_many(
			3,
			array(
				'post_type'    => 'page',
				'post_content' => 'foo',
				'post_title'   => 'foo title',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => 'bar',
				'post_title'   => 'bar title',
			)
		);

		$request = array(
			'type'            => 'quick-search-posttype-page',
			'q'               => 'foo',
			'response-format' => 'json',
		);

		$output = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
		$this->assertNotEmpty( $output );

		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 3, $results );
	}

	/**
	 * Test that search only returns results for posts with term in title.
	 *
	 * @ticket 48655
	 */
	public function test_search_only_returns_results_for_posts_with_term_in_title() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		// This will make sure that WP_Query sets is_admin to true.
		set_current_screen( 'nav-menu.php' );

		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Publish FOO',
				'post_content' => 'FOO',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Publish without search term',
				'post_content' => 'FOO',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-post',
			'q'    => 'FOO',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );
	}

	/**
	 * Test that search only returns results for published posts.
	 *
	 * @ticket 33742
	 */
	public function test_search_returns_results_for_published_posts() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		// This will make sure that WP_Query sets is_admin to true.
		set_current_screen( 'nav-menu.php' );

		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Publish FOO',
				'post_content' => 'FOO',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => 'Draft FOO',
				'post_content' => 'FOO',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'pending',
				'post_title'   => 'Pending FOO',
				'post_content' => 'FOO',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'future',
				'post_title'   => 'Future FOO',
				'post_content' => 'FOO',
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-post',
			'q'    => 'FOO',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );
	}

	/**
	 * Test that search displays terms that are not assigned to any posts.
	 *
	 * @ticket 45298
	 */
	public function test_search_should_return_unassigned_term_items() {
		register_taxonomy( 'wptests_tax', 'post' );

		self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'foobar',
			)
		);

		$request = array(
			'type' => 'quick-search-taxonomy-wptests_tax',
			'q'    => 'foobar',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );
	}

	/**
	 * Test that search displays results for post types with numeric slugs
	 *
	 * @ticket 63633
	 */
	public function test_search_returns_post_types_with_numeric_slugs() {
		register_post_type( 'wptests_123' );

		self::factory()->post->create(
			array(
				'post_title'   => 'Post Title 123 FOO',
				'post_type'    => 'wptests_123',
				'post_status'  => 'publish',
				'post_content' => 'FOO',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-wptests_123',
			'q'    => 'FOO',
		);

		$output = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
		$this->assertNotEmpty( $output );

		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );

		$results_json = array_map( 'json_decode', $results );
		$this->assertSame( 'wptests_123', $results_json[0]->post_type );
	}

	/**
	 * Test that search returns an exact post slug match even when the title has no overlap with the query.
	 *
	 * @ticket 44803
	 */
	public function test_search_returns_exact_slug_match_for_posttype() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$slug_matched_post = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Completely Unrelated Title',
				'post_name'  => 'zzz-exact-slug-target',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Another Unrelated Page',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-page',
			'q'    => 'zzz-exact-slug-target',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );

		$result = json_decode( $results[0] );
		$this->assertSame( $slug_matched_post, $result->ID );
	}

	/**
	 * Test that an exact post slug match is ranked first, ahead of newer title matches.
	 *
	 * @ticket 44803
	 */
	public function test_search_ranks_exact_slug_match_first_for_posttype() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$slug_matched_post = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Old Unrelated Title',
				'post_name'  => 'gamma',
				'post_date'  => gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) ),
			)
		);
		self::factory()->post->create_many(
			3,
			array(
				'post_type'  => 'page',
				'post_title' => 'Gamma Info Page',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-page',
			'q'    => 'gamma',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 4, $results );

		$first_result = json_decode( $results[0] );
		$this->assertSame( $slug_matched_post, $first_result->ID );
	}

	/**
	 * Test that an exact slug match takes a slot within the existing results cap rather than exceeding it.
	 *
	 * @ticket 44803
	 */
	public function test_search_respects_posts_per_page_cap_with_exact_slug_match() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		self::factory()->post->create_many(
			10,
			array(
				'post_type'  => 'page',
				'post_title' => 'Widget Info Page',
			)
		);
		$slug_matched_post = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Completely Unrelated Title',
				'post_name'  => 'widget',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-page',
			'q'    => 'widget',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 10, $results );

		$ids = array_map(
			static function ( $result ) {
				return json_decode( $result )->ID;
			},
			$results
		);
		$this->assertContains( $slug_matched_post, $ids );
	}

	/**
	 * Test that an exact slug match against a non-published post is excluded.
	 *
	 * @ticket 44803
	 */
	public function test_search_exact_slug_match_excludes_non_published_posts() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Unrelated Draft Title',
				'post_name'   => 'draft-exact-slug-target',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-page',
			'q'    => 'draft-exact-slug-target',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertEmpty( $output );
	}

	/**
	 * Test that search returns an exact term slug match even when the name has no overlap with the query.
	 *
	 * @ticket 44803
	 */
	public function test_search_returns_exact_slug_match_for_taxonomy() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		register_taxonomy( 'wptests_tax', 'post' );

		$slug_matched_term = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
				'name'     => 'Something Else Entirely',
				'slug'     => 'zzz-term-slug-target',
			)
		);

		$request = array(
			'type' => 'quick-search-taxonomy-wptests_tax',
			'q'    => 'zzz-term-slug-target',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );

		$result = json_decode( $results[0] );
		$this->assertSame( $slug_matched_term, $result->ID );
	}

	/**
	 * Test that a numeric-looking slug is matched as a slug, not misread as a post ID.
	 *
	 * @ticket 44803
	 */
	public function test_search_numeric_slug_is_matched_by_slug_not_post_id() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		// Hierarchical post types (e.g. pages) refuse purely numeric slugs, so use a flat post type here.
		// An unrelated post, so a real post ID exists in the table alongside our numeric slug.
		self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Some Other Page',
			)
		);

		$slug_matched_post = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Unrelated Title',
				'post_name'  => '55',
			)
		);

		$request = array(
			'type' => 'quick-search-posttype-post',
			'q'    => '55',
		);
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );

		$this->assertNotEmpty( $output );
		$results = explode( "\n", trim( $output ) );
		$this->assertCount( 1, $results );

		$result = json_decode( $results[0] );
		$this->assertSame( $slug_matched_post, $result->ID );
	}
}
