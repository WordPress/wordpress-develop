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

	public function set_up() {
		parent::set_up();
		// Make some post objects.
		self::$posts = self::factory()->post->create_many( 5 );
		self::$pages = self::factory()->post->create_many( 5, array( 'post_type' => 'page' ) );

		self::$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'Foo',
			)
		);

		wp_set_post_terms( self::$posts[0], self::$t1, 'category' );
		add_post_meta( self::$posts[0], 'color', '#000000' );
	}

	/**
	 * @dataProvider data_query_args
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
	public function data_query_args() {
		return array(
			'cache true'                                  => array(
				'args' => array(
					'post_query_cache' => true,
				),
			),
			'cache true and page'                         => array(
				'args' => array(
					'post_query_cache' => true,
					'post_type'        => 'page',
				),
			),
			'cache true and ids'                          => array(
				'args' => array(
					'post_query_cache' => true,
					'fields'           => 'ids',
				),
			),
			'cache true and id=>parent and no found rows' => array(
				'args' => array(
					'post_query_cache' => true,
					'fields'           => 'id=>parent',
				),
			),
			'cache true and ids and no found rows'        => array(
				'args' => array(
					'no_found_rows'    => true,
					'post_query_cache' => true,
					'fields'           => 'ids',
				),
			),
			'cache true and id=>parent'                   => array(
				'args' => array(
					'no_found_rows'    => true,
					'post_query_cache' => true,
					'fields'           => 'id=>parent',
				),
			),
			'cache and ignore_sticky_posts'               => array(
				'args' => array(
					'post_query_cache'    => true,
					'ignore_sticky_posts' => true,
				),
			),
			'cache meta query'                            => array(
				'args' => array(
					'post_query_cache' => true,
					'meta_query'       => array(
						array(
							'key' => 'color',
						),
					),
				),
			),
			'cache comment_count'                         => array(
				'args' => array(
					'post_query_cache' => true,
					'comment_count'    => 0,
				),
			),
			'cache term query'                            => array(
				'args' => array(
					'post_query_cache' => true,
					'tax_query'        => array(
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
			'post_query_cache' => true,
			'fields'           => 'ids',
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

	public function filter_posts_request( $request ) {
		return $request . ' -- Add comment';
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_post() {
		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		self::factory()->post->create();

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_different_args() {
		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		$args           = array(
			'post_query_cache'       => true,
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
	public function test_query_cache_new_comment() {
		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
			'comment_count'    => 1,
		);
		$query1 = new WP_Query();
		$posts1 = $query1->query( $args );

		self::factory()->comment->create( array( 'comment_post_ID' => self::$posts[0] ) );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_delete_comment() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::$posts[0] ) );
		$args       = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
			'comment_count'    => 1,
		);
		$query1     = new WP_Query();
		$posts1     = $query1->query( $args );

		wp_delete_comment( $comment_id, true );

		$query2 = new WP_Query();
		$posts2 = $query2->query( $args );

		$this->assertNotSame( $posts1, $posts2 );
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_update_post() {
		$p1 = self::factory()->post->create();

		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
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
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_meta() {
		$p1 = self::factory()->post->create();

		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
			'meta_query'       => array(
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
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_new_term() {
		$p1 = self::factory()->post->create();

		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
			'tax_query'        => array(
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
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}

	/**
	 * @ticket 22176
	 */
	public function test_query_cache_delete_term() {
		$p1 = self::factory()->post->create();
		register_taxonomy( 'wptests_tax1', 'post' );

		$t1 = self::factory()->term->create( array( 'taxonomy' => 'wptests_tax1' ) );

		wp_set_object_terms( $p1, array( $t1 ), 'wptests_tax1' );

		$args   = array(
			'post_query_cache' => true,
			'fields'           => 'ids',
			'tax_query'        => array(
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
		$this->assertNotSame( $query1->found_posts, $query2->found_posts );
	}
}
