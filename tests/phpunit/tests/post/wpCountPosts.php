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

		$num_queries = $wpdb->num_queries;
		$counts      = wp_count_posts();

		$this->assertSame( $num_queries, $wpdb->num_queries, 'No database query should run when the filter returns a value.' );
		$this->assertSame( 42, $counts->publish );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_null_runs_default_query() {
		self::factory()->post->create_many( 3 );

		add_filter( 'pre_wp_count_posts', '__return_null' );

		$this->assertSame( '3', wp_count_posts()->publish );
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

		$this->assertEquals( new stdClass(), wp_count_posts( 'not_a_post_type' ) );
		$this->assertSame( 0, $filter->get_call_count() );
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

		$counts = wp_count_posts();

		foreach ( get_post_stati() as $status ) {
			$this->assertObjectHasProperty( $status, $counts );
		}
		$this->assertSame( 0, $counts->draft );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_result_passes_through_wp_count_posts_filter() {
		add_filter(
			'pre_wp_count_posts',
			static function () {
				return (object) array( 'publish' => 5 );
			}
		);
		add_filter(
			'wp_count_posts',
			static function ( $counts ) {
				$counts->publish += 1;
				return $counts;
			}
		);

		$this->assertSame( 6, wp_count_posts()->publish );
	}

	/**
	 * @ticket 66098
	 */
	public function test_pre_wp_count_posts_result_is_not_cached() {
		add_filter(
			'pre_wp_count_posts',
			static function () {
				return (object) array( 'publish' => 99 );
			}
		);

		wp_count_posts();

		$this->assertFalse( wp_cache_get( _count_posts_cache_key( 'post' ), 'counts' ) );
	}
}
