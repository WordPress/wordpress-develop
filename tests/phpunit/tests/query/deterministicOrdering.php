<?php
/**
 * Tests that paginated queries return each post exactly once.
 *
 * @package WordPress\UnitTests
 *
 * @group query
 * @group ordering
 *
 * @ticket 44349
 */
class Tests_Query_DeterministicOrdering extends WP_UnitTestCase {

	/**
	 * Posts sharing one date, split across two post statuses.
	 *
	 * @var int[]
	 */
	protected static $mixed_status_ids = array();

	/**
	 * Pages sharing one menu_order.
	 *
	 * @var int[]
	 */
	protected static $menu_order_ids = array();

	/**
	 * Posts sharing one title.
	 *
	 * @var int[]
	 */
	protected static $same_title_ids = array();

	/**
	 * Creates the posts shared by every test in this class.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		/*
		 * Every post here shares one date. Splitting them across two statuses stops
		 * the database using the type_status_date index, which happens to end in ID
		 * and would otherwise hide the missing ID clause.
		 */
		for ( $i = 1; $i <= 20; $i++ ) {
			self::$mixed_status_ids[] = $factory->post->create(
				array(
					'post_title'  => "Mixed status $i",
					'post_date'   => '2023-01-01 10:00:00',
					'post_status' => ( 0 === $i % 2 ) ? 'private' : 'publish',
				)
			);
		}

		// menu_order has no index at all, so ties in it are never ordered.
		for ( $i = 1; $i <= 20; $i++ ) {
			self::$menu_order_ids[] = $factory->post->create(
				array(
					'post_type'  => 'page',
					'post_title' => "Page $i",
					'menu_order' => 0,
				)
			);
		}

