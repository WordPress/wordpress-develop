<?php
/**
 * Test `_prime_post_parent_id_caches()`.
 *
 * @package WordPress
 */

/**
 * Test class for `_prime_post_parent_id_caches()`.
 *
 * @group post
 * @group cache
 *
 * @covers ::_prime_post_parent_id_caches
 */
class Tests_Post_PrimePostParentIdCaches extends WP_UnitTestCase {

	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	public static $posts;

	/**
	 * Set up test resources before the class.
	 *
	 * @param WP_UnitTest_Factory $factory The unit test factory.
	 */
	public static function wpSetupBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$posts = $factory->post->create_many( 3 );
	}

	/**
	 * @ticket 59188
	 */
	public function test_prime_post_parent_id_caches() {
		$post_id = self::$posts[0];

		$before_num_queries = get_num_queries();
		_prime_post_parent_id_caches( array( $post_id ) );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries.' );
		$this->assertSameSets( array( 0 ), wp_cache_get_multiple( array( "post_parent:{$post_id}" ), 'posts' ), 'Array of parent ids' );
	}

	/**
	 * @ticket 59188
	 */
	public function test_prime_post_parent_id_caches_multiple() {
		$before_num_queries = get_num_queries();
		_prime_post_parent_id_caches( self::$posts );
		$num_queries = get_num_queries() - $before_num_queries;

		$cache_keys = array_map(
			function ( $post_id ) {
				return "post_parent:{$post_id}";
			},
			self::$posts
		);

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries.' );
		$this->assertSameSets( array( 0, 0, 0 ), wp_cache_get_multiple( $cache_keys, 'posts' ), 'Array of parent ids' );
	}

	/**
	 * @ticket 59188
	 */
	public function test_prime_post_parent_id_caches_multiple_runs() {
		_prime_post_parent_id_caches( self::$posts );
		$before_num_queries = get_num_queries();
		_prime_post_parent_id_caches( self::$posts );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 0, $num_queries, 'Unexpected number of queries.' );
	}

	/**
	 * @ticket 59188
	 */
	public function test_prime_post_parent_id_caches_update() {
		$page_id            = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => self::$posts[0],
			)
		);
		$before_num_queries = get_num_queries();
		_prime_post_parent_id_caches( array( $page_id ) );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries on first run' );
		$this->assertSameSets( array( self::$posts[0] ), wp_cache_get_multiple( array( "post_parent:{$page_id}" ), 'posts' ), 'Array of parent ids with post 0 as parent' );

		wp_update_post(
			array(
				'ID'          => $page_id,
				'post_parent' => self::$posts[1],
			)
		);

		$before_num_queries = get_num_queries();
		_prime_post_parent_id_caches( array( $page_id ) );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries on second run' );
		$this->assertSameSets( array( self::$posts[1] ), wp_cache_get_multiple( array( "post_parent:{$page_id}" ), 'posts' ), 'Array of parent ids with post 1 as parent' );
	}

	/**
	 * @ticket 59188
	 */
	public function test_prime_post_parent_id_caches_delete() {
		$parent_page_id     = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$page_id            = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_page_id,
			)
		);
		$before_num_queries = get_num_queries();
		_prime_post_parent_id_caches( array( $page_id ) );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries on first run' );
		$this->assertSameSets( array( $parent_page_id ), wp_cache_get_multiple( array( "post_parent:{$page_id}" ), 'posts' ), 'Array of parent ids with post 0 as parent' );

		wp_delete_post( $parent_page_id, true );

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries on second run' );
		$this->assertSameSets( array( false ), wp_cache_get_multiple( array( "post_parent:{$page_id}" ), 'posts' ), 'Array of parent ids with false values' );
	}
}
