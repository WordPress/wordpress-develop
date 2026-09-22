<?php

/**
 * Tests for the wp_cache_set_posts_last_changed() function.
 *
 * @group post
 * @group cache
 *
 * @covers ::wp_cache_set_posts_last_changed
 */
class Tests_Post_WpCacheSetPostsLastChanged extends WP_UnitTestCase {

	/**
	 * A post used across the tests.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Creates a shared post before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Adding post meta should update the 'posts' and 'post-meta'
	 * last changed values but leave 'post-queries' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_post_meta_action_updates_posts_and_post_meta() {
		$posts_before        = wp_cache_get_last_changed( 'posts' );
		$post_meta_before    = wp_cache_get_last_changed( 'post-meta' );
		$post_queries_before = wp_cache_get_last_changed( 'post-queries' );

		add_post_meta( self::$post_id, 'test_key', 'test_value' );

		$this->assertNotSame(
			$posts_before,
			wp_cache_get_last_changed( 'posts' ),
			'The posts last changed value should be updated.'
		);
		$this->assertNotSame(
			$post_meta_before,
			wp_cache_get_last_changed( 'post-meta' ),
			'The post-meta last changed value should be updated.'
		);
		$this->assertSame(
			$post_queries_before,
			wp_cache_get_last_changed( 'post-queries' ),
			'The post-queries last changed value should not be updated.'
		);
	}

	/**
	 * Cleaning the post cache should update the 'posts' and 'post-queries'
	 * last changed values but leave 'post-meta' untouched.
	 *
	 * @ticket 65487
	 */
	public function test_post_query_action_updates_posts_and_post_queries() {
		$posts_before        = wp_cache_get_last_changed( 'posts' );
		$post_meta_before    = wp_cache_get_last_changed( 'post-meta' );
		$post_queries_before = wp_cache_get_last_changed( 'post-queries' );

		clean_post_cache( self::$post_id );

		$this->assertNotSame(
			$posts_before,
			wp_cache_get_last_changed( 'posts' ),
			'The posts last changed value should be updated.'
		);
		$this->assertNotSame(
			$post_queries_before,
			wp_cache_get_last_changed( 'post-queries' ),
			'The post-queries last changed value should be updated.'
		);
		$this->assertSame(
			$post_meta_before,
			wp_cache_get_last_changed( 'post-meta' ),
			'The post-meta last changed value should not be updated.'
		);
	}
}
