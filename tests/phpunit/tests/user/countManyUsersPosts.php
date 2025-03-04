<?php

/**
 * @group user
 * @group post
 */
class Tests_User_CountManyUsersPosts extends WP_UnitTestCase {
	protected static $user_id_a;
	protected static $user_id_b;
	protected static $post_ids = array();

	/**
	 * Set up test users and posts before the class of tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Factory object to create test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {

		self::$user_id_a = $factory->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'count_many_posts_user_a',
				'user_email' => 'count_many_posts_user_a@example.com',
			)
		);

		self::$user_id_b = $factory->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'count_many_posts_user_b',
				'user_email' => 'count_many_posts_user_b@example.com',
			)
		);

		$test_posts = array(
			array(
				'count'       => 3,
				'post_author' => self::$user_id_a,
				'post_type'   => 'post',
			),
			array(
				'count'       => 2,
				'post_author' => self::$user_id_b,
				'post_type'   => 'post',
			),
			array(
				'count'       => 2,
				'post_author' => self::$user_id_a,
				'post_type'   => 'wptests_pt',
			),
			array(
				'count'       => 1,
				'post_author' => self::$user_id_b,
				'post_type'   => 'wptests_pt',
			),
		);

		// Create the posts.
		foreach ( $test_posts as $post_data ) {
			$count = $post_data['count'];
			unset( $post_data['count'] );

			self::$post_ids = array_merge(
				self::$post_ids,
				$factory->post->create_many( $count, $post_data )
			);
		}

		// Create a private post for user B.
		self::$post_ids[] = $factory->post->create(
			array(
				'post_author' => self::$user_id_b,
				'post_type'   => 'post',
				'post_status' => 'private',
			)
		);
	}

	/**
	 * Set up before each test method.
	 */
	public function set_up() {
		parent::set_up();
		register_post_type( 'wptests_pt' );
	}

	/**
	 * Test that count_many_users_posts() returns correct counts.
	 *
	 * @ticket 63045
	 */
	public function test_count_many_users_posts_basic() {
		$counts = count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), 'post', false );

		$this->assertSame( '3', $counts[ self::$user_id_a ] );
		$this->assertSame( '2', $counts[ self::$user_id_b ] );
	}

	/**
	 * Test that count_many_users_posts() caches results.
	 *
	 * @ticket 63045
	 */
	public function test_count_many_users_posts_should_use_cache() {
		count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), 'post', false );

		$query_num_start = get_num_queries();
		count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), 'post', false );
		$total_queries = get_num_queries() - $query_num_start;

		$this->assertSame( 0, $total_queries, 'Cache should be hit for identical queries' );
	}

	/**
	 * Test that count_many_users_posts() invalidates cache when posts are added.
	 *
	 * @ticket 63045
	 */
	public function test_count_many_users_posts_should_invalidate_cache_when_posts_added() {
		$counts_before = count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), 'post', false );

		self::factory()->post->create(
			array(
				'post_author' => self::$user_id_a,
				'post_type'   => 'post',
			)
		);

		wp_cache_delete( 'last_changed', 'posts' );

		$counts_after = count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), 'post', false );

		$this->assertSame( (string) ( (int) $counts_before[ self::$user_id_a ] + 1 ), $counts_after[ self::$user_id_a ], 'User A should have one more post' );
		$this->assertSame( $counts_before[ self::$user_id_b ], $counts_after[ self::$user_id_b ], 'User B should have the same number of posts' );
	}

	/**
	 * Test count_many_users_posts() with different post types.
	 *
	 * @ticket 63045
	 */
	public function test_count_many_users_posts_with_different_post_types() {
		$counts = count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), 'wptests_pt', false );

		$this->assertSame( '2', $counts[ self::$user_id_a ] );
		$this->assertSame( '1', $counts[ self::$user_id_b ] );
	}

	/**
	 * Test count_many_users_posts() with an array of post types.
	 *
	 * @ticket 63045
	 */
	public function test_count_many_users_posts_with_array_of_post_types() {
		$counts = count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), array( 'post', 'wptests_pt' ), false );

		$this->assertSame( '5', $counts[ self::$user_id_a ] );
		$this->assertSame( '3', $counts[ self::$user_id_b ] );
	}

	/**
	 * Test that count_many_users_posts() caches array of post types properly.
	 *
	 * @ticket 63045
	 */
	public function test_count_many_users_posts_should_cache_array_of_post_types() {
		count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), array( 'post', 'wptests_pt' ), false );

		$query_num_start = get_num_queries();
		count_many_users_posts( array( self::$user_id_a, self::$user_id_b ), array( 'post', 'wptests_pt' ), false );
		$total_queries = get_num_queries() - $query_num_start;

		$this->assertSame( 0, $total_queries, 'Cache should be hit for identical array of post types' );
	}
}
