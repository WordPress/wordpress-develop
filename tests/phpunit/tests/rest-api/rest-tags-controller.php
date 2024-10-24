<?php
/**
 * Unit tests covering WP_REST_Terms_Controller functionality, used for Tags.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Tags_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $superadmin;
	protected static $administrator;
	protected static $editor;
	protected static $contributor;
	protected static $subscriber;

	protected static $tag_ids    = array();
	protected static $total_tags = 30;
	protected static $per_page   = 50;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$superadmin    = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);
		self::$administrator = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor        = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$contributor   = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);
		self::$subscriber    = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}

		// Set up tags for pagination tests.
		for ( $i = 0; $i < self::$total_tags; $i++ ) {
			self::$tag_ids[] = $factory->tag->create(
				array(
					'name' => "Tag {$i}",
				)
			);
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$superadmin );
		self::delete_user( self::$administrator );
		self::delete_user( self::$editor );
		self::delete_user( self::$subscriber );

		// Remove tags for pagination tests.
		foreach ( self::$tag_ids as $tag_id ) {
			wp_delete_term( $tag_id, 'post_tag' );
		}
	}

	public function set_up() {
		parent::set_up();

		register_meta(
			'term',
			'test_single',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_meta(
			'term',
			'test_multi',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'string',
			)
		);
		register_term_meta(
			'post_tag',
			'test_tag_single',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_term_meta(
			'post_tag',
			'test_tag_multi',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'string',
			)
		);
		register_term_meta(
			'category',
			'test_cat_meta',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/tags', $routes );
		$this->assertArrayHasKey( '/wp/v2/tags/(?P<id>[\d]+)', $routes );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$tag1     = self::factory()->tag->create( array( 'name' => 'Season 5' ) );
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags/' . $tag1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame(
			array(
				'context',
				'exclude',
				'hide_empty',
				'include',
				'offset',
				'order',
				'orderby',
				'page',
				'per_page',
				'post',
				'search',
				'slug',
			),
			$keys
		);
	}

	public function test_get_items() {
		self::factory()->tag->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_items_invalid_permission_for_context() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_items_hide_empty_arg() {
		$post_id = self::factory()->post->create();
		$tag1    = self::factory()->tag->create( array( 'name' => 'Season 5' ) );
		$tag2    = self::factory()->tag->create( array( 'name' => 'The Be Sharps' ) );

		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'hide_empty', true );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'Season 5', $data[0]['name'] );
		$this->assertSame( 'The Be Sharps', $data[1]['name'] );

		// Invalid 'hide_empty' should error.
		$request->set_param( 'hide_empty', 'nothanks' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_include_query() {
		$id1 = self::factory()->tag->create();
		$id2 = self::factory()->tag->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );

		// 'orderby' => 'asc'.
		$request->set_param( 'include', array( $id2, $id1 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id1, $data[0]['id'] );

		// 'orderby' => 'include'.
		$request->set_param( 'orderby', 'include' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( $id2, $data[0]['id'] );

		// Invalid 'include' should error.
		$request->set_param( 'include', array( 'myterm' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_exclude_query() {
		$id1 = self::factory()->tag->create();
		$id2 = self::factory()->tag->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertContains( $id1, $ids );
		$this->assertContains( $id2, $ids );

		$request->set_param( 'exclude', array( $id2 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );
		$this->assertContains( $id1, $ids );
		$this->assertNotContains( $id2, $ids );

		// Invalid 'exclude' should error.
		$request->set_param( 'exclude', array( 'invalid' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_offset_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'offset', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( self::$total_tags - 1, $response->get_data() );

		// 'offset' works with 'per_page'.
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// 'offset' takes priority over 'page'.
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );

		// Invalid 'offset' should error.
		$request->set_param( 'offset', 'moreplease' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}


	public function test_get_items_orderby_args() {
		$tag1 = self::factory()->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = self::factory()->tag->create( array( 'name' => 'Zucchini' ) );

		/*
		 * Tests:
		 * - orderby
		 * - order
		 * - per_page
		 */
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Zucchini', $data[0]['name'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'Apple', $data[0]['name'] );

		// Invalid 'orderby' should error.
		$request->set_param( 'orderby', 'invalid' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_orderby_id() {
		$tag0 = self::factory()->tag->create( array( 'name' => 'Cantaloupe' ) );
		$tag1 = self::factory()->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = self::factory()->tag->create( array( 'name' => 'Banana' ) );

		// Defaults to 'orderby' => 'name', 'order' => 'asc'.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Apple', $data[0]['name'] );
		$this->assertSame( 'Banana', $data[1]['name'] );
		$this->assertSame( 'Cantaloupe', $data[2]['name'] );

		// 'orderby' => 'id', with default 'order' => 'asc'.
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Tag 0', $data[0]['name'] );
		$this->assertSame( 'Tag 1', $data[1]['name'] );
		$this->assertSame( 'Tag 2', $data[2]['name'] );

		// 'orderby' => 'id', 'order' => 'desc'.
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'id' );
		$request->set_param( 'order', 'desc' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Banana', $data[0]['name'] );
		$this->assertSame( 'Apple', $data[1]['name'] );
		$this->assertSame( 'Cantaloupe', $data[2]['name'] );
	}

	public function test_get_items_orderby_slugs() {
		self::factory()->tag->create( array( 'name' => 'Burrito' ) );
		self::factory()->tag->create( array( 'name' => 'Taco' ) );
		self::factory()->tag->create( array( 'name' => 'Chalupa' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'orderby', 'include_slugs' );
		$request->set_param( 'slug', array( 'taco', 'burrito', 'chalupa' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'taco', $data[0]['slug'] );
		$this->assertSame( 'burrito', $data[1]['slug'] );
		$this->assertSame( 'chalupa', $data[2]['slug'] );
	}

	public function test_get_items_post_args() {
		$post_id = self::factory()->post->create();
		$tag1    = self::factory()->tag->create( array( 'name' => 'DC' ) );
		$tag2    = self::factory()->tag->create( array( 'name' => 'Marvel' ) );
		self::factory()->tag->create( array( 'name' => 'Dark Horse' ) );

		wp_set_object_terms( $post_id, array( $tag1, $tag2 ), 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'DC', $data[0]['name'] );

		// Invalid 'post' should error.
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', 'invalid-post' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_terms_post_args_paging() {
		$post_id = self::factory()->post->create();

		wp_set_object_terms( $post_id, self::$tag_ids, 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $post_id );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 15 );
		$request->set_param( 'orderby', 'id' );
		$response = rest_get_server()->dispatch( $request );
		$tags     = $response->get_data();

		$this->assertNotEmpty( $tags );

		$i = 0;
		foreach ( $tags as $tag ) {
			$this->assertSame( $tag['name'], "Tag {$i}" );
			++$i;
		}

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $post_id );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 15 );
		$request->set_param( 'orderby', 'id' );
		$response = rest_get_server()->dispatch( $request );
		$tags     = $response->get_data();

		$this->assertNotEmpty( $tags );

		foreach ( $tags as $tag ) {
			$this->assertSame( $tag['name'], "Tag {$i}" );
			++$i;
		}
	}

	public function test_get_items_post_empty() {
		$post_id = self::factory()->post->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 0, $data );
	}

	public function test_get_items_custom_tax_post_args() {
		register_taxonomy( 'batman', 'post', array( 'show_in_rest' => true ) );
		$controller = new WP_REST_Terms_Controller( 'batman' );
		$controller->register_routes();
		$term1 = self::factory()->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'batman',
			)
		);
		$term2 = self::factory()->term->create(
			array(
				'name'     => 'Mask',
				'taxonomy' => 'batman',
			)
		);
		self::factory()->term->create(
			array(
				'name'     => 'Car',
				'taxonomy' => 'batman',
			)
		);
		$post_id = self::factory()->post->create();

		wp_set_object_terms( $post_id, array( $term1, $term2 ), 'batman' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/batman' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'Cape', $data[0]['name'] );
	}

	public function test_get_items_search_args() {
		$tag1 = self::factory()->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = self::factory()->tag->create( array( 'name' => 'Banana' ) );

		/*
		 * Tests:
		 * - search
		 */
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'search', 'App' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Apple', $data[0]['name'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'search', 'Garbage' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 0, $data );
	}

	public function test_get_items_slug_arg() {
		$tag1 = self::factory()->tag->create( array( 'name' => 'Apple' ) );
		$tag2 = self::factory()->tag->create( array( 'name' => 'Banana' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'slug', 'apple' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Apple', $data[0]['name'] );
	}

	public function test_get_items_slug_array_arg() {
		$id1 = self::factory()->tag->create( array( 'name' => 'Taco' ) );
		$id2 = self::factory()->tag->create( array( 'name' => 'Enchilada' ) );
		$id3 = self::factory()->tag->create( array( 'name' => 'Burrito' ) );
		self::factory()->tag->create( array( 'name' => 'Pizza' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param(
			'slug',
			array(
				'taco',
				'burrito',
				'enchilada',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data  = $response->get_data();
		$names = wp_list_pluck( $data, 'name' );
		sort( $names );
		$this->assertSame( array( 'Burrito', 'Enchilada', 'Taco' ), $names );
	}

	public function test_get_items_slug_csv_arg() {
		$id1 = self::factory()->tag->create( array( 'name' => 'Taco' ) );
		$id2 = self::factory()->tag->create( array( 'name' => 'Enchilada' ) );
		$id3 = self::factory()->tag->create( array( 'name' => 'Burrito' ) );
		self::factory()->tag->create( array( 'name' => 'Pizza' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'slug', 'taco,burrito, enchilada' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data  = $response->get_data();
		$names = wp_list_pluck( $data, 'name' );
		sort( $names );
		$this->assertSame( array( 'Burrito', 'Enchilada', 'Taco' ), $names );
	}

	public function test_get_terms_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = self::factory()->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'robin',
			)
		);
		$term2 = self::factory()->term->create(
			array(
				'name'     => 'Mask',
				'taxonomy' => 'robin',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/terms/robin' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_terms_pagination_headers( $method ) {
		$total_tags  = self::$total_tags;
		$total_pages = (int) ceil( $total_tags / 10 );

		// Start of the index.
		$request  = new WP_REST_Request( $method, '/wp/v2/tags' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_tags, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( 'wp/v2/tags' )
		);
		$this->assertStringNotContainsString( 'rel="prev"', $headers['Link'] );
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// 3rd page.
		self::factory()->tag->create();
		++$total_tags;
		++$total_pages;
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_tags, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( 'wp/v2/tags' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'page' => 4,
			),
			rest_url( 'wp/v2/tags' )
		);
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// Last page.
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'page', $total_pages );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_tags, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages - 1,
			),
			rest_url( 'wp/v2/tags' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );

		// Out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_tags, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages,
			),
			rest_url( 'wp/v2/tags' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );
	}

	public function test_get_items_invalid_context() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'context', 'banana' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_item() {
		$id = self::factory()->tag->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $id );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response, $id );
	}

	/**
	 * @ticket 39122
	 */
	public function test_get_item_meta() {
		$id = self::factory()->tag->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertSame( $meta['test_single'], '' );
		$this->assertArrayHasKey( 'test_multi', $meta );
		$this->assertSame( $meta['test_multi'], array() );
		$this->assertArrayHasKey( 'test_tag_single', $meta );
		$this->assertSame( $meta['test_tag_single'], '' );
		$this->assertArrayHasKey( 'test_tag_multi', $meta );
		$this->assertSame( $meta['test_tag_multi'], array() );
	}

	/**
	 * @ticket 39122
	 */
	public function test_get_item_meta_registered_for_different_taxonomy() {
		$id = self::factory()->tag->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_cat_meta', $meta );
	}

	public function test_get_term_invalid_term() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_invalid_permission_for_context() {
		$id = self::factory()->tag->create();

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $id );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_term_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = self::factory()->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'robin',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/terms/robin/' . $term1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_item_incorrect_taxonomy() {
		register_taxonomy( 'robin', 'post' );
		$term1 = self::factory()->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'robin',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $term1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_create_item() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'description', 'This term is so awesome.' );
		$request->set_param( 'slug', 'so-awesome' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data    = $response->get_data();
		$this->assertStringContainsString( '/wp/v2/tags/' . $data['id'], $headers['Location'] );
		$this->assertSame( 'My Awesome Term', $data['name'] );
		$this->assertSame( 'This term is so awesome.', $data['description'] );
		$this->assertSame( 'so-awesome', $data['slug'] );
	}

	public function test_create_item_contributor() {
		wp_set_current_user( self::$contributor );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'description', 'This term is so awesome.' );
		$request->set_param( 'slug', 'so-awesome' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data    = $response->get_data();
		$this->assertStringContainsString( '/wp/v2/tags/' . $data['id'], $headers['Location'] );
		$this->assertSame( 'My Awesome Term', $data['name'] );
		$this->assertSame( 'This term is so awesome.', $data['description'] );
		$this->assertSame( 'so-awesome', $data['slug'] );
	}

	public function test_create_item_incorrect_permissions() {
		wp_set_current_user( self::$subscriber );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	public function test_create_item_missing_arguments() {
		wp_set_current_user( self::$administrator );

		$request  = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	public function test_create_item_parent_non_hierarchical_taxonomy() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_taxonomy_not_hierarchical', $response, 400 );
	}

	public function test_create_item_with_meta() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'meta', array( 'test_tag_single' => 'hello' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data    = $response->get_data();
		$this->assertStringContainsString( '/wp/v2/tags/' . $data['id'], $headers['Location'] );
		$this->assertSame( 'My Awesome Term', $data['name'] );
		$this->assertSame( 'hello', get_term_meta( $data['id'], 'test_tag_single', true ) );
	}

	public function test_create_item_with_meta_wrong_id() {
		wp_set_current_user( self::$administrator );

		$existing_tag_id = self::factory()->tag->create( array( 'name' => 'My Not So Awesome Term' ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'meta', array( 'test_tag_single' => 'hello' ) );
		$request->set_param( 'id', $existing_tag_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data    = $response->get_data();
		$this->assertStringContainsString( '/wp/v2/tags/' . $data['id'], $headers['Location'] );
		$this->assertSame( 'My Awesome Term', $data['name'] );
		$this->assertSame( '', get_term_meta( $existing_tag_id, 'test_tag_single', true ) );
		$this->assertSame( 'hello', get_term_meta( $data['id'], 'test_tag_single', true ) );
	}

	public function test_update_item() {
		wp_set_current_user( self::$administrator );

		$orig_args = array(
			'name'        => 'Original Name',
			'description' => 'Original Description',
			'slug'        => 'original-slug',
		);

		$term = get_term_by( 'id', self::factory()->tag->create( $orig_args ), 'post_tag' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'slug', 'new-slug' );
		$request->set_param(
			'meta',
			array(
				'test_single'     => 'just meta',
				'test_tag_single' => 'tag-specific meta',
				'test_cat_meta'   => 'category-specific meta',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'New Name', $data['name'] );
		$this->assertSame( 'New Description', $data['description'] );
		$this->assertSame( 'new-slug', $data['slug'] );
		$this->assertSame( 'just meta', $data['meta']['test_single'] );
		$this->assertSame( 'tag-specific meta', $data['meta']['test_tag_single'] );
		$this->assertArrayNotHasKey( 'test_cat_meta', $data['meta'] );
	}

	public function test_update_item_no_change() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', self::factory()->tag->create(), 'post_tag' );

		$request  = new WP_REST_Request( 'PUT', '/wp/v2/tags/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$request->set_param( 'slug', $term->slug );

		// Run twice to make sure that the update still succeeds
		// even if no DB rows are updated.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_item_invalid_term() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'name', 'Invalid Term' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_update_item_incorrect_permissions() {
		wp_set_current_user( self::$subscriber );

		$term = get_term_by( 'id', self::factory()->tag->create(), 'post_tag' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_update', $response, 403 );
	}

	/**
	 * @ticket 38505
	 */
	public function test_update_item_with_edit_term_cap_granted() {
		wp_set_current_user( self::$subscriber );

		$term = self::factory()->tag->create_and_get();

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'name', 'New Name' );

		add_filter( 'map_meta_cap', array( $this, 'grant_edit_term' ), 10, 2 );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'user_has_cap', array( $this, 'grant_edit_term' ), 10, 2 );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'New Name', $data['name'] );
	}

	public function grant_edit_term( $caps, $cap ) {
		if ( 'edit_term' === $cap ) {
			$caps = array( 'read' );
		}
		return $caps;
	}

	/**
	 * @ticket 38505
	 */
	public function test_update_item_with_edit_term_cap_revoked() {
		wp_set_current_user( self::$administrator );

		$term = self::factory()->tag->create_and_get();

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'name', 'New Name' );

		add_filter( 'map_meta_cap', array( $this, 'revoke_edit_term' ), 10, 2 );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'user_has_cap', array( $this, 'revoke_edit_term' ), 10, 2 );

		$this->assertErrorResponse( 'rest_cannot_update', $response, 403 );
	}

	public function revoke_edit_term( $caps, $cap ) {
		if ( 'edit_term' === $cap ) {
			$caps = array( 'do_not_allow' );
		}
		return $caps;
	}

	public function test_update_item_parent_non_hierarchical_taxonomy() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', self::factory()->tag->create(), 'post_tag' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_taxonomy_not_hierarchical', $response, 400 );
	}

	public function verify_tag_roundtrip( $input = array(), $expected_output = array() ) {
		// Create the tag.
		$request = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['name'], $actual_output['name'] );
		$this->assertSame( $expected_output['description'], $actual_output['description'] );

		// Compare expected API output to WP internal values.
		$tag = get_term_by( 'id', $actual_output['id'], 'post_tag' );
		$this->assertSame( $expected_output['name'], $tag->name );
		$this->assertSame( $expected_output['description'], $tag->description );

		// Update the tag.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/tags/%d', $actual_output['id'] ) );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$actual_output = $response->get_data();

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['name'], $actual_output['name'] );
		$this->assertSame( $expected_output['description'], $actual_output['description'] );

		// Compare expected API output to WP internal values.
		$tag = get_term_by( 'id', $actual_output['id'], 'post_tag' );
		$this->assertSame( $expected_output['name'], $tag->name );
		$this->assertSame( $expected_output['description'], $tag->description );
	}

	public function test_tag_roundtrip_as_editor() {
		wp_set_current_user( self::$editor );

		$this->assertSame( ! is_multisite(), current_user_can( 'unfiltered_html' ) );
		$this->verify_tag_roundtrip(
			array(
				'name'        => '\o/ ¯\_(ツ)_/¯',
				'description' => '\o/ ¯\_(ツ)_/¯',
			),
			array(
				'name'        => '\o/ ¯\_(ツ)_/¯',
				'description' => '\o/ ¯\_(ツ)_/¯',
			)
		);
	}

	public function test_tag_roundtrip_as_editor_html() {
		wp_set_current_user( self::$editor );

		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'unfiltered_html' ) );
			$this->verify_tag_roundtrip(
				array(
					'name'        => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'name'        => 'div strong',
					'description' => 'div <strong>strong</strong> oh noes',
				)
			);
		} else {
			$this->assertTrue( current_user_can( 'unfiltered_html' ) );
			$this->verify_tag_roundtrip(
				array(
					'name'        => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'name'        => 'div strong',
					'description' => 'div <strong>strong</strong> oh noes',
				)
			);
		}
	}

	public function test_tag_roundtrip_as_superadmin() {
		wp_set_current_user( self::$superadmin );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_tag_roundtrip(
			array(
				'name'        => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				'description' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
			),
			array(
				'name'        => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
				'description' => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
			)
		);
	}

	public function test_tag_roundtrip_as_superadmin_html() {
		wp_set_current_user( self::$superadmin );

		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_tag_roundtrip(
			array(
				'name'        => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			),
			array(
				'name'        => 'div strong',
				'description' => 'div <strong>strong</strong> oh noes',
			)
		);
	}

	public function test_delete_item() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', self::factory()->tag->create( array( 'name' => 'Deleted Tag' ) ), 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Deleted Tag', $data['previous']['name'] );
	}

	public function test_delete_item_no_trash() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', self::factory()->tag->create( array( 'name' => 'Deleted Tag' ) ), 'post_tag' );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );
	}

	public function test_delete_item_invalid_term() {
		wp_set_current_user( self::$administrator );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_delete_item_incorrect_permissions() {
		wp_set_current_user( self::$subscriber );

		$term = get_term_by( 'id', self::factory()->tag->create(), 'post_tag' );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	/**
	 * @ticket 38505
	 */
	public function test_delete_item_with_delete_term_cap_granted() {
		wp_set_current_user( self::$subscriber );

		$term = get_term_by( 'id', self::factory()->tag->create( array( 'name' => 'Deleted Tag' ) ), 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'force', true );

		add_filter( 'map_meta_cap', array( $this, 'grant_delete_term' ), 10, 2 );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'map_meta_cap', array( $this, 'grant_delete_term' ), 10, 2 );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Deleted Tag', $data['previous']['name'] );
	}

	public function grant_delete_term( $caps, $cap ) {
		if ( 'delete_term' === $cap ) {
			$caps = array( 'read' );
		}
		return $caps;
	}

	/**
	 * @ticket 38505
	 */
	public function test_delete_item_with_delete_term_cap_revoked() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', self::factory()->tag->create( array( 'name' => 'Deleted Tag' ) ), 'post_tag' );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $term->term_id );
		$request->set_param( 'force', true );

		add_filter( 'map_meta_cap', array( $this, 'revoke_delete_term' ), 10, 2 );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'map_meta_cap', array( $this, 'revoke_delete_term' ), 10, 2 );

		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function revoke_delete_term( $caps, $cap ) {
		if ( 'delete_term' === $cap ) {
			$caps = array( 'do_not_allow' );
		}
		return $caps;
	}

	public function test_prepare_item() {
		$term = get_term_by( 'id', self::factory()->tag->create(), 'post_tag' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->check_taxonomy_term( $term, $data, $response->get_links() );
	}

	public function test_prepare_item_limit_fields() {
		$request  = new WP_REST_Request();
		$endpoint = new WP_REST_Terms_Controller( 'post_tag' );
		$request->set_param( '_fields', 'id,name' );
		$term     = get_term_by( 'id', self::factory()->tag->create(), 'post_tag' );
		$response = $endpoint->prepare_item_for_response( $term, $request );
		$this->assertSame(
			array(
				'id',
				'name',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 8, $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'count', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'taxonomy', $properties );
		$this->assertSame( array( 'post_tag' ), $properties['taxonomy']['enum'] );
	}

	public function test_get_item_schema_non_hierarchical() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayNotHasKey( 'parent', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'tag',
			'my_custom_int',
			array(
				'schema'       => $schema,
				'get_callback' => array( $this, 'additional_field_get_callback' ),
			)
		);

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/tags' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		$tag_id = self::factory()->tag->create();

		$request  = new WP_REST_Request( 'GET', '/wp/v2/tags/' . $tag_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

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
			'tag',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		wp_set_current_user( self::$administrator );

		$tag_id = self::factory()->tag->create();

		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/tags/%d', $tag_id ) );
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

	public function additional_field_get_callback( $response_data, $field_name ) {
		return 123;
	}

	public function additional_field_update_callback( $value, $tag ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
	}

	/**
	 * @ticket 38504
	 */
	public function test_object_term_queries_are_cached() {
		$tags = self::factory()->tag->create_many( 2 );
		$p    = self::factory()->post->create();
		wp_set_object_terms( $p, $tags[0], 'post_tag' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $p );
		$response = rest_get_server()->dispatch( $request );
		$found_1  = wp_list_pluck( $response->data, 'id' );

		unset( $request, $response );

		$num_queries = get_num_queries();

		$request = new WP_REST_Request( 'GET', '/wp/v2/tags' );
		$request->set_param( 'post', $p );
		$response = rest_get_server()->dispatch( $request );
		$found_2  = wp_list_pluck( $response->data, 'id' );

		$this->assertSameSets( $found_1, $found_2 );
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 41411
	 */
	public function test_editable_response_uses_edit_context() {
		wp_set_current_user( self::$administrator );

		$view_field = 'view_only_field';
		$edit_field = 'edit_only_field';

		register_rest_field(
			'tag',
			$view_field,
			array(
				'context'      => array( 'view' ),
				'get_callback' => '__return_empty_string',
			)
		);

		register_rest_field(
			'tag',
			$edit_field,
			array(
				'context'      => array( 'edit' ),
				'get_callback' => '__return_empty_string',
			)
		);

		$create = new WP_REST_Request( 'POST', '/wp/v2/tags' );
		$create->set_param( 'name', 'My New Term' );
		$response = rest_get_server()->dispatch( $create );
		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( $edit_field, $data );
		$this->assertArrayNotHasKey( $view_field, $data );

		$update = new WP_REST_Request( 'PUT', '/wp/v2/tags/' . $data['id'] );
		$update->set_param( 'name', 'My Awesome New Term' );
		$response = rest_get_server()->dispatch( $update );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( $edit_field, $data );
		$this->assertArrayNotHasKey( $view_field, $data );
	}

	protected function check_get_taxonomy_terms_response( $response ) {
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$args = array(
			'hide_empty' => false,
		);
		$tags = get_terms( 'post_tag', $args );
		$this->assertCount( count( $tags ), $data );
		$this->assertSame( $tags[0]->term_id, $data[0]['id'] );
		$this->assertSame( $tags[0]->name, $data[0]['name'] );
		$this->assertSame( $tags[0]->slug, $data[0]['slug'] );
		$this->assertSame( $tags[0]->taxonomy, $data[0]['taxonomy'] );
		$this->assertSame( $tags[0]->description, $data[0]['description'] );
		$this->assertSame( $tags[0]->count, $data[0]['count'] );
	}

	protected function check_taxonomy_term( $term, $data, $links ) {
		$this->assertSame( $term->term_id, $data['id'] );
		$this->assertSame( $term->name, $data['name'] );
		$this->assertSame( $term->slug, $data['slug'] );
		$this->assertSame( $term->description, $data['description'] );
		$this->assertSame( get_term_link( $term ), $data['link'] );
		$this->assertSame( $term->count, $data['count'] );
		$taxonomy = get_taxonomy( $term->taxonomy );
		if ( $taxonomy->hierarchical ) {
			$this->assertSame( $term->parent, $data['parent'] );
		} else {
			$this->assertArrayNotHasKey( 'parent', $data );
		}
		$expected_links = array(
			'self',
			'collection',
			'about',
			'https://api.w.org/post_type',
		);
		if ( $taxonomy->hierarchical && $term->parent ) {
			$expected_links[] = 'up';
		}
		$this->assertSameSets( $expected_links, array_keys( $links ) );
		$this->assertStringContainsString( 'wp/v2/taxonomies/' . $term->taxonomy, $links['about'][0]['href'] );
		$this->assertSame( add_query_arg( 'tags', $term->term_id, rest_url( 'wp/v2/posts' ) ), $links['https://api.w.org/post_type'][0]['href'] );
	}

	protected function check_get_taxonomy_term_response( $response, $id ) {

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$tag  = get_term( $id, 'post_tag' );
		$this->check_taxonomy_term( $tag, $data, $response->get_links() );
	}

	/**
	 * @dataProvider data_readable_http_methods
	 * @ticket 56481
	 *
	 * @param string $method HTTP method to use.
	 */
	public function test_get_items_returns_only_fetches_ids_for_head_requests( $method ) {
		$is_head_request = 'HEAD' === $method;
		$request         = new WP_REST_Request( $method, '/wp/v2/tags' );

		$filter = new MockAction();

		add_filter( 'terms_pre_query', array( $filter, 'filter' ), 10, 2 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		if ( $is_head_request ) {
			$this->assertEmpty( $response->get_data() );
		} else {
			$this->assertNotEmpty( $response->get_data() );
		}

		$args = $filter->get_args();
		$this->assertTrue( isset( $args[0][1] ), 'Query parameters were not captured.' );
		$this->assertInstanceOf( WP_Term_Query::class, $args[0][1], 'Query parameters were not captured.' );

		/** @var WP_Term_Query $query */
		$query = $args[0][1];

		if ( $is_head_request ) {
			$this->assertArrayHasKey( 'fields', $query->query_vars, 'The fields parameter is not set in the query vars.' );
			$this->assertSame( 'ids', $query->query_vars['fields'], 'The query must fetch only term IDs.' );
		} else {
			$this->assertTrue(
				! array_key_exists( 'fields', $query->query_vars ) || 'ids' !== $query->query_vars['fields'],
				'The fields parameter should not be forced to "ids" for non-HEAD requests.'
			);
		}

		if ( ! $is_head_request ) {
			return;
		}

		global $wpdb;
		$terms_table = preg_quote( $wpdb->terms, '/' );

		$pattern = '/SELECT\s+t\.term_id.+FROM\s+' . $terms_table . '\s+AS\s+t\s+INNER\s+JOIN/is';

		// Assert that the SQL query only fetches the term_id column.
		$this->assertMatchesRegularExpression( $pattern, $query->request, 'The SQL query does not match the expected string.' );
	}

	/**
	 * Data provider intended to provide HTTP method names for testing GET and HEAD requests.
	 *
	 * @return array
	 */
	public function data_readable_http_methods() {
		return array(
			'GET request'  => array( 'GET' ),
			'HEAD request' => array( 'HEAD' ),
		);
	}

	/**
	 * @ticket 56481
	 */
	public function test_get_item_with_head_request_should_not_prepare_tag_data() {
		$tag_id = self::factory()->tag->create();

		$request = new WP_REST_Request( 'HEAD', sprintf( '/wp/v2/tags/%d', $tag_id ) );

		$hook_name = 'rest_prepare_post_tag';

		$filter   = new MockAction();
		$callback = array( $filter, 'filter' );
		add_filter( $hook_name, $callback );
		$response = rest_get_server()->dispatch( $request );
		remove_filter( $hook_name, $callback );

		$this->assertSame( 200, $response->get_status(), 'The response status should be 200.' );

		$this->assertSame( 0, $filter->get_call_count(), 'The "' . $hook_name . '" filter was called when it should not be for HEAD requests.' );
		$this->assertNull( $response->get_data(), 'The server should not generate a body in response to a HEAD request.' );
	}
}
