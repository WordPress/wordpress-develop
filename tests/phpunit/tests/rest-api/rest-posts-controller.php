<?php
/**
 * Unit tests covering WP_REST_Posts_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Posts_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {
	protected static $post_id;

	protected static $superadmin_id;
	protected static $editor_id;
	protected static $author_id;
	protected static $contributor_id;
	protected static $private_reader_id;

	protected static $supported_formats;
	protected static $post_ids    = array();
	protected static $total_posts = 30;
	protected static $per_page    = 50;

	protected $forbidden_cat;
	protected $posts_clauses;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = $factory->post->create();

		self::$superadmin_id  = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		self::$editor_id      = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$author_id      = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
		self::$contributor_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);

		self::$private_reader_id = $factory->user->create(
			array(
				'role' => 'private_reader',
			)
		);

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}

		// Only support 'post' and 'gallery'.
		self::$supported_formats = get_theme_support( 'post-formats' );
		add_theme_support( 'post-formats', array( 'post', 'gallery' ) );

		// Set up posts for pagination tests.
		for ( $i = 0; $i < self::$total_posts - 1; $i++ ) {
			self::$post_ids[] = $factory->post->create(
				array(
					'post_title' => "Post {$i}",
				)
			);
		}
	}

	public static function wpTearDownAfterClass() {
		// Restore theme support for formats.
		if ( self::$supported_formats ) {
			add_theme_support( 'post-formats', self::$supported_formats );
		} else {
			remove_theme_support( 'post-formats' );
		}

		// Remove posts for pagination tests.
		foreach ( self::$post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		wp_delete_post( self::$post_id, true );

		self::delete_user( self::$superadmin_id );
		self::delete_user( self::$editor_id );
		self::delete_user( self::$author_id );
		self::delete_user( self::$contributor_id );
		self::delete_user( self::$private_reader_id );
	}

	public function setUp() {
		parent::setUp();
		register_post_type(
			'youseeme',
			array(
				'supports'     => array(),
				'show_in_rest' => true,
			)
		);

		add_role( 'private_reader', 'Private Reader' );
		$role = get_role( 'private_reader' );
		$role->add_cap( 'read_private_posts' );

		add_filter( 'rest_pre_dispatch', array( $this, 'wpSetUpBeforeRequest' ), 10, 3 );
		add_filter( 'posts_clauses', array( $this, 'save_posts_clauses' ), 10, 2 );
	}

	public function wpSetUpBeforeRequest( $result, $server, $request ) {
		$this->posts_clauses = array();
		return $result;
	}

	public function save_posts_clauses( $orderby, $query ) {
		array_push( $this->posts_clauses, $orderby );
		return $orderby;
	}

	public function assertPostsClause( $clause, $pattern ) {
		global $wpdb;
		$expected_clause = str_replace( '{posts}', $wpdb->posts, $pattern );
		$this->assertCount( 1, $this->posts_clauses );
		$this->assertSame( $expected_clause, $wpdb->remove_placeholder_escape( $this->posts_clauses[0][ $clause ] ) );
	}

	public function assertPostsOrderedBy( $pattern ) {
		$this->assertPostsClause( 'orderby', $pattern );
	}

	public function assertPostsWhere( $pattern ) {
		$this->assertPostsClause( 'where', $pattern );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts', $routes );
		$this->assertCount( 2, $routes['/wp/v2/posts'] );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/posts/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame(
			array(
				'after',
				'author',
				'author_exclude',
				'before',
				'categories',
				'categories_exclude',
				'context',
				'exclude',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'per_page',
				'search',
				'slug',
				'status',
				'sticky',
				'tags',
				'tags_exclude',
				'tax_relation',
			),
			$keys
		);
	}

	public function test_registered_get_item_params() {
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame( array( 'context', 'id', 'password' ), $keys );
	}

	/**
	 * @ticket 43701
	 */
	public function test_allow_header_sent_on_options_request() {
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$headers  = $response->get_headers();

		$this->assertNotEmpty( $headers['Allow'] );
		$this->assertSame( $headers['Allow'], 'GET' );

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$headers  = $response->get_headers();

		$this->assertNotEmpty( $headers['Allow'] );
		$this->assertSame( $headers['Allow'], 'GET, POST, PUT, PATCH, DELETE' );
	}

	public function test_get_items() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_posts_response( $response );
	}

	/**
	 * A valid query that returns 0 results should return an empty JSON list.
	 *
	 * @issue 862
	 */
	public function test_get_items_empty_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params(
			array(
				'author' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEmpty( $response->get_data() );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_items_author_query() {
		$this->factory->post->create( array( 'post_author' => self::$editor_id ) );
		$this->factory->post->create( array( 'post_author' => self::$author_id ) );

		$total_posts = self::$total_posts + 2;

		// All posts in the database.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $total_posts, count( $response->get_data() ) );

		// Limit to editor and author.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'author', array( self::$editor_id, self::$author_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$this->assertSameSets( array( self::$editor_id, self::$author_id ), wp_list_pluck( $data, 'author' ) );

		// Limit to editor.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'author', self::$editor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 1, count( $data ) );
		$this->assertSame( self::$editor_id, $data[0]['author'] );
	}

	public function test_get_items_author_exclude_query() {
		$this->factory->post->create( array( 'post_author' => self::$editor_id ) );
		$this->factory->post->create( array( 'post_author' => self::$author_id ) );

		$total_posts = self::$total_posts + 2;

		// All posts in the database.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $total_posts, count( $response->get_data() ) );

		// Exclude editor and author.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'author_exclude', array( self::$editor_id, self::$author_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $total_posts - 2, count( $data ) );
		$this->assertNotEquals( self::$editor_id, $data[0]['author'] );
		$this->assertNotEquals( self::$author_id, $data[0]['author'] );

		// Exclude editor.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'author_exclude', self::$editor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $total_posts - 1, count( $data ) );
		$this->assertNotEquals( self::$editor_id, $data[0]['author'] );
		$this->assertNotEquals( self::$editor_id, $data[1]['author'] );

		// Invalid 'author_exclude' should error.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'author_exclude', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_include_query() {
		$id1 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2001-02-03 04:05:06',
			)
		);
		$id2 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2001-02-03 04:05:07',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );

		// Order defaults to date descending.
		$request->set_param( 'include', array( $id1, $id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$this->assertSame( $id2, $data[0]['id'] );
		$this->assertPostsOrderedBy( '{posts}.post_date DESC' );

		// 'orderby' => 'include'.
		$request->set_param( 'orderby', 'include' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$this->assertSame( $id1, $data[0]['id'] );
		$this->assertPostsOrderedBy( "FIELD({posts}.ID,$id1,$id2)" );

		// Invalid 'include' should error.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'include', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_orderby_author_query() {
		$id2 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => self::$editor_id,
			)
		);
		$id3 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => self::$editor_id,
			)
		);
		$id1 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => self::$author_id,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'include', array( $id1, $id2, $id3 ) );
		$request->set_param( 'orderby', 'author' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::$author_id, $data[0]['author'] );
		$this->assertSame( self::$editor_id, $data[1]['author'] );
		$this->assertSame( self::$editor_id, $data[2]['author'] );

		$this->assertPostsOrderedBy( '{posts}.post_author DESC' );
	}

	public function test_get_items_orderby_modified_query() {
		$id1 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$this->update_post_modified( $id1, '2016-04-20 4:26:20' );
		$this->update_post_modified( $id2, '2016-02-01 20:24:02' );
		$this->update_post_modified( $id3, '2016-02-21 12:24:02' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'include', array( $id1, $id2, $id3 ) );
		$request->set_param( 'orderby', 'modified' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $id1, $data[0]['id'] );
		$this->assertSame( $id3, $data[1]['id'] );
		$this->assertSame( $id2, $data[2]['id'] );

		$this->assertPostsOrderedBy( '{posts}.post_modified DESC' );
	}

	public function test_get_items_orderby_parent_query() {
		$id1 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id2 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id3 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_parent' => $id1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'include', array( $id1, $id2, $id3 ) );
		$request->set_param( 'orderby', 'parent' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $id3, $data[0]['id'] );
		// Check ordering. Default ORDER is DESC.
		$this->assertSame( $id1, $data[0]['parent'] );
		$this->assertSame( 0, $data[1]['parent'] );
		$this->assertSame( 0, $data[2]['parent'] );

		$this->assertPostsOrderedBy( '{posts}.post_parent DESC' );
	}

	public function test_get_items_exclude_query() {
		$id1 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertTrue( in_array( $id1, $ids, true ) );
		$this->assertTrue( in_array( $id2, $ids, true ) );

		$request->set_param( 'exclude', array( $id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertTrue( in_array( $id1, $ids, true ) );
		$this->assertFalse( in_array( $id2, $ids, true ) );

		$request->set_param( 'exclude', "$id2" );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertTrue( in_array( $id1, $ids, true ) );
		$this->assertFalse( in_array( $id2, $ids, true ) );

		$request->set_param( 'exclude', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_search_query() {
		$this->factory->post->create(
			array(
				'post_title'  => 'Search Result',
				'post_status' => 'publish',
			)
		);
		$total_posts = self::$total_posts + 1;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( $total_posts, count( $response->get_data() ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'search', 'Search Result' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 1, count( $data ) );
		$this->assertSame( 'Search Result', $data[0]['title']['rendered'] );
	}

	public function test_get_items_slug_query() {
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Banana',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'slug', 'apple' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 1, count( $data ) );
		$this->assertSame( 'Apple', $data[0]['title']['rendered'] );
	}

	public function test_get_items_multiple_slugs_array_query() {
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Banana',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Peach',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'slug', array( 'banana', 'peach' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$titles = array(
			$data[0]['title']['rendered'],
			$data[1]['title']['rendered'],
		);
		sort( $titles );
		$this->assertSame( array( 'Banana', 'Peach' ), $titles );
	}

	public function test_get_items_multiple_slugs_string_query() {
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Banana',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Peach',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'slug', 'apple,banana' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$titles = array(
			$data[0]['title']['rendered'],
			$data[1]['title']['rendered'],
		);
		sort( $titles );
		$this->assertSame( array( 'Apple', 'Banana' ), $titles );
	}

	public function test_get_items_status_query() {
		wp_set_current_user( 0 );

		$this->factory->post->create( array( 'post_status' => 'draft' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'status', 'publish' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::$total_posts, count( $response->get_data() ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'draft' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'draft' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, count( $response->get_data() ) );
	}

	public function test_get_items_multiple_statuses_string_query() {
		wp_set_current_user( self::$editor_id );

		$this->factory->post->create( array( 'post_status' => 'draft' ) );
		$this->factory->post->create( array( 'post_status' => 'private' ) );
		$this->factory->post->create( array( 'post_status' => 'publish' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'status', 'draft,private' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$statuses = array(
			$data[0]['status'],
			$data[1]['status'],
		);
		sort( $statuses );
		$this->assertSame( array( 'draft', 'private' ), $statuses );
	}

	public function test_get_items_multiple_statuses_array_query() {
		wp_set_current_user( self::$editor_id );

		$this->factory->post->create( array( 'post_status' => 'draft' ) );
		$this->factory->post->create( array( 'post_status' => 'pending' ) );
		$this->factory->post->create( array( 'post_status' => 'publish' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'status', array( 'draft', 'pending' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 2, count( $data ) );
		$statuses = array(
			$data[0]['status'],
			$data[1]['status'],
		);
		sort( $statuses );
		$this->assertSame( array( 'draft', 'pending' ), $statuses );
	}

	public function test_get_items_multiple_statuses_one_invalid_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'status', array( 'draft', 'nonsense' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 43701
	 */
	public function test_get_items_multiple_statuses_custom_role_one_invalid_query() {
		$private_post_id = $this->factory->post->create( array( 'post_status' => 'private' ) );

		wp_set_current_user( self::$private_reader_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', array( 'private', 'future' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_invalid_status_query() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_status_without_permissions() {
		$draft_id = $this->factory->post->create(
			array(
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$all_data = $response->get_data();
		foreach ( $all_data as $post ) {
			$this->assertNotEquals( $draft_id, $post['id'] );
		}
	}

	public function test_get_items_order_and_orderby() {
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple Pie',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple Sauce',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple Cobbler',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'  => 'Apple Coffee Cake',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'search', 'Apple' );

		// Order defaults to 'desc'.
		$request->set_param( 'orderby', 'title' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'Apple Sauce', $data[0]['title']['rendered'] );
		$this->assertPostsOrderedBy( '{posts}.post_title DESC' );

		// 'order' => 'asc'.
		$request->set_param( 'order', 'asc' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'Apple Cobbler', $data[0]['title']['rendered'] );
		$this->assertPostsOrderedBy( '{posts}.post_title ASC' );

		// 'order' => 'asc,id' should error.
		$request->set_param( 'order', 'asc,id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// 'orderby' => 'content' should error (invalid param test).
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'orderby', 'content' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_with_orderby_include_without_include_param() {
		$this->factory->post->create( array( 'post_status' => 'publish' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'include' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_orderby_include_missing_include', $response, 400 );
	}

	public function test_get_items_with_orderby_id() {
		$id1 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2016-01-13 02:26:48',
			)
		);
		$id2 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2016-01-12 02:26:48',
			)
		);
		$id3 = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => '2016-01-11 02:26:48',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'id' );
		$request->set_param( 'include', array( $id1, $id2, $id3 ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Default ORDER is DESC.
		$this->assertSame( $id3, $data[0]['id'] );
		$this->assertSame( $id2, $data[1]['id'] );
		$this->assertSame( $id1, $data[2]['id'] );
		$this->assertPostsOrderedBy( '{posts}.ID DESC' );
	}

	public function test_get_items_with_orderby_slug() {
		$id1 = $this->factory->post->create(
			array(
				'post_title'  => 'ABC',
				'post_name'   => 'xyz',
				'post_status' => 'publish',
			)
		);
		$id2 = $this->factory->post->create(
			array(
				'post_title'  => 'XYZ',
				'post_name'   => 'abc',
				'post_status' => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'slug' );
		$request->set_param( 'include', array( $id1, $id2 ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Default ORDER is DESC.
		$this->assertSame( 'xyz', $data[0]['slug'] );
		$this->assertSame( 'abc', $data[1]['slug'] );
		$this->assertPostsOrderedBy( '{posts}.post_name DESC' );
	}

	public function test_get_items_with_orderby_slugs() {
		$slugs = array( 'burrito', 'taco', 'chalupa' );
		foreach ( $slugs as $slug ) {
			$this->factory->post->create(
				array(
					'post_title'  => $slug,
					'post_name'   => $slug,
					'post_status' => 'publish',
				)
			);
		}

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'include_slugs' );
		$request->set_param( 'slug', array( 'taco', 'chalupa', 'burrito' ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 'taco', $data[0]['slug'] );
		$this->assertSame( 'chalupa', $data[1]['slug'] );
		$this->assertSame( 'burrito', $data[2]['slug'] );
	}

	public function test_get_items_with_orderby_relevance() {
		$id1 = $this->factory->post->create(
			array(
				'post_title'   => 'Title is more relevant',
				'post_content' => 'Content is',
				'post_status'  => 'publish',
			)
		);
		$id2 = $this->factory->post->create(
			array(
				'post_title'   => 'Title is',
				'post_content' => 'Content is less relevant',
				'post_status'  => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'relevance' );
		$request->set_param( 'search', 'relevant' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id1, $data[0]['id'] );
		$this->assertSame( $id2, $data[1]['id'] );
		$this->assertPostsOrderedBy( '{posts}.post_title LIKE \'%relevant%\' DESC, {posts}.post_date DESC' );
	}

	public function test_get_items_with_orderby_relevance_two_terms() {
		$id1 = $this->factory->post->create(
			array(
				'post_title'   => 'Title is more relevant',
				'post_content' => 'Content is',
				'post_status'  => 'publish',
			)
		);
		$id2 = $this->factory->post->create(
			array(
				'post_title'   => 'Title is',
				'post_content' => 'Content is less relevant',
				'post_status'  => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'relevance' );
		$request->set_param( 'search', 'relevant content' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id1, $data[0]['id'] );
		$this->assertSame( $id2, $data[1]['id'] );
		$this->assertPostsOrderedBy( '(CASE WHEN {posts}.post_title LIKE \'%relevant content%\' THEN 1 WHEN {posts}.post_title LIKE \'%relevant%\' AND {posts}.post_title LIKE \'%content%\' THEN 2 WHEN {posts}.post_title LIKE \'%relevant%\' OR {posts}.post_title LIKE \'%content%\' THEN 3 WHEN {posts}.post_excerpt LIKE \'%relevant content%\' THEN 4 WHEN {posts}.post_content LIKE \'%relevant content%\' THEN 5 ELSE 6 END), {posts}.post_date DESC' );
	}

	public function test_get_items_with_orderby_relevance_missing_search() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'orderby', 'relevance' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_search_term_defined', $response, 400 );
	}

	public function test_get_items_offset_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'offset', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( self::$total_posts - 1, $response->get_data() );

		// 'offset' works with 'per_page'.
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// 'offset' takes priority over 'page'.
		$request->set_param( 'page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// Invalid 'offset' should error.
		$request->set_param( 'offset', 'moreplease' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_tags_query() {
		$id1 = self::$post_id;
		$tag = wp_insert_term( 'My Tag', 'post_tag' );

		wp_set_object_terms( $id1, array( $tag['term_id'] ), 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'tags', array( $tag['term_id'] ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id1, $data[0]['id'] );
	}

	public function test_get_items_tags_exclude_query() {
		$id1 = self::$post_id;
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id4 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$tag = wp_insert_term( 'My Tag', 'post_tag' );

		$total_posts = self::$total_posts + 3;

		wp_set_object_terms( $id1, array( $tag['term_id'] ), 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'tags_exclude', array( $tag['term_id'] ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( $total_posts - 1, $data );
		$this->assertSame( $id4, $data[0]['id'] );
		$this->assertSame( $id3, $data[1]['id'] );
		$this->assertSame( $id2, $data[2]['id'] );
	}

	public function test_get_items_tags_and_categories_query() {
		$id1      = self::$post_id;
		$id2      = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$tag      = wp_insert_term( 'My Tag', 'post_tag' );
		$category = wp_insert_term( 'My Category', 'category' );

		wp_set_object_terms( $id1, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id2, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id1, array( $category['term_id'] ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'tags', array( $tag['term_id'] ) );
		$request->set_param( 'categories', array( $category['term_id'] ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 1, $response->get_data() );

		$request->set_param( 'tags', array( 'my-tag' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 44326
	 */
	public function test_get_items_tags_or_categories_query() {
		$id1      = self::$post_id;
		$id2      = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$tag      = wp_insert_term( 'My Tag', 'post_tag' );
		$category = wp_insert_term( 'My Category', 'category' );

		wp_set_object_terms( $id1, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id2, array( $category['term_id'] ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'tax_relation', 'OR' );
		$request->set_param( 'tags', array( $tag['term_id'] ) );
		$request->set_param( 'categories', array( $category['term_id'] ) );
		$request->set_param( 'orderby', 'id' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id2, $data[0]['id'] );
		$this->assertSame( $id1, $data[1]['id'] );
	}

	public function test_get_items_tags_and_categories_exclude_query() {
		$id1      = self::$post_id;
		$id2      = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$tag      = wp_insert_term( 'My Tag', 'post_tag' );
		$category = wp_insert_term( 'My Category', 'category' );

		wp_set_object_terms( $id1, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id2, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id1, array( $category['term_id'] ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'tags', array( $tag['term_id'] ) );
		$request->set_param( 'categories_exclude', array( $category['term_id'] ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );

		$request->set_param( 'tags_exclude', array( 'my-tag' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 44326
	 */
	public function test_get_items_tags_or_categories_exclude_query() {
		$id1      = end( self::$post_ids );
		$id2      = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3      = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id4      = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$tag      = wp_insert_term( 'My Tag', 'post_tag' );
		$category = wp_insert_term( 'My Category', 'category' );

		$total_posts = self::$total_posts + 3;

		wp_set_object_terms( $id1, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id2, array( $tag['term_id'] ), 'post_tag' );
		wp_set_object_terms( $id2, array( $category['term_id'] ), 'category' );
		wp_set_object_terms( $id3, array( $category['term_id'] ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'tags', array( $tag['term_id'] ) );
		$request->set_param( 'categories_exclude', array( $category['term_id'] ) );
		$request->set_param( 'tax_relation', 'OR' );
		$request->set_param( 'orderby', 'id' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( $total_posts - 1, $data );
		$this->assertSame( $id4, $data[0]['id'] );
		$this->assertSame( $id2, $data[1]['id'] );
		$this->assertSame( $id1, $data[2]['id'] );
	}

	/**
	 * @ticket 44326
	 */
	public function test_get_items_relation_with_no_tax_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'tax_relation', 'OR' );
		$request->set_param( 'include', self::$post_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( self::$post_id, $response->get_data()[0]['id'] );
	}

	public function test_get_items_sticky() {
		$id1 = self::$post_id;
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		update_option( 'sticky_posts', array( $id2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'sticky', true );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 1, $response->get_data() );

		$posts = $response->get_data();
		$post  = $posts[0];
		$this->assertSame( $id2, $post['id'] );

		$request->set_param( 'sticky', 'nothanks' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_sticky_with_include() {
		$id1 = self::$post_id;
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		update_option( 'sticky_posts', array( $id2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'sticky', true );
		$request->set_param( 'include', array( $id1 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );

		// FIXME Since this request returns zero posts, the query is executed twice.
		$this->assertCount( 2, $this->posts_clauses );
		$this->posts_clauses = array_slice( $this->posts_clauses, 0, 1 );

		$this->assertPostsWhere( " AND {posts}.ID IN (0) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );

		update_option( 'sticky_posts', array( $id1, $id2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'sticky', true );
		$request->set_param( 'include', array( $id1 ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertCount( 1, $response->get_data() );

		$posts = $response->get_data();
		$post  = $posts[0];
		$this->assertSame( $id1, $post['id'] );

		$this->assertPostsWhere( " AND {posts}.ID IN ($id1) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );
	}

	public function test_get_items_sticky_no_sticky_posts() {
		update_option( 'sticky_posts', array() );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'sticky', true );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );

		// FIXME Since this request returns zero posts, the query is executed twice.
		$this->assertCount( 2, $this->posts_clauses );
		$this->posts_clauses = array_slice( $this->posts_clauses, 0, 1 );

		$this->assertPostsWhere( " AND {posts}.ID IN (0) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );
	}

	public function test_get_items_sticky_with_include_no_sticky_posts() {
		$id1 = self::$post_id;

		update_option( 'sticky_posts', array() );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'sticky', true );
		$request->set_param( 'include', array( $id1 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );

		// FIXME Since this request returns zero posts, the query is executed twice.
		$this->assertCount( 2, $this->posts_clauses );
		$this->posts_clauses = array_slice( $this->posts_clauses, 0, 1 );

		$this->assertPostsWhere( " AND {posts}.ID IN (0) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );
	}

	public function test_get_items_not_sticky() {
		$id1 = end( self::$post_ids );
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$total_posts = self::$total_posts + 1;

		update_option( 'sticky_posts', array( $id2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'sticky', false );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_posts - 1, $response->get_data() );

		$posts = $response->get_data();
		$post  = $posts[0];
		$this->assertSame( $id1, $post['id'] );

		$this->assertPostsWhere( " AND {posts}.ID NOT IN ($id2) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );
	}

	public function test_get_items_not_sticky_with_exclude() {
		$id1 = end( self::$post_ids );
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$total_posts = self::$total_posts + 2;

		update_option( 'sticky_posts', array( $id2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'sticky', false );
		$request->set_param( 'exclude', array( $id3 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_posts - 2, $response->get_data() );

		$posts = $response->get_data();
		$ids   = wp_list_pluck( $posts, 'id' );
		$this->assertTrue( in_array( $id1, $ids, true ) );
		$this->assertFalse( in_array( $id2, $ids, true ) );
		$this->assertFalse( in_array( $id3, $ids, true ) );

		$this->assertPostsWhere( " AND {posts}.ID NOT IN ($id3,$id2) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );
	}

	public function test_get_items_not_sticky_with_exclude_no_sticky_posts() {
		$id1 = self::$post_id;
		$id2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$total_posts = self::$total_posts + 2;

		update_option( 'sticky_posts', array() );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'sticky', false );
		$request->set_param( 'exclude', array( $id3 ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $total_posts - 1, $response->get_data() );

		$posts = $response->get_data();
		$ids   = wp_list_pluck( $posts, 'id' );
		$this->assertTrue( in_array( $id1, $ids, true ) );
		$this->assertTrue( in_array( $id2, $ids, true ) );
		$this->assertFalse( in_array( $id3, $ids, true ) );

		$this->assertPostsWhere( " AND {posts}.ID NOT IN ($id3) AND {posts}.post_type = 'post' AND (({posts}.post_status = 'publish'))" );
	}

	public function test_get_items_pagination_headers() {
		$total_posts = self::$total_posts;
		$total_pages = (int) ceil( $total_posts / 10 );

		// Start of the index.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_posts, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( '/wp/v2/posts' )
		);
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// 3rd page.
		$this->factory->post->create();
		$total_posts++;
		$total_pages++;
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_posts, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( '/wp/v2/posts' )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'page' => 4,
			),
			rest_url( '/wp/v2/posts' )
		);
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// Last page.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', $total_pages );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_posts, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages - 1,
			),
			rest_url( '/wp/v2/posts' )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertFalse( stripos( $headers['Link'], 'rel="next"' ) );

		// Out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertErrorResponse( 'rest_post_invalid_page_number', $response, 400 );

		// With query params.
		$total_pages = (int) ceil( $total_posts / 5 );
		$request     = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params(
			array(
				'per_page' => 5,
				'page'     => 2,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_posts, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'per_page' => 5,
				'page'     => 1,
			),
			rest_url( '/wp/v2/posts' )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'per_page' => 5,
				'page'     => 3,
			),
			rest_url( '/wp/v2/posts' )
		);
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
	}

	public function test_get_items_status_draft_permissions() {
		$draft_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		// Drafts status query var inaccessible to unauthorized users.
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'draft' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// Users with 'read_private_posts' cap shouldn't also be able to view drafts.
		wp_set_current_user( self::$private_reader_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'draft' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// But drafts are accessible to authorized users.
		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $draft_id, $data[0]['id'] );
	}

	/**
	 * @ticket 43701
	 */
	public function test_get_items_status_private_permissions() {
		$private_post_id = $this->factory->post->create( array( 'post_status' => 'private' ) );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'private' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		wp_set_current_user( self::$private_reader_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'status', 'private' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( $private_post_id, $data[0]['id'] );
	}

	public function test_get_items_invalid_per_page() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params( array( 'per_page' => -1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 39061
	 */
	public function test_get_items_invalid_max_pages() {
		// Out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'page', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_page_number', $response, 400 );
	}

	public function test_get_items_invalid_context() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'context', 'banana' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_invalid_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'after', rand_str() );
		$request->set_param( 'before', rand_str() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_valid_date() {
		$post1 = $this->factory->post->create( array( 'post_date' => '2016-01-15T00:00:00Z' ) );
		$post2 = $this->factory->post->create( array( 'post_date' => '2016-01-16T00:00:00Z' ) );
		$post3 = $this->factory->post->create( array( 'post_date' => '2016-01-17T00:00:00Z' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $post2, $data[0]['id'] );
	}

	public function test_get_items_all_post_formats() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$formats = array_values( get_post_format_slugs() );

		$this->assertSame( $formats, $data['schema']['properties']['format']['enum'] );
	}

	public function test_get_item() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );
	}

	public function test_get_item_links() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$links = $response->get_links();

		$this->assertSame( rest_url( '/wp/v2/posts/' . self::$post_id ), $links['self'][0]['href'] );
		$this->assertSame( rest_url( '/wp/v2/posts' ), $links['collection'][0]['href'] );
		$this->assertArrayNotHasKey( 'embeddable', $links['self'][0]['attributes'] );

		$this->assertSame( rest_url( '/wp/v2/types/' . get_post_type( self::$post_id ) ), $links['about'][0]['href'] );

		$replies_url = rest_url( '/wp/v2/comments' );
		$replies_url = add_query_arg( 'post', self::$post_id, $replies_url );
		$this->assertSame( $replies_url, $links['replies'][0]['href'] );

		$this->assertSame( rest_url( '/wp/v2/posts/' . self::$post_id . '/revisions' ), $links['version-history'][0]['href'] );
		$this->assertSame( 0, $links['version-history'][0]['attributes']['count'] );
		$this->assertFalse( isset( $links['predecessor-version'] ) );

		$attachments_url = rest_url( '/wp/v2/media' );
		$attachments_url = add_query_arg( 'parent', self::$post_id, $attachments_url );
		$this->assertSame( $attachments_url, $links['https://api.w.org/attachment'][0]['href'] );

		$term_links  = $links['https://api.w.org/term'];
		$tag_link    = null;
		$cat_link    = null;
		$format_link = null;
		foreach ( $term_links as $link ) {
			if ( 'post_tag' === $link['attributes']['taxonomy'] ) {
				$tag_link = $link;
			} elseif ( 'category' === $link['attributes']['taxonomy'] ) {
				$cat_link = $link;
			} elseif ( 'post_format' === $link['attributes']['taxonomy'] ) {
				$format_link = $link;
			}
		}
		$this->assertNotEmpty( $tag_link );
		$this->assertNotEmpty( $cat_link );
		$this->assertNull( $format_link );

		$tags_url = add_query_arg( 'post', self::$post_id, rest_url( '/wp/v2/tags' ) );
		$this->assertSame( $tags_url, $tag_link['href'] );

		$category_url = add_query_arg( 'post', self::$post_id, rest_url( '/wp/v2/categories' ) );
		$this->assertSame( $category_url, $cat_link['href'] );
	}

	public function test_get_item_links_predecessor() {
		wp_update_post(
			array(
				'post_content' => 'This content is marvelous.',
				'ID'           => self::$post_id,
			)
		);
		$revisions  = wp_get_post_revisions( self::$post_id );
		$revision_1 = array_pop( $revisions );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$links = $response->get_links();

		$this->assertSame( rest_url( '/wp/v2/posts/' . self::$post_id . '/revisions' ), $links['version-history'][0]['href'] );
		$this->assertSame( 1, $links['version-history'][0]['attributes']['count'] );

		$this->assertSame( rest_url( '/wp/v2/posts/' . self::$post_id . '/revisions/' . $revision_1->ID ), $links['predecessor-version'][0]['href'] );
		$this->assertSame( $revision_1->ID, $links['predecessor-version'][0]['attributes']['id'] );
	}

	public function test_get_item_links_no_author() {
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();
		$this->assertFalse( isset( $links['author'] ) );
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_author' => self::$author_id,
			)
		);
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();
		$this->assertSame( rest_url( '/wp/v2/users/' . self::$author_id ), $links['author'][0]['href'] );
	}

	public function test_get_post_draft_status_not_authenicated() {
		$draft_id = $this->factory->post->create(
			array(
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $draft_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	public function test_get_post_invalid_id() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_get_post_list_context_with_permission() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);

		wp_set_current_user( self::$editor_id );

		$response = rest_get_server()->dispatch( $request );

		$this->check_get_posts_response( $response, 'edit' );
	}

	public function test_get_post_list_context_without_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_post_context_without_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_query_params(
			array(
				'context' => 'edit',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_post_with_password() {
		$post_id = $this->factory->post->create(
			array(
				'post_password' => '$inthebananastand',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );

		$data = $response->get_data();
		$this->assertSame( '', $data['content']['rendered'] );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( '', $data['excerpt']['rendered'] );
		$this->assertTrue( $data['excerpt']['protected'] );
	}

	public function test_get_post_with_password_using_password() {
		$post_id = $this->factory->post->create(
			array(
				'post_password' => '$inthebananastand',
				'post_content'  => 'Some secret content.',
				'post_excerpt'  => 'Some secret excerpt.',
			)
		);

		$post = get_post( $post_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->set_param( 'password', '$inthebananastand' );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_post_response( $response, 'view' );

		$data = $response->get_data();
		$this->assertSame( wpautop( $post->post_content ), $data['content']['rendered'] );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( wpautop( $post->post_excerpt ), $data['excerpt']['rendered'] );
		$this->assertTrue( $data['excerpt']['protected'] );
	}

	public function test_get_post_with_password_using_incorrect_password() {
		$post_id = $this->factory->post->create(
			array(
				'post_password' => '$inthebananastand',
			)
		);

		$post = get_post( $post_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->set_param( 'password', 'wrongpassword' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_incorrect_password', $response, 403 );
	}

	public function test_get_post_with_password_without_permission() {
		$post_id = $this->factory->post->create(
			array(
				'post_password' => '$inthebananastand',
				'post_content'  => 'Some secret content.',
				'post_excerpt'  => 'Some secret excerpt.',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->check_get_post_response( $response, 'view' );
		$this->assertSame( '', $data['content']['rendered'] );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( '', $data['excerpt']['rendered'] );
		$this->assertTrue( $data['excerpt']['protected'] );
	}

	/**
	 * The post response should not have `block_version` when in view context.
	 *
	 * @ticket 43887
	 */
	public function test_get_post_should_not_have_block_version_when_context_view() {
		$post_id = $this->factory->post->create(
			array(
				'post_content' => '<!-- wp:core/separator -->',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertFalse( isset( $data['content']['block_version'] ) );
	}

	/**
	 * The post response should have `block_version` indicate that block content is present when in edit context.
	 *
	 * @ticket 43887
	 */
	public function test_get_post_should_have_block_version_indicate_block_content_when_context_edit() {
		wp_set_current_user( self::$editor_id );

		$post_id = $this->factory->post->create(
			array(
				'post_content' => '<!-- wp:core/separator -->',
			)
		);

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 1, $data['content']['block_version'] );
	}

	/**
	 * The post response should have `block_version` indicate that no block content is present when in edit context.
	 *
	 * @ticket 43887
	 */
	public function test_get_post_should_have_block_version_indicate_no_block_content_when_context_edit() {
		wp_set_current_user( self::$editor_id );

		$post_id = $this->factory->post->create(
			array(
				'post_content' => '<hr />',
			)
		);

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 0, $data['content']['block_version'] );
	}

	public function test_get_item_read_permission_custom_post_status_not_authenticated() {
		register_post_status( 'testpubstatus', array( 'public' => true ) );
		register_post_status( 'testprivtatus', array( 'public' => false ) );

		// Public status.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'testpubstatus',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		// Private status.
		wp_update_post(
			array(
				'ID'          => self::$post_id,
				'post_status' => 'testprivtatus',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_get_post_response( $response, 'edit' );
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$editor_id );

		$endpoint = new WP_REST_Posts_Controller( 'post' );
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,slug' );
		$obj      = get_post( self::$post_id );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertSame(
			array(
				'id',
				'slug',
			),
			array_keys( $response->get_data() )
		);
	}

	/**
	 * @ticket 42094
	 */
	public function test_prepare_item_filters_content_when_needed() {
		$filter_count   = 0;
		$filter_content = function() use ( &$filter_count ) {
			$filter_count++;
			return '<p>Filtered content.</p>';
		};
		add_filter( 'the_content', $filter_content );

		wp_set_current_user( self::$editor_id );

		$endpoint = new WP_REST_Posts_Controller( 'post' );
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'content.rendered' );

		$post     = get_post( self::$post_id );
		$response = $endpoint->prepare_item_for_response( $post, $request );

		remove_filter( 'the_content', $filter_content );

		$this->assertSame(
			array(
				'id'      => self::$post_id,
				'content' => array(
					'rendered' => '<p>Filtered content.</p>',
				),
			),
			$response->get_data()
		);
		$this->assertSame( 1, $filter_count );
	}

	/**
	 * @ticket 42094
	 */
	public function test_prepare_item_skips_content_filter_if_not_needed() {
		$filter_count   = 0;
		$filter_content = function() use ( &$filter_count ) {
			$filter_count++;
			return '<p>Filtered content.</p>';
		};
		add_filter( 'the_content', $filter_content );

		wp_set_current_user( self::$editor_id );

		$endpoint = new WP_REST_Posts_Controller( 'post' );
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );

		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'content.raw' );

		$post     = get_post( self::$post_id );
		$response = $endpoint->prepare_item_for_response( $post, $request );

		remove_filter( 'the_content', $filter_content );

		$this->assertSame(
			array(
				'id'      => $post->ID,
				'content' => array(
					'raw' => $post->post_content,
				),
			),
			$response->get_data()
		);
		$this->assertSame( 0, $filter_count );
	}

	public function test_create_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_post_response( $response );
	}

	public function post_dates_provider() {
		$all_statuses = array(
			'draft',
			'publish',
			'future',
			'pending',
			'private',
		);

		$cases_short = array(
			'set date without timezone'     => array(
				'statuses' => $all_statuses,
				'params'   => array(
					'timezone_string' => 'America/New_York',
					'date'            => '2016-12-12T14:00:00',
				),
				'results'  => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date_gmt without timezone' => array(
				'statuses' => $all_statuses,
				'params'   => array(
					'timezone_string' => 'America/New_York',
					'date_gmt'        => '2016-12-12T19:00:00',
				),
				'results'  => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date with timezone'        => array(
				'statuses' => array( 'draft', 'publish' ),
				'params'   => array(
					'timezone_string' => 'America/New_York',
					'date'            => '2016-12-12T18:00:00-01:00',
				),
				'results'  => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
			'set date_gmt with timezone'    => array(
				'statuses' => array( 'draft', 'publish' ),
				'params'   => array(
					'timezone_string' => 'America/New_York',
					'date_gmt'        => '2016-12-12T18:00:00-01:00',
				),
				'results'  => array(
					'date'     => '2016-12-12T14:00:00',
					'date_gmt' => '2016-12-12T19:00:00',
				),
			),
		);

		$cases = array();
		foreach ( $cases_short as $description => $case ) {
			foreach ( $case['statuses'] as $status ) {
				$cases[ $description . ', status=' . $status ] = array(
					$status,
					$case['params'],
					$case['results'],
				);
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider post_dates_provider
	 */
	public function test_create_post_date( $status, $params, $results ) {
		wp_set_current_user( self::$editor_id );

		update_option( 'timezone_string', $params['timezone_string'] );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_param( 'status', $status );
		$request->set_param( 'title', 'not empty' );
		if ( isset( $params['date'] ) ) {
			$request->set_param( 'date', $params['date'] );
		}
		if ( isset( $params['date_gmt'] ) ) {
			$request->set_param( 'date_gmt', $params['date_gmt'] );
		}
		$response = rest_get_server()->dispatch( $request );

		update_option( 'timezone_string', '' );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$post = get_post( $data['id'] );

		$this->assertSame( $results['date'], $data['date'] );
		$post_date = str_replace( 'T', ' ', $results['date'] );
		$this->assertSame( $post_date, $post->post_date );

		$this->assertSame( $results['date_gmt'], $data['date_gmt'] );
		$post_date_gmt = str_replace( 'T', ' ', $results['date_gmt'] );
		$this->assertSame( $post_date_gmt, $post->post_date_gmt );
	}

	/**
	 * @ticket 38698
	 */
	public function test_create_item_with_template() {
		wp_set_current_user( self::$editor_id );

		add_filter( 'theme_post_templates', array( $this, 'filter_theme_post_templates' ) );

		// Re-register the route as we now have a template available.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'template' => 'post-my-test-template.php',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data          = $response->get_data();
		$post_template = get_page_template_slug( get_post( $data['id'] ) );

		remove_filter( 'theme_post_templates', array( $this, 'filter_theme_post_templates' ) );

		$this->assertSame( 'post-my-test-template.php', $data['template'] );
		$this->assertSame( 'post-my-test-template.php', $post_template );
	}

	/**
	 * @ticket 38698
	 */
	public function test_create_item_with_template_none_available() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'template' => 'post-my-test-template.php',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 38877
	 */
	public function test_create_item_with_template_none() {
		wp_set_current_user( self::$editor_id );

		add_filter( 'theme_post_templates', array( $this, 'filter_theme_post_templates' ) );
		update_post_meta( self::$post_id, '_wp_page_template', 'post-my-test-template.php' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'template' => '',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data          = $response->get_data();
		$post_template = get_page_template_slug( get_post( $data['id'] ) );

		$this->assertSame( '', $data['template'] );
		$this->assertSame( '', $post_template );
	}

	public function test_rest_create_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_post_response( $response );
	}

	public function test_create_post_invalid_id() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'id' => '3',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_exists', $response, 400 );
	}

	public function test_create_post_as_contributor() {
		wp_set_current_user( self::$contributor_id );

		update_option( 'timezone_string', 'America/Chicago' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				// This results in a special `post_date_gmt` value
				// of '0000-00-00 00:00:00'. See #38883.
				'status' => 'pending',
			)
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$post = get_post( $data['id'] );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );
		$this->assertNotEquals( '0000-00-00T00:00:00', $data['date_gmt'] );

		$this->check_create_post_response( $response );

		update_option( 'timezone_string', '' );
	}

	public function test_create_post_sticky() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'sticky' => true,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertTrue( $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertTrue( is_sticky( $post->ID ) );
	}

	public function test_create_post_sticky_as_contributor() {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'sticky' => true,
				'status' => 'pending',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_assign_sticky', $response, 403 );
	}

	public function test_create_post_other_author_without_permission() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'author' => self::$editor_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_others', $response, 403 );
	}

	public function test_create_post_without_permission() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'status' => 'draft',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_create', $response, 401 );
	}

	public function test_create_post_draft() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'status' => 'draft',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'draft', $data['status'] );
		$this->assertSame( 'draft', $new_post->post_status );
		// Confirm dates are shimmed for gmt_offset.
		$post_modified_gmt = gmdate( 'Y-m-d H:i:s', strtotime( $new_post->post_modified ) + ( get_option( 'gmt_offset' ) * 3600 ) );
		$post_date_gmt     = gmdate( 'Y-m-d H:i:s', strtotime( $new_post->post_date ) + ( get_option( 'gmt_offset' ) * 3600 ) );

		$this->assertSame( mysql_to_rfc3339( $post_modified_gmt ), $data['modified_gmt'] );
		$this->assertSame( mysql_to_rfc3339( $post_date_gmt ), $data['date_gmt'] );
	}

	public function test_create_post_private() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'status' => 'private',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'private', $data['status'] );
		$this->assertSame( 'private', $new_post->post_status );
	}

	public function test_create_post_private_without_permission() {
		wp_set_current_user( self::$author_id );

		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'status' => 'private',
				'author' => self::$author_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_publish', $response, 403 );
	}

	public function test_create_post_publish_without_permission() {
		wp_set_current_user( self::$author_id );

		$user = wp_get_current_user();
		$user->add_cap( 'publish_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'status' => 'publish',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_publish', $response, 403 );
	}

	public function test_create_post_invalid_status() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'status' => 'teststatus',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_post_with_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'format' => 'gallery',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'gallery', $data['format'] );
		$this->assertSame( 'gallery', get_post_format( $new_post->ID ) );
	}

	public function test_create_post_with_standard_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'format' => 'standard',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'standard', $data['format'] );
		$this->assertFalse( get_post_format( $new_post->ID ) );
	}

	public function test_create_post_with_invalid_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'format' => 'testformat',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test with a valid format, but one unsupported by the theme.
	 *
	 * https://core.trac.wordpress.org/ticket/38610
	 */
	public function test_create_post_with_unsupported_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'format' => 'link',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'link', $data['format'] );
	}

	public function test_create_update_post_with_featured_media() {

		$file                = DIR_TESTDATA . '/images/canola.jpg';
		$this->attachment_id = $this->factory->attachment->create_object(
			$file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => rand( 1, 100 ),
			)
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'featured_media' => $this->attachment_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( $this->attachment_id, $data['featured_media'] );
		$this->assertSame( $this->attachment_id, (int) get_post_thumbnail_id( $new_post->ID ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $new_post->ID );
		$params  = $this->set_post_data(
			array(
				'featured_media' => 0,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 0, $data['featured_media'] );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $new_post->ID ) );
	}

	public function test_create_post_invalid_author() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'author' => -1,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_author', $response, 400 );
	}

	public function test_create_post_invalid_author_without_permission() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'author' => self::$editor_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_others', $response, 403 );
	}

	public function test_create_post_with_password() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password' => 'testing',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( 'testing', $data['password'] );
	}

	public function test_create_post_with_falsey_password() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password' => '0',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( '0', $data['password'] );
	}

	public function test_create_post_with_empty_string_password_and_sticky() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password' => '',
				'sticky'   => true,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( '', $data['password'] );
	}

	public function test_create_post_with_password_and_sticky_fails() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password' => '123',
				'sticky'   => true,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_create_post_custom_date() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'date' => '2010-01-01T02:00:00Z',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$time     = gmmktime( 2, 0, 0, 1, 1, 2010 );
		$this->assertSame( '2010-01-01T02:00:00', $data['date'] );
		$this->assertSame( $time, strtotime( $new_post->post_date ) );
	}

	public function test_create_post_custom_date_with_timezone() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'date' => '2010-01-01T02:00:00-10:00',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$time     = gmmktime( 12, 0, 0, 1, 1, 2010 );

		$this->assertSame( '2010-01-01T12:00:00', $data['date'] );
		$this->assertSame( '2010-01-01T12:00:00', $data['modified'] );

		$this->assertSame( $time, strtotime( $new_post->post_date ) );
		$this->assertSame( $time, strtotime( $new_post->post_modified ) );
	}

	public function test_create_post_with_db_error() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data( array() );
		$request->set_body_params( $params );

		/**
		 * Disable showing error as the below is going to intentionally
		 * trigger a DB error.
		 */
		global $wpdb;
		$wpdb->suppress_errors = true;
		add_filter( 'query', array( $this, 'error_insert_query' ) );

		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'query', array( $this, 'error_insert_query' ) );
		$wpdb->show_errors = true;

		$this->assertErrorResponse( 'db_insert_error', $response, 500 );
	}

	public function test_create_post_with_invalid_date() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'date' => '2010-60-01T02:00:00Z',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_post_with_invalid_date_gmt() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'date_gmt' => '2010-60-01T02:00:00',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_create_post_with_quotes_in_title() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'title' => "Rob O'Rourke's Diary",
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( "Rob O'Rourke's Diary", $data['title']['raw'] );
	}

	public function test_create_post_with_categories() {
		wp_set_current_user( self::$editor_id );

		$category = wp_insert_term( 'Test Category', 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password'   => 'testing',
				'categories' => array(
					$category['term_id'],
				),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( array( $category['term_id'] ), $data['categories'] );
	}

	public function test_create_post_with_categories_as_csv() {
		wp_set_current_user( self::$editor_id );

		$category  = wp_insert_term( 'Chicken', 'category' );
		$category2 = wp_insert_term( 'Ribs', 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'categories' => $category['term_id'] . ',' . $category2['term_id'],
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( array( $category['term_id'], $category2['term_id'] ), $data['categories'] );
	}

	public function test_create_post_with_invalid_categories() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password'   => 'testing',
				'categories' => array(
					REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
				),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( array(), $data['categories'] );
	}

	/**
	 * @ticket 38505
	 */
	public function test_create_post_with_categories_that_cannot_be_assigned_by_current_user() {
		$cats                = self::factory()->category->create_many( 2 );
		$this->forbidden_cat = $cats[1];

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$params  = $this->set_post_data(
			array(
				'password'   => 'testing',
				'categories' => $cats,
			)
		);
		$request->set_body_params( $params );

		add_filter( 'map_meta_cap', array( $this, 'revoke_assign_term' ), 10, 4 );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'map_meta_cap', array( $this, 'revoke_assign_term' ), 10, 4 );

		$this->assertErrorResponse( 'rest_cannot_assign_term', $response, 403 );
	}

	public function revoke_assign_term( $caps, $cap, $user_id, $args ) {
		if ( 'assign_term' === $cap && isset( $args[0] ) && $this->forbidden_cat === $args[0] ) {
			$caps = array( 'do_not_allow' );
		}
		return $caps;
	}

	public function test_update_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = $this->set_post_data();
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertSame( self::$post_id, $new_data['id'] );
		$this->assertSame( $params['title'], $new_data['title']['raw'] );
		$this->assertSame( $params['content'], $new_data['content']['raw'] );
		$this->assertSame( $params['excerpt'], $new_data['excerpt']['raw'] );
		$post = get_post( self::$post_id );
		$this->assertSame( $params['title'], $post->post_title );
		$this->assertSame( $params['content'], $post->post_content );
		$this->assertSame( $params['excerpt'], $post->post_excerpt );
	}

	public function test_update_item_no_change() {
		wp_set_current_user( self::$editor_id );

		$post = get_post( self::$post_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_param( 'author', $post->post_author );

		// Run twice to make sure that the update still succeeds
		// even if no DB rows are updated.
		$response = rest_get_server()->dispatch( $request );
		$this->check_update_post_response( $response );

		$response = rest_get_server()->dispatch( $request );
		$this->check_update_post_response( $response );
	}

	public function test_rest_update_post() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertSame( self::$post_id, $new_data['id'] );
		$this->assertSame( $params['title'], $new_data['title']['raw'] );
		$this->assertSame( $params['content'], $new_data['content']['raw'] );
		$this->assertSame( $params['excerpt'], $new_data['excerpt']['raw'] );
		$post = get_post( self::$post_id );
		$this->assertSame( $params['title'], $post->post_title );
		$this->assertSame( $params['content'], $post->post_content );
		$this->assertSame( $params['excerpt'], $post->post_excerpt );
	}

	/**
	 * Verify that updating a post with a `null` date or date_gmt results in a reset post, where all
	 * date values are equal (date, date_gmt, date_modified and date_modofied_gmt) in the API response.
	 * In the database, the post_date_gmt field is reset to the default `0000-00-00 00:00:00`.
	 *
	 * @ticket 44975
	 */
	public function test_rest_update_post_with_empty_date() {
		// Create a new test post.
		$post_id = $this->factory->post->create();

		wp_set_current_user( self::$editor_id );

		// Set the post date to the future.
		$future_date = '2919-07-29T18:00:00';

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data(
			array(
				'date_gmt' => $future_date,
				'date'     => $future_date,
				'title'    => 'update',
				'status'   => 'draft',
			)
		);
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );
		$this->check_update_post_response( $response );
		$new_data = $response->get_data();

		// Verify the post is set to the future date.
		$this->assertSame( $new_data['date_gmt'], $future_date );
		$this->assertSame( $new_data['date'], $future_date );
		$this->assertNotEquals( $new_data['date_gmt'], $new_data['modified_gmt'] );
		$this->assertNotEquals( $new_data['date'], $new_data['modified'] );

		// Update post with a blank field (date or date_gmt).
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_post_data(
			array(
				'date_gmt' => null,
				'title'    => 'test',
				'status'   => 'draft',
			)
		);
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		// Verify the date field values are reset in the API response.
		$this->check_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertSame( $new_data['date_gmt'], $new_data['date'] );
		$this->assertNotEquals( $new_data['date_gmt'], $future_date );

		$post = get_post( $post_id, 'ARRAY_A' );
		$this->assertSame( $post['post_date_gmt'], '0000-00-00 00:00:00' );
		$this->assertNotEquals( $new_data['date_gmt'], $future_date );
		$this->assertNotEquals( $new_data['date'], $future_date );
	}

	public function test_rest_update_post_raw() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$params = $this->set_raw_post_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = rest_get_server()->dispatch( $request );

		$this->check_update_post_response( $response );
		$new_data = $response->get_data();
		$this->assertSame( self::$post_id, $new_data['id'] );
		$this->assertSame( $params['title']['raw'], $new_data['title']['raw'] );
		$this->assertSame( $params['content']['raw'], $new_data['content']['raw'] );
		$this->assertSame( $params['excerpt']['raw'], $new_data['excerpt']['raw'] );
		$post = get_post( self::$post_id );
		$this->assertSame( $params['title']['raw'], $post->post_title );
		$this->assertSame( $params['content']['raw'], $post->post_content );
		$this->assertSame( $params['excerpt']['raw'], $post->post_excerpt );
	}

	public function test_update_post_without_extra_params() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data();
		unset( $params['type'] );
		unset( $params['name'] );
		unset( $params['author'] );
		unset( $params['status'] );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_update_post_response( $response );
	}

	public function test_update_post_without_permission() {
		wp_set_current_user( self::$editor_id );

		$user = wp_get_current_user();
		$user->add_cap( 'edit_published_posts', false );
		// Flush capabilities, https://core.trac.wordpress.org/ticket/28374
		$user->get_role_caps();
		$user->update_user_level_from_caps();

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data();
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_post_sticky_as_contributor() {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'sticky' => true,
				'status' => 'pending',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_post_invalid_id() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_update_post_invalid_route() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_update_post_with_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'format' => 'gallery',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'gallery', $data['format'] );
		$this->assertSame( 'gallery', get_post_format( $new_post->ID ) );
	}

	public function test_update_post_with_standard_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'format' => 'standard',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'standard', $data['format'] );
		$this->assertFalse( get_post_format( $new_post->ID ) );
	}

	public function test_update_post_with_invalid_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'format' => 'testformat',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test with a valid format, but one unsupported by the theme.
	 *
	 * https://core.trac.wordpress.org/ticket/38610
	 */
	public function test_update_post_with_unsupported_format() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'format' => 'link',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'link', $data['format'] );
	}

	public function test_update_post_ignore_readonly() {
		wp_set_current_user( self::$editor_id );

		$new_content       = rand_str();
		$expected_modified = current_time( 'mysql' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'modified' => '2010-06-01T02:00:00Z',
				'content'  => $new_content,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		// The readonly modified param should be ignored, request should be a success.
		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );

		$this->assertSame( $new_content, $data['content']['raw'] );
		$this->assertSame( $new_content, $new_post->post_content );

		// The modified date should equal the current time.
		$this->assertSame( gmdate( 'Y-m-d', strtotime( mysql_to_rfc3339( $expected_modified ) ) ), gmdate( 'Y-m-d', strtotime( $data['modified'] ) ) );
		$this->assertSame( gmdate( 'Y-m-d', strtotime( $expected_modified ) ), gmdate( 'Y-m-d', strtotime( $new_post->post_modified ) ) );
	}

	/**
	 * @dataProvider post_dates_provider
	 */
	public function test_update_post_date( $status, $params, $results ) {
		wp_set_current_user( self::$editor_id );

		update_option( 'timezone_string', $params['timezone_string'] );

		$post_id = $this->factory->post->create( array( 'post_status' => $status ) );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $post_id ) );
		if ( isset( $params['date'] ) ) {
			$request->set_param( 'date', $params['date'] );
		}
		if ( isset( $params['date_gmt'] ) ) {
			$request->set_param( 'date_gmt', $params['date_gmt'] );
		}
		$response = rest_get_server()->dispatch( $request );

		update_option( 'timezone_string', '' );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$post = get_post( $data['id'] );

		$this->assertSame( $results['date'], $data['date'] );
		$post_date = str_replace( 'T', ' ', $results['date'] );
		$this->assertSame( $post_date, $post->post_date );

		$this->assertSame( $results['date_gmt'], $data['date_gmt'] );
		$post_date_gmt = str_replace( 'T', ' ', $results['date_gmt'] );
		$this->assertSame( $post_date_gmt, $post->post_date_gmt );
	}

	public function test_update_post_with_invalid_date() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'date' => rand_str(),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_update_post_with_invalid_date_gmt() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'date_gmt' => rand_str(),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_empty_post_date_gmt_shimmed_using_post_date() {
		global $wpdb;

		wp_set_current_user( self::$editor_id );

		update_option( 'timezone_string', 'America/Chicago' );

		// Need to set dates using wpdb directly because `wp_update_post` and
		// `wp_insert_post` have additional validation on dates.
		$post_id = $this->factory->post->create();
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'     => '2016-02-23 12:00:00',
				'post_date_gmt' => '0000-00-00 00:00:00',
			),
			array(
				'ID' => $post_id,
			),
			array( '%s', '%s' ),
			array( '%d' )
		);
		wp_cache_delete( $post_id, 'posts' );

		$post = get_post( $post_id );
		$this->assertSame( $post->post_date, '2016-02-23 12:00:00' );
		$this->assertSame( $post->post_date_gmt, '0000-00-00 00:00:00' );

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( '2016-02-23T12:00:00', $data['date'] );
		$this->assertSame( '2016-02-23T18:00:00', $data['date_gmt'] );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->set_param( 'date', '2016-02-23T13:00:00' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( '2016-02-23T13:00:00', $data['date'] );
		$this->assertSame( '2016-02-23T19:00:00', $data['date_gmt'] );

		$post = get_post( $post_id );
		$this->assertSame( $post->post_date, '2016-02-23 13:00:00' );
		$this->assertSame( $post->post_date_gmt, '2016-02-23 19:00:00' );

		update_option( 'timezone_string', '' );
	}

	public function test_update_post_slug() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'slug' => 'sample-slug',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertSame( 'sample-slug', $new_data['slug'] );
		$post = get_post( $new_data['id'] );
		$this->assertSame( 'sample-slug', $post->post_name );
	}

	public function test_update_post_slug_accented_chars() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'slug' => 'tęst-acceńted-chäræcters',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertSame( 'test-accented-charaecters', $new_data['slug'] );
		$post = get_post( $new_data['id'] );
		$this->assertSame( 'test-accented-charaecters', $post->post_name );
	}

	public function test_update_post_sticky() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'sticky' => true,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertTrue( $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertTrue( is_sticky( $post->ID ) );

		// Updating another field shouldn't change sticky status.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'title' => 'This should not reset sticky',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertTrue( $new_data['sticky'] );
		$post = get_post( $new_data['id'] );
		$this->assertTrue( is_sticky( $post->ID ) );
	}

	public function test_update_post_excerpt() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'excerpt' => 'An Excerpt',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( 'An Excerpt', $new_data['excerpt']['raw'] );
	}

	public function test_update_post_empty_excerpt() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'excerpt' => '',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( '', $new_data['excerpt']['raw'] );
	}

	public function test_update_post_content() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'content' => 'Some Content',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( 'Some Content', $new_data['content']['raw'] );
	}

	public function test_update_post_empty_content() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'content' => '',
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( '', $new_data['content']['raw'] );
	}

	public function test_update_post_with_empty_password() {
		wp_set_current_user( self::$editor_id );

		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_password' => 'foo',
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'password' => '',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( '', $data['password'] );
	}

	public function test_update_post_with_password_and_sticky_fails() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'password' => '123',
				'sticky'   => true,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_update_stick_post_with_password_fails() {
		wp_set_current_user( self::$editor_id );

		stick_post( self::$post_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'password' => '123',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_update_password_protected_post_with_sticky_fails() {
		wp_set_current_user( self::$editor_id );

		wp_update_post(
			array(
				'ID'            => self::$post_id,
				'post_password' => '123',
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'sticky' => true,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field', $response, 400 );
	}

	public function test_update_post_with_quotes_in_title() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'title' => "Rob O'Rourke's Diary",
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( "Rob O'Rourke's Diary", $new_data['title']['raw'] );
	}

	public function test_update_post_with_categories() {
		wp_set_current_user( self::$editor_id );

		$category = wp_insert_term( 'Test Category', 'category' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'title'      => 'Tester',
				'categories' => array(
					$category['term_id'],
				),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( array( $category['term_id'] ), $new_data['categories'] );
		$categories_path = '';
		$links           = $response->get_links();
		foreach ( $links['https://api.w.org/term'] as $link ) {
			if ( 'category' === $link['attributes']['taxonomy'] ) {
				$categories_path = $link['href'];
			}
		}
		$query = parse_url( $categories_path, PHP_URL_QUERY );
		parse_str( $query, $args );

		$request = new WP_REST_Request( 'GET', $args['rest_route'] );
		unset( $args['rest_route'] );
		$request->set_query_params( $args );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Test Category', $data[0]['name'] );
	}

	public function test_update_post_with_empty_categories() {
		wp_set_current_user( self::$editor_id );

		$category = wp_insert_term( 'Test Category', 'category' );
		wp_set_object_terms( self::$post_id, $category['term_id'], 'category' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'title'      => 'Tester',
				'categories' => array(),
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( array(), $new_data['categories'] );
	}

	/**
	 * @ticket 38505
	 */
	public function test_update_post_with_categories_that_cannot_be_assigned_by_current_user() {
		$cats                = self::factory()->category->create_many( 2 );
		$this->forbidden_cat = $cats[1];

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'password'   => 'testing',
				'categories' => $cats,
			)
		);
		$request->set_body_params( $params );

		add_filter( 'map_meta_cap', array( $this, 'revoke_assign_term' ), 10, 4 );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'map_meta_cap', array( $this, 'revoke_assign_term' ), 10, 4 );

		$this->assertErrorResponse( 'rest_cannot_assign_term', $response, 403 );
	}

	/**
	 * @ticket 38698
	 */
	public function test_update_item_with_template() {
		wp_set_current_user( self::$editor_id );

		add_filter( 'theme_post_templates', array( $this, 'filter_theme_post_templates' ) );

		// reregister the route as we now have a template available.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'template' => 'post-my-test-template.php',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data          = $response->get_data();
		$post_template = get_page_template_slug( get_post( $data['id'] ) );

		$this->assertSame( 'post-my-test-template.php', $data['template'] );
		$this->assertSame( 'post-my-test-template.php', $post_template );
	}

	/**
	 * @ticket 38877
	 */
	public function test_update_item_with_template_none() {
		wp_set_current_user( self::$editor_id );

		add_filter( 'theme_post_templates', array( $this, 'filter_theme_post_templates' ) );
		update_post_meta( self::$post_id, '_wp_page_template', 'post-my-test-template.php' );

		// reregister the route as we now have a template available.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'template' => '',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data          = $response->get_data();
		$post_template = get_page_template_slug( get_post( $data['id'] ) );

		$this->assertSame( '', $data['template'] );
		$this->assertSame( '', $post_template );
	}

	/**
	 * Test update_item() with same template that no longer exists.
	 *
	 * @covers WP_REST_Posts_Controller::check_template
	 * @ticket 39996
	 */
	public function test_update_item_with_same_template_that_no_longer_exists() {
		wp_set_current_user( self::$editor_id );

		update_post_meta( self::$post_id, '_wp_page_template', 'post-my-invalid-template.php' );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$params  = $this->set_post_data(
			array(
				'template' => 'post-my-invalid-template.php',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data          = $response->get_data();
		$post_template = get_page_template_slug( get_post( $data['id'] ) );

		$this->assertSame( 'post-my-invalid-template.php', $post_template );
		$this->assertSame( 'post-my-invalid-template.php', $data['template'] );
	}

	public function verify_post_roundtrip( $input = array(), $expected_output = array() ) {
		// Create the post.
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['title']['raw'], $actual_output['title']['raw'] );
		$this->assertSame( $expected_output['title']['rendered'], trim( $actual_output['title']['rendered'] ) );
		$this->assertSame( $expected_output['content']['raw'], $actual_output['content']['raw'] );
		$this->assertSame( $expected_output['content']['rendered'], trim( $actual_output['content']['rendered'] ) );
		$this->assertSame( $expected_output['excerpt']['raw'], $actual_output['excerpt']['raw'] );
		$this->assertSame( $expected_output['excerpt']['rendered'], trim( $actual_output['excerpt']['rendered'] ) );

		// Compare expected API output to WP internal values.
		$post = get_post( $actual_output['id'] );
		$this->assertSame( $expected_output['title']['raw'], $post->post_title );
		$this->assertSame( $expected_output['content']['raw'], $post->post_content );
		$this->assertSame( $expected_output['excerpt']['raw'], $post->post_excerpt );

		// Update the post.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $actual_output['id'] ) );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['title']['raw'], $actual_output['title']['raw'] );
		$this->assertSame( $expected_output['title']['rendered'], trim( $actual_output['title']['rendered'] ) );
		$this->assertSame( $expected_output['content']['raw'], $actual_output['content']['raw'] );
		$this->assertSame( $expected_output['content']['rendered'], trim( $actual_output['content']['rendered'] ) );
		$this->assertSame( $expected_output['excerpt']['raw'], $actual_output['excerpt']['raw'] );
		$this->assertSame( $expected_output['excerpt']['rendered'], trim( $actual_output['excerpt']['rendered'] ) );

		// Compare expected API output to WP internal values.
		$post = get_post( $actual_output['id'] );
		$this->assertSame( $expected_output['title']['raw'], $post->post_title );
		$this->assertSame( $expected_output['content']['raw'], $post->post_content );
		$this->assertSame( $expected_output['excerpt']['raw'], $post->post_excerpt );
	}

	public static function post_roundtrip_provider() {
		return array(
			array(
				// Raw values.
				array(
					'title'   => '\o/ ¯\_(ツ)_/¯',
					'content' => '\o/ ¯\_(ツ)_/¯',
					'excerpt' => '\o/ ¯\_(ツ)_/¯',
				),
				// Expected returned values.
				array(
					'title'   => array(
						'raw'      => '\o/ ¯\_(ツ)_/¯',
						'rendered' => '\o/ ¯\_(ツ)_/¯',
					),
					'content' => array(
						'raw'      => '\o/ ¯\_(ツ)_/¯',
						'rendered' => '<p>\o/ ¯\_(ツ)_/¯</p>',
					),
					'excerpt' => array(
						'raw'      => '\o/ ¯\_(ツ)_/¯',
						'rendered' => '<p>\o/ ¯\_(ツ)_/¯</p>',
					),
				),
			),
			array(
				// Raw values.
				array(
					'title'   => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
					'content' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
					'excerpt' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				),
				// Expected returned values.
				array(
					'title'   => array(
						'raw'      => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
						'rendered' => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
					),
					'content' => array(
						'raw'      => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
						'rendered' => '<p>\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;</p>',
					),
					'excerpt' => array(
						'raw'      => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
						'rendered' => '<p>\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;</p>',
					),
				),
			),
			array(
				// Raw values.
				array(
					'title'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'content' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'excerpt' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				// Expected returned values.
				array(
					'title'   => array(
						'raw'      => 'div <strong>strong</strong> oh noes',
						'rendered' => 'div <strong>strong</strong> oh noes',
					),
					'content' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
					'excerpt' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
				),
			),
			array(
				// Raw values.
				array(
					'title'   => '<a href="#" target="_blank" unfiltered=true>link</a>',
					'content' => '<a href="#" target="_blank" unfiltered=true>link</a>',
					'excerpt' => '<a href="#" target="_blank" unfiltered=true>link</a>',
				),
				// Expected returned values.
				array(
					'title'   => array(
						'raw'      => '<a href="#">link</a>',
						'rendered' => '<a href="#">link</a>',
					),
					'content' => array(
						'raw'      => '<a href="#" target="_blank" rel="noopener noreferrer">link</a>',
						'rendered' => '<p><a href="#" target="_blank" rel="noopener noreferrer">link</a></p>',
					),
					'excerpt' => array(
						'raw'      => '<a href="#" target="_blank" rel="noopener noreferrer">link</a>',
						'rendered' => '<p><a href="#" target="_blank" rel="noopener noreferrer">link</a></p>',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider post_roundtrip_provider
	 */
	public function test_post_roundtrip_as_author( $raw, $expected ) {
		wp_set_current_user( self::$author_id );

		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$this->verify_post_roundtrip( $raw, $expected );
	}

	public function test_post_roundtrip_as_editor_unfiltered_html() {
		wp_set_current_user( self::$editor_id );

		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'unfiltered_html' ) );
			$this->verify_post_roundtrip(
				array(
					'title'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'content' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'excerpt' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'title'   => array(
						'raw'      => 'div <strong>strong</strong> oh noes',
						'rendered' => 'div <strong>strong</strong> oh noes',
					),
					'content' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
					'excerpt' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
				)
			);
		} else {
			$this->assertTrue( current_user_can( 'unfiltered_html' ) );
			$this->verify_post_roundtrip(
				array(
					'title'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'content' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'excerpt' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'title'   => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					),
					'content' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
					),
					'excerpt' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
					),
				)
			);
		}
	}

	public function test_post_roundtrip_as_superadmin_unfiltered_html() {
		wp_set_current_user( self::$superadmin_id );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_post_roundtrip(
			array(
				'title'   => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'content' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'excerpt' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			),
			array(
				'title'   => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				'content' => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
				),
				'excerpt' => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
				),
			)
		);
	}

	public function test_delete_item() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Deleted post' ) );

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Deleted post', $data['title']['raw'] );
		$this->assertSame( 'trash', $data['status'] );
	}

	public function test_delete_item_skip_trash() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Deleted post' ) );

		wp_set_current_user( self::$editor_id );

		$request          = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request['force'] = true;
		$response         = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertNotEmpty( $data['previous'] );
	}

	public function test_delete_item_already_trashed() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Deleted post' ) );

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_already_trashed', $response, 410 );
	}

	public function test_delete_post_invalid_id() {
		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_delete_post_invalid_post_type() {
		$page_id = $this->factory->post->create( array( 'post_type' => 'page' ) );

		wp_set_current_user( self::$editor_id );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . $page_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	public function test_delete_post_without_permission() {
		wp_set_current_user( self::$author_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_register_post_type_invalid_controller() {

		register_post_type(
			'invalid-controller',
			array(
				'show_in_rest'          => true,
				'rest_controller_class' => 'Fake_Class_Baba',
			)
		);
		create_initial_rest_routes();
		$routes = rest_get_server()->get_routes();
		$this->assertFalse( isset( $routes['/wp/v2/invalid-controller'] ) );
		_unregister_post_type( 'invalid-controller' );

	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertSame( 26, count( $properties ) );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'featured_media', $properties );
		$this->assertArrayHasKey( 'generated_slug', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'format', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'permalink_template', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'sticky', $properties );
		$this->assertArrayHasKey( 'template', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'tags', $properties );
		$this->assertArrayHasKey( 'categories', $properties );
	}

	/**
	 * @ticket 48401
	 */
	public function test_get_item_schema_issues_doing_it_wrong_when_taxonomy_name_is_already_set_in_properties() {
		$this->setExpectedIncorrectUsage( 'register_taxonomy' );

		// Register a taxonomy with 'status' as name.
		register_taxonomy( 'status', 'post', array( 'show_in_rest' => true ) );

		// Re-initialize the controller.
		$controller = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
	}

	/**
	 * @ticket 39805
	 */
	public function test_get_post_view_context_properties() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_param( 'context', 'view' );
		$response = rest_get_server()->dispatch( $request );
		$keys     = array_keys( $response->get_data() );
		sort( $keys );

		$expected_keys = array(
			'author',
			'categories',
			'comment_status',
			'content',
			'date',
			'date_gmt',
			'excerpt',
			'featured_media',
			'format',
			'guid',
			'id',
			'link',
			'meta',
			'modified',
			'modified_gmt',
			'ping_status',
			'slug',
			'status',
			'sticky',
			'tags',
			'template',
			'title',
			'type',
		);

		$this->assertSame( $expected_keys, $keys );
	}

	public function test_get_post_edit_context_properties() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$keys     = array_keys( $response->get_data() );
		sort( $keys );

		$expected_keys = array(
			'author',
			'categories',
			'comment_status',
			'content',
			'date',
			'date_gmt',
			'excerpt',
			'featured_media',
			'format',
			'generated_slug',
			'guid',
			'id',
			'link',
			'meta',
			'modified',
			'modified_gmt',
			'password',
			'permalink_template',
			'ping_status',
			'slug',
			'status',
			'sticky',
			'tags',
			'template',
			'title',
			'type',
		);

		$this->assertSame( $expected_keys, $keys );
	}

	public function test_get_post_embed_context_properties() {
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_param( 'context', 'embed' );
		$response = rest_get_server()->dispatch( $request );
		$keys     = array_keys( $response->get_data() );
		sort( $keys );

		$expected_keys = array(
			'author',
			'date',
			'excerpt',
			'featured_media',
			'id',
			'link',
			'slug',
			'title',
			'type',
		);

		$this->assertSame( $expected_keys, $keys );
	}

	public function test_status_array_enum_args() {
		$request         = new WP_REST_Request( 'GET', '/wp/v2' );
		$response        = rest_get_server()->dispatch( $request );
		$data            = $response->get_data();
		$list_posts_args = $data['routes']['/wp/v2/posts']['endpoints'][0]['args'];
		$status_arg      = $list_posts_args['status'];
		$this->assertSame( 'array', $status_arg['type'] );
		$this->assertSame(
			array(
				'enum' => array(
					'publish',
					'future',
					'draft',
					'pending',
					'private',
					'trash',
					'auto-draft',
					'inherit',
					'request-pending',
					'request-confirmed',
					'request-failed',
					'request-completed',
					'any',
				),
				'type' => 'string',
			),
			$status_arg['items']
		);
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'post',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		wp_set_current_user( 1 );

		$post_id = $this->factory->post->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 123, get_post_meta( $post_id, 'my_custom_int', true ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->set_body_params(
			array(
				'my_custom_int' => 123,
				'title'         => 'hello',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 123, $response->data['my_custom_int'] );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	/**
	 * @ticket 45220
	 */
	public function test_get_additional_field_registration_null_schema() {
		register_rest_field(
			'post',
			'my_custom_int',
			array(
				'schema'          => null,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => null,
			)
		);
		$post_id = $this->factory->post->create();

		// 'my_custom_int' should appear because ?_fields= isn't set.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		// 'my_custom_int' should appear because it's present in ?_fields=.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( '_fields', 'title,my_custom_int' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		// 'my_custom_int' should not appear because it's not present in ?_fields=.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( '_fields', 'title' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayNotHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function test_additional_field_update_errors() {
		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'post',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		wp_set_current_user( self::$editor_id );

		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/posts/%d', self::$post_id ) );
		$request->set_body_params(
			array(
				'my_custom_int' => 'returnError',
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_post_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $post ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
		update_post_meta( $post->ID, 'my_custom_int', $value );
	}

	public function test_publish_action_ldo_registered() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$publish = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-publish' ) );

		$this->assertCount( 1, $publish, 'LDO found on schema.' );
	}

	public function test_sticky_action_ldo_registered_for_posts() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$publish = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-sticky' ) );

		$this->assertCount( 1, $publish, 'LDO found on schema.' );
	}

	public function test_sticky_action_ldo_not_registered_for_non_posts() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/pages' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$publish = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-sticky' ) );

		$this->assertCount( 0, $publish, 'LDO found on schema.' );
	}

	public function test_author_action_ldo_registered_for_post_types_with_author_support() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$publish = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-assign-author' ) );

		$this->assertCount( 1, $publish, 'LDO found on schema.' );
	}

	public function test_author_action_ldo_not_registered_for_post_types_without_author_support() {
		remove_post_type_support( 'post', 'author' );

		// Re-initialize the controller to cache-bust schemas from prior test runs.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$publish = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-assign-author' ) );

		$this->assertCount( 0, $publish, 'LDO found on schema.' );
	}

	public function test_term_action_ldos_registered() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$rels = array_flip( wp_list_pluck( $schema['links'], 'rel' ) );

		$this->assertArrayHasKey( 'https://api.w.org/action-assign-categories', $rels );
		$this->assertArrayHasKey( 'https://api.w.org/action-create-categories', $rels );
		$this->assertArrayHasKey( 'https://api.w.org/action-assign-tags', $rels );
		$this->assertArrayHasKey( 'https://api.w.org/action-create-tags', $rels );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-assign-post_format', $rels );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-create-post_format', $rels );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-assign-nav_menu', $rels );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-create-nav_menu', $rels );
	}

	public function test_action_links_only_available_in_edit_context() {
		wp_set_current_user( self::$author_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'view' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-publish', $links );
	}

	public function test_publish_action_link_exists_for_author() {
		wp_set_current_user( self::$author_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'https://api.w.org/action-publish', $links );
	}

	public function test_publish_action_link_does_not_exist_for_contributor() {
		wp_set_current_user( self::$contributor_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$contributor_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-publish', $links );
	}

	public function test_sticky_action_exists_for_editor() {
		wp_set_current_user( self::$editor_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'https://api.w.org/action-sticky', $links );
	}

	public function test_sticky_action_does_not_exist_for_author() {
		wp_set_current_user( self::$author_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-sticky', $links );
	}

	public function test_sticky_action_does_not_exist_for_non_post_posts() {
		wp_set_current_user( self::$editor_id );

		$post = self::factory()->post->create(
			array(
				'post_author' => self::$author_id,
				'post_type'   => 'page',
			)
		);
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-sticky', $links );
	}


	public function test_assign_author_action_exists_for_editor() {
		wp_set_current_user( self::$editor_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'https://api.w.org/action-assign-author', $links );
	}

	public function test_assign_author_action_does_not_exist_for_author() {
		wp_set_current_user( self::$author_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-assign-author', $links );
	}

	public function test_assign_author_action_does_not_exist_for_post_types_without_author_support() {
		remove_post_type_support( 'post', 'author' );

		wp_set_current_user( self::$editor_id );

		$post = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-assign-author', $links );
	}

	public function test_create_term_action_exists_for_editor() {
		wp_set_current_user( self::$editor_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'https://api.w.org/action-create-categories', $links );
		$this->assertArrayHasKey( 'https://api.w.org/action-create-tags', $links );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-create-post_format', $links );
	}

	public function test_create_term_action_non_hierarchical_exists_for_author() {
		wp_set_current_user( self::$author_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'https://api.w.org/action-create-tags', $links );
	}

	public function test_create_term_action_hierarchical_does_not_exists_for_author() {
		wp_set_current_user( self::$author_id );

		$post = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-create-categories', $links );
	}

	public function test_assign_term_action_exists_for_contributor() {
		wp_set_current_user( self::$contributor_id );

		$post = self::factory()->post->create(
			array(
				'post_author' => self::$contributor_id,
				'post_status' => 'draft',
			)
		);
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'https://api.w.org/action-assign-categories', $links );
		$this->assertArrayHasKey( 'https://api.w.org/action-assign-tags', $links );
	}

	public function test_assign_unfiltered_html_action_superadmin() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$links    = $response->get_links();
		$this->assertArrayHasKey( 'https://api.w.org/action-unfiltered-html', $links );
	}

	public function test_assign_unfiltered_html_action_editor() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$links    = $response->get_links();
		// Editors can only unfiltered html on single site.
		if ( is_multisite() ) {
			$this->assertArrayNotHasKey( 'https://api.w.org/action-unfiltered-html', $links );
		} else {
			$this->assertArrayHasKey( 'https://api.w.org/action-unfiltered-html', $links );
		}
	}

	public function test_assign_unfiltered_html_action_author() {
		$post_id = self::factory()->post->create();

		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$links    = $response->get_links();
		// Authors can't ever unfiltered html.
		$this->assertArrayNotHasKey( 'https://api.w.org/action-unfiltered-html', $links );
	}

	public function test_generated_permalink_template_generated_slug_for_non_viewable_posts() {
		register_post_type(
			'private-post',
			array(
				'label'              => 'Private Posts',
				'supports'           => array( 'title', 'editor', 'author' ),
				'show_in_rest'       => true,
				'publicly_queryable' => false,
				'public'             => true,
				'rest_base'          => 'private-post',
			)
		);
		create_initial_rest_routes();

		wp_set_current_user( self::$editor_id );

		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Permalink Template',
				'post_type'   => 'private-post',
				'post_status' => 'draft',
			)
		);

		// Neither 'permalink_template' and 'generated_slug' are expected for this post type.
		$request = new WP_REST_Request( 'GET', '/wp/v2/private-post/' . $post_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'permalink_template', $data );
		$this->assertArrayNotHasKey( 'generated_slug', $data );
	}

	public function test_generated_permalink_template_generated_slug_for_posts() {
		$this->set_permalink_structure( '/%postname%/' );
		$expected_permalink_template = trailingslashit( home_url( '/%postname%/' ) );

		wp_set_current_user( self::$editor_id );

		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Permalink Template',
				'post_type'   => 'post',
				'post_status' => 'draft',
			)
		);

		// Both 'permalink_template' and 'generated_slug' are expected for context=edit.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected_permalink_template, $data['permalink_template'] );
		$this->assertSame( 'permalink-template', $data['generated_slug'] );

		// Neither 'permalink_template' and 'generated_slug' are expected for context=view.
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'context', 'view' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'permalink_template', $data );
		$this->assertArrayNotHasKey( 'generated_slug', $data );

	}

	/**
	 * @ticket 39953
	 */
	public function test_putting_same_publish_date_does_not_remove_floating_date() {
		wp_set_current_user( self::$superadmin_id );

		$time = gmdate( 'Y-m-d H:i:s' );

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_date'   => $time,
			)
		);

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		$get = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post->ID}" );
		$get->set_query_params( array( 'context' => 'edit' ) );

		$get      = rest_get_server()->dispatch( $get );
		$get_body = $get->get_data();

		$put = new WP_REST_Request( 'PUT', "/wp/v2/posts/{$post->ID}" );
		$put->set_body_params( $get_body );

		$response = rest_get_server()->dispatch( $put );
		$body     = $response->get_data();

		$this->assertEqualsWithDelta( strtotime( $get_body['date'] ), strtotime( $body['date'] ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( $get_body['date_gmt'] ), strtotime( $body['date_gmt'] ), 2, 'The dates should be equal' );

		$this->assertSame( '0000-00-00 00:00:00', get_post( $post->ID )->post_date_gmt );
	}

	/**
	 * @ticket 39953
	 */
	public function test_putting_different_publish_date_removes_floating_date() {
		wp_set_current_user( self::$superadmin_id );

		$time     = gmdate( 'Y-m-d H:i:s' );
		$new_time = gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) );

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_date'   => $time,
			)
		);

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		$get = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post->ID}" );
		$get->set_query_params( array( 'context' => 'edit' ) );

		$get      = rest_get_server()->dispatch( $get );
		$get_body = $get->get_data();

		$put = new WP_REST_Request( 'PUT', "/wp/v2/posts/{$post->ID}" );
		$put->set_body_params(
			array_merge(
				$get_body,
				array(
					'date' => mysql_to_rfc3339( $new_time ),
				)
			)
		);

		$response = rest_get_server()->dispatch( $put );
		$body     = $response->get_data();

		$this->assertEqualsWithDelta( strtotime( mysql_to_rfc3339( $new_time ) ), strtotime( $body['date'] ), 2, 'The dates should be equal' );

		$this->assertNotEquals( '0000-00-00 00:00:00', get_post( $post->ID )->post_date_gmt );
	}

	/**
	 * @ticket 39953
	 */
	public function test_publishing_post_with_same_date_removes_floating_date() {
		wp_set_current_user( self::$superadmin_id );

		$time = gmdate( 'Y-m-d H:i:s' );

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_date'   => $time,
			)
		);

		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		$get = new WP_REST_Request( 'GET', "/wp/v2/posts/{$post->ID}" );
		$get->set_query_params( array( 'context' => 'edit' ) );

		$get      = rest_get_server()->dispatch( $get );
		$get_body = $get->get_data();

		$put = new WP_REST_Request( 'PUT', "/wp/v2/posts/{$post->ID}" );
		$put->set_body_params(
			array_merge(
				$get_body,
				array(
					'status' => 'publish',
				)
			)
		);

		$response = rest_get_server()->dispatch( $put );
		$body     = $response->get_data();

		$this->assertEqualsWithDelta( strtotime( $get_body['date'] ), strtotime( $body['date'] ), 2, 'The dates should be equal' );
		$this->assertEqualsWithDelta( strtotime( $get_body['date_gmt'] ), strtotime( $body['date_gmt'] ), 2, 'The dates should be equal' );

		$this->assertNotEquals( '0000-00-00 00:00:00', get_post( $post->ID )->post_date_gmt );
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_reuses_same_instance() {
		$this->assertSame(
			get_post_type_object( 'post' )->get_rest_controller(),
			get_post_type_object( 'post' )->get_rest_controller()
		);
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_null_if_post_type_does_not_show_in_rest() {
		register_post_type(
			'not_in_rest',
			array(
				'show_in_rest' => false,
			)
		);

		$this->assertNull( get_post_type_object( 'not_in_rest' )->get_rest_controller() );
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_null_if_class_does_not_exist() {
		register_post_type(
			'class_not_found',
			array(
				'show_in_rest'          => true,
				'rest_controller_class' => 'Class_That_Does_Not_Exist',
			)
		);

		$this->assertNull( get_post_type_object( 'class_not_found' )->get_rest_controller() );
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_null_if_class_does_not_subclass_rest_controller() {
		register_post_type(
			'invalid_class',
			array(
				'show_in_rest'          => true,
				'rest_controller_class' => 'WP_Post',
			)
		);

		$this->assertNull( get_post_type_object( 'invalid_class' )->get_rest_controller() );
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_posts_controller_if_custom_class_not_specified() {
		register_post_type(
			'test',
			array(
				'show_in_rest' => true,
			)
		);

		$this->assertInstanceOf(
			WP_REST_Posts_Controller::class,
			get_post_type_object( 'test' )->get_rest_controller()
		);
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_provided_controller_class() {
		$this->assertInstanceOf(
			WP_REST_Blocks_Controller::class,
			get_post_type_object( 'wp_block' )->get_rest_controller()
		);
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_null_for_invalid_provided_controller() {
		register_post_type(
			'test',
			array(
				'show_in_rest'    => true,
				'rest_controller' => new \stdClass(),
			)
		);

		$this->assertNull( get_post_type_object( 'test' )->get_rest_controller() );
	}

	/**
	 * @ticket 45677
	 */
	public function test_get_for_post_type_returns_null_for_controller_class_mismatch() {
		register_post_type(
			'test',
			array(
				'show_in_rest'          => true,
				'rest_controller_class' => WP_REST_Posts_Controller::class,
				'rest_controller'       => new WP_REST_Terms_Controller( 'category' ),
			)
		);

		$this->assertNull( get_post_type_object( 'test' )->get_rest_controller() );
	}

	/**
	 * @ticket 47779
	 */
	public function test_rest_post_type_item_schema_filter_change_property() {
		add_filter( 'rest_post_item_schema', array( $this, 'filter_post_item_schema' ) );

		// Re-initialize the controller to cache-bust schemas from prior test runs.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;

		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties']['content']['properties'];

		$this->assertArrayHasKey( 'new_prop', $properties );
		$this->assertSame( array( 'new_context' ), $properties['new_prop']['context'] );
	}

	/**
	 * @ticket 47779
	 */
	public function test_rest_post_type_item_schema_filter_add_property_triggers_doing_it_wrong() {
		add_filter( 'rest_post_item_schema', array( $this, 'filter_post_item_schema_add_property' ) );
		$this->setExpectedIncorrectUsage( 'WP_REST_Posts_Controller::get_item_schema' );

		// Re-initialize the controller to cache-bust schemas from prior test runs.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'post' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;
	}

	public function tearDown() {
		_unregister_post_type( 'private-post' );
		_unregister_post_type( 'youseeme' );
		if ( isset( $this->attachment_id ) ) {
			$this->remove_added_uploads();
		}
		remove_filter( 'rest_pre_dispatch', array( $this, 'wpSetUpBeforeRequest' ), 10, 3 );
		remove_filter( 'posts_clauses', array( $this, 'save_posts_clauses' ), 10, 2 );
		parent::tearDown();
	}

	/**
	 * Internal function used to disable an insert query which
	 * will trigger a wpdb error for testing purposes.
	 */
	public function error_insert_query( $query ) {
		if ( strpos( $query, 'INSERT' ) === 0 ) {
			$query = '],';
		}
		return $query;
	}

	public function filter_theme_post_templates( $post_templates ) {
		return array(
			'post-my-test-template.php' => 'My Test Template',
		);
	}

	public function filter_post_item_schema( $schema ) {
		$schema['properties']['content']['properties']['new_prop'] = array(
			'description' => __( 'A new prop added with a the rest_post_item_schema filter.' ),
			'type'        => 'string',
			'context'     => array( 'new_context' ),
		);
		return $schema;
	}

	public function filter_post_item_schema_add_property( $schema ) {
		$schema['properties']['something_entirely_new'] = array(
			'description' => __( 'A new prop added with a the rest_post_item_schema filter.' ),
			'type'        => 'string',
			'context'     => array( 'new_context' ),
		);
		return $schema;
	}
}
