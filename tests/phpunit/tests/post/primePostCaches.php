<?php
/**
 * Test `_prime_post_caches()`.
 *
 * @package WordPress
 */

/**
 * Test class for `_prime_post_caches()`.
 *
 * @group post
 * @group cache
 *
 * @covers ::_prime_post_caches
 */
class Tests_Post_PrimePostCaches extends WP_UnitTestCase {

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

		$category = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'Foo',
			)
		);

		wp_set_post_terms( self::$posts[0], $category, 'category' );
		add_post_meta( self::$posts[0], 'meta', 'foo' );
		add_post_meta( self::$posts[1], 'meta', 'bar' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches() {
		$post_id = self::$posts[0];

		$this->assertSame( array( $post_id ), _get_non_cached_ids( array( $post_id ), 'posts' ), 'Post is already cached.' );

		// Test posts cache.
		$before_num_queries = get_num_queries();
		_prime_post_caches( array( $post_id ) );
		$num_queries = get_num_queries() - $before_num_queries;

		/*
		 * Four expected queries:
		 * 1: Posts data,
		 * 2: Post meta data,
		 * 3: Taxonomy data,
		 * 4: Term data.
		 */
		$this->assertSame( 4, $num_queries, 'Unexpected number of queries.' );

		$this->assertSame( array(), _get_non_cached_ids( array( $post_id ), 'posts' ), 'Post is not cached.' );

		// Test post meta cache.
		$before_num_queries = get_num_queries();
		$meta               = get_post_meta( $post_id, 'meta', true );
		$num_queries        = get_num_queries() - $before_num_queries;

		$this->assertSame( 'foo', $meta, 'Meta has unexpected value.' );
		$this->assertSame( 0, $num_queries, 'Unexpected number of queries.' );

		// Test term cache.
		$before_num_queries = get_num_queries();
		$categories         = get_the_category( $post_id );
		$num_queries        = get_num_queries() - $before_num_queries;

		$this->assertNotEmpty( $categories, 'Categories does return an empty result set.' );
		$this->assertSame( 0, $num_queries, 'Unexpected number of queries.' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches_with_multiple_posts() {
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		$before_num_queries = get_num_queries();
		_prime_post_caches( self::$posts );
		$num_queries = get_num_queries() - $before_num_queries;

		/*
		 * Four expected queries:
		 * 1: Posts data,
		 * 2: Post meta data,
		 * 3: Taxonomy data,
		 * 4: Term data.
		 */
		$this->assertSame( 4, $num_queries, 'Unexpected number of queries.' );

		$this->assertSame( array(), _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are not cached.' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches_only_posts_cache() {
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		$before_num_queries = get_num_queries();
		_prime_post_caches( self::$posts, false, false );
		$num_queries = get_num_queries() - $before_num_queries;

		/*
		 * One expected query:
		 * 1: Posts data.
		 */
		$this->assertSame( 1, $num_queries, 'Unexpected number of queries.' );

		$this->assertSame( array(), _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are not cached.' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches_only_posts_and_term_cache() {
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		$before_num_queries = get_num_queries();
		_prime_post_caches( self::$posts, true, false );
		$num_queries = get_num_queries() - $before_num_queries;

		/*
		 * Three expected queries:
		 * 1: Posts data.
		 * 2: Taxonomy data,
		 * 3: Term data.
		 */
		$this->assertSame( 3, $num_queries, 'Unexpected number of queries.' );

		$this->assertSame( array(), _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are not cached.' );

		// Test term cache.
		$before_num_queries = get_num_queries();
		$categories         = get_the_category( self::$posts[0] );
		$num_queries        = get_num_queries() - $before_num_queries;

		$this->assertNotEmpty( $categories, 'Categories does return an empty result set.' );
		$this->assertSame( 0, $num_queries, 'Unexpected number of queries.' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches_only_posts_and_meta_cache() {
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		$before_num_queries = get_num_queries();
		_prime_post_caches( self::$posts, false, true );
		$num_queries = get_num_queries() - $before_num_queries;

		/*
		 * Two expected queries:
		 * 1: Posts data.
		 * 2: Post meta data.
		 */
		$this->assertSame( 2, $num_queries, 'Unexpected number of queries warming cache.' );

		$this->assertSame( array(), _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are not cached.' );

		// Test post meta cache.
		$before_num_queries = get_num_queries();
		$meta_1             = get_post_meta( self::$posts[0], 'meta', true );
		$meta_2             = get_post_meta( self::$posts[1], 'meta', true );
		$num_queries        = get_num_queries() - $before_num_queries;

		$this->assertSame( 'foo', $meta_1, 'Meta 1 has unexpected value.' );
		$this->assertSame( 'bar', $meta_2, 'Meta 2 has unexpected value.' );
		$this->assertSame( 0, $num_queries, 'Unexpected number of queries getting post meta.' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches_accounts_for_posts_without_primed_meta_terms() {
		$post_id = self::$posts[0];

		$this->assertSame( array( $post_id ), _get_non_cached_ids( array( $post_id ), 'posts' ), 'Post is already cached.' );

		// Warm only the posts cache.
		$post = get_post( $post_id );
		$this->assertNotEmpty( $post, 'Post does not exist.' );
		$this->assertEmpty( _get_non_cached_ids( array( $post_id ), 'posts' ), 'Post is not cached.' );

		$before_num_queries = get_num_queries();
		_prime_post_caches( array( $post_id ) );
		$num_queries = get_num_queries() - $before_num_queries;

		/*
		 * Three expected queries:
		 * 1: Post meta data,
		 * 2: Taxonomy data,
		 * 3: Term data.
		 */
		$this->assertSame( 3, $num_queries, 'Unexpected number of queries.' );
	}

	/**
	 * @ticket 57163
	 */
	public function test_prime_post_caches_does_not_prime_caches_twice() {
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		_prime_post_caches( self::$posts );

		$this->assertSame( array(), _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are not cached.' );

		$before_num_queries = get_num_queries();
		_prime_post_caches( self::$posts );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 0, $num_queries, 'Unexpected number of queries.' );
	}

	/**
	 * @ticket 57498
	 */
	public function test_prime_post_caches_with_objects_does_not_make_query() {
		// Get post objects, then invalidate cache to simulate them not being in cache.
		$post_objects = array_map( 'get_post', self::$posts );
		foreach ( self::$posts as $post_id ) {
			wp_cache_delete( $post_id, 'posts' );
		}
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		// Because we are passing the objects, no query should be made to get them from the database.
		$before_num_queries = get_num_queries();
		_prime_post_caches( $post_objects, false, false );
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 0, $num_queries, 'Unexpected number of queries.' );
	}

	/**
	 * @ticket 57498
	 */
	public function test_prime_post_caches_with_objects_queries_only_those_needed() {
		global $wpdb;

		// Get first post object, then invalidate cache to simulate it not being in cache.
		$first_post_object = get_post( self::$posts[0] );
		wp_cache_delete( self::$posts[0], 'posts' );
		$this->assertSame( self::$posts, _get_non_cached_ids( self::$posts, 'posts' ), 'Posts are already cached.' );

		// Because we pass one post as an object, the query should be made only for the other two.
		$before_num_queries = get_num_queries();
		$query              = '';
		add_filter(
			'query',
			function( $q ) use ( &$query ) {
				$query = $q;
				return $q;
			}
		);
		_prime_post_caches(
			array(
				$first_post_object,
				self::$posts[1],
				self::$posts[2],
			),
			false,
			false
		);
		$num_queries = get_num_queries() - $before_num_queries;

		$this->assertSame( 1, $num_queries, 'Unexpected number of queries.' );

		$expected_query = sprintf( "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE ID IN (%s)", implode( ',', array( self::$posts[1], self::$posts[2] ) ) );
		$this->assertSame( $expected_query, $query, 'Query included unnecessary post IDs.' );
	}
}
