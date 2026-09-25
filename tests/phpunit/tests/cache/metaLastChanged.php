<?php
/**
 * Tests for meta-specific cache invalidation via last_changed keys.
 *
 * @ticket 43818
 *
 * @group cache
 * @group meta
 */
class Tests_Cache_MetaLastChanged extends WP_UnitTestCase {

	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	protected static $post_ids;

	/**
	 * Term IDs.
	 *
	 * @var int[]
	 */
	protected static $term_ids;

	/**
	 * Comment IDs.
	 *
	 * @var int[]
	 */
	protected static $comment_ids;

	/**
	 * User IDs.
	 *
	 * @var int[]
	 */
	protected static $user_ids;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 3 );

		self::$term_ids = array(
			$factory->term->create( array( 'taxonomy' => 'category' ) ),
			$factory->term->create( array( 'taxonomy' => 'category' ) ),
		);

		self::$comment_ids = array(
			$factory->comment->create( array( 'comment_post_ID' => self::$post_ids[0] ) ),
			$factory->comment->create( array( 'comment_post_ID' => self::$post_ids[0] ) ),
		);

		self::$user_ids = $factory->user->create_many(
			2,
			array( 'role' => 'author' )
		);
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_posts_meta_last_changed
	 */
	public function test_wp_cache_set_posts_meta_last_changed_updates_posts_meta_group() {
		wp_cache_set_posts_meta_last_changed();
		$last_changed = wp_cache_get_last_changed( 'posts-meta' );
		$this->assertNotEmpty( $last_changed, 'posts-meta last_changed should be set.' );
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_posts_meta_last_changed
	 */
	public function test_adding_post_meta_updates_posts_meta_group_not_posts_group() {
		$posts_before      = wp_cache_get_last_changed( 'posts' );
		$posts_meta_before = wp_cache_get_last_changed( 'posts-meta' );

		add_post_meta( self::$post_ids[0], 'test_key', 'test_value' );

		$posts_after      = wp_cache_get_last_changed( 'posts' );
		$posts_meta_after = wp_cache_get_last_changed( 'posts-meta' );

		$this->assertSame( $posts_before, $posts_after, 'posts last_changed should not change when post meta is added.' );
		$this->assertNotSame( $posts_meta_before, $posts_meta_after, 'posts-meta last_changed should change when post meta is added.' );
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_posts_meta_last_changed
	 */
	public function test_updating_post_meta_updates_posts_meta_group_not_posts_group() {
		add_post_meta( self::$post_ids[0], 'update_key', 'old_value' );

		$posts_before      = wp_cache_get_last_changed( 'posts' );
		$posts_meta_before = wp_cache_get_last_changed( 'posts-meta' );

		update_post_meta( self::$post_ids[0], 'update_key', 'new_value' );

		$posts_after      = wp_cache_get_last_changed( 'posts' );
		$posts_meta_after = wp_cache_get_last_changed( 'posts-meta' );

		$this->assertSame( $posts_before, $posts_after, 'posts last_changed should not change when post meta is updated.' );
		$this->assertNotSame( $posts_meta_before, $posts_meta_after, 'posts-meta last_changed should change when post meta is updated.' );
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_posts_meta_last_changed
	 */
	public function test_deleting_post_meta_updates_posts_meta_group_not_posts_group() {
		add_post_meta( self::$post_ids[0], 'delete_key', 'value' );

		$posts_before      = wp_cache_get_last_changed( 'posts' );
		$posts_meta_before = wp_cache_get_last_changed( 'posts-meta' );

		delete_post_meta( self::$post_ids[0], 'delete_key' );

		$posts_after      = wp_cache_get_last_changed( 'posts' );
		$posts_meta_after = wp_cache_get_last_changed( 'posts-meta' );

		$this->assertSame( $posts_before, $posts_after, 'posts last_changed should not change when post meta is deleted.' );
		$this->assertNotSame( $posts_meta_before, $posts_meta_after, 'posts-meta last_changed should change when post meta is deleted.' );
	}

	/**
	 * WP_Query without meta_query should not be invalidated by meta changes.
	 *
	 * @ticket 43818
	 * @covers WP_Query::get_posts
	 */
	public function test_wp_query_without_meta_query_not_invalidated_by_meta_change() {
		$query_args = array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			'cache_results'  => true,
			'fields'         => 'ids',
		);

		// Prime the cache.
		$q1    = new WP_Query( $query_args );
		$ids_1 = $q1->posts;

		$posts_before = wp_cache_get_last_changed( 'posts' );
		add_post_meta( self::$post_ids[0], 'nonmeta_test', 'val' );

		$posts_after = wp_cache_get_last_changed( 'posts' );
		$this->assertSame( $posts_before, $posts_after, 'posts last_changed should not change when post meta is added.' );

		$q2    = new WP_Query( $query_args );
		$ids_2 = $q2->posts;

		$this->assertSameSets( $ids_1, $ids_2, 'Query without meta_query should return same results after meta change.' );
	}

	/**
	 * WP_Query with meta_query should be invalidated when meta changes.
	 *
	 * @ticket 43818
	 * @covers WP_Query::get_posts
	 */
	public function test_wp_query_with_meta_query_is_invalidated_by_meta_change() {
		$meta_key = 'meta_query_test_key_' . uniqid();
		add_post_meta( self::$post_ids[0], $meta_key, 'alpha' );
		add_post_meta( self::$post_ids[1], $meta_key, 'beta' );

		$query_args = array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			'cache_results'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => $meta_key,
					'value'   => 'alpha',
					'compare' => '=',
				),
			),
		);

		$q1    = new WP_Query( $query_args );
		$ids_1 = $q1->posts;
		$this->assertContains( self::$post_ids[0], $ids_1, 'First query should find post with matching meta.' );

		update_post_meta( self::$post_ids[0], $meta_key, 'gamma' );

		$q2    = new WP_Query( $query_args );
		$ids_2 = $q2->posts;

		$this->assertNotContains( self::$post_ids[0], $ids_2, 'Query with meta_query should reflect updated meta value.' );
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_comments_meta_last_changed
	 */
	public function test_adding_comment_meta_updates_comment_meta_group_not_comment_group() {
		$comment_before      = wp_cache_get_last_changed( 'comment' );
		$comment_meta_before = wp_cache_get_last_changed( 'comment-meta' );

		add_comment_meta( self::$comment_ids[0], 'c_key', 'val' );

		$comment_after      = wp_cache_get_last_changed( 'comment' );
		$comment_meta_after = wp_cache_get_last_changed( 'comment-meta' );

		$this->assertSame( $comment_before, $comment_after, 'comment last_changed should not change when comment meta is added.' );
		$this->assertNotSame( $comment_meta_before, $comment_meta_after, 'comment-meta last_changed should change when comment meta is added.' );
	}

	/**
	 * @ticket 43818
	 * @covers WP_Comment_Query::get_comments
	 */
	public function test_comment_query_without_meta_query_not_invalidated_by_meta_change() {
		$query_args = array(
			'post_id' => self::$post_ids[0],
			'fields'  => 'ids',
		);

		// Prime the cache.
		$q1 = new WP_Comment_Query( $query_args );
		$r1 = $q1->comments;

		$comment_before = wp_cache_get_last_changed( 'comment' );

		// Add meta to a comment – must not bump the 'comment' group.
		add_comment_meta( self::$comment_ids[0], 'noq_key', 'val' );

		$comment_after = wp_cache_get_last_changed( 'comment' );
		$this->assertSame( $comment_before, $comment_after, 'comment last_changed should not change when comment meta is added.' );

		// Re-run the same query – should still hit cache.
		$q2 = new WP_Comment_Query( $query_args );
		$r2 = $q2->comments;

		$this->assertSameSets( $r1, $r2, 'Query without meta_query should return same results after comment meta change.' );
	}

	/**
	 * @ticket 43818
	 * @covers WP_Comment_Query::get_comments
	 */
	public function test_comment_query_with_meta_query_is_invalidated_by_meta_change() {
		$meta_key = 'cq_test_key_' . uniqid();
		add_comment_meta( self::$comment_ids[0], $meta_key, 'yes' );

		$query_args = array(
			'post_id'    => self::$post_ids[0],
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => $meta_key,
					'value'   => 'yes',
					'compare' => '=',
				),
			),
		);

		$q1 = new WP_Comment_Query( $query_args );
		$r1 = $q1->comments;
		$this->assertContains( self::$comment_ids[0], $r1, 'First query should find comment with matching meta.' );

		delete_comment_meta( self::$comment_ids[0], $meta_key );

		$q2 = new WP_Comment_Query( $query_args );
		$r2 = $q2->comments;

		$this->assertNotContains( self::$comment_ids[0], $r2, 'Query with meta_query should reflect deleted comment meta.' );
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_terms_meta_last_changed
	 */
	public function test_adding_term_meta_updates_terms_meta_group_not_terms_group() {
		$terms_before      = wp_cache_get_last_changed( 'terms' );
		$terms_meta_before = wp_cache_get_last_changed( 'terms-meta' );

		add_term_meta( self::$term_ids[0], 't_key', 'val' );

		$terms_after      = wp_cache_get_last_changed( 'terms' );
		$terms_meta_after = wp_cache_get_last_changed( 'terms-meta' );

		$this->assertSame( $terms_before, $terms_after, 'terms last_changed should not change when term meta is added.' );
		$this->assertNotSame( $terms_meta_before, $terms_meta_after, 'terms-meta last_changed should change when term meta is added.' );
	}

	/**
	 * @ticket 43818
	 * @covers WP_Term_Query::get_terms
	 */
	public function test_term_query_without_meta_query_not_invalidated_by_meta_change() {
		$terms_before = wp_cache_get_last_changed( 'terms' );

		add_term_meta( self::$term_ids[0], 'tnoq_key', 'val' );

		$terms_after = wp_cache_get_last_changed( 'terms' );

		$this->assertSame( $terms_before, $terms_after, 'terms last_changed should not change when term meta is added.' );
	}

	/**
	 * @ticket 43818
	 * @covers WP_Term_Query::get_terms
	 */
	public function test_term_query_with_meta_query_is_invalidated_by_meta_change() {
		$meta_key = 'tq_test_key_' . uniqid();
		add_term_meta( self::$term_ids[0], $meta_key, 'present' );

		$query_args = array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => $meta_key,
					'value'   => 'present',
					'compare' => '=',
				),
			),
		);

		$q1 = new WP_Term_Query( $query_args );
		$r1 = $q1->get_terms();
		$this->assertContains( self::$term_ids[0], $r1, 'First query should find term with matching meta.' );

		delete_term_meta( self::$term_ids[0], $meta_key );

		$q2 = new WP_Term_Query( $query_args );
		$r2 = $q2->get_terms();

		$this->assertNotContains( self::$term_ids[0], $r2, 'Query with meta_query should reflect deleted term meta.' );
	}

	/**
	 * @ticket 43818
	 * @covers ::wp_cache_set_users_meta_last_changed
	 */
	public function test_adding_user_meta_updates_users_meta_group_not_users_group() {
		$users_before      = wp_cache_get_last_changed( 'users' );
		$users_meta_before = wp_cache_get_last_changed( 'users-meta' );

		add_user_meta( self::$user_ids[0], 'u_key', 'val' );

		$users_after      = wp_cache_get_last_changed( 'users' );
		$users_meta_after = wp_cache_get_last_changed( 'users-meta' );

		$this->assertSame( $users_before, $users_after, 'users last_changed should not change when user meta is added.' );
		$this->assertNotSame( $users_meta_before, $users_meta_after, 'users-meta last_changed should change when user meta is added.' );
	}

	/**
	 * @ticket 43818
	 * @covers WP_User_Query::query
	 */
	public function test_user_query_without_meta_query_not_invalidated_by_meta_change() {
		$users_before = wp_cache_get_last_changed( 'users' );

		add_user_meta( self::$user_ids[0], 'unoq_key', 'val' );

		$users_after = wp_cache_get_last_changed( 'users' );

		$this->assertSame( $users_before, $users_after, 'users last_changed should not change when user meta is added.' );
	}

	/**
	 * @ticket 43818
	 * @covers WP_User_Query::query
	 */
	public function test_user_query_with_meta_query_is_invalidated_by_meta_change() {
		$meta_key = 'uq_test_key_' . uniqid();
		add_user_meta( self::$user_ids[0], $meta_key, 'found' );

		$query_args = array(
			'blog_id'    => 0,
			'fields'     => 'ID',
			'meta_query' => array(
				array(
					'key'     => $meta_key,
					'value'   => 'found',
					'compare' => '=',
				),
			),
		);

		$q1 = new WP_User_Query( $query_args );
		$r1 = array_map( 'intval', $q1->get_results() );
		$this->assertContains( self::$user_ids[0], $r1, 'First query should find user with matching meta.' );

		delete_user_meta( self::$user_ids[0], $meta_key );

		$q2 = new WP_User_Query( $query_args );
		$r2 = array_map( 'intval', $q2->get_results() );

		$this->assertNotContains( self::$user_ids[0], $r2, 'Query with meta_query should reflect deleted user meta.' );
	}
}
