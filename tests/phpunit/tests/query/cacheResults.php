<?php

/**
 * @group query
 */
class Test_Query_CacheResults extends WP_UnitTestCase {
	/**
	 * Page IDs.
	 *
	 * @var int[]
	 */
	public static $pages;

	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	public static $posts;

	/**
	 * Term ID.
	 *
	 * @var int
	 */
	public static $t1;

	/**
	 * Author's user ID.
	 *
	 * @var int
	 */
	public static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Make some post objects.
		self::$posts = $factory->post->create_many( 5 );
		self::$pages = $factory->post->create_many( 5, array( 'post_type' => 'page' ) );

		self::$t1 = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'Foo',
			)
		);

		wp_set_post_terms( self::$posts[0], self::$t1, 'category' );
		add_post_meta( self::$posts[0], 'color', '#000000' );

		// Make a user.
		self::$author_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	/**
	 * @dataProvider data_query_cache
	 * @ticket 22176
	 */
	public function test_query_cache( $args ) {
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		$queries_before = get_num_queries();
		$query2         = new WP_Query();
		$posts2         = $query2->query( $args );
		$queries_after  = get_num_queries();

		if ( isset( $args['fields'] ) ) {
			if ( 'all' !== $args['fields'] ) {
				$this->assertSameSets( $posts1, $posts2 );
			}
			if ( 'id=>parent' !== $args['fields'] ) {
				$this->assertSame( $queries_after, $queries_before );
			}
		} else {
			$this->assertSame( $queries_after, $queries_before );
		}
		$this->assertSame( $query1->found_posts, $query2->found_posts );
		$this->assertSame( $query1->max_num_pages, $query2->max_num_pages );

		if ( ! $query1->query_vars['no_found_rows'] ) {
			wp_delete_post( self::$posts[0], true );
			wp_delete_post( self::$pages[0], true );
			$query3 = new WP_Query();
			$query3->query( $args );

			$this->assertNotSame( $query1->found_posts, $query3->found_posts );
			$this->assertNotSame( $queries_after, get_num_queries() );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_query_cache() {
		return array(
			'cache true'                                  => array(
				'args' => array(
					'cache_results' => true,
				),
			),
			'cache true and pagination'                   => array(
				'args' => array(
					'cache_results'  => true,
					'posts_per_page' => 3,
					'page'           => 2,
				),
			),
			'cache true and no pagination'                => array(
				'args' => array(
					'cache_results' => true,
					'nopaging'      => true,
				),
			),
			'cache true and post type any'                => array(
				'args' => array(
					'cache_results' => true,
					'nopaging'      => true,
					'post_type'     => 'any',
				),
			),
			'cache true and get all'                      => array(
				'args' => array(
					'cache_results'  => true,
					'fields'         => 'all',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'post_type'      => 'any',
				),
			),
			'cache true and page'                         => array(
				'args' => array(
					'cache_results' => true,
					'post_type'     => 'page',
				),
			),
			'cache true and ids'                          => array(
				'args' => array(
					'cache_results' => true,
					'fields'        => 'ids',
				),
			),
			'cache true and id=>parent and no found rows' => array(
				'args' => array(
					'cache_results' => true,
					'fields'        => 'id=>parent',
				),
			),
			'cache true and ids and no found rows'        => array(
				'args' => array(
					'no_found_rows' => true,
					'cache_results' => true,
					'fields'        => 'ids',
				),
			),
			'cache true and id=>parent'                   => array(
				'args' => array(
					'no_found_rows' => true,
					'cache_results' => true,
					'fields'        => 'id=>parent',
				),
			),
			'cache and ignore_sticky_posts'               => array(
				'args' => array(
					'cache_results'       => true,
					'ignore_sticky_posts' => true,
				),
			),
			'cache meta query'                            => array(
				'args' => array(
					'cache_results' => true,
					'meta_query'    => array(
						array(
							'key' => 'color',
						),
					),
				),
			),
			'cache comment_count'                         => array(
				'args' => array(
					'cache_results' => true,
					'comment_count' => 0,
				),
			),
			'cache term query'                            => array(
				'args' => array(
					'cache_results' => true,
					'tax_query'     => array(
						array(
							'taxonomy' => 'category',
							'terms'    => array( 'foo' ),
							'field'    => 'slug',
						),
					),
				),
			),
		);
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_filter_request() {
		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
		);
		$query1 = new WP_Query();
		$query1->query( $args );
		$queries_before = get_num_queries();

		add_filter( 'posts_request', array( $this, 'filter_posts_request' ) );

		$query2 = new WP_Query();
		$query2->query( $args );

		remove_filter( 'posts_request', array( $this, 'filter_posts_request' ) );

		$queries_after = get_num_queries();

		$this->assertNotSame( $queries_before, $queries_after );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_no_caching() {
		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
		);
		$query1 = new WP_Query();
		$query1->query( $args );
		$queries_before = get_num_queries();

		$query2                = new WP_Query();
		$args['cache_results'] = false;
		$query2->query( $args );

		$queries_after = get_num_queries();

		$this->assertNotSame( $queries_before, $queries_after );
	}

	public function filter_posts_request( $request ) {
		return $request . ' -- Add comment';
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_post() {
		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		$p1 = self::factory()->post->create();

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_main_query_sticky_posts_change() {
		add_action( 'parse_query', array( $this, 'set_cache_results' ) );
		update_option( 'posts_per_page', 5 );

		$old_date = date_create( '-25 hours' );
		$old_post = self::factory()->post->create( array( 'post_date' => $old_date->format( 'Y-m-d H:i:s' ) ) );

		// Post is unstuck.
		$this->go_to( '/' );
		$unstuck     = $GLOBALS['wp_query']->posts;
		$unstuck_ids = wp_list_pluck( $unstuck, 'ID' );

		$expected = array_reverse( self::$posts );
		$this->assertSame( $expected, $unstuck_ids );

		// Stick the post.
		stick_post( $old_post );

		$this->go_to( '/' );
		$stuck     = $GLOBALS['wp_query']->posts;
		$stuck_ids = wp_list_pluck( $stuck, 'ID' );

		$expected = array_reverse( self::$posts );
		array_unshift( $expected, $old_post );

		$this->assertSame( $expected, $stuck_ids );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_sticky_posts_change() {
		add_action( 'parse_query', array( $this, 'set_cache_results' ) );

		$old_date = date_create( '-25 hours' );
		$old_post = self::factory()->post->create( array( 'post_date' => $old_date->format( 'Y-m-d H:i:s' ) ) );

		// Post is unstuck.
		$unstuck     = ( new WP_Query( array( 'posts_per_page' => 5 ) ) )->posts;
		$unstuck_ids = wp_list_pluck( $unstuck, 'ID' );

		$expected = array_reverse( self::$posts );

		$this->assertSame( $expected, $unstuck_ids );

		// Stick the post.
		stick_post( $old_post );

		$stuck     = ( new WP_Query( array( 'posts_per_page' => 5 ) ) )->posts;
		$stuck_ids = wp_list_pluck( $stuck, 'ID' );

		$expected = array_reverse( self::$posts );
		array_unshift( $expected, $old_post );

		$this->assertSame( $expected, $stuck_ids );

		// Just to make sure everything has changed.
		$this->assertNotSame( $unstuck, $stuck );
	}

	public function set_cache_results( $q ) {
		$q->set( 'cache_results', true );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_different_args() {
		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		$args           = array(
			'cache_results'          => true,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'cache_results'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'lazy_load_term_meta'    => false,
		);
		$queries_before = get_num_queries();
		$query2         = new WP_Query();
		$posts2         = $query2->query( $args );
		$queries_after  = get_num_queries();

		$this->assertSame( $queries_before, $queries_after );
		$this->assertSame( $posts1, $posts2 );
		$this->assertSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_different_fields() {
		$args   = array(
			'cache_results' => true,
			'fields'        => 'all',
		);
		$query1 = new WP_Query();
		$query1->query( $args );

		$args           = array(
			'cache_results' => true,
			'fields'        => 'id=>parent',
		);
		$queries_before = get_num_queries();
		$query2         = new WP_Query();
		$query2->query( $args );
		$queries_after = get_num_queries();

		$this->assertSame( $queries_before, $queries_after );
		$this->assertCount( 5, $query1->posts );
		$this->assertCount( 5, $query2->posts );
		$this->assertSame( $query1->found_posts, $query2->found_posts );

		/*
		 * Make sure the returned post objects differ due to the field argument.
		 *
		 * This uses assertNotEquals rather than assertNotSame as the former is
		 * agnostic to the instance ID of objects, whereas the latter will take
		 * it in to account. The test needs to discard the instance ID when
		 * confirming inequality.
		 */
		$this->assertNotEquals( $query1->posts, $query2->posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_logged_in() {
		$user_id = self::$author_id;

		self::factory()->post->create(
			array(
				'post_status' => 'private',
				'post_author' => $user_id,
			)
		);

		$args   = array(
			'cache_results' => true,
			'author'        => $user_id,
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		wp_set_current_user( $user_id );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );
		$this->assertEmpty( $posts1 );
		$this->assertNotSame( $posts1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_logged_in_password() {
		$user_id = self::$author_id;
		self::factory()->post->create(
			array(
				'post_title'    => 'foo',
				'post_password' => 'password',
				'post_author'   => $user_id,
			)
		);

		$args   = array(
			'cache_results' => true,
			's'             => 'foo',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		wp_set_current_user( $user_id );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );
		$this->assertEmpty( $posts1 );
		$this->assertNotSame( $posts1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_comment() {
		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'comment_count' => 1,
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		self::factory()->comment->create( array( 'comment_post_ID' => self::$posts[0] ) );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( self::$posts[0], $posts2 );
		$this->assertNotEmpty( $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_main_comments_feed_includes_attachment_comments() {
		$attachment_id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $attachment_id,
				'comment_approved' => '1',
			)
		);

		$args   = array(
			'cache_results' => true,
			'withcomments'  => 1,
			'feed'          => 'feed',
		);
		$query1 = new WP_Query();
		$query1->query( $args );

		$query2 = new WP_Query();
		$query2->query( $args );

		$this->assertTrue( $query1->have_comments() );
		$this->assertTrue( $query2->have_comments() );

		$feed_comment = $query1->next_comment();
		$this->assertEquals( $comment_id, $feed_comment->comment_ID );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_delete_comment() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$posts[0] ) );
		$args       = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'comment_count' => 1,
		);
		$query1     = new WP_Query();
		$posts1     = $query1->query( $args );

		wp_delete_comment( $comment_id, true );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertEmpty( $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_update_post() {
		$p1 = self::$posts[0];

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		wp_update_post(
			array(
				'ID'          => $p1,
				'post_status' => 'draft',
			)
		);

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts1 );
		$this->assertNotContains( $p1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_meta() {
		$p1 = self::$posts[1]; // Post 0 already has a color meta value.

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key' => 'color',
				),
			),
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		add_post_meta( $p1, 'color', 'black' );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_update_meta() {
		// Posts[0] already has a color meta value set to #000000.
		$p1 = self::$posts[0];

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key'   => 'color',
					'value' => '#000000',
				),
			),
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		update_post_meta( $p1, 'color', 'blue' );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts1 );
		$this->assertEmpty( $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}


	/**
	 * @ticket 22176
	 */
	public function test_query_cache_delete_attachment() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
			)
		);

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'post_type'     => 'attachment',
			'post_status'   => 'inherit',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		wp_delete_attachment( $p1 );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts1 );
		$this->assertEmpty( $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_delete_meta() {
		// Post 0 already has a color meta value.
		$p1 = self::$posts[1];
		add_post_meta( $p1, 'color', 'black' );

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'meta_query'    => array(
				array(
					'key' => 'color',
				),
			),
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		delete_post_meta( $p1, 'color' );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts1 );
		$this->assertNotEmpty( $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_term() {
		// Post 0 already has the category foo.
		$p1 = self::$posts[1];

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'tax_query'     => array(
				array(
					'taxonomy' => 'category',
					'terms'    => array( 'foo' ),
					'field'    => 'slug',
				),
			),
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		wp_set_post_terms( $p1, array( self::$t1 ), 'category' );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_delete_term() {
		// Post 0 already has the category foo.
		$p1 = self::$posts[1];
		register_taxonomy( 'wptests_tax1', 'post' );

		$t1 = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax1' ) );

		wp_set_object_terms( $p1, array( $t1 ), 'wptests_tax1' );

		$args   = array(
			'cache_results' => true,
			'fields'        => 'ids',
			'tax_query'     => array(
				array(
					'taxonomy' => 'wptests_tax1',
					'terms'    => array( $t1 ),
					'field'    => 'term_id',
				),
			),
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		wp_delete_term( $t1, 'wptests_tax1' );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertContains( $p1, $posts1 );
		$this->assertEmpty( $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}
}
