<?php
/**
 * @group query
 *
 * @covers WP_Query::get_posts
 */
class Tests_Query_FieldsClause extends WP_UnitTestCase {

	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	private static $post_ids = array();

	/**
	 * Page IDs.
	 *
	 * @var int[]
	 */
	private static $page_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Register CPT for use with shared fixtures.
		register_post_type( 'wptests_pt' );

		self::$post_ids = $factory->post->create_many( 5, array( 'post_type' => 'wptests_pt' ) );
	}

	public function set_up() {
		parent::set_up();
		/*
		 * Re-register the CPT for use within each test.
		 *
		 * Custom post types are deregistered by the default tear_down method
		 * so need to be re-registered for each test as WP_Query calls
		 * get_post_types().
		 */
		register_post_type( 'wptests_pt' );
	}

	/**
	 * Tests limiting the WP_Query fields to the ID and parent sub-set.
	 *
	 * @ticket 57012
	 */
	public function test_should_limit_fields_to_id_and_parent_subset() {
		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'id=>parent',
		);

		$q = new WP_Query( $query_args );

		$expected = array();
		foreach ( self::$post_ids as $post_id ) {
			$expected[] = (object) array(
				'ID'          => $post_id,
				'post_parent' => 0,
			);
		}

		$this->assertEqualSets( $expected, $q->posts, 'Posts property for first query is not of expected form.' );
		$this->assertSame( 5, $q->found_posts, 'Number of found posts is not five.' );
		$this->assertSame( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Tests limiting the WP_Query fields to the IDs only.
	 *
	 * @ticket 57012
	 */
	public function test_should_limit_fields_to_ids() {
		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'ids',
		);

		$q = new WP_Query( $query_args );

		$expected = self::$post_ids;

		$this->assertEqualSets( $expected, $q->posts, 'Posts property for first query is not of expected form.' );
		$this->assertSame( 5, $q->found_posts, 'Number of found posts is not five.' );
		$this->assertSame( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Tests querying all fields via WP_Query.
	 *
	 * @ticket 57012
	 */
	public function test_should_query_all_fields() {
		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'all',
		);

		$q = new WP_Query( $query_args );

		$expected = array_map( 'get_post', self::$post_ids );

		$this->assertEqualSets( $expected, $q->posts, 'Posts property for first query is not of expected form.' );
		$this->assertSame( 5, $q->found_posts, 'Number of found posts is not five.' );
		$this->assertSame( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Tests adding fields to WP_Query via filters when requesting the ID and parent sub-set.
	 *
	 * @ticket 57012
	 */
	public function test_should_include_filtered_values_in_addition_to_id_and_parent_subset() {
		add_filter( 'posts_fields', array( $this, 'filter_posts_fields' ) );
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ) );

		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'id=>parent',
		);

		$q = new WP_Query( $query_args );

		$expected = array();
		foreach ( self::$post_ids as $post_id ) {
			$expected[] = (object) array(
				'ID'                => $post_id,
				'post_parent'       => 0,
				'test_post_fields'  => '1',
				'test_post_clauses' => '2',
			);
		}

		$this->assertEqualSets( $expected, $q->posts, 'Posts property for first query is not of expected form.' );
		$this->assertSame( 5, $q->found_posts, 'Number of found posts is not five.' );
		$this->assertSame( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Tests adding fields to WP_Query via filters when requesting the ID field.
	 *
	 * @ticket 57012
	 */
	public function test_should_include_filtered_values_in_addition_to_id() {
		add_filter( 'posts_fields', array( $this, 'filter_posts_fields' ) );
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ) );

		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'ids',
		);

		$q = new WP_Query( $query_args );

		// `fields => ids` does not include the additional fields.
		$expected = self::$post_ids;

		$this->assertEqualSets( $expected, $q->posts, 'Posts property for first query is not of expected form.' );
		$this->assertSame( 5, $q->found_posts, 'Number of found posts is not five.' );
		$this->assertSame( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Tests adding fields to WP_Query via filters when requesting all fields.
	 *
	 * @ticket 57012
	 */
	public function test_should_include_filtered_values() {
		add_filter( 'posts_fields', array( $this, 'filter_posts_fields' ) );
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ) );

		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'all',
		);

		$q = new WP_Query( $query_args );

		$expected = array_map( 'get_post', self::$post_ids );
		foreach ( $expected as $post ) {
			$post->test_post_fields  = '1';
			$post->test_post_clauses = '2';
		}

		$this->assertEqualSets( $expected, $q->posts, 'Posts property for first query is not of expected form.' );
		$this->assertSame( 5, $q->found_posts, 'Number of found posts is not five.' );
		$this->assertSame( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Tests the value returned when the fields are limited to the ID and parent sub-set.
	 *
	 * The second query is served from the `post-queries` cache, which previously returned
	 * the parent IDs keyed by the `post_parent:{$post_id}` cache keys they are stored
	 * under rather than by post ID.
	 *
	 * The cache hit is not specific to an external object cache being in use: the second
	 * query is served from the cache with the default in-memory implementation as well.
	 *
	 * @ticket 65817
	 *
	 * @covers WP_Query::query
	 */
	public function test_id_and_parent_subset_should_return_parent_ids_keyed_by_post_id_when_cached() {
		$parent_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertIsInt( $parent_id, 'The parent page was not created.' );

		$child_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			)
		);
		$this->assertIsInt( $child_id, 'The child page was not created.' );

		$query_args = array(
			'post_type'      => 'page',
			'fields'         => 'id=>parent',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$expected = array(
			$parent_id => 0,
			$child_id  => $parent_id,
		);

		$q1 = new WP_Query();
		$this->assertSame( $expected, $q1->query( $query_args ), 'The uncached query is not of the expected form.' );

		$q2 = new WP_Query();
		$this->assertSame( $expected, $q2->query( $query_args ), 'The cached query is not of the expected form.' );
	}

	/**
	 * Tests the value returned when the fields are limited to the ID and parent sub-set
	 * and the query results are not cached.
	 *
	 * With caching disabled every query runs against the database, so the `post-queries`
	 * cache is never consulted.
	 *
	 * @ticket 65817
	 *
	 * @covers WP_Query::query
	 */
	public function test_id_and_parent_subset_should_return_parent_ids_keyed_by_post_id_when_not_caching_results() {
		$parent_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertIsInt( $parent_id, 'The parent page was not created.' );

		$child_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_id,
			)
		);
		$this->assertIsInt( $child_id, 'The child page was not created.' );

		$query_args = array(
			'post_type'      => 'page',
			'fields'         => 'id=>parent',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'cache_results'  => false,
		);

		$expected = array(
			$parent_id => 0,
			$child_id  => $parent_id,
		);

		$q1 = new WP_Query();
		$this->assertSame( $expected, $q1->query( $query_args ), 'First query is not of the expected form.' );

		$q2 = new WP_Query();
		$this->assertSame( $expected, $q2->query( $query_args ), 'Second query is not of the expected form.' );
	}

	/**
	 * Tests the posts property when the fields are limited to the ID and parent sub-set
	 * and the query matches nothing.
	 *
	 * An empty result set is cached like any other, and the cached branch only ever
	 * appended to the posts property, so a second identical query left it as null where
	 * the first left it an empty array.
	 *
	 * @ticket 65817
	 */
	public function test_id_and_parent_subset_should_populate_posts_property_when_cached_query_has_no_results() {
		$query_args = array(
			'post_type' => 'wptests_pt',
			'fields'    => 'id=>parent',
			'name'      => 'this-slug-does-not-exist',
		);

		$q1 = new WP_Query();
		$this->assertSame( array(), $q1->query( $query_args ), 'The uncached query did not return an empty array.' );
		$this->assertSame( array(), $q1->posts, 'The posts property is not an empty array after the uncached query.' );

		$q2 = new WP_Query();
		$this->assertSame( array(), $q2->query( $query_args ), 'The cached query did not return an empty array.' );
		$this->assertSame( array(), $q2->posts, 'The posts property is not an empty array after the cached query.' );
	}

	/**
	 * Filters the posts fields.
	 *
	 * @param string $fields The fields to SELECT.
	 * @return string The filtered fields.
	 */
	public function filter_posts_fields( $fields ) {
		return "$fields, 1 as test_post_fields";
	}

	/**
	 * Filters the posts clauses.
	 *
	 * @param array $clauses The WP_Query database clauses.
	 * @return array The filtered database clauses.
	 */
	public function filter_posts_clauses( $clauses ) {
		$clauses['fields'] .= ', 2 as test_post_clauses';
		return $clauses;
	}
}
