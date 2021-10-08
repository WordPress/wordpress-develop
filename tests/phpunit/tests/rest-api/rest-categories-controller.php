<?php
/**
 * Unit tests covering WP_REST_Terms_Controller functionality, used for
 * Categories.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Categories_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $administrator;
	protected static $contributor;
	protected static $subscriber;

	protected static $category_ids     = array();
	protected static $total_categories = 30;
	protected static $per_page         = 50;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$administrator = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$contributor   = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		self::$subscriber    = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		// Set up categories for pagination tests.
		for ( $i = 0; $i < self::$total_categories - 1; $i++ ) {
			$category_ids[] = $factory->category->create(
				array(
					'name' => "Category {$i}",
				)
			);
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$administrator );
		self::delete_user( self::$subscriber );

		// Remove categories for pagination tests.
		foreach ( self::$category_ids as $category_id ) {
			wp_delete_term( $category_id, 'category' );
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
			'category',
			'test_cat_single',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_term_meta(
			'category',
			'test_cat_multi',
			array(
				'show_in_rest' => true,
				'single'       => false,
				'type'         => 'string',
			)
		);
		register_term_meta(
			'post_tag',
			'test_tag_meta',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/categories', $routes );
		$this->assertArrayHasKey( '/wp/v2/categories/(?P<id>[\d]+)', $routes );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$category1 = $this->factory->category->create( array( 'name' => 'Season 5' ) );
		$request   = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories/' . $category1 );
		$response  = rest_get_server()->dispatch( $request );
		$data      = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories' );
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
				'order',
				'orderby',
				'page',
				'parent',
				'per_page',
				'post',
				'search',
				'slug',
			),
			$keys
		);
	}

	public function test_get_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'per_page', self::$per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_terms_response( $response );
	}

	public function test_get_items_invalid_permission_for_context() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_items_hide_empty_arg() {
		$post_id   = $this->factory->post->create();
		$category1 = $this->factory->category->create( array( 'name' => 'Season 5' ) );
		$category2 = $this->factory->category->create( array( 'name' => 'The Be Sharps' ) );

		$total_categories = self::$total_categories + 2;

		wp_set_object_terms( $post_id, array( $category1, $category2 ), 'category' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'hide_empty', true );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'Season 5', $data[0]['name'] );
		$this->assertSame( 'The Be Sharps', $data[1]['name'] );

		// Confirm the empty category "Uncategorized" category appears.
		$request->set_param( 'hide_empty', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( $total_categories, $data );
	}

	public function test_get_items_parent_zero_arg() {
		$parent1 = $this->factory->category->create( array( 'name' => 'Homer' ) );
		$parent2 = $this->factory->category->create( array( 'name' => 'Marge' ) );
		$this->factory->category->create(
			array(
				'name'   => 'Bart',
				'parent' => $parent1,
			)
		);
		$this->factory->category->create(
			array(
				'name'   => 'Lisa',
				'parent' => $parent2,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'parent', 0 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$args       = array(
			'hide_empty' => false,
			'parent'     => 0,
		);
		$categories = get_terms( 'category', $args );
		$this->assertSame( count( $categories ), count( $data ) );
	}

	public function test_get_items_parent_zero_arg_string() {
		$parent1 = $this->factory->category->create( array( 'name' => 'Homer' ) );
		$parent2 = $this->factory->category->create( array( 'name' => 'Marge' ) );
		$this->factory->category->create(
			array(
				'name'   => 'Bart',
				'parent' => $parent1,
			)
		);
		$this->factory->category->create(
			array(
				'name'   => 'Lisa',
				'parent' => $parent2,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'per_page', self::$per_page );
		$request->set_param( 'parent', '0' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$args       = array(
			'hide_empty' => false,
			'parent'     => 0,
		);
		$categories = get_terms( 'category', $args );
		$this->assertSame( count( $categories ), count( $data ) );
	}

	public function test_get_items_by_parent_non_found() {
		$parent1 = $this->factory->category->create( array( 'name' => 'Homer' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'parent', $parent1 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( array(), $data );
	}

	public function test_get_items_invalid_page() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'page', 0 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$data        = $response->get_data();
		$first_error = array_shift( $data['data']['params'] );
		$this->assertStringContainsString( 'page must be greater than or equal to 1', $first_error );
	}

	public function test_get_items_include_query() {
		$id1 = $this->factory->category->create();
		$id2 = $this->factory->category->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );

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
	}

	public function test_get_items_exclude_query() {
		$id1 = $this->factory->category->create();
		$id2 = $this->factory->category->create();

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
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
	}

	public function test_get_items_orderby_args() {
		$this->factory->category->create( array( 'name' => 'Apple' ) );
		$this->factory->category->create( array( 'name' => 'Banana' ) );

		/*
		 * Tests:
		 * - orderby
		 * - order
		 * - per_page
		 */
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );
		$request->set_param( 'per_page', 1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Uncategorized', $data[0]['name'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'per_page', 2 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSame( 'Apple', $data[0]['name'] );
	}

	public function test_get_items_orderby_id() {
		$this->factory->category->create( array( 'name' => 'Cantaloupe' ) );
		$this->factory->category->create( array( 'name' => 'Apple' ) );
		$this->factory->category->create( array( 'name' => 'Banana' ) );

		// Defaults to 'orderby' => 'name', 'order' => 'asc'.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Apple', $data[0]['name'] );
		$this->assertSame( 'Banana', $data[1]['name'] );
		$this->assertSame( 'Cantaloupe', $data[2]['name'] );

		// 'orderby' => 'id', with default 'order' => 'asc'.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'orderby', 'id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Category 0', $data[1]['name'] );
		$this->assertSame( 'Category 1', $data[2]['name'] );
		$this->assertSame( 'Category 2', $data[3]['name'] );

		// 'orderby' => 'id', 'order' => 'desc'.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
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
		$this->factory->category->create( array( 'name' => 'Burrito' ) );
		$this->factory->category->create( array( 'name' => 'Taco' ) );
		$this->factory->category->create( array( 'name' => 'Chalupa' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'orderby', 'include_slugs' );
		$request->set_param( 'slug', array( 'taco', 'burrito', 'chalupa' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'taco', $data[0]['slug'] );
		$this->assertSame( 'burrito', $data[1]['slug'] );
		$this->assertSame( 'chalupa', $data[2]['slug'] );
	}

	protected function post_with_categories() {
		$post_id   = $this->factory->post->create();
		$category1 = $this->factory->category->create(
			array(
				'name'        => 'DC',
				'description' => 'Purveyor of fine detective comics',
			)
		);
		$category2 = $this->factory->category->create(
			array(
				'name'        => 'Marvel',
				'description' => 'Home of the Marvel Universe',
			)
		);
		$category3 = $this->factory->category->create(
			array(
				'name'        => 'Image',
				'description' => 'American independent comic publisher',
			)
		);
		wp_set_object_terms( $post_id, array( $category1, $category2, $category3 ), 'category' );

		return $post_id;
	}

	public function test_get_items_post_args() {
		$post_id = $this->post_with_categories();

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );

		// Check ordered by name by default.
		$names = wp_list_pluck( $data, 'name' );
		$this->assertSame( array( 'DC', 'Image', 'Marvel' ), $names );
	}

	public function test_get_items_post_ordered_by_description() {
		$post_id = $this->post_with_categories();

		// Regular request.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'post', $post_id );
		$request->set_param( 'orderby', 'description' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );
		$names = wp_list_pluck( $data, 'name' );
		$this->assertSame( array( 'Image', 'Marvel', 'DC' ), $names, 'Terms should be ordered by description' );

		// Flip the order.
		$request->set_param( 'order', 'desc' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );
		$names = wp_list_pluck( $data, 'name' );
		$this->assertSame( array( 'DC', 'Marvel', 'Image' ), $names, 'Terms should be reverse-ordered by description' );
	}

	public function test_get_items_post_ordered_by_id() {
		$post_id = $this->post_with_categories();

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'post', $post_id );
		$request->set_param( 'orderby', 'id' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 3, $data );
		$names = wp_list_pluck( $data, 'name' );
		$this->assertSame( array( 'DC', 'Marvel', 'Image' ), $names );
	}

	public function test_get_items_custom_tax_post_args() {
		register_taxonomy( 'batman', 'post', array( 'show_in_rest' => true ) );
		$controller = new WP_REST_Terms_Controller( 'batman' );
		$controller->register_routes();
		$term1 = $this->factory->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'batman',
			)
		);
		$term2 = $this->factory->term->create(
			array(
				'name'     => 'Mask',
				'taxonomy' => 'batman',
			)
		);
		$this->factory->term->create(
			array(
				'name'     => 'Car',
				'taxonomy' => 'batman',
			)
		);
		$post_id = $this->factory->post->create();
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
		$this->factory->category->create( array( 'name' => 'Apple' ) );
		$this->factory->category->create( array( 'name' => 'Banana' ) );

		/*
		 * Tests:
		 * - search
		 */
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'search', 'App' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Apple', $data[0]['name'] );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'search', 'Garbage' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 0, $data );
	}

	public function test_get_items_slug_arg() {
		$this->factory->category->create( array( 'name' => 'Apple' ) );
		$this->factory->category->create( array( 'name' => 'Banana' ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'slug', 'apple' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Apple', $data[0]['name'] );
	}

	public function test_get_terms_parent_arg() {
		$category1 = $this->factory->category->create( array( 'name' => 'Parent' ) );
		$this->factory->category->create(
			array(
				'name'   => 'Child',
				'parent' => $category1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'parent', $category1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( 'Child', $data[0]['name'] );
	}

	public function test_get_terms_invalid_parent_arg() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'parent', 'invalid-parent' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_terms_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$this->factory->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'robin',
			)
		);
		$this->factory->term->create(
			array(
				'name'     => 'Mask',
				'taxonomy' => 'robin',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/terms/robin' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_terms_invalid_taxonomy() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/invalid-taxonomy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_terms_pagination_headers() {
		$total_categories = self::$total_categories;
		$total_pages      = (int) ceil( $total_categories / 10 );

		// Start of the index + Uncategorized default term.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_categories, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$this->assertCount( 10, $response->get_data() );
		$next_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( 'wp/v2/categories' )
		);
		$this->assertStringNotContainsString( 'rel="prev"', $headers['Link'] );
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// 3rd page.
		$this->factory->category->create();
		$total_categories++;
		$total_pages++;
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'page', 3 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_categories, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$this->assertCount( 10, $response->get_data() );
		$prev_link = add_query_arg(
			array(
				'page' => 2,
			),
			rest_url( 'wp/v2/categories' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$next_link = add_query_arg(
			array(
				'page' => 4,
			),
			rest_url( 'wp/v2/categories' )
		);
		$this->assertStringContainsString( '<' . $next_link . '>; rel="next"', $headers['Link'] );

		// Last page.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'page', $total_pages );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_categories, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$this->assertCount( 1, $response->get_data() );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages - 1,
			),
			rest_url( 'wp/v2/categories' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );

		// Out of bounds.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $total_categories, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$this->assertCount( 0, $response->get_data() );
		$prev_link = add_query_arg(
			array(
				'page' => $total_pages,
			),
			rest_url( 'wp/v2/categories' )
		);
		$this->assertStringContainsString( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
		$this->assertStringNotContainsString( 'rel="next"', $headers['Link'] );
	}

	public function test_get_items_per_page_exceeds_number_of_items() {
		// Start of the index + Uncategorized default term.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( self::$total_categories, $headers['X-WP-Total'] );
		$this->assertSame( 1, $headers['X-WP-TotalPages'] );
		$this->assertCount( self::$total_categories, $response->get_data() );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 100 );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( self::$total_categories, $headers['X-WP-Total'] );
		$this->assertSame( 1, $headers['X-WP-TotalPages'] );
		$this->assertCount( 0, $response->get_data() );
	}

	public function test_get_item() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/1' );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_taxonomy_term_response( $response );
	}

	/**
	 * @ticket 39122
	 */
	public function test_get_item_meta() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayHasKey( 'test_single', $meta );
		$this->assertSame( $meta['test_single'], '' );
		$this->assertArrayHasKey( 'test_multi', $meta );
		$this->assertSame( $meta['test_multi'], array() );
		$this->assertArrayHasKey( 'test_cat_single', $meta );
		$this->assertSame( $meta['test_cat_single'], '' );
		$this->assertArrayHasKey( 'test_cat_multi', $meta );
		$this->assertSame( $meta['test_cat_multi'], array() );
	}

	/**
	 * @ticket 39122
	 */
	public function test_get_item_meta_registered_for_different_taxonomy() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );

		$meta = (array) $data['meta'];
		$this->assertArrayNotHasKey( 'test_tag_meta', $meta );
	}

	public function test_get_term_invalid_taxonomy() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/invalid-taxonomy/1' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_term_invalid_term() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_get_item_invalid_permission_for_context() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/categories/1' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_forbidden_context', $response, 401 );
	}

	public function test_get_term_private_taxonomy() {
		register_taxonomy( 'robin', 'post', array( 'public' => false ) );
		$term1 = $this->factory->term->create(
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
		$term1 = $this->factory->term->create(
			array(
				'name'     => 'Cape',
				'taxonomy' => 'robin',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/' . $term1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_create_item() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'description', 'This term is so awesome.' );
		$request->set_param( 'slug', 'so-awesome' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$headers = $response->get_headers();
		$data    = $response->get_data();
		$this->assertStringContainsString( '/wp/v2/categories/' . $data['id'], $headers['Location'] );
		$this->assertSame( 'My Awesome Term', $data['name'] );
		$this->assertSame( 'This term is so awesome.', $data['description'] );
		$this->assertSame( 'so-awesome', $data['slug'] );
	}

	/**
	 * @ticket 41370
	 */
	public function test_create_item_term_already_exists() {
		wp_set_current_user( self::$administrator );

		$existing_id = $this->factory->category->create( array( 'name' => 'Existing' ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$request->set_param( 'name', 'Existing' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'term_exists', $data['code'] );
		$this->assertSame( $existing_id, (int) $data['data']['term_id'] );

		wp_delete_term( $existing_id, 'category' );
	}

	public function test_create_item_invalid_taxonomy() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/invalid-taxonomy' );
		$request->set_param( 'name', 'Invalid Taxonomy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_create_item_incorrect_permissions() {
		wp_set_current_user( self::$subscriber );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	public function test_create_item_incorrect_permissions_contributor() {
		wp_set_current_user( self::$contributor );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	public function test_create_item_missing_arguments() {
		wp_set_current_user( self::$administrator );

		$request  = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	public function test_create_item_with_parent() {
		wp_set_current_user( self::$administrator );

		$parent = wp_insert_term( 'test-category', 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', $parent['term_id'] );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $parent['term_id'], $data['parent'] );
	}

	public function test_create_item_invalid_parent() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 400 );
	}

	public function test_create_item_with_no_parent() {
		wp_set_current_user( self::$administrator );

		$parent = 0;

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories' );
		$request->set_param( 'name', 'My Awesome Term' );
		$request->set_param( 'parent', $parent );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $parent, $data['parent'] );
	}

	public function test_update_item() {
		wp_set_current_user( self::$administrator );

		$orig_args = array(
			'name'        => 'Original Name',
			'description' => 'Original Description',
			'slug'        => 'original-slug',
		);

		$term = get_term_by( 'id', $this->factory->category->create( $orig_args ), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'name', 'New Name' );
		$request->set_param( 'description', 'New Description' );
		$request->set_param( 'slug', 'new-slug' );
		$request->set_param(
			'meta',
			array(
				'test_single'     => 'just meta',
				'test_cat_single' => 'category-specific meta',
				'test_tag_meta'   => 'tag-specific meta',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'New Name', $data['name'] );
		$this->assertSame( 'New Description', $data['description'] );
		$this->assertSame( 'new-slug', $data['slug'] );
		$this->assertSame( 'just meta', $data['meta']['test_single'] );
		$this->assertSame( 'category-specific meta', $data['meta']['test_cat_single'] );
		$this->assertArrayNotHasKey( 'test_tag_meta', $data['meta'] );
	}

	public function test_update_item_invalid_taxonomy() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/invalid-taxonomy/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'name', 'Invalid Taxonomy' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_update_item_invalid_term() {
		wp_set_current_user( self::$administrator );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'name', 'Invalid Term' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_update_item_incorrect_permissions() {
		wp_set_current_user( self::$subscriber );

		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'name', 'Incorrect permissions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_update', $response, 403 );
	}

	public function test_update_item_parent() {
		wp_set_current_user( self::$administrator );

		$parent = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$term   = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'parent', $parent->term_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $parent->term_id, $data['parent'] );
	}

	public function test_update_item_remove_parent() {
		wp_set_current_user( self::$administrator );

		$old_parent_term = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$new_parent_id   = 0;

		$term = get_term_by(
			'id',
			$this->factory->category->create(
				array(
					'parent' => $old_parent_term->term_id,
				)
			),
			'category'
		);

		$this->assertSame( $old_parent_term->term_id, $term->parent );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'parent', $new_parent_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $new_parent_id, $data['parent'] );
	}

	public function test_update_item_invalid_parent() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', $this->factory->category->create(), 'category' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 400 );
	}

	public function test_delete_item() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', $this->factory->category->create( array( 'name' => 'Deleted Category' ) ), 'category' );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/categories/' . $term->term_id );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'Deleted Category', $data['previous']['name'] );
	}

	public function test_delete_item_no_trash() {
		wp_set_current_user( self::$administrator );

		$term = get_term_by( 'id', $this->factory->category->create( array( 'name' => 'Deleted Category' ) ), 'category' );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/categories/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );
	}

	public function test_delete_item_invalid_taxonomy() {
		wp_set_current_user( self::$administrator );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/invalid-taxonomy/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_delete_item_invalid_term() {
		wp_set_current_user( self::$administrator );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/categories/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_term_invalid', $response, 404 );
	}

	public function test_delete_item_incorrect_permissions() {
		wp_set_current_user( self::$subscriber );

		$term     = get_term_by( 'id', $this->factory->category->create(), 'category' );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/categories/' . $term->term_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_prepare_item() {
		$term = get_term( 1, 'category' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/1' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->check_taxonomy_term( $term, $data, $response->get_links() );
	}

	public function test_prepare_item_limit_fields() {
		$request  = new WP_REST_Request;
		$endpoint = new WP_REST_Terms_Controller( 'category' );
		$request->set_param( '_fields', 'id,name' );
		$term     = get_term( 1, 'category' );
		$response = $endpoint->prepare_item_for_response( $term, $request );
		$this->assertSame(
			array(
				'id',
				'name',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_prepare_taxonomy_term_child() {
		$child = $this->factory->category->create(
			array(
				'parent' => 1,
			)
		);
		$term  = get_term( $child, 'category' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/categories/' . $child );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->check_taxonomy_term( $term, $data, $response->get_links() );

		$this->assertSame( 1, $data['parent'] );

		$links = $response->get_links();
		$this->assertSame( rest_url( 'wp/v2/categories/1' ), $links['up'][0]['href'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 9, $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'count', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'taxonomy', $properties );
		$this->assertSame( array( 'category' ), $properties['taxonomy']['enum'] );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'category',
			'my_custom_int',
			array(
				'schema'       => $schema,
				'get_callback' => array( $this, 'additional_field_get_callback' ),
			)
		);

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/categories' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		$category_id = $this->factory->category->create();
		$request     = new WP_REST_Request( 'GET', '/wp/v2/categories/' . $category_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object, $request ) {
		return 123;
	}

	protected function check_get_taxonomy_terms_response( $response ) {
		$this->assertSame( 200, $response->get_status() );
		$data       = $response->get_data();
		$args       = array(
			'hide_empty' => false,
		);
		$categories = get_terms( 'category', $args );
		$this->assertSame( count( $categories ), count( $data ) );
		$this->assertSame( $categories[0]->term_id, $data[0]['id'] );
		$this->assertSame( $categories[0]->name, $data[0]['name'] );
		$this->assertSame( $categories[0]->slug, $data[0]['slug'] );
		$this->assertSame( $categories[0]->taxonomy, $data[0]['taxonomy'] );
		$this->assertSame( $categories[0]->description, $data[0]['description'] );
		$this->assertSame( $categories[0]->count, $data[0]['count'] );
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
			$this->assertObjectNotHasProperty( 'parent', $term );
		}

		$relations = array(
			'self',
			'collection',
			'about',
			'https://api.w.org/post_type',
		);

		if ( ! empty( $data['parent'] ) ) {
			$relations[] = 'up';
		}

		$this->assertSameSets( $relations, array_keys( $links ) );
		$this->assertStringContainsString( 'wp/v2/taxonomies/' . $term->taxonomy, $links['about'][0]['href'] );
		$this->assertSame( add_query_arg( 'categories', $term->term_id, rest_url( 'wp/v2/posts' ) ), $links['https://api.w.org/post_type'][0]['href'] );
	}

	protected function check_get_taxonomy_term_response( $response ) {

		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$category = get_term( 1, 'category' );
		$this->check_taxonomy_term( $category, $data, $response->get_links() );
	}
}
