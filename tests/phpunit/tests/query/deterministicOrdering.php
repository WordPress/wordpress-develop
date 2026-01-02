<?php
/**
 * Test deterministic ordering functionality in WP_Query.
 *
 * @package WordPress\UnitTests
 *
 * @group query
 * @group ordering
 * @ticket xxxxx
 */
class Tests_Query_DeterministicOrdering extends WP_UnitTestCase {

	/**
	 * Post IDs for posts with identical dates (for date ordering tests).
	 *
	 * @var array
	 */
	protected static $date_identical_post_ids = array();

	/**
	 * Post IDs for posts with identical titles (for title ordering tests).
	 *
	 * @var array
	 */
	protected static $title_identical_post_ids = array();

	/**
	 * Post IDs for search tests.
	 *
	 * @var array
	 */
	protected static $search_post_ids = array();

	/**
	 * Post IDs for menu_order tests.
	 *
	 * @var array
	 */
	protected static $menu_order_post_ids = array();

	/**
	 * Post IDs for search relevance tests.
	 *
	 * @var array
	 */
	protected static $search_relevance_post_ids = array();

	/**
	 * Set up shared fixtures for all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Register custom post types for test isolation.
		register_post_type(
			'wptests_time_ident',
			array(
				'public' => true,
			)
		);

		register_post_type(
			'wptests_title_ident',
			array(
				'public' => true,
			)
		);

		// Create posts with identical dates for date ordering tests.
		$identical_date = '2023-01-01 10:00:00';
		for ( $i = 1; $i <= 20; $i++ ) {
			self::$date_identical_post_ids[] = self::factory()->post->create(
				array(
					'post_type'  => 'wptests_time_ident',
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		// Create posts with identical titles for title ordering tests.
		$identical_title = 'Same Title';
		for ( $i = 1; $i <= 15; $i++ ) {
			self::$title_identical_post_ids[] = self::factory()->post->create(
				array(
					'post_type'  => 'wptests_title_ident',
					'post_title' => $identical_title,
					'post_date'  => '2023-01-' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) . ' 10:00:00',
				)
			);
		}

		// Create posts for search tests.
		$identical_date = '2023-01-01 10:00:00';
		for ( $i = 1; $i <= 12; $i++ ) {
			self::$search_post_ids[] = self::factory()->post->create(
				array(
					'post_type'    => 'wptests_time_ident',
					'post_title'   => "Test Post $i",
					'post_content' => 'This is a test post',
					'post_date'    => $identical_date,
				)
			);
		}

		// Create pages with identical menu_order for menu_order tests.
		for ( $i = 1; $i <= 20; $i++ ) {
			self::$menu_order_post_ids[] = self::factory()->post->create(
				array(
					'post_type'  => 'page',
					'post_title' => "Page $i",
					'menu_order' => 0, // All pages have same menu_order
				)
			);
		}

		// Create posts for search relevance tests.
		// All posts will have the same content to ensure same relevance scores.
		$identical_content = 'This is a search test post with identical content';
		for ( $i = 1; $i <= 20; $i++ ) {
			self::$search_relevance_post_ids[] = self::factory()->post->create(
				array(
					'post_type'    => 'wptests_time_ident',
					'post_title'   => "Search Post $i",
					'post_content' => $identical_content,
					'post_excerpt' => $identical_content,
				)
			);
		}
	}

	/**
	 * Clean up after all tests.
	 */
	public static function tear_down_after_class() {
		_unregister_post_type( 'wptests_time_ident' );
		_unregister_post_type( 'wptests_title_ident' );

		self::$date_identical_post_ids   = array();
		self::$title_identical_post_ids  = array();
		self::$search_post_ids           = array();
		self::$menu_order_post_ids       = array();
		self::$search_relevance_post_ids = array();

		parent::tear_down_after_class();
	}

