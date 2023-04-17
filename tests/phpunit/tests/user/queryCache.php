<?php
/**
 * Test WP_User Query, in wp-includes/user.php
 *
 * @group user
 *
 * @coversDefaultClass WP_User_Query
 */
class Tests_User_Query_Cache extends WP_UnitTestCase {
	/**
	 * @var int[]
	 */
	protected static $author_ids;

	/**
	 * @var int[]
	 */
	protected static $sub_ids;

	/**
	 * @var int[]
	 */
	protected static $editor_ids;

	/**
	 * @var int[]
	 */
	protected static $contrib_id;

	/**
	 * @var int[]
	 */
	protected static $admin_ids;

	/**
	 * @var int[]
	 */
	protected $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_ids = $factory->user->create_many(
			4,
			array(
				'role' => 'author',
			)
		);

		self::$sub_ids = $factory->user->create_many(
			2,
			array(
				'role' => 'subscriber',
			)
		);

		self::$editor_ids = $factory->user->create_many(
			3,
			array(
				'role' => 'editor',
			)
		);

		self::$contrib_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);

		self::$admin_ids = $factory->user->create_many(
			2,
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_different_count() {
		$args = array(
			'count_total' => true,
		);

		$query1       = new WP_User_Query( $args );
		$users1       = wp_list_pluck( $query1->get_results(), 'ID' );
		$users_total1 = $query1->get_total();

		$queries_before = get_num_queries();

		$args = array(
			'count_total' => false,
		);

		$query2        = new WP_User_Query( $args );
		$users2        = wp_list_pluck( $query2->get_results(), 'ID' );
		$users_total2  = $query2->get_total();
		$queries_after = get_num_queries();

		$this->assertNotSame( $queries_before, $queries_after, 'Assert that the number of queries is not equal' );
		$this->assertNotSame( $users_total1, $users_total2, 'Assert that totals do not match' );
		$this->assertSameSets( $users1, $users2, 'Results of the query are expected to match.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_results() {
		$args = array(
			'cache_results' => true,
		);

		$query1 = new WP_User_Query( $args );
		$users1 = wp_list_pluck( $query1->get_results(), 'ID' );

		$queries_before = get_num_queries();

		$args = array(
			'cache_results' => false,
		);

		$query2        = new WP_User_Query( $args );
		$users2        = wp_list_pluck( $query2->get_results(), 'ID' );
		$queries_after = get_num_queries();

		$this->assertNotSame( $queries_before, $queries_after, 'Assert that queries are run' );
		$this->assertSameSets( $users1, $users2, 'Results of the query are expected to match.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 * @expectedDeprecated WP_User_Query
	 */
	public function test_query_cache_who() {
		$args = array(
			'who'    => 'authors',
			'fields' => array( 'ID' ),
		);

		$query1       = new WP_User_Query( $args );
		$users1       = $query1->get_results();
		$users_total1 = $query1->get_total();

		$queries_before = get_num_queries();
		$query2         = new WP_User_Query( $args );
		$users2         = $query2->get_results();
		$users_total2   = $query2->get_total();
		$queries_after  = get_num_queries();

		$this->assertSame( $queries_before, $queries_after, 'No queries are expected run.' );
		$this->assertSame( $users_total1, $users_total2, 'Number of users returned us expected to match.' );
		$this->assertSameSets( $users1, $users2, 'Results of the query are expected to match.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 * @dataProvider data_query_cache
	 * @param array $args Optional. See WP_User_Query::prepare_query()
	 */
	public function test_query_cache( array $args ) {
		$query1       = new WP_User_Query( $args );
		$users1       = $query1->get_results();
		$users_total1 = $query1->get_total();

		$queries_before = get_num_queries();
		$query2         = new WP_User_Query( $args );
		$users2         = $query2->get_results();
		$users_total2   = $query2->get_total();
		$queries_after  = get_num_queries();

		$this->assertSame( 0, $queries_after - $queries_before, 'Assert that no queries are run' );
		$this->assertSame( $users_total1, $users_total2, 'Assert that totals do match' );
		$this->assertSameSets( $users1, $users2, 'Asset that results of query match' );
	}

	/**
	 * Data provider
	 *
	 * @return array
	 */
	public function data_query_cache() {
		$data = array(
			'id'                    => array(
				'args' => array( 'fields' => array( 'id' ) ),

			),
			'ID'                    => array(
				'args' => array( 'fields' => array( 'ID' ) ),
			),
			'user_login'            => array(
				'args' => array( 'fields' => array( 'user_login' ) ),
			),
			'user_nicename'         => array(
				'args' => array( 'fields' => array( 'user_nicename' ) ),
			),
			'user_email'            => array(
				'args' => array( 'fields' => array( 'user_email' ) ),
			),
			'user_url'              => array(
				'args' => array( 'fields' => array( 'user_url' ) ),
			),
			'user_status'           => array(
				'args' => array( 'fields' => array( 'user_status' ) ),
			),
			'display_name'          => array(
				'args' => array( 'fields' => array( 'display_name' ) ),
			),
			'invalid_field'         => array(
				'args' => array( 'fields' => array( 'invalid_field' ) ),
			),
			'valid array inc id'    => array(
				'args' => array( 'fields' => array( 'display_name', 'user_email', 'id' ) ),
			),
			'valid array inc ID'    => array(
				'args' => array( 'fields' => array( 'display_name', 'user_email', 'ID' ) ),
			),
			'partly valid array'    => array(
				'args' => array( 'fields' => array( 'display_name', 'invalid_field' ) ),
			),
			'orderby'               => array(
				'args' => array(
					'fields'  => array( 'ID' ),
					'orderby' => array( 'login', 'nicename' ),
				),
			),
			'meta query'            => array(
				'args' => array(
					'fields'     => array( 'ID' ),
					'meta_query' => array(
						'foo_key' => array(
							'key'     => 'foo',
							'compare' => 'EXISTS',
						),
					),
					'orderby'    => 'foo_key',
					'order'      => 'DESC',
				),
			),
			'meta query LIKE'       => array(
				'args' => array(
					'fields'     => array( 'ID' ),
					'meta_query' => array(
						array(
							'key'     => 'foo',
							'value'   => '00',
							'compare' => 'LIKE',
						),
					),
					'orderby'    => 'foo_key',
					'order'      => 'DESC',
				),
			),
			'published posts'       => array(
				'args' => array(
					'has_published_posts' => true,
					'fields'              => array( 'ID' ),
				),
			),
			'published posts order' => array(
				'args' => array(
					'orderby' => 'post_count',
					'fields'  => array( 'ID' ),
				),
			),
			'published count_total' => array(
				'args' => array(

					'count_total' => false,
					'fields'      => array( 'ID' ),
				),
			),
			'capability'            => array(
				'args' => array(
					'capability' => 'install_plugins',
					'fields'     => array( 'ID' ),
				),
			),
			'include'               => array(
				'args' => array(
					'includes' => self::$author_ids,
					'fields'   => array( 'ID' ),
				),
			),
			'exclude'               => array(
				'args' => array(
					'exclude' => self::$author_ids,
					'fields'  => array( 'ID' ),
				),
			),
			'search'                => array(
				'args' => array(
					'search' => 'User',
					'fields' => array( 'ID' ),
				),
			),
		);

		if ( is_multisite() ) {
			$data['spam']    = array(
				'args' => array( 'fields' => array( 'spam' ) ),
			);
			$data['deleted'] = array(
				'args' => array( 'fields' => array( 'deleted' ) ),
			);
		}

		return $data;
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_remove_user_role() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$q1 = new WP_User_Query(
			array(
				'role' => 'author',
			)
		);

		$found = wp_list_pluck( $q1->get_results(), 'ID' );

		$this->assertContains( $user_id, $found, 'Expected to find author in returned values.' );

		$user = get_user_by( 'id', $user_id );
		$user->remove_role( 'author' );

		$q2 = new WP_User_Query(
			array(
				'role' => 'author',
			)
		);

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertNotContains( $user_id, $found, 'Expected not to find author in returned values.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_set_user_role() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$q1 = new WP_User_Query(
			array(
				'role' => 'author',
			)
		);

		$found = wp_list_pluck( $q1->get_results(), 'ID' );

		$this->assertContains( $user_id, $found, 'Expected to find author in returned values.' );

		$user = get_user_by( 'id', $user_id );
		$user->set_role( 'editor' );

		$q2 = new WP_User_Query(
			array(
				'role' => 'author',
			)
		);

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertNotContains( $user_id, $found, 'Expected not to find author in returned values.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_delete_user() {
		$user_id = self::factory()->user->create();

		$q1 = new WP_User_Query(
			array(
				'include' => array( $user_id ),
			)
		);

		$found    = wp_list_pluck( $q1->get_results(), 'ID' );
		$expected = array( $user_id );

		$this->assertSameSets( $expected, $found, 'Find author in returned values' );

		wp_delete_user( $user_id );

		$q2 = new WP_User_Query(
			array(
				'include' => array( $user_id ),
			)
		);

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertNotContains( $user_id, $found, 'Expected not to find author in returned values.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_do_not_cache() {
		$user_id = self::factory()->user->create();

		$args = array(
			'fields'  => array(
				'user_login',
				'user_nicename',
				'user_email',
				'user_url',
				'user_status',
				'display_name',
			),
			'include' => array( $user_id ),
		);

		$q1       = new WP_User_Query( $args );
		$found1   = $q1->get_results();
		$callback = static function( $user ) {
			return (array) $user;
		};

		$found1 = array_map( $callback, $found1 );

		$queries_before = get_num_queries();
		$q2             = new WP_User_Query( $args );
		$found2         = $q2->get_results();
		$found2         = array_map( $callback, $found2 );
		$queries_after  = get_num_queries();

		$this->assertSame( $queries_after - $queries_before, 2, 'Ensure that query is not cached' );
		$this->assertSameSets( $found1, $found2, 'Expected results to match.', 'Ensure that to results match' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_update_user() {
		$user_id = end( self::$admin_ids );

		wp_update_user(
			array(
				'ID'            => $user_id,
				'user_nicename' => 'paul',
			)
		);

		$args = array(
			'nicename__in' => array( 'paul' ),
		);

		$q1 = new WP_User_Query( $args );

		$found    = wp_list_pluck( $q1->get_results(), 'ID' );
		$expected = array( $user_id );

		$this->assertSameSets( $expected, $found, 'Find author in returned values' );

		wp_update_user(
			array(
				'ID'            => $user_id,
				'user_nicename' => 'linda',
			)
		);

		$q2 = new WP_User_Query( $args );

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertNotContains( $user_id, $found, 'Expected not to find author in returned values.' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_query_cache_create_user() {
		$user_id = end( self::$admin_ids );

		$args = array( 'blog_id' => get_current_blog_id() );

		$q1 = new WP_User_Query( $args );

		$found = wp_list_pluck( $q1->get_results(), 'ID' );

		$this->assertContains( $user_id, $found, 'Expected to find author in returned values.' );

		$user_id_2 = self::factory()->user->create();

		$q2 = new WP_User_Query( $args );

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertContains( $user_id_2, $found, 'Find author in returned values' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_has_published_posts_delete_post() {
		register_post_type( 'wptests_pt_public', array( 'public' => true ) );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[2],
				'post_status' => 'publish',
				'post_type'   => 'wptests_pt_public',
			)
		);

		$q1 = new WP_User_Query(
			array(
				'has_published_posts' => true,
			)
		);

		$found    = wp_list_pluck( $q1->get_results(), 'ID' );
		$expected = array( self::$author_ids[2] );

		$this->assertSameSets( $expected, $found, 'Find author in returned values' );

		wp_delete_post( $post_id, true );

		$q2 = new WP_User_Query(
			array(
				'has_published_posts' => true,
			)
		);

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertSameSets( array(), $found, 'Not to find author in returned values' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_has_published_posts_delete_post_order() {
		register_post_type( 'wptests_pt_public', array( 'public' => true ) );

		$user_id = self::factory()->user->create();

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_type'   => 'wptests_pt_public',
			)
		);

		$q1 = new WP_User_Query(
			array(
				'orderby' => 'post_count',
			)
		);

		$found1 = wp_list_pluck( $q1->get_results(), 'ID' );
		$this->assertContains( $user_id, $found1, 'Find author in returned values in first run of WP_User_Query' );

		wp_delete_post( $post_id, true );

		$q2 = new WP_User_Query(
			array(
				'orderby' => 'post_count',
			)
		);

		$found2 = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertContains( $user_id, $found1, 'Find author in returned values in second run of WP_User_Query' );
		$this->assertSameSets( $found1, $found2, 'Not same order' );
	}

	/**
	 * @ticket 40613
	 * @covers ::query
	 */
	public function test_meta_query_cache_invalidation() {
		add_user_meta( self::$author_ids[0], 'foo', 'bar' );
		add_user_meta( self::$author_ids[1], 'foo', 'bar' );

		$q1 = new WP_User_Query(
			array(
				'meta_query' => array(
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
				),
			)
		);

		$found    = wp_list_pluck( $q1->get_results(), 'ID' );
		$expected = array( self::$author_ids[0], self::$author_ids[1] );

		$this->assertSameSets( $expected, $found, 'Asset that results contain authors' );

		delete_user_meta( self::$author_ids[1], 'foo' );

		$q2 = new WP_User_Query(
			array(
				'meta_query' => array(
					array(
						'key'   => 'foo',
						'value' => 'bar',
					),
				),
			)
		);

		$found    = wp_list_pluck( $q2->get_results(), 'ID' );
		$expected = array( self::$author_ids[0] );

		$this->assertSameSets( $expected, $found, 'Asset that results do not contain author without meta' );
	}

	/**
	 * @ticket 40613
	 * @group ms-required
	 * @covers ::query
	 */
	public function test_get_single_capability_multisite_blog_id() {
		$blog_id = self::factory()->blog->create();

		add_user_to_blog( $blog_id, self::$author_ids[0], 'subscriber' );
		add_user_to_blog( $blog_id, self::$author_ids[1], 'author' );
		add_user_to_blog( $blog_id, self::$author_ids[2], 'editor' );

		$q1 = new WP_User_Query(
			array(
				'capability' => 'publish_posts',
				'blog_id'    => $blog_id,
			)
		);

		$found = wp_list_pluck( $q1->get_results(), 'ID' );

		$this->assertNotContains( self::$author_ids[0], $found, 'Asset that results do not contain author 0 without capability on site on first run' );
		$this->assertContains( self::$author_ids[1], $found, 'Asset that results do contain author with capability on site on first run' );
		$this->assertContains( self::$author_ids[2], $found, 'Asset that results do contain author with capability on site on first run' );

		remove_user_from_blog( self::$author_ids[2], $blog_id );

		$q2 = new WP_User_Query(
			array(
				'capability' => 'publish_posts',
				'blog_id'    => $blog_id,
			)
		);

		$found = wp_list_pluck( $q2->get_results(), 'ID' );
		$this->assertNotContains( self::$author_ids[0], $found, 'Asset that results do not contain author 0 without capability on site on second run' );
		$this->assertContains( self::$author_ids[1], $found, 'Asset that results do contain author with capability on site on second run' );
		$this->assertNotContains( self::$author_ids[2], $found, 'Asset that results do not contain author 1 without capability on site on second run' );
	}

	/**
	 * @ticket 40613
	 * @group ms-required
	 * @covers ::query
	 */
	public function test_query_should_respect_blog_id() {
		$blogs = self::factory()->blog->create_many( 2 );

		add_user_to_blog( $blogs[0], self::$author_ids[0], 'author' );
		add_user_to_blog( $blogs[0], self::$author_ids[1], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[0], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[1], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[2], 'author' );

		$q = new WP_User_Query(
			array(
				'fields'  => 'ids',
				'blog_id' => $blogs[0],
			)
		);

		$expected = array( (string) self::$author_ids[0], (string) self::$author_ids[1] );

		$this->assertSameSets( $expected, $q->get_results(), 'Asset that expected users return' );

		$q = new WP_User_Query(
			array(
				'fields'  => 'ids',
				'blog_id' => $blogs[1],
			)
		);

		$expected = array( (string) self::$author_ids[0], (string) self::$author_ids[1], (string) self::$author_ids[2] );

		$this->assertSameSets( $expected, $q->get_results(), 'Asset that expected users return from different blog' );
	}

	/**
	 * @ticket 40613
	 * @group ms-required
	 * @covers ::query
	 */
	public function test_has_published_posts_should_respect_blog_id() {
		$blogs = self::factory()->blog->create_many( 2 );

		add_user_to_blog( $blogs[0], self::$author_ids[0], 'author' );
		add_user_to_blog( $blogs[0], self::$author_ids[1], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[0], 'author' );
		add_user_to_blog( $blogs[1], self::$author_ids[1], 'author' );

		switch_to_blog( $blogs[0] );
		self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[0],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		restore_current_blog();

		switch_to_blog( $blogs[1] );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$author_ids[1],
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
		restore_current_blog();

		$q = new WP_User_Query(
			array(
				'has_published_posts' => array( 'post' ),
				'blog_id'             => $blogs[1],
			)
		);

		$found    = wp_list_pluck( $q->get_results(), 'ID' );
		$expected = array( self::$author_ids[1] );

		$this->assertSameSets( $expected, $found, 'Asset that expected users returned with posts on this site' );
		switch_to_blog( $blogs[1] );
		wp_delete_post( $post_id, true );
		restore_current_blog();

		$q = new WP_User_Query(
			array(
				'has_published_posts' => array( 'post' ),
				'blog_id'             => $blogs[1],
			)
		);

		$found = wp_list_pluck( $q->get_results(), 'ID' );

		$this->assertSameSets( array(), $found, 'Asset that no users returned with posts on this site as posts have been deleted' );
	}

	/**
	 * Ensure cache keys are generated without WPDB placeholders.
	 *
	 * @ticket 40613
	 *
	 * @covers ::generate_cache_key
	 */
	public function test_generate_cache_key_placeholder() {
		global $wpdb;
		$query1 = new WP_User_Query( array( 'capability' => 'edit_posts' ) );

		$query_vars                  = $query1->query_vars;
		$request_with_placeholder    = $query1->request;
		$request_without_placeholder = $wpdb->remove_placeholder_escape( $query1->request );

		$reflection = new ReflectionMethod( $query1, 'generate_cache_key' );
		$reflection->setAccessible( true );

		$cache_key_1 = $reflection->invoke( $query1, $query_vars, $request_with_placeholder );
		$cache_key_2 = $reflection->invoke( $query1, $query_vars, $request_without_placeholder );

		$this->assertSame( $cache_key_1, $cache_key_2, 'Cache key differs when using wpdb placeholder.' );
	}
}
