<?php

/**
 * Tests for the pre_wp_count_posts filter in wp_count_posts().
 *
 * @group post
 *
 * @covers ::wp_count_posts
 */
class Tests_Post_wpCountPosts extends WP_UnitTestCase {

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_short_circuits_query() {
		self::factory()->post->create_many( 3 );

		add_filter(
			'pre_wp_count_posts',
			static function () {
				return (object) array( 'publish' => 42 );
			}
		);

		$start_num_queries = get_num_queries();
		$counts            = wp_count_posts();

		$this->assertSame( 0, get_num_queries() - $start_num_queries, 'No database query should run when the filter returns a value.' );
		$this->assertSame( 42, $counts->publish, 'Count is expected to be filtered value (42).' );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_null_runs_default_query() {
		self::factory()->post->create_many( 3 );

		add_filter( 'pre_wp_count_posts', '__return_null' );
		$start_num_queries = get_num_queries();

		$this->assertSame( '3', wp_count_posts()->publish, 'Published post count is expected to be set.' );
		$this->assertGreaterThan( 0, get_num_queries() - $start_num_queries, 'wp_count_posts() is expected to trigger database queries.' );
	}

	/**
	 * @ticket 66098
	 *
	 * @dataProvider data_non_stdclass_values
	 *
	 * @param mixed $value Filtered value.
	 */
	public function test_pre_wp_count_posts_non_stdclass_runs_default_query( $value ) {
		self::factory()->post->create_many( 3 );

		add_filter(
			'pre_wp_count_posts',
			static function () use ( $value ) {
				return $value;
			}
		);
		$start_num_queries = get_num_queries();

		$this->assertSame( '3', wp_count_posts()->publish, 'A filtered value that is not a stdClass object is expected to be ignored.' );
		$this->assertGreaterThan( 0, get_num_queries() - $start_num_queries, 'wp_count_posts() is expected to trigger database queries.' );
	}

	/**
	 * Data provider for {@see self::test_pre_wp_count_posts_non_stdclass_runs_default_query()}.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed }>
	 */
	public function data_non_stdclass_values(): array {
		return array(
			'array'       => array( array( 'publish' => 42 ) ),
			'false'       => array( false ),
			'ArrayObject' => array( new ArrayObject( array( 'publish' => 42 ) ) ),
		);
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_receives_type_and_perm() {
		$filter = new MockAction();
		add_filter( 'pre_wp_count_posts', array( $filter, 'filter' ), 10, 3 );

		wp_count_posts( 'page', 'readable' );

		$args = $filter->get_args();
		$this->assertSame( array( null, 'page', 'readable' ), $args[0] );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_skipped_for_unregistered_post_type() {
		$filter = new MockAction();
		add_filter( 'pre_wp_count_posts', array( $filter, 'filter' ) );

		$this->assertEquals( new stdClass(), wp_count_posts( 'not_a_post_type' ), 'An unregistered post type should return an empty object.' );
		$this->assertSame( 0, $filter->get_call_count(), 'The pre_wp_count_posts filter should not fire for an unregistered post type.' );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_fills_missing_statuses() {
		add_filter(
			'pre_wp_count_posts',
			static function () {
				return (object) array( 'publish' => 5 );
			}
		);

		$counts            = wp_count_posts();
		$expected_statuses = get_post_stati();
		$actual_statuses   = array_keys( get_object_vars( $counts ) );

		$this->assertSameSets( $expected_statuses, $actual_statuses, 'Counts for all statuses should be returned' );
		$this->assertSame( 0, $counts->draft, 'A status missing from the filtered value should default to 0.' );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_filtered_post_count_returns_same_statuses_as_unfiltered_post_count() {
		$expected_statuses = array_keys( get_object_vars( wp_count_posts() ) );

		add_filter(
			'pre_wp_count_posts',
			static function () {
				return (object) array( 'publish' => 5 );
			}
		);

		$actual_statuses = array_keys( get_object_vars( wp_count_posts() ) );
		$this->assertSameSets( $expected_statuses, $actual_statuses, 'Pre filtered and unfiltered post counts should return the same set of statuses' );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_value_is_passed_to_wp_count_posts_filter() {
		add_filter(
			'pre_wp_count_posts',
			static function () {
				return (object) array( 'publish' => 5 );
			}
		);
		$filter = new MockAction();
		add_filter( 'wp_count_posts', array( $filter, 'filter' ), 10, 3 );

		wp_count_posts( 'page', 'readable' );

		$expected_counts = (object) array_merge(
			array_fill_keys( get_post_stati(), 0 ),
			array( 'publish' => 5 )
		);

		$args = $filter->get_args();
		$this->assertSame( 1, $filter->get_call_count(), 'Expected the wp_count_posts filter to run once.' );
		$this->assertEquals( array( $expected_counts, 'page', 'readable' ), $args[0], 'Expected the wp_count_posts filter to receive the filtered counts, type, and permission.' );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_does_not_modify_filtered_object() {
		$stored_counts = (object) array( 'publish' => 5 );
		add_filter(
			'pre_wp_count_posts',
			static function () use ( $stored_counts ) {
				return $stored_counts;
			}
		);

		$counts = wp_count_posts();

		$this->assertSame( 0, $counts->draft, 'A status missing from the filtered value should default to 0.' );
		$this->assertEquals( (object) array( 'publish' => 5 ), $stored_counts, 'The object returned by the filter should not be modified.' );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_result_is_not_cached() {
		self::factory()->post->create_many( 3 );

		$filter = static function () {
			return (object) array( 'publish' => 99 );
		};
		add_filter( 'pre_wp_count_posts', $filter );

		$this->assertSame( 99, wp_count_posts()->publish, 'Expected the filtered count.' );
		$this->assertFalse( wp_cache_get( _count_posts_cache_key( 'post' ), 'counts' ), 'Expected the filtered counts not to be cached.' );

		remove_filter( 'pre_wp_count_posts', $filter );
		$start_num_queries = get_num_queries();

		$this->assertSame( '3', wp_count_posts()->publish, 'Expected the real count once the filter is removed.' );
		$this->assertGreaterThan( 0, get_num_queries() - $start_num_queries, 'Expected database queries since nothing should have been cached.' );
	}
}