	/**
	 * Test that deterministic ordering prevents duplicate records across pages.
	 *
	 * This is the core test for the bug fix. When multiple posts have the same
	 * value for a field (like post_date), pagination can show duplicate records
	 * without deterministic ordering.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_prevents_duplicates_across_pages() {
		// Use shared fixtures with identical post_date

		// Get first page
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		// Get second page
		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no overlap between pages (no duplicates)
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts' );

		// Verify total count is correct
		$this->assertEquals( 20, $query1->found_posts, 'Total posts should be 20' );
		$this->assertEquals( 10, count( $page1_ids ), 'First page should have 10 posts' );
		$this->assertEquals( 10, count( $page2_ids ), 'Second page should have 10 posts' );

		// Verify deterministic ordering: same query should return same results
		$query1_repeat    = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);
		$page1_repeat_ids = wp_list_pluck( $query1_repeat->posts, 'ID' );

		$this->assertEquals( $page1_ids, $page1_repeat_ids, 'Same query should return same results' );
	}

	/**
	 * Test that deterministic ordering works with post_title field.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_post_title() {
		// Use shared fixtures with identical post_title
		// Get first page
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_title_ident',
				'post__in'       => self::$title_identical_post_ids,
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => 8,
				'paged'          => 1,
			)
		);

		// Get second page
		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_title_ident',
				'post__in'       => self::$title_identical_post_ids,
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => 8,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts when ordering by title' );
	}

	/**
	 * Test that deterministic ordering works with DESC order.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_desc_order() {
		// Use shared fixtures with identical post_date
		// Get first page with DESC order
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'posts_per_page' => 6,
				'paged'          => 1,
			)
		);

		// Get second page with DESC order
		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'posts_per_page' => 6,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts with DESC order' );
	}

	/**
	 * Test that deterministic ordering works with array orderby.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_array_orderby() {
		// Use shared fixtures with identical post_date
		// Test with array orderby
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => array(
					'post_date'  => 'ASC',
					'post_title' => 'ASC',
				),
				'posts_per_page' => 8,
				'paged'          => 1,
			)
		);

		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => array(
					'post_date'  => 'ASC',
					'post_title' => 'ASC',
				),
				'posts_per_page' => 8,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts with array orderby' );
	}

	/**
	 * Test that deterministic ordering doesn't add ID when ID is already present.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_does_not_duplicate_id() {
		// Use shared fixtures with identical post_date
		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		// Should not add duplicate ID ordering
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ID ASC, ID ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with search queries.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_search() {
		// Use shared fixtures for search tests
		// Test with search
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_post_ids,
				's'              => 'test',
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 6,
				'paged'          => 1,
			)
		);

		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_post_ids,
				's'              => 'test',
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 6,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages even with search
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts even with search' );
	}

	/**
	 * Test that deterministic ordering works with menu_order field.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_menu_order() {
		// Use shared fixtures with identical menu_order
		// Get first page
		$query1 = new WP_Query(
			array(
				'post_type'      => 'page',
				'post__in'       => self::$menu_order_post_ids,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		// Get second page
		$query2 = new WP_Query(
			array(
				'post_type'      => 'page',
				'post__in'       => self::$menu_order_post_ids,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no overlap between pages (no duplicates)
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts when ordering by menu_order' );

		// Verify total count is correct
		$this->assertEquals( 20, $query1->found_posts, 'Total pages should be 20' );
		$this->assertEquals( 10, count( $page1_ids ), 'First page should have 10 pages' );
		$this->assertEquals( 10, count( $page2_ids ), 'Second page should have 10 pages' );

		// Verify deterministic ordering: same query should return same results
		$query1_repeat    = new WP_Query(
			array(
				'post_type'      => 'page',
				'post__in'       => self::$menu_order_post_ids,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);
		$page1_repeat_ids = wp_list_pluck( $query1_repeat->posts, 'ID' );

		$this->assertEquals( $page1_ids, $page1_repeat_ids, 'Same query should return same results when ordering by menu_order' );
	}

	/**
	 * Test that deterministic ordering works with metadata ordering.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_metadata() {
		$post_ids = array();

		// Create posts with identical meta values to trigger the bug
		$identical_meta_value = 'same_price';
		for ( $i = 1; $i <= 20; $i++ ) {
			$post_id = self::factory()->post->create(
				array(
					'post_type'  => 'wptests_time_ident',
					'post_title' => "Post $i",
				)
			);
			add_post_meta( $post_id, 'price', $identical_meta_value );
			$post_ids[] = $post_id;
		}

		// Get first page ordering by metadata
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => $post_ids,
				'meta_query'     => array(
					'price_key' => array(
						'key'     => 'price',
						'compare' => 'EXISTS',
					),
				),
				'orderby'        => 'price_key',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		// Get second page ordering by metadata
		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => $post_ids,
				'meta_query'     => array(
					'price_key' => array(
						'key'     => 'price',
						'compare' => 'EXISTS',
					),
				),
				'orderby'        => 'price_key',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no overlap between pages (no duplicates)
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts when ordering by metadata' );

		// Verify total count is correct
		$this->assertEquals( 20, $query1->found_posts, 'Total posts should be 20' );
		$this->assertEquals( 10, count( $page1_ids ), 'First page should have 10 posts' );
		$this->assertEquals( 10, count( $page2_ids ), 'Second page should have 10 posts' );
	}

	/**
	 * Test that deterministic ordering works with search relevance ordering.
	 *
	 * When ordering by search relevance, multiple posts can have the same relevance score,
	 * causing duplicate records across pages without deterministic ordering.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_search_relevance() {
		// Use shared fixtures with identical content (same relevance scores)
		// Get first page ordering by relevance
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_relevance_post_ids,
				's'              => 'search test',
				'orderby'        => 'relevance',
				'order'          => 'DESC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		// Get second page ordering by relevance
		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_relevance_post_ids,
				's'              => 'search test',
				'orderby'        => 'relevance',
				'order'          => 'DESC',
				'posts_per_page' => 10,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no overlap between pages (no duplicates)
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts when ordering by search relevance' );

		// Verify total count is correct
		$this->assertEquals( 20, $query1->found_posts, 'Total posts should be 20' );
		$this->assertEquals( 10, count( $page1_ids ), 'First page should have 10 posts' );
		$this->assertEquals( 10, count( $page2_ids ), 'Second page should have 10 posts' );

		// Verify deterministic ordering: same query should return same results
		$query1_repeat    = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_relevance_post_ids,
				's'              => 'search test',
				'orderby'        => 'relevance',
				'order'          => 'DESC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);
		$page1_repeat_ids = wp_list_pluck( $query1_repeat->posts, 'ID' );

		$this->assertEquals( $page1_ids, $page1_repeat_ids, 'Same query should return same results when ordering by search relevance' );
	}

	/**
	 * Test that deterministic ordering works with search when orderby is empty (defaults to relevance).
	 *
	 * When orderby is empty and search is present, WordPress orders by relevance.
	 * Multiple posts can have the same relevance score, causing duplicate records across pages.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_search_empty_orderby() {
		// Use shared fixtures with identical content (same relevance scores)
		// Get first page with empty orderby (defaults to relevance)
		$query1 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_relevance_post_ids,
				's'              => 'search test',
				'orderby'        => '', // Empty orderby with search defaults to relevance
				'order'          => 'DESC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		// Get second page with empty orderby
		$query2 = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$search_relevance_post_ids,
				's'              => 'search test',
				'orderby'        => '', // Empty orderby with search defaults to relevance
				'order'          => 'DESC',
				'posts_per_page' => 10,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no overlap between pages (no duplicates)
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts when ordering by search relevance (empty orderby)' );

		// Verify total count is correct
		$this->assertEquals( 20, $query1->found_posts, 'Total posts should be 20' );
		$this->assertEquals( 10, count( $page1_ids ), 'First page should have 10 posts' );
		$this->assertEquals( 10, count( $page2_ids ), 'Second page should have 10 posts' );
	}

	/**
	 * Test that posts_orderby filter receives original orderby value.
	 *
	 * This ensures backward compatibility - filters should receive the same orderby
	 * value they received before the deterministic ordering changes.
	 *
	 * @ticket xxxxx
	 */
	public function test_posts_orderby_filter_receives_original_orderby() {
		global $wpdb;

		$received_orderby = '';

		$filter_callback = function ( $orderby ) use ( &$received_orderby ) {
			$received_orderby = $orderby;
			return $orderby;
		};

		add_filter( 'posts_orderby', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_orderby', $filter_callback );

		// Filter should receive orderby without ID tie-breaker.
		$expected_orderby = "{$wpdb->posts}.post_date ASC";
		$this->assertEquals( $expected_orderby, $received_orderby, 'posts_orderby filter should receive original orderby without ID tie-breaker' );
	}

	/**
	 * Test that ID tie-breaker is added when posts_orderby filter does not modify orderby.
	 *
	 * @ticket xxxxx
	 */
	public function test_id_tie_breaker_added_when_posts_orderby_filter_does_not_modify() {
		global $wpdb;

		$filter_callback = function ( $orderby ) {
			return $orderby; // Return unchanged.
		};

		add_filter( 'posts_orderby', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_orderby', $filter_callback );

		// Since filter did NOT modify orderby, ID tie-breaker SHOULD be added.
		$this->assertStringContainsString( ', ' . $wpdb->posts . '.ID ASC', $query->request, 'ID tie-breaker should be added when posts_orderby filter does not modify orderby' );
	}

	/**
	 * Test that posts_clauses filter receives original orderby value.
	 *
	 * This ensures backward compatibility - filters should receive the same orderby
	 * value they received before the deterministic ordering changes.
	 *
	 * @ticket xxxxx
	 */
	public function test_posts_clauses_filter_receives_original_orderby() {
		global $wpdb;

		$received_orderby = '';

		$filter_callback = function ( $clauses ) use ( &$received_orderby ) {
			$received_orderby = $clauses['orderby'] ?? '';
			return $clauses;
		};

		add_filter( 'posts_clauses', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_clauses', $filter_callback );

		// Filter should receive orderby without ID tie-breaker.
		$expected_orderby = "{$wpdb->posts}.post_date ASC";
		$this->assertEquals( $expected_orderby, $received_orderby, 'posts_clauses filter should receive original orderby without ID tie-breaker' );
	}

	/**
	 * Test that ID tie-breaker is added when posts_clauses filter does not modify orderby.
	 *
	 * @ticket xxxxx
	 */
	public function test_id_tie_breaker_added_when_posts_clauses_filter_does_not_modify() {
		global $wpdb;

		$filter_callback = function ( $clauses ) {
			return $clauses; // Return unchanged.
		};

		add_filter( 'posts_clauses', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_clauses', $filter_callback );

		// Since filter did NOT modify orderby, ID tie-breaker SHOULD be added.
		$this->assertStringContainsString( ', ' . $wpdb->posts . '.ID ASC', $query->request, 'ID tie-breaker should be added when posts_clauses filter does not modify orderby' );
	}

	/**
	 * Test that filter modifications to orderby are preserved.
	 *
	 * When a filter modifies the orderby, the modification should be preserved
	 * and we should not add the ID tie-breaker (we assume the filter knows what it's doing).
	 *
	 * @ticket xxxxx
	 */
	public function test_filter_modifications_to_orderby_are_preserved() {
		// Filter that modifies the orderby by adding post_title.
		$filter_callback = function ( $orderby ) {
			global $wpdb;
			return $orderby . ', ' . "{$wpdb->posts}.post_title ASC";
		};

		add_filter( 'posts_orderby', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_orderby', $filter_callback );

		// Verify filter modification is preserved in the final query.
		$this->assertStringContainsString( 'post_title ASC', $query->request, 'Filter modification to orderby should be preserved' );

		// Verify ID tie-breaker is NOT added when filter modifies orderby.
		$this->assertStringNotContainsString( ', ' . $GLOBALS['wpdb']->posts . '.ID ASC', $query->request, 'ID tie-breaker should not be added when filter modifies orderby' );
	}

	/**
	 * Test that posts_clauses filter modifications to orderby are preserved.
	 *
	 * When a posts_clauses filter modifies the orderby, the modification should be preserved
	 * and we should not add the ID tie-breaker (we assume the filter knows what it's doing).
	 *
	 * @ticket xxxxx
	 */
	public function test_posts_clauses_filter_modifications_to_orderby_are_preserved() {
		global $wpdb;

		// Filter that modifies the orderby via posts_clauses.
		$filter_callback = function ( $clauses ) use ( $wpdb ) {
			// Modify orderby to add post_title.
			$clauses['orderby'] = "{$wpdb->posts}.post_date ASC, {$wpdb->posts}.post_title ASC";
			return $clauses;
		};

		add_filter( 'posts_clauses', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_clauses', $filter_callback );

		// Verify filter modification is preserved in the final query.
		$this->assertStringContainsString( 'post_title ASC', $query->request, 'posts_clauses filter modification to orderby should be preserved' );

		// Verify ID tie-breaker is NOT added when filter modifies orderby.
		$this->assertStringNotContainsString( ', ' . $wpdb->posts . '.ID ASC', $query->request, 'ID tie-breaker should not be added when posts_clauses filter modifies orderby' );
	}

	/**
	 * Test that ID tie-breaker is not duplicated when filter already includes ID.
	 *
	 * If a filter adds ID to the orderby, we should not add it again.
	 *
	 * @ticket xxxxx
	 */
	public function test_id_tie_breaker_not_duplicated_when_filter_includes_id() {
		global $wpdb;

		// Filter that already includes ID in orderby.
		$filter_callback = function ( $orderby ) use ( $wpdb ) {
			return "{$wpdb->posts}.post_date ASC, {$wpdb->posts}.ID ASC";
		};

		add_filter( 'posts_orderby', $filter_callback );

		$query = new WP_Query(
			array(
				'post_type'      => 'wptests_time_ident',
				'post__in'       => self::$date_identical_post_ids,
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		remove_filter( 'posts_orderby', $filter_callback );

		// Should not have duplicate ID ordering.
		$this->assertStringContainsString( 'ID ASC', $query->request, 'ID should be present' );
		// Count occurrences of "ID ASC" - should be exactly 1.
		$id_count = substr_count( $query->request, 'ID ASC' );
		$this->assertEquals( 1, $id_count, 'ID should not be duplicated when filter already includes it' );
	}
}
