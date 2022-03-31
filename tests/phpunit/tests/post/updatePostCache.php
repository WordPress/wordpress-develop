<?php

use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @group post
 * @group query
 */
class Tests_Post_UpdatePostCache extends WP_UnitTestCase {

	/**
	 * Post IDs from the shared fixture.
	 * @var array
	 */
	static public $post_ids;

	public static function wpSetupBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 1 );
	}

	public function set_up() {
		wp_cache_set( 'last_changed', microtime(), 'posts' );
	}

	/**
	 * Ensure filter = raw is always set via Query.
	 *
	 * @ticket 50567
	 */
	public function test_query_caches_post_filter() {
		$post_id = self::$post_ids[0];
		$this->go_to( '/' );

		$cached_post = wp_cache_get( $post_id, 'posts' );
		$this->assertSame( 'raw', $cached_post->filter );
	}

	/**
	 * Ensure filter = raw is always set via get_post.
	 *
	 * @ticket 50567
	 */
	public function test_get_post_caches_post_filter() {
		$post_id = self::$post_ids[0];
		get_post( $post_id );

		$cached_post = wp_cache_get( $post_id, 'posts' );
		$this->assertSame( 'raw', $cached_post->filter );
	}

	/**
	 * Ensure filter = raw is always set via get_post called with a different filter setting.
	 *
	 * @ticket 50567
	 */
	public function test_get_post_caches_post_filter_is_always_raw() {
		$post_id = self::$post_ids[0];
		get_post( $post_id, OBJECT, 'display' );

		$cached_post = wp_cache_get( $post_id, 'posts' );
		$this->assertSame( 'raw', $cached_post->filter );
	}

	/**
	 * Ensure filter = raw is always set via get_posts.
	 *
	 * @ticket 50567
	 */
	public function test_get_posts_caches_post_filter_is_always_raw() {
		$post_id = self::$post_ids[0];
		get_posts( array( 'includes' => $post_id ) );

		$cached_post = wp_cache_get( $post_id, 'posts' );
		$this->assertSame( 'raw', $cached_post->filter );
	}
}
