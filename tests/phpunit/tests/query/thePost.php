<?php

/**
 * @group query
 * @covers WP_Query::the_post
 */
class Tests_Query_ThePost extends WP_UnitTestCase {

	/**
	 * Author IDs created for shared fixtures.
	 *
	 * @var int[]
	 */
	public static $author_ids = array();

	/**
	 * Post parent ID created for shared fixtures.
	 *
	 * @var int
	 */
	public static $page_parent_id = 0;

	/**
	 * Post child IDs created for shared fixtures.
	 *
	 * @var int[]
	 */
	public static $page_child_ids = array();

	/**
	 * Create the shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory object.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_ids     = $factory->user->create_many( 5, array( 'role' => 'author' ) );
		self::$page_parent_id = $factory->post->create( array( 'post_type' => 'page' ) );

		// Create child pages.
		foreach ( self::$author_ids as $author_id ) {
			self::$page_child_ids[] = $factory->post->create(
				array(
					'post_type'   => 'page',
					'post_parent' => self::$page_parent_id,
					'post_author' => $author_id,
				)
			);
		}
	}

	/**
	 * Ensure that a secondary loop populates the global post completely regardless of the fields parameter.
	 *
	 * @ticket 56992
	 *
	 * @dataProvider data_the_loop_fields
	 *
	 * @param string $fields Fields parameter for use in the query.
	 */
	public function test_the_loop_populates_the_global_post_completely( $fields ) {
		$query = new WP_Query(
			array(
				'fields'    => $fields,
				'post_type' => 'page',
				'page_id'   => self::$page_child_ids[0],
			)
		);

		$this->assertNotEmpty( $query->posts, 'The query is expected to return results' );

		// Start the loop.
		$query->the_post();

		// Get the global post and specific post.
		$global_post   = get_post();
		$specific_post = get_post( self::$page_child_ids[0], ARRAY_A );

		$this->assertNotEmpty( get_the_title(), 'The title is expected to be populated.' );
		$this->assertNotEmpty( get_the_content(), 'The content is expected to be populated.' );
		$this->assertNotEmpty( get_the_excerpt(), 'The excerpt is expected to be populated.' );

		$this->assertSameSetsWithIndex( $specific_post, $global_post->to_array(), 'The global post is expected to be fully populated.' );
	}

	/**
	 * Ensure that a secondary loop primes the post cache completely regardless of the fields parameter.
	 *
	 * @ticket 56992
	 *
	 * @dataProvider data_the_loop_fields
	 *
	 * @param string $fields           Fields parameter for use in the query.
	 * @param int    $expected_queries Expected number of queries when starting the loop.
	 */
	public function test_the_loop_primes_the_post_cache( $fields, $expected_queries ) {
		$query = new WP_Query(
			array(
				'fields'    => $fields,
				'post_type' => 'page',
				'post__in'  => self::$page_child_ids,
			)
		);

		// Start the loop.
		$start_queries = get_num_queries();
		$query->the_post();
		$end_queries = get_num_queries();
		/*
		 * Querying complete posts: 2 queries.
		 * 1. User meta data.
		 * 2. User data.
		 *
		 * Querying partial posts: 4 queries.
		 * 1. Post objects
		 * 2. Post meta data.
		 * 3. User meta data.
		 * 4. User data.
		 */
		$this->assertSame( $expected_queries, $end_queries - $start_queries, "Starting the loop should make $expected_queries db queries." );

		// Complete the loop.
		$start_queries = get_num_queries();
		while ( $query->have_posts() ) {
			$query->the_post();
		}
		$end_queries = get_num_queries();

		$this->assertSame( 0, $end_queries - $start_queries, 'The cache is expected to be primed by the loop.' );
	}

	/**
	 * Ensure that a secondary loop primes the author cache completely regardless of the fields parameter.
	 *
	 * @ticket 56992
	 *
	 * @dataProvider data_the_loop_fields
	 *
	 * @param string $fields           Fields parameter for use in the query.
	 * @param int    $expected_queries Expected number of queries when starting the loop.
	 */
	public function test_the_loop_primes_the_author_cache( $fields, $expected_queries ) {
		$query = new WP_Query(
			array(
				'fields'    => $fields,
				'post_type' => 'page',
				'post__in'  => self::$page_child_ids,
			)
		);

		// Start the loop.
		$start_queries = get_num_queries();
		$query->the_post();
		$end_queries = get_num_queries();
		/*
		 * Querying complete posts: 2 queries.
		 * 1. User meta data.
		 * 2. User data.
		 *
		 * Querying partial posts: 4 queries.
		 * 1. Post objects
		 * 2. Post meta data.
		 * 3. User meta data.
		 * 4. User data.
		 */
		$this->assertSame( $expected_queries, $end_queries - $start_queries, "Starting the loop should make $expected_queries db queries." );

		// Complete the loop.
		$start_queries = get_num_queries();
		while ( $query->have_posts() ) {
			$query->the_post();
			get_the_author();
		}
		$end_queries = get_num_queries();

		$this->assertSame( 0, $end_queries - $start_queries, 'The cache is expected to be primed by the loop.' );
	}

	/**
	 * Data provider for:
	 * - test_the_loop_populates_the_global_post_completely,
	 * - test_the_loop_primes_the_post_cache, and,
	 * - test_the_loop_primes_the_author_cache.
	 *
	 * @return array[]
	 */
	public function data_the_loop_fields() {
		return array(
			'all fields'                => array( 'all', 2 ),
			'all fields (empty fields)' => array( '', 2 ),
			'post IDs'                  => array( 'ids', 4 ),
			'post ids and parent'       => array( 'id=>parent', 4 ),
		);
	}
}
