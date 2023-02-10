<?php
/**
 * Test `update_post_cache()`.
 *
 * @package WordPress
 */

/**
 * Test class for `update_post_cache()`.
 *
 * @group post
 * @group query
 *
 * @covers ::update_post_cache
 */
class Tests_Post_UpdatePostCache extends WP_UnitTestCase {

	/**
	 * Post IDs from the shared fixture.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Set up test resources before the class.
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetupBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 1 );
	}

	/**
	 * Ensure that `update_post_cache()` returns `null` when
	 * `$posts` is empty.
	 *
	 * @ticket 50567
	 */
	public function test_should_return_null_with_an_empty_array() {
		$posts = array();
		$this->assertNull( update_post_cache( $posts ) );
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
		$this->assertIsObject(
			$cached_post,
			'The cached post is not an object'
		);

		$this->assertObjectHasAttribute(
			'filter',
			$cached_post,
			'The cached post does not have a "filter" property'
		);

		$this->assertSame(
			'raw',
			$cached_post->filter,
			'The filter is not set to "raw"'
		);
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
		$this->assertIsObject(
			$cached_post,
			'The cached post is not an object'
		);

		$this->assertObjectHasAttribute(
			'filter',
			$cached_post,
			'The cached post does not have a "filter" property'
		);

		$this->assertSame(
			'raw',
			$cached_post->filter,
			'The filter is not set to "raw"'
		);
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
		$this->assertIsObject(
			$cached_post,
			'The cached post is not an object'
		);

		$this->assertObjectHasAttribute(
			'filter',
			$cached_post,
			'The cached post does not have a "filter" property'
		);

		$this->assertSame(
			'raw',
			$cached_post->filter,
			'The filter is not set to "raw"'
		);
	}
}
