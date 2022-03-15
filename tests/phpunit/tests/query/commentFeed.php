<?php

/**
 * @group query
 */
class Tests_Query_CommentFeed extends WP_UnitTestCase {
	public static $post_type = 'post'; // Can be anything.

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		for ( $i = 0; $i < 10; $i ++ ) {
			$post_id = $factory->post->create(
				array(
					'post_type'   => self::$post_type,
					'post_status' => 'publish',
				)
			);
			$factory->comment->create_post_comments( $post_id, 5 );
		}

		update_option( 'posts_per_rss', 100 );
	}

	/**
	 * @ticket 36904
	 */
	public function test_archive_comment_feed() {
		global $wpdb;
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
		$num_queries = $wpdb->num_queries;
		$q2          = new WP_Query();
		$q2->query( $args );
		$this->assertTrue( $q2->is_comment_feed() );
		$this->assertFalse( $q2->is_singular() );
		$this->assertSame( $num_queries + 4, $wpdb->num_queries );
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
		$this->assertSame( 50, $comment_count );

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
		$this->assertSame( 55, $comment_count );
	}

	/**
	 * @ticket 36904
	 */
	public function test_single_comment_feed() {
		global $wpdb;
		$post = self::factory()->post->create_and_get( array( 'post_type' => self::$post_type ) );
		self::factory()->comment->create_post_comments( $post->ID, 5 );

		$q1   = new WP_Query();
		$args = array(
			'withcomments'           => 1,
			'feed'                   => 'comments-rss',
			'post_type'              => self::$post_type,
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
