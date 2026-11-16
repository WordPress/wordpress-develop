<?php

/**
 * Tests for the sanitization of the 'posts_per_page' query var.
 *
 * @group query
 *
 * @covers WP_Query::get_posts
 */
class Tests_Query_PostsPerPage extends WP_UnitTestCase {

	/**
	 * Shared fixture post IDs.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Generate shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test suite factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 7 );
	}

	/**
	 * Runs a query for the shared fixture posts with the given arguments.
	 *
	 * @param array $args Query arguments.
	 * @return WP_Query The query object.
	 */
	private function get_query( $args ) {
		return new WP_Query(
			array_merge(
				array(
					'post_type' => 'post',
					'fields'    => 'ids',
				),
				$args
			)
		);
	}

	/**
	 * Asserts that the query ran without a database error and did not produce
	 * a LIMIT clause with a negative row count.
	 *
	 * @param WP_Query $query The query object.
	 */
	private function assertQueryHasValidLimit( $query ) {
		global $wpdb;

		$this->assertSame( '', $wpdb->last_error, 'The query should not produce a database error.' );
		$this->assertStringNotContainsString( 'LIMIT 0, -1', $query->request, 'The query should not contain a negative LIMIT.' );
	}

	/**
	 * @ticket 39945
	 *
	 * @dataProvider data_posts_per_page_values_that_truncate_to_minus_one
	 *
	 * @param mixed $posts_per_page The 'posts_per_page' value.
	 */
	public function test_posts_per_page_that_truncates_to_minus_one_should_return_all_posts( $posts_per_page ) {
		$query = $this->get_query( array( 'posts_per_page' => $posts_per_page ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertTrue( $query->get( 'nopaging' ), 'nopaging should be enabled.' );
		$this->assertSame( -1, $query->get( 'posts_per_page' ), 'posts_per_page should be normalized to an integer.' );
		$this->assertCount( 7, $query->posts );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_posts_per_page_values_that_truncate_to_minus_one() {
		return array(
			'string -1.5'            => array( '-1.5' ),
			'float -1.5'             => array( -1.5 ),
			'leading-numeric string' => array( '-1abc' ),
		);
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_page_minus_one_should_return_all_posts() {
		$query = $this->get_query( array( 'posts_per_page' => -1 ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertTrue( $query->get( 'nopaging' ), 'nopaging should be enabled.' );
		$this->assertStringNotContainsString( 'LIMIT', $query->request, 'The query should not contain a LIMIT clause.' );
		$this->assertCount( 7, $query->posts );
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_page_minus_one_with_nopaging_false_should_return_all_posts() {
		$query = $this->get_query(
			array(
				'posts_per_page' => -1,
				'nopaging'       => false,
			)
		);

		$this->assertQueryHasValidLimit( $query );
		$this->assertCount( 7, $query->posts );
		$this->assertSame(
			$this->get_query( array( 'posts_per_page' => -1 ) )->max_num_pages,
			$query->max_num_pages,
			'max_num_pages should match a plain posts_per_page -1 query.'
		);
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_page_php_int_min_should_be_clamped_to_an_integer() {
		$query = $this->get_query( array( 'posts_per_page' => PHP_INT_MIN ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertSame( PHP_INT_MAX, $query->get( 'posts_per_page' ), 'posts_per_page should be clamped to PHP_INT_MAX.' );
		$this->assertStringContainsString( 'LIMIT 0, ' . PHP_INT_MAX, $query->request );
		$this->assertCount( 7, $query->posts );
	}

	/**
	 * Converting an out-of-range numeric string to an integer is platform-dependent,
	 * so only the validity of the generated LIMIT clause is checked.
	 *
	 * @ticket 39945
	 */
	public function test_posts_per_page_huge_negative_numeric_string_should_not_produce_a_float_limit() {
		$query = $this->get_query( array( 'posts_per_page' => '-99999999999999999999' ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertDoesNotMatchRegularExpression( '/LIMIT[^\n]*[.eE+]/', $query->request, 'The LIMIT clause should not contain a float.' );
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_page_below_minus_one_should_use_absolute_value() {
		$query = $this->get_query( array( 'posts_per_page' => -5 ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertFalse( $query->get( 'nopaging' ), 'nopaging should not be enabled.' );
		$this->assertSame( 5, $query->get( 'posts_per_page' ) );
		$this->assertCount( 5, $query->posts );
	}

	/**
	 * An empty value falls back to the 'posts_per_page' option.
	 *
	 * @ticket 39945
	 */
	public function test_posts_per_page_zero_should_use_the_option() {
		update_option( 'posts_per_page', 4 );

		$query = $this->get_query( array( 'posts_per_page' => 0 ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertSame( 4, $query->get( 'posts_per_page' ) );
		$this->assertCount( 4, $query->posts );
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_page_that_truncates_to_zero_should_return_one_post() {
		$query = $this->get_query( array( 'posts_per_page' => '0.5' ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertSame( 1, $query->get( 'posts_per_page' ) );
		$this->assertCount( 1, $query->posts );
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_rss_should_override_posts_per_page_in_feeds() {
		$query = $this->get_query(
			array(
				'feed'           => 'rss2',
				'posts_per_page' => -1,
				'posts_per_rss'  => '3',
			)
		);

		$this->assertQueryHasValidLimit( $query );
		$this->assertTrue( $query->is_feed(), 'The query should be a feed.' );
		$this->assertFalse( $query->get( 'nopaging' ), 'nopaging should be disabled in feeds.' );
		$this->assertSame( 3, $query->get( 'posts_per_page' ) );
		$this->assertCount( 3, $query->posts );
	}

	/**
	 * @ticket 39945
	 */
	public function test_posts_per_rss_minus_one_should_fall_back_to_the_option_in_feeds() {
		update_option( 'posts_per_rss', 4 );

		$query = $this->get_query(
			array(
				'feed'          => 'rss2',
				'posts_per_rss' => -1,
			)
		);

		$this->assertQueryHasValidLimit( $query );
		$this->assertTrue( $query->is_feed(), 'The query should be a feed.' );
		$this->assertStringContainsString( 'LIMIT 0, 4', $query->request, 'Feeds should always have a LIMIT clause.' );
		$this->assertFalse( $query->get( 'nopaging' ), 'nopaging should be disabled in feeds.' );
		$this->assertCount( 4, $query->posts );
	}

	/**
	 * Option filters bypass the absint() sanitization applied when saving 'posts_per_rss'.
	 *
	 * @ticket 39945
	 */
	public function test_filtered_posts_per_rss_option_of_minus_one_should_return_one_post_in_feeds() {
		add_filter(
			'option_posts_per_rss',
			static function () {
				return -1;
			}
		);

		$query = $this->get_query( array( 'feed' => 'rss2' ) );

		$this->assertQueryHasValidLimit( $query );
		$this->assertTrue( $query->is_feed(), 'The query should be a feed.' );
		$this->assertMatchesRegularExpression( '/LIMIT \d+, \d+$/', $query->request, 'Feeds should always have a LIMIT clause with no negative value.' );
		$this->assertCount( 1, $query->posts );
	}
}
