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
	static $post_ids = array();

	/**
	 * Page IDs.
	 *
	 * @var int[]
	 */
	static $page_ids = array();

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
		$this->assertEquals( 1, $q->max_num_pages, 'Number of found pages is not one.' );

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
		$this->assertEquals( 1, $q->max_num_pages, 'Number of found pages is not one.' );

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
		$this->assertEquals( 1, $q->max_num_pages, 'Number of found pages is not one.' );

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
		$this->assertEquals( 1, $q->max_num_pages, 'Number of found pages is not one.' );

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
		$this->assertEquals( 1, $q->max_num_pages, 'Number of found pages is not one.' );

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
		$this->assertEquals( 1, $q->max_num_pages, 'Number of found pages is not one.' );

		// Test the second query's results match.
		$q2 = new WP_Query( $query_args );
		$this->assertEqualSets( $expected, $q2->posts, 'Posts property for second query is not in the expected form.' );
	}

	/**
	 * Filters the posts fields.
	 *
	 * @param string $fields The fields to SELECT.
	 * @return string The filtered fields.
	 */
	function filter_posts_fields( $fields ) {
		return "$fields, 1 as test_post_fields";
	}

	/**
	 * Filters the posts clauses.
	 *
	 * @param array $clauses The WP_Query database clauses.
	 * @return array The filtered database clauses.
	 */
	function filter_posts_clauses( $clauses ) {
		$clauses['fields'] .= ', 2 as test_post_clauses';
		return $clauses;
	}
}
