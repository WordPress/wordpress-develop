<?php

/**
 * Tests for wp_get_recent_posts().
 *
 * @group post
 *
 * @covers ::wp_get_recent_posts
 */
class Tests_Post_WpGetRecentPosts extends WP_UnitTestCase {

	/**
	 * IDs of the posts, from the oldest to the newest by date.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		/*
		 * Twelve posts, one for each day, so their order does not depend on the time they are created.
		 * The newest post is created first, so the order of the IDs is the opposite of the order of the dates.
		 */
		for ( $day = 12; $day >= 1; $day-- ) {
			self::$post_ids[ $day - 1 ] = $factory->post->create(
				array(
					'post_title'    => "Post $day",
					'post_date'     => sprintf( '2020-01-%02d 12:00:00', $day ),
					'post_date_gmt' => sprintf( '2020-01-%02d 12:00:00', $day ),
				)
			);
		}

		ksort( self::$post_ids );
	}

	/**
	 * Tests that the ten newest posts are returned as arrays by default, newest first.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_the_ten_newest_posts_as_arrays_by_default() {
		$posts = wp_get_recent_posts();

		$this->assertCount( 10, $posts );
		$this->assertSame( array_reverse( array_slice( self::$post_ids, 2 ) ), wp_list_pluck( $posts, 'ID' ) );
		$this->assertIsArray( $posts[0] );
		$this->assertSame( 'Post 12', $posts[0]['post_title'] );
		$this->assertArrayHasKey( 'post_status', $posts[0] );
	}

	/**
	 * Tests that the posts are returned as objects when the output is OBJECT.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_post_objects_for_object_output() {
		$posts = wp_get_recent_posts( array( 'numberposts' => 2 ), OBJECT );

		$this->assertCount( 2, $posts );
		$this->assertContainsOnlyInstancesOf( WP_Post::class, $posts );
		$this->assertSame( self::$post_ids[11], $posts[0]->ID );
	}

	/**
	 * Tests that an empty list is returned for array output, and false for object output, when nothing is found.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_empty_results_when_nothing_is_found() {
		$args = array( 'post_type' => 'a-post-type-without-posts' );

		$this->assertSame( array(), wp_get_recent_posts( $args ) );
		$this->assertFalse( wp_get_recent_posts( $args, OBJECT ) );
	}

	/**
	 * Tests that the number of posts and the offset can be set.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_number_of_posts_and_the_offset() {
		$posts = wp_get_recent_posts(
			array(
				'numberposts' => 3,
				'offset'      => 2,
			)
		);

		$this->assertSame( array( self::$post_ids[9], self::$post_ids[8], self::$post_ids[7] ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Tests that the order of the posts can be changed.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_order() {
		$posts = wp_get_recent_posts(
			array(
				'numberposts' => 2,
				'order'       => 'ASC',
			)
		);

		$this->assertSame( array( self::$post_ids[0], self::$post_ids[1] ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Tests that the posts can be ordered by another field.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_orderby() {
		$posts = wp_get_recent_posts(
			array(
				'numberposts' => 2,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		// The titles are "Post 1", "Post 10", "Post 11", "Post 12", "Post 2", and so on.
		$this->assertSame( array( 'Post 1', 'Post 10' ), wp_list_pluck( $posts, 'post_title' ) );
	}

	/**
	 * Tests that posts can be included and excluded by ID.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_include_and_exclude() {
		$included = wp_get_recent_posts( array( 'include' => array( self::$post_ids[0], self::$post_ids[5] ) ) );
		$excluded = wp_get_recent_posts(
			array(
				'numberposts' => 2,
				'exclude'     => array( self::$post_ids[11] ),
			)
		);

		$this->assertSame( array( self::$post_ids[5], self::$post_ids[0] ), wp_list_pluck( $included, 'ID' ) );
		$this->assertSame( array( self::$post_ids[10], self::$post_ids[9] ), wp_list_pluck( $excluded, 'ID' ) );
	}

	/**
	 * Tests that the posts of a category are returned.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_category() {
		$category_id = self::factory()->category->create();
		wp_set_post_categories( self::$post_ids[3], array( $category_id ) );

		$posts = wp_get_recent_posts( array( 'category' => $category_id ) );

		$this->assertSame( array( self::$post_ids[3] ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Tests that the posts can be limited by a custom field.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_meta_key_and_value() {
		update_post_meta( self::$post_ids[4], 'recent_posts_test', 'yes' );
		update_post_meta( self::$post_ids[6], 'recent_posts_test', 'no' );

		$posts = wp_get_recent_posts(
			array(
				'meta_key'   => 'recent_posts_test',
				'meta_value' => 'yes',
			)
		);

		$this->assertSame( array( self::$post_ids[4] ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Tests that the draft, pending, future and private posts are returned by default, and posts in the trash are not.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_default_post_statuses() {
		$status_ids = array();
		foreach ( array( 'draft', 'pending', 'future', 'private', 'trash' ) as $status ) {
			$status_ids[ $status ] = self::factory()->post->create(
				array(
					'post_status'   => $status,
					'post_date'     => '2030-01-01 12:00:00',
					'post_date_gmt' => '2030-01-01 12:00:00',
				)
			);
		}

		$ids = wp_list_pluck( wp_get_recent_posts( array( 'numberposts' => 50 ) ), 'ID' );

		foreach ( array( 'draft', 'pending', 'future', 'private' ) as $status ) {
			$this->assertContains( $status_ids[ $status ], $ids, "A post with the $status status should be returned." );
		}

		$this->assertNotContains( $status_ids['trash'], $ids, 'A post in the trash should not be returned.' );
	}

	/**
	 * Tests that the post statuses can be set.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_post_status() {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$posts = wp_get_recent_posts( array( 'post_status' => 'draft' ) );

		$this->assertSame( array( $draft_id ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Tests that the post type can be set.
	 *
	 * @ticket 66495
	 */
	public function test_should_use_the_post_type() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$posts = wp_get_recent_posts( array( 'post_type' => 'page' ) );

		$this->assertSame( array( $page_id ), wp_list_pluck( $posts, 'ID' ) );
	}

	/**
	 * Tests that the query filters are not applied by default, and are applied when asked.
	 *
	 * @ticket 66495
	 */
	public function test_should_suppress_the_query_filters_by_default() {
		add_filter(
			'posts_where',
			static function ( $where ) {
				return $where . ' AND 1 = 0';
			}
		);

		$this->assertCount( 10, wp_get_recent_posts(), 'The filters should be suppressed by default.' );
		$this->assertSame( array(), wp_get_recent_posts( array( 'suppress_filters' => false ) ), 'The filter should apply when the filters are not suppressed.' );
	}

	/**
	 * Tests that a number as the arguments is deprecated, and is used as the number of posts.
	 *
	 * @ticket 66495
	 *
	 * @expectedDeprecated wp_get_recent_posts
	 */
	public function test_should_accept_a_number_as_the_number_of_posts() {
		$posts = wp_get_recent_posts( 3 );

		$this->assertSame( array( self::$post_ids[11], self::$post_ids[10], self::$post_ids[9] ), wp_list_pluck( $posts, 'ID' ) );
	}
}