		for ( $i = 1; $i <= 20; $i++ ) {
			self::$same_title_ids[] = $factory->post->create(
				array(
					'post_title' => 'Same title',
					'post_date'  => '2023-02-' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) . ' 10:00:00',
				)
			);
		}
	}

	/**
	 * Returns the post IDs on one page of a query.
	 *
	 * @param array $args Query arguments. 'posts_per_page' and 'paged' are set by the caller.
	 * @return int[] Post IDs, in the order the query returned them.
	 */
	private function get_page_of_ids( $args ) {
		$query = new WP_Query( $args );
		return wp_list_pluck( $query->posts, 'ID' );
	}

	/**
	 * Asserts that paging through a query returns every post exactly once.
	 *
	 * @param array $args     Query arguments, without 'posts_per_page' or 'paged'.
	 * @param int   $per_page Posts per page.
	 * @param int   $pages    Number of pages to walk.
	 * @param int   $expected Total number of posts expected across those pages.
	 * @param string $message Message describing the ordering under test.
	 */
	private function assertPagesDoNotRepeatPosts( $args, $per_page, $pages, $expected, $message ) {
		$seen = array();

		for ( $page = 1; $page <= $pages; $page++ ) {
			$seen = array_merge(
				$seen,
				$this->get_page_of_ids(
					array_merge(
						$args,
						array(
							'posts_per_page' => $per_page,
							'paged'          => $page,
						)
					)
				)
			);
		}

		$this->assertSameSets( array_unique( $seen ), $seen, $message . ': a post appeared on more than one page' );
		$this->assertCount( $expected, $seen, $message . ': the pages did not add up to every post' );
	}

	/**
	 * Ordering by date is stable when posts share a date.
	 *
	 * Two statuses are queried together so the database sorts the rows itself
	 * rather than reading them from an index that already ends in ID.
	 *
	 * @ticket 44349
	 */
	public function test_paging_by_date_returns_each_post_once() {
		$this->assertPagesDoNotRepeatPosts(
			array(
				'post_type'   => 'post',
				'post_status' => array( 'publish', 'private' ),
				'post__in'    => self::$mixed_status_ids,
				'orderby'     => 'date',
				'order'       => 'DESC',
			),
			10,
			2,
			20,
			'Ordering by date'
		);
	}

	/**
	 * Ordering by menu_order is stable when posts share a menu_order.
	 *
	 * @ticket 44349
	 * @ticket 46294
	 */
	public function test_paging_by_menu_order_returns_each_post_once() {
		$this->assertPagesDoNotRepeatPosts(
			array(
				'post_type' => 'page',
				'post__in'  => self::$menu_order_ids,
				'orderby'   => 'menu_order',
				'order'     => 'ASC',
			),
			10,
			2,
			20,
			'Ordering by menu_order'
		);
	}

	/**
	 * Ordering by title is stable when posts share a title.
	 *
	 * @ticket 44349
	 */
	public function test_paging_by_title_returns_each_post_once() {
		$this->assertPagesDoNotRepeatPosts(
			array(
				'post_type' => 'post',
				'post__in'  => self::$same_title_ids,
				'orderby'   => 'title',
				'order'     => 'ASC',
			),
			10,
			2,
			20,
			'Ordering by title'
		);
	}

	/**
	 * The same query run twice returns the same page in the same order.
	 *
	 * @ticket 44349
	 */
	public function test_repeating_a_query_returns_the_same_page() {
		$args = array(
			'post_type'      => 'page',
			'post__in'       => self::$menu_order_ids,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => 10,
			'paged'          => 1,
		);

		$this->assertSame(
			$this->get_page_of_ids( $args ),
			$this->get_page_of_ids( $args ),
			'Running the same query twice returned a different page'
		);
	}

	/**
	 * The ID clause sorts the same way as the column above it.
	 *
	 * @ticket 44349
	 *
	 * @dataProvider data_orderby_directions
	 *
	 * @param array  $args     Query arguments.
	 * @param string $expected Expected ORDER BY clause, with {posts} standing in for the table name.
	 */
	public function test_id_clause_follows_the_sort_direction( $args, $expected ) {
		global $wpdb;

		$query = new WP_Query( array_merge( $args, array( 'posts_per_page' => 5 ) ) );

		$this->assertStringContainsString(
			'ORDER BY ' . str_replace( '{posts}', $wpdb->posts, $expected ),
			$query->request
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_orderby_directions() {
		return array(
			'descending date'      => array(
				array(
					'orderby' => 'date',
					'order'   => 'DESC',
				),
				'{posts}.post_date DESC, {posts}.ID DESC',
			),
			'ascending date'       => array(
				array(
					'orderby' => 'date',
					'order'   => 'ASC',
				),
				'{posts}.post_date ASC, {posts}.ID ASC',
			),
			'no orderby given'     => array(
				array(),
				'{posts}.post_date DESC, {posts}.ID DESC',
			),
			'ascending menu_order' => array(
				array(
					'orderby' => 'menu_order',
					'order'   => 'ASC',
				),
				'{posts}.menu_order ASC, {posts}.ID ASC',
			),
			'two columns'          => array(
				array(
					'orderby' => array(
						'title' => 'DESC',
						'date'  => 'ASC',
					),
				),
				'{posts}.post_title DESC, {posts}.post_date ASC, {posts}.ID ASC',
			),
			'unparseable column'   => array(
				array( 'orderby' => 'a_column_that_does_not_exist' ),
				'{posts}.post_date DESC, {posts}.ID DESC',
			),
		);
	}

	/**
	 * Ordering that already fixes the sequence gets no extra ID clause.
	 *
	 * @ticket 44349
	 *
	 * @dataProvider data_orderby_that_fixes_the_sequence
	 *
	 * @param array $args Query arguments.
	 */
	public function test_orderby_id_gets_no_extra_id_clause( $args ) {
		global $wpdb;

		$query = new WP_Query( array_merge( $args, array( 'posts_per_page' => 5 ) ) );

		preg_match( '/ORDER BY(.*?)LIMIT/s', $query->request, $matches );
		$orderby = isset( $matches[1] ) ? $matches[1] : '';

		$this->assertSame(
			1,
			substr_count( $orderby, "{$wpdb->posts}.ID" ),
			'The ID appeared more than once in the ORDER BY'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_orderby_that_fixes_the_sequence() {
		return array(
			'ID'                      => array(
				array(
					'orderby' => 'ID',
					'order'   => 'ASC',
				),
			),
			'ID, descending'          => array(
				array(
					'orderby' => 'ID',
					'order'   => 'DESC',
				),
			),
			'ID given as an array'    => array( array( 'orderby' => array( 'ID' => 'DESC' ) ) ),
			'ID named after another'  => array(
				array(
					'orderby' => 'title ID',
					'order'   => 'ASC',
				),
			),
			'ID named before another' => array(
				array(
					'orderby' => array(
						'ID'    => 'DESC',
						'title' => 'ASC',
					),
				),
			),
		);
	}

	/**
	 * Random ordering is left alone.
	 *
	 * A seed is passed to get the same shuffle back on every page, which sorting
	 * by ID afterwards would undo.
	 *
	 * @ticket 44349
	 *
	 * @dataProvider data_random_orderby
	 *
	 * @param string $orderby The 'orderby' value.
	 */
	public function test_random_ordering_gets_no_id_clause( $orderby ) {
		global $wpdb;

		$query = new WP_Query(
			array(
				'orderby'        => $orderby,
				'posts_per_page' => 5,
			)
		);

		preg_match( '/ORDER BY(.*?)LIMIT/s', $query->request, $matches );

		$this->assertStringNotContainsString( "{$wpdb->posts}.ID", $matches[1] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_random_orderby() {
		return array(
			'unseeded'             => array( 'rand' ),
			'seeded'               => array( 'RAND(5)' ),
			'seeded with zero'     => array( 'RAND(0)' ),
			'seeded, lower case'   => array( 'rand(5)' ),
			'seeded, large number' => array( 'RAND(99999999999)' ),
		);
	}

	/**
	 * A seed returns the same shuffle on every page.
	 *
	 * @ticket 44349
	 */
	public function test_a_random_seed_gives_the_same_order_each_time() {
		$args = array(
			'post_type'      => 'page',
			'post__in'       => self::$menu_order_ids,
			'orderby'        => 'RAND(5)',
			'posts_per_page' => 5,
			'paged'          => 1,
		);

		$this->assertSame(
			$this->get_page_of_ids( $args ),
			$this->get_page_of_ids( $args ),
			'A seeded random order changed between two runs of the same query'
		);
	}

	/**
	 * An explicit list of IDs keeps the order it was given in.
	 *
	 * @ticket 44349
	 */
	public function test_post__in_keeps_its_own_order() {
		$ids = array_slice( self::$menu_order_ids, 0, 5 );
		shuffle( $ids );

		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => 5,
			)
		);

		$this->assertSame( $ids, wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * Ordering by a list of parents or slugs still gets the ID clause.
	 *
	 * Unlike post__in, these lists do not order posts uniquely: several posts can
	 * share one parent, or one slug across post types.
	 *
	 * @ticket 44349
	 */
	public function test_field_orderings_get_the_id_clause() {
		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'FIELD parent',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
			)
		);

		$query = new WP_Query(
			array(
				'post_type'       => 'page',
				'post_parent__in' => array( $parent ),
				'orderby'         => 'post_parent__in',
				'posts_per_page'  => 5,
			)
		);

		global $wpdb;
		preg_match( '/ORDER BY(.*?)LIMIT/s', $query->request, $matches );

		$this->assertStringContainsString( "FIELD( {$wpdb->posts}.post_parent,", $matches[1] );
		$this->assertStringContainsString( "{$wpdb->posts}.ID", $matches[1] );
	}

	/**
	 * Paging posts that all share one parent returns each post exactly once.
	 *
	 * FIELD( wp_posts.post_parent, ... ) gives every child of the same parent the
	 * same sort value, so without the ID clause the whole result set is one tie.
	 *
	 * @ticket 44349
	 */
	public function test_paging_by_post_parent__in_returns_each_post_once() {
		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Shared parent',
			)
		);

		$children = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$children[] = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_title'  => "Child $i",
					'post_parent' => $parent,
				)
			);
		}

		$this->assertPagesDoNotRepeatPosts(
			array(
				'post_type'       => 'page',
				'post_parent__in' => array( $parent ),
				'orderby'         => 'post_parent__in',
			),
			5,
			3,
			12,
			'Ordering by post_parent__in'
		);
	}

	/**
	 * An 'orderby' of 'none' still produces no ORDER BY.
	 *
	 * @ticket 44349
	 *
	 * @dataProvider data_orderby_that_blanks_the_clause
	 *
	 * @param mixed $orderby The 'orderby' value.
	 */
	public function test_orderby_can_still_be_blanked( $orderby ) {
		$query = new WP_Query(
			array(
				'orderby'        => $orderby,
				'posts_per_page' => 5,
			)
		);

		$this->assertStringNotContainsString( 'ORDER BY', $query->request );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_orderby_that_blanks_the_clause() {
		return array(
			'the string none'                 => array( 'none' ),
			'none as an array key'            => array( array( 'none' => 'DESC' ) ),
			'an empty array'                  => array( array() ),
			'false'                           => array( false ),
			'an array of only invalid fields' => array( array( 'a_column_that_does_not_exist' => 'ASC' ) ),
		);
	}

	/**
	 * 'none' next to real columns does not blank the ordering.
	 *
	 * get_pages( array( 'sort_column' => 'post_title,none' ) ) produces
	 * array( 'post_title' => ..., 'none' => ... ); only the 'none' part is
	 * dropped, the rest orders as requested.
	 *
	 * @ticket 44349
	 */
	public function test_none_beside_real_columns_keeps_the_ordering() {
		global $wpdb;

		$query = new WP_Query(
			array(
				'orderby'        => array(
					'title' => 'ASC',
					'none'  => 'DESC',
				),
				'posts_per_page' => 5,
			)
		);

		$this->assertStringContainsString(
			"ORDER BY {$wpdb->posts}.post_title ASC, {$wpdb->posts}.ID ASC",
			$query->request
		);
	}

	/**
	 * get_pages() can still ask for no ordering.
	 *
	 * It passes 'sort_column' through as an array key rather than a bare string.
	 *
	 * @ticket 44349
	 */
	public function test_get_pages_can_still_ask_for_no_ordering() {
		global $wpdb;

		get_pages( array( 'sort_column' => 'none' ) );

		$this->assertStringNotContainsString( 'ORDER BY', $wpdb->last_query );
	}

	/**
	 * Clause filters are handed the ORDER BY that will actually run.
	 *
	 * @ticket 44349
	 *
	 * @dataProvider data_orderby_filters
	 *
	 * @param string $filter Name of the filter under test.
	 */
	public function test_filters_receive_the_final_orderby( $filter ) {
		global $wpdb;

		$received = null;
		$is_array = str_contains( $filter, 'clauses' );

		$callback = static function ( $value ) use ( &$received, $is_array ) {
			if ( null === $received ) {
				$received = $is_array ? $value['orderby'] : $value;
			}
			return $value;
		};

		add_filter( $filter, $callback );
		new WP_Query(
			array(
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => 5,
			)
		);
		remove_filter( $filter, $callback );

		$this->assertSame( "{$wpdb->posts}.post_date ASC, {$wpdb->posts}.ID ASC", $received );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_orderby_filters() {
		return array(
			'posts_orderby'         => array( 'posts_orderby' ),
			'posts_clauses'         => array( 'posts_clauses' ),
			'posts_orderby_request' => array( 'posts_orderby_request' ),
			'posts_clauses_request' => array( 'posts_clauses_request' ),
		);
	}

	/**
	 * A filtered ORDER BY is used exactly as the filter returned it.
	 *
	 * Nothing is appended afterwards, so a filter cannot be handed back SQL it
	 * did not write.
	 *
	 * @ticket 44349
	 */
	public function test_a_filtered_orderby_is_used_verbatim() {
		global $wpdb;

		$callback = static function () use ( $wpdb ) {
			return "{$wpdb->posts}.post_title ASC";
		};

		add_filter( 'posts_orderby', $callback );
		$query = new WP_Query( array( 'posts_per_page' => 5 ) );
		remove_filter( 'posts_orderby', $callback );

		$this->assertStringContainsString( "ORDER BY {$wpdb->posts}.post_title ASC", $query->request );
		$this->assertStringNotContainsString( "post_title ASC, {$wpdb->posts}.ID", $query->request );
	}

	/**
	 * A filter returning an unchanged clause keeps the ID clause.
	 *
	 * Trailing whitespace used to be enough to lose it.
	 *
	 * @ticket 44349
	 *
	 * @dataProvider data_filters_that_change_nothing
	 *
	 * @param callable $callback Filter callback.
	 */
	public function test_a_filter_that_changes_nothing_keeps_the_id_clause( $callback ) {
		global $wpdb;

		add_filter( 'posts_orderby', $callback );
		$query = new WP_Query(
			array(
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => 5,
			)
		);
		remove_filter( 'posts_orderby', $callback );

		$this->assertStringContainsString( "{$wpdb->posts}.ID ASC", $query->request );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_filters_that_change_nothing() {
		return array(
			'returns the value it was given' => array(
				static function ( $orderby ) {
					return $orderby;
				},
			),
			'adds a trailing space'          => array(
				static function ( $orderby ) {
					return $orderby . ' ';
				},
			),
		);
	}

	/**
	 * The ORDER BY survives a posts_clauses_request filter that leaves it out.
	 *
	 * @ticket 44349
	 */
	public function test_orderby_survives_a_clauses_filter_that_omits_it() {
		global $wpdb;

		$callback = static function ( $clauses ) {
			unset( $clauses['orderby'] );
			return $clauses;
		};

		add_filter( 'posts_clauses_request', $callback );
		$query = new WP_Query( array( 'posts_per_page' => 5 ) );
		remove_filter( 'posts_clauses_request', $callback );

		$this->assertStringContainsString(
			"ORDER BY {$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC",
			$query->request
		);
	}

	/**
	 * posts_orderby_request survives a later filter that returns no ordering.
	 *
	 * @ticket 44349
	 */
	public function test_orderby_request_survives_a_later_clauses_filter() {
		global $wpdb;

		$set_orderby  = static function () use ( $wpdb ) {
			return "{$wpdb->posts}.post_title ASC";
		};
		$drop_orderby = static function ( $clauses ) {
			unset( $clauses['orderby'] );
			return $clauses;
		};

		add_filter( 'posts_orderby_request', $set_orderby );
		add_filter( 'posts_clauses_request', $drop_orderby );
		$query = new WP_Query( array( 'posts_per_page' => 5 ) );
		remove_filter( 'posts_orderby_request', $set_orderby );
		remove_filter( 'posts_clauses_request', $drop_orderby );

		$this->assertStringContainsString( "ORDER BY {$wpdb->posts}.post_title ASC", $query->request );
	}

	/**
	 * Searching still orders by relevance first.
	 *
	 * @ticket 44349
	 */
	public function test_search_relevance_still_comes_first() {
		global $wpdb;

		$query = new WP_Query(
			array(
				's'              => 'Same title',
				'posts_per_page' => 5,
			)
		);

		$this->assertStringContainsString( 'ORDER BY (CASE WHEN', $query->request );
		$this->assertStringContainsString( "{$wpdb->posts}.ID DESC", $query->request );
	}
}
