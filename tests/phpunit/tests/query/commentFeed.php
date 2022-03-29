<?php

/**
 * @group query
 * @group comments
 * @group feeds
 */
class Tests_Query_CommentFeed extends WP_UnitTestCase {
	public static $post_type   = 'post';
	protected static $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many(
			3,
			array(
				'post_type'   => self::$post_type,
				'post_status' => 'publish',
			)
		);
		foreach ( self::$post_ids as $post_id ) {
			$factory->comment->create_post_comments( $post_id, 5 );
		}

		update_option( 'posts_per_rss', 100 );
	}

	/**
	 * @ticket 36904
	 */
	public function test_archive_comment_feed() {
		global $wpdb;
		add_filter( 'split_the_query', '__return_false' );
		$q1   = new WP_Query();
		$args = array(
			'withcomments'           => 1,
			'feed'                   => 'comments-rss',
			'post_type'              => self::$post_type,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => false,
			'no_found_rows'          => true,
		);
		$q1->query( $args );
		$num_queries = $wpdb->num_queries;
		$q2          = new WP_Query();
		$q2->query( $args );
		$this->assertTrue( $q2->is_comment_feed() );
		$this->assertFalse( $q2->is_singular() );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
	}

	/**
	 * @ticket 36904
	 */
	public function test_archive_comment_feed_invalid_cache() {
		$q1   = new WP_Query();
		$args = array(
			'withcomments'           => 1,
			'feed'                   => 'comments-rss',
			'post_type'              => self::$post_type,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => false,
		);
		$q1->query( $args );
		$comment_count = $q1->comment_count;
		$this->assertSame( 15, $comment_count );

		$post = self::factory()->post->create_and_get(
			array(
				'post_type'   => self::$post_type,
				'post_status' => 'publish',
			)
		);
		self::factory()->comment->create_post_comments( $post->ID, 5 );
		$q2 = new WP_Query();
		$q2->query( $args );
		$this->assertTrue( $q2->is_comment_feed() );
		$this->assertFalse( $q2->is_singular() );

		$comment_count = $q2->comment_count;
		$this->assertSame( 20, $comment_count );
	}

	/**
	 * @ticket 36904
	 */
	public function test_single_comment_feed() {
		global $wpdb;
		$post = get_post( self::$post_ids[0] );

		$q1   = new WP_Query();
		$args = array(
			'withcomments'           => 1,
			'feed'                   => 'comments-rss',
			'post_type'              => $post->post_type,
			'name'                   => $post->post_name,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => false,
		);

		$q1->query( $args );
		$num_queries = $wpdb->num_queries;
		$q2          = new WP_Query();
		$q2->query( $args );

		$this->assertTrue( $q2->is_comment_feed() );
		$this->assertTrue( $q2->is_singular() );
		$this->assertSame( $num_queries + 1, $wpdb->num_queries );
	}
}
