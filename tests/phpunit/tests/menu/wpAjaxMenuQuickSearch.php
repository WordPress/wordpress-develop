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
	 * Test that post search results can be paginated.
	 *
	 * @ticket 38224
	 */
	public function test_search_paginates_post_results() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$post_ids = self::factory()->post->create_many(
			21,
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Ticket 38224 post result',
			)
		);

		// Request-controlled page sizes should not alter the server-defined limit.
		$request = array(
			'type'            => 'quick-search-posttype-page',
			'q'               => 'Ticket 38224 post result',
			'posts_per_page'  => 1,
			'response-format' => 'json',
		);
		$pages   = array();

		foreach ( array( 1, 2, 3 ) as $paged ) {
			$request['paged'] = $paged;
			$output           = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
			$results          = array_map( 'json_decode', explode( "\n", trim( $output ) ) );
			$pages[ $paged ]  = wp_list_pluck( $results, 'ID' );
		}

		$this->assertCount( 10, $pages[1], 'Request-controlled page sizes should be ignored.' );
		$this->assertCount( 10, $pages[2] );
		$this->assertCount( 1, $pages[3] );
		$this->assertSame( array_reverse( $post_ids ), array_merge( $pages[1], $pages[2], $pages[3] ) );

		$request['paged'] = 0;
		$output           = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
		$results          = array_map( 'json_decode', explode( "\n", trim( $output ) ) );
		$this->assertSame( $pages[1], wp_list_pluck( $results, 'ID' ) );

		unset( $request['paged'] );
		$output  = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
		$results = array_map( 'json_decode', explode( "\n", trim( $output ) ) );
		$this->assertCount( 10, $results );
	}

	/**
	 * Test that taxonomy search results can be paginated.
	 *
	 * @ticket 38224
	 */
	public function test_search_paginates_taxonomy_results() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		register_taxonomy( 'wptests_tax_38224', 'post' );

		$term_ids = array();
		for ( $i = 1; $i <= 21; $i++ ) {
			$term_ids[] = self::factory()->term->create(
				array(
					'taxonomy' => 'wptests_tax_38224',
					'name'     => sprintf( 'Ticket 38224 term %02d', $i ),
				)
			);
		}

		$request = array(
			'type'            => 'quick-search-taxonomy-wptests_tax_38224',
			'q'               => 'Ticket 38224 term',
			'response-format' => 'json',
		);
		$pages   = array();

		foreach ( array( 1, 2, 3 ) as $paged ) {
			$request['paged'] = $paged;
			$output           = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
			$results          = array_map( 'json_decode', explode( "\n", trim( $output ) ) );
			$pages[ $paged ]  = wp_list_pluck( $results, 'ID' );
		}

		$this->assertCount( 10, $pages[1] );
		$this->assertCount( 10, $pages[2] );
		$this->assertCount( 1, $pages[3] );
		$this->assertSame( $term_ids, array_merge( $pages[1], $pages[2], $pages[3] ) );

		$terms_filter = static function () use ( $term_ids ) {
			return array_map( 'get_term', $term_ids );
		};
		add_filter( 'get_terms', $terms_filter );
		unset( $request['paged'] );
		$output = get_echo( '_wp_ajax_menu_quick_search', array( $request ) );
		remove_filter( 'get_terms', $terms_filter );

		$results = array_map( 'json_decode', explode( "\n", trim( $output ) ) );
		$this->assertCount( 21, $results, 'Unpaginated responses should preserve filtered results.' );
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
}
