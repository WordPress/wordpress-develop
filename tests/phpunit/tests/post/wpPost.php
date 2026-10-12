<?php

/**
 * @group post
 */
class Tests_Post_wpPost extends WP_UnitTestCase {
	protected static int $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		// Ensure that there is a post with ID 1.
		if ( ! get_post( 1 ) ) {
			$wpdb->insert(
				$wpdb->posts,
				array(
					'ID'         => 1,
					'post_title' => 'Post 1',
				)
			);
		}

		self::$post_id = $factory->post->create();
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Post::get_instance( (string) self::$post_id );

		$this->assertSame( self::$post_id, $found->ID );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Post::get_instance( -self::$post_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 63850
	 */
	public function test_get_instance_should_not_perform_database_query_for_negative_number() {
		$num_queries = get_num_queries();
		$found       = WP_Post::get_instance( -self::$post_id );

		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Post::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Post::get_instance( 1.0 );

		$this->assertSame( 1, $found->ID );
	}

	/**
	 * Tests that a cached value which cannot be used as a post is treated as a cache miss.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss( $cache_value ): void {
		wp_cache_set( self::$post_id, $cache_value, 'posts' );

		$num_queries = get_num_queries();

		$post = WP_Post::get_instance( self::$post_id );

		$this->assertInstanceOf( WP_Post::class, $post, 'A post object was not returned.' );
		$this->assertSame( self::$post_id, $post->ID, 'The wrong post was returned.' );
		$this->assertSame( $num_queries + 1, get_num_queries(), 'The post was not fetched from the database.' );
	}

	/**
	 * Tests that the refetched post replaces the poisoned cache value.
	 *
	 * Otherwise the poisoned value survives and every subsequent lookup queries the database again.
	 *
	 * @ticket 65962
	 *
	 * @dataProvider data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss
	 *
	 * @param mixed $cache_value Value to poison the object cache with.
	 */
	public function test_get_instance_replaces_a_poisoned_cache_value( $cache_value ): void {
		wp_cache_set( self::$post_id, $cache_value, 'posts' );

		// Prime the object cache, replacing the poisoned value.
		WP_Post::get_instance( self::$post_id );

		$num_queries = get_num_queries();

		$post = WP_Post::get_instance( self::$post_id );

		$this->assertInstanceOf( WP_Post::class, $post, 'A post object was not returned.' );
		$this->assertSame( self::$post_id, $post->ID, 'The wrong post was returned.' );
		$this->assertSame( $num_queries, get_num_queries(), 'The database was queried again.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ mixed }>
	 */
	public function data_get_instance_treats_a_poisoned_cache_value_as_a_cache_miss(): array {
		return array(
			'true'                  => array( true ),
			'a non-numeric string'  => array( 'not-a-post' ),
			'an empty array'        => array( array() ),
			'an array of post data' => array(
				array(
					'ID'         => 1,
					'post_title' => 'Post 1',
				),
			),
			'an object without ID'  => array(
				(object) array(
					'post_title' => 'Post 1',
				),
			),
			'a WP_Post without ID'  => array( new WP_Post( new stdClass() ) ),
		);
	}
}
