<?php

/**
 * @group user
 * @group post
 */
class Tests_User_CountUserPosts extends WP_UnitTestCase {
	public static $user_id;
	public static $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'count_user_posts_user',
				'user_email' => 'count_user_posts_user@example.com',
			)
		);

		self::$post_ids = $factory->post->create_many(
			4,
			array(
				'post_author' => self::$user_id,
				'post_type'   => 'post',
			)
		);
		self::$post_ids = array_merge(
			self::$post_ids,
			$factory->post->create_many(
				3,
				array(
					'post_author' => self::$user_id,
					'post_type'   => 'wptests_pt',
				)
			)
		);
		self::$post_ids = array_merge(
			self::$post_ids,
			$factory->post->create_many(
				2,
				array(
					'post_author' => 12345,
					'post_type'   => 'wptests_pt',
				)
			)
		);

		self::$post_ids[] = $factory->post->create(
			array(
				'post_author' => 12345,
				'post_type'   => 'wptests_pt',
			)
		);
	}

	public function set_up() {
		parent::set_up();
		register_post_type( 'wptests_pt' );
	}

	public function test_count_user_posts_post_type_should_default_to_post() {
		$this->assertSame( '4', count_user_posts( self::$user_id ) );
	}

	/**
	 * @ticket 21364
	 */
	public function test_count_user_posts_post_type_post() {
		$this->assertSame( '4', count_user_posts( self::$user_id, 'post' ) );
	}

	/**
	 * @ticket 21364
	 */
	public function test_count_user_posts_post_type_cpt() {
		$this->assertSame( '3', count_user_posts( self::$user_id, 'wptests_pt' ) );
	}

	/**
	 * @ticket 32243
	 */
	public function test_count_user_posts_with_multiple_post_types() {
		$this->assertSame( '7', count_user_posts( self::$user_id, array( 'wptests_pt', 'post' ) ) );
	}

	/**
	 * @ticket 32243
	 */
	public function test_count_user_posts_should_ignore_non_existent_post_types() {
		$this->assertSame( '4', count_user_posts( self::$user_id, array( 'foo', 'post' ) ) );
	}

	/**
	 * Post count should be correct after reassigning posts to another user.
	 *
	 * @ticket 39242
	 */
	public function test_reassigning_users_posts_modifies_count() {
		// Create new user.
		$new_user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		// Prior to reassigning posts.
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of four posts prior to reassignment.' );
		$this->assertSame( '0', count_user_posts( $new_user_id ), 'New user is expected to have a count of zero posts prior to reassignment.' );

		// Delete the original user, reassigning their posts to the new user.
		wp_delete_user( self::$user_id, $new_user_id );

		// After reassigning posts.
		$this->assertSame( '0', count_user_posts( self::$user_id ), 'Original user is expected to have a count of zero posts following reassignment.' );
		$this->assertSame( '4', count_user_posts( $new_user_id ), 'New user is expected to have a count of four posts following reassignment.' );
	}

	/**
	 * Post count should be correct after different user creates a post.
	 *
	 * @ticket 39242
	 */
	public function test_invalidate_cache_different_users() {
		// Create new user.
		$new_user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		// Prior to reassigning posts.
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of four posts prior to reassignment.' );
		$this->assertSame( '0', count_user_posts( $new_user_id ), 'New user is expected to have a count of zero posts prior to reassignment.' );

		// Assign post to next user.
		self::factory()->post->create(
			array(
				'post_author' => $new_user_id,
				'post_type'   => 'post',
			)
		);

		// After reassigning posts.
		$query_num_start = get_num_queries();
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of zero posts following reassignment.' );
		$this->assertSame( $query_num_start, get_num_queries(), 'Cache should as another users has been updated' );
		$query_num_start = get_num_queries();
		$this->assertSame( '1', count_user_posts( $new_user_id ), 'New user is expected to have a count of four posts following reassignment.' );
		$this->assertSame( $query_num_start + 1, get_num_queries(), 'Cache not be hit as this user has created a post' );
	}

	/**
	 * Post count should be correct after different user delete a post.
	 *
	 * @ticket 39242
	 */
	public function test_invalidate_cache_different_users_post_delete() {
		// Create new user.
		$new_user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		// Assign post to next user.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $new_user_id,
				'post_type'   => 'post',
			)
		);

		// Prior to reassigning posts.
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of four posts prior to reassignment.' );
		$this->assertSame( '1', count_user_posts( $new_user_id ), 'New user is expected to have a count of zero posts prior to reassignment.' );

		wp_delete_post( $post_id, true );

		// After reassigning posts.
		$query_num_start = get_num_queries();
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of zero posts following reassignment.' );
		$this->assertSame( $query_num_start, get_num_queries(), 'Cache should as another users has been updated' );
		$query_num_start = get_num_queries();
		$this->assertSame( '0', count_user_posts( $new_user_id ), 'New user is expected to have a count of four posts following reassignment.' );
		$this->assertSame( $query_num_start + 1, get_num_queries(), 'Cache not be hit as this user has created a post' );
	}

	/**
	 * Post count should be correct after different user update a post.
	 *
	 * @ticket 39242
	 */
	public function test_invalidate_cache_different_users_post_update() {
		// Create new user.
		$new_user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		// Assign post to next user.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $new_user_id,
				'post_type'   => 'post',
			)
		);

		// Prior to reassigning posts.
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of four posts prior to reassignment.' );
		$this->assertSame( '1', count_user_posts( $new_user_id ), 'New user is expected to have a count of zero posts prior to reassignment.' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_author' => self::$user_id,
			)
		);

		// After reassigning posts.
		$query_num_start = get_num_queries();
		$this->assertSame( '5', count_user_posts( self::$user_id ), 'Original user is expected to have a count of zero posts following reassignment.' );
		$this->assertSame( $query_num_start + 1, get_num_queries(), 'Cache should as another users has been updated' );
		$query_num_start = get_num_queries();
		$this->assertSame( '0', count_user_posts( $new_user_id ), 'New user is expected to have a count of four posts following reassignment.' );
		$this->assertSame( $query_num_start + 1, get_num_queries(), 'Cache not be hit as this user has created a post' );
	}

	/**
	 * Post count should be correct after different user update a post via a database query.
	 *
	 * @ticket 39242
	 */
	public function test_invalidate_cache_different_users_post_update_db() {
		global $wpdb;

		// Create new user.
		$new_user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		// Assign post to next user.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $new_user_id,
				'post_type'   => 'post',
			)
		);

		// Prior to reassigning posts.
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'Original user is expected to have a count of four posts prior to reassignment.' );
		$this->assertSame( '1', count_user_posts( $new_user_id ), 'New user is expected to have a count of zero posts prior to reassignment.' );

		$wpdb->update( $wpdb->posts, array( 'post_author' => self::$user_id ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		clean_post_author_cache( self::$user_id );

		// After reassigning posts.
		$query_num_start = get_num_queries();
		$this->assertSame( '5', count_user_posts( self::$user_id ), 'Original user is expected to have a count of zero posts following reassignment.' );
		$this->assertSame( $query_num_start + 1, get_num_queries(), 'Cache should as another users has been updated' );
		$query_num_start = get_num_queries();
		$this->assertSame( '0', count_user_posts( $new_user_id ), 'New user is expected to have a count of four posts following reassignment.' );
		$this->assertSame( $query_num_start + 1, get_num_queries(), 'Cache not be hit as this user has created a post' );
	}

	/**
	 * Post count should be correct after deleting user without reassigning posts.
	 *
	 * @ticket 39242
	 */
	public function test_post_count_retained_after_deleting_user_without_reassigning_posts() {
		$this->assertSame( '4', count_user_posts( self::$user_id ), 'User is expected to have a count of four posts prior to deletion.' );

		// Delete the original user without reassigning their posts.
		wp_delete_user( self::$user_id );

		$this->assertSame( '0', count_user_posts( self::$user_id ), 'User is expected to have a count of zero posts following deletion.' );
	}

	/**
	 * Post count should work for users that don't exist but have posts assigned.
	 *
	 * @ticket 39242
	 */
	public function test_count_user_posts_for_non_existent_user() {
		$next_user_id = self::$user_id + 1;

		// Assign post to next user.
		self::factory()->post->create(
			array(
				'post_author' => $next_user_id,
				'post_type'   => 'post',
			)
		);

		$next_user_post_count = count_user_posts( $next_user_id );
		$this->assertSame( '1', $next_user_post_count, 'Non-existent user is expected to have count of one post.' );
	}

	/**
	 * Cached user count value should be accurate after user is created.
	 *
	 * @ticket 39242
	 */
	public function test_count_user_posts_for_user_created_after_being_assigned_posts() {
		global $wpdb;
		$next_user_id = (int) $wpdb->get_var( "SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name = '$wpdb->users'" );

		// Assign post to next user.
		self::factory()->post->create(
			array(
				'post_author' => $next_user_id,
				'post_type'   => 'post',
			)
		);

		// Cache the user count.
		count_user_posts( $next_user_id );

		// Create user.
		$real_next_user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		$this->assertSame( $next_user_id, $real_next_user_id, 'User ID should match calculated value' );
		$this->assertSame( '1', count_user_posts( $next_user_id ), 'User is expected to have count of one post.' );
	}

	/**
	 * User count cache should be hit regardless of post type order.
	 *
	 * @ticket 39242
	 */
	public function test_cache_should_be_hit_regardless_of_post_type_order() {
		// Prime cache.
		count_user_posts( self::$user_id, array( 'wptests_pt', 'post' ) );

		$query_num_start = get_num_queries();
		count_user_posts( self::$user_id, array( 'post', 'wptests_pt' ) );
		$total_queries = get_num_queries() - $query_num_start;

		$this->assertSame( 0, $total_queries, 'Cache should be hit regardless of post type order.' );
	}

	/**
	 * User count cache should be hit for string and array of post types.
	 *
	 * @ticket 39242
	 */
	public function test_cache_should_be_hit_for_string_and_array_equivalent_queries() {
		// Prime cache.
		count_user_posts( self::$user_id, 'post' );

		$query_num_start = get_num_queries();
		count_user_posts( self::$user_id, array( 'post' ) );
		$total_queries = get_num_queries() - $query_num_start;

		$this->assertSame( 0, $total_queries, 'Cache should be hit for string and array equivalent post types.' );
	}

	/**
	 * @ticket 39242
	 * @dataProvider data_user_id_values
	 */
	public function test_cache_should_be_hit_for_values( $user_value ) {
		// Prime cache.
		count_user_posts( $user_value, 'post' );

		$query_num_start = get_num_queries();
		count_user_posts( $user_value, array( 'post' ) );
		$total_queries = get_num_queries() - $query_num_start;

		$this->assertSame( 0, $total_queries, 'Cache should be hit for string and array equivalent post types.' );
	}

	/**
	 * Data provider for test_cache_should_be_hit_for_values
	 *
	 * @return array
	 */
	public function data_user_id_values() {
		return array(
			'null value'   => array( null ),
			'false value'  => array( false ),
			'zero value'   => array( 0 ),
			'string value' => array( 'foo' ),
			'float value'  => array( 1.0 ),
		);
	}

	/**
	 * User count cache should be hit for array duplicates and equivalent queries.
	 *
	 * @ticket 39242
	*/
	public function test_cache_should_be_hit_for_and_array_duplicates_equivalent_queries() {
		// Prime cache.
		count_user_posts( self::$user_id, array( 'post', 'post', 'post' ) );

		$query_num_start = get_num_queries();
		count_user_posts( self::$user_id, array( 'post' ) );
		$total_queries = get_num_queries() - $query_num_start;

		$this->assertSame( 0, $total_queries, 'Cache is expected to be hit for equivalent queries with duplicate post types' );
	}
}
