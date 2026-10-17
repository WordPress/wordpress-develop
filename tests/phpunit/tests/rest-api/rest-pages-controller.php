<?php
/**
 * Unit tests covering WP_REST_Posts_Controller functionality, used for
 * Pages
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Pages_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {
	protected static $admin_id;
	protected static $editor_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id  = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$editor_id = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$editor_id );
	}

	public function set_up() {
		parent::set_up();
		add_filter( 'theme_page_templates', array( $this, 'filter_theme_page_templates' ) );
		// Re-register the route as we now have a template available.
		$GLOBALS['wp_rest_server']->override_by_default = true;
		$controller                                     = new WP_REST_Posts_Controller( 'page' );
		$controller->register_routes();
		$GLOBALS['wp_rest_server']->override_by_default = false;
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/pages', $routes );
		$this->assertCount( 2, $routes['/wp/v2/pages'] );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/pages/(?P<id>[\d]+)'] );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$page_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/pages/' . $page_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
		$keys = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame(
			array(
				'after',
				'author',
				'author_exclude',
				'before',
				'context',
				'exclude',
				'include',
				'menu_order',
				'modified_after',
				'modified_before',
				'offset',
				'order',
				'orderby',
				'page',
				'parent',
				'parent_exclude',
				'per_page',
				'search',
				'search_columns',
				'search_semantics',
				'slug',
				'status',
			),
			$keys
		);
	}

	public function test_get_items() {
		$id1      = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id2      = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_type'   => 'page',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id1, $data[0]['id'] );
	}

	public function test_get_items_parent_query() {
		$id1 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id2 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_parent' => $id1,
			)
		);

		// No parent.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );

		// Filter to parent.
		$request->set_param( 'parent', $id1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );

		// Invalid 'parent' should error.
		$request->set_param( 'parent', 'some-slug' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_parents_query() {
		$id1 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id2 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_parent' => $id1,
			)
		);
		$id3 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id4 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_parent' => $id3,
			)
		);

		// No parent.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 4, $data );

		// Filter to parents.
		$request->set_param( 'parent', array( $id1, $id3 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );
		$this->assertSameSets( array( $id2, $id4 ), wp_list_pluck( $data, 'id' ) );
	}

	public function test_get_items_parent_exclude_query() {
		$id1 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'post_parent' => $id1,
			)
		);

		// No parent.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data );

		// Filter to parent.
		$request->set_param( 'parent_exclude', $id1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id1, $data[0]['id'] );

		// Invalid 'parent_exclude' should error.
		$request->set_param( 'parent_exclude', 'some-slug' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_menu_order_query() {
		$id1 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$id2 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'menu_order'  => 2,
			)
		);
		$id3 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'menu_order'  => 3,
			)
		);
		$id4 = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
				'menu_order'  => 1,
			)
		);

		// No parent.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameSets( array( $id1, $id2, $id3, $id4 ), wp_list_pluck( $data, 'id' ) );

		// Filter to 'menu_order'.
		$request->set_param( 'menu_order', 1 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSameSets( array( $id4 ), wp_list_pluck( $data, 'id' ) );

		// Order by 'menu order'.
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'orderby', 'menu_order' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $id1, $data[0]['id'] );
		$this->assertSame( $id4, $data[1]['id'] );
		$this->assertSame( $id2, $data[2]['id'] );
		$this->assertSame( $id3, $data[3]['id'] );

		// Invalid 'menu_order' should error.
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'menu_order', 'top-first' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_min_max_pages_query() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'per_page', 0 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$data = $response->get_data();
		// Safe format for 4.4 and 4.5. See https://core.trac.wordpress.org/ticket/35028
		$first_error = array_shift( $data['data']['params'] );
		$this->assertStringContainsString( 'per_page must be between 1 (inclusive) and 100 (inclusive)', $first_error );
		$request->set_param( 'per_page', 101 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$data        = $response->get_data();
		$first_error = array_shift( $data['data']['params'] );
		$this->assertStringContainsString( 'per_page must be between 1 (inclusive) and 100 (inclusive)', $first_error );
	}

	public function test_get_items_private_filter_query_var() {
		// Private query vars inaccessible to unauthorized users.
		wp_set_current_user( 0 );
		$page_id  = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$draft_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_type'   => 'page',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'status', 'draft' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// But they are accessible to authorized users.
		wp_set_current_user( self::$editor_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $draft_id, $data[0]['id'] );
	}

	public function test_get_items_invalid_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'after', 'foo' );
		$request->set_param( 'before', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_valid_date() {
		$post1   = self::factory()->post->create(
			array(
				'post_date' => '2016-01-15T00:00:00Z',
				'post_type' => 'page',
			)
		);
		$post2   = self::factory()->post->create(
			array(
				'post_date' => '2016-01-16T00:00:00Z',
				'post_type' => 'page',
			)
		);
		$post3   = self::factory()->post->create(
			array(
				'post_date' => '2016-01-17T00:00:00Z',
				'post_type' => 'page',
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $post2, $data[0]['id'] );
	}

	/**
	 * @ticket 50617
	 */
	public function test_get_items_invalid_modified_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'modified_after', 'foo' );
		$request->set_param( 'modified_before', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 50617
	 */
	public function test_get_items_valid_modified_date() {
		$post1 = self::factory()->post->create(
			array(
				'post_date' => '2016-01-01 00:00:00',
				'post_type' => 'page',
			)
		);
		$post2 = self::factory()->post->create(
			array(
				'post_date' => '2016-01-02 00:00:00',
				'post_type' => 'page',
			)
		);
		$post3 = self::factory()->post->create(
			array(
				'post_date' => '2016-01-03 00:00:00',
				'post_type' => 'page',
			)
		);
		$this->update_post_modified( $post1, '2016-01-15 00:00:00' );
		$this->update_post_modified( $post2, '2016-01-16 00:00:00' );
		$this->update_post_modified( $post3, '2016-01-17 00:00:00' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'modified_after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'modified_before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $post2, $data[0]['id'] );
	}

	/**
	 * @ticket 40538
	 */
	public function test_get_item() {
		$page_id = self::factory()->post->create(
			array(
				'post_title'   => 'Page Title',
				'post_content' => 'Page content',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $page_id, $data['id'] );
		$this->assertSame( 'page', $data['type'] );
		$this->assertSame( 'Page Title', $data['title']['rendered'] );
		$this->assertSame( wpautop( 'Page content' ), $data['content']['rendered'] );
	}

	public function test_get_item_invalid_post_type() {
		$post_id  = self::factory()->post->create();
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * @ticket 40538
	 */
	public function test_create_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params(
			array(
				'title'   => 'Page Title',
				'content' => 'Page content',
				'slug'    => 'page-slug',
				'status'  => 'publish',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'page', $data['type'] );
		$this->assertSame( 'Page Title', $data['title']['raw'] );
		$this->assertSame( 'Page content', $data['content']['raw'] );
		$this->assertSame( 'page-slug', $data['slug'] );
		$this->assertSame( 'publish', $data['status'] );
		$this->assertSame( self::$editor_id, $data['author'] );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Location', $headers );
		$this->assertSame( rest_url( sprintf( 'wp/v2/pages/%d', $data['id'] ) ), $headers['Location'] );

		$page = get_post( $data['id'] );
		$this->assertInstanceOf( WP_Post::class, $page );
		$this->assertSame( 'page', $page->post_type );
		$this->assertSame( 'Page Title', $page->post_title );
		$this->assertSame( 'Page content', $page->post_content );
		$this->assertSame( 'page-slug', $page->post_name );
		$this->assertSame( 'publish', $page->post_status );
		$this->assertSame( self::$editor_id, (int) $page->post_author );
	}

	public function test_create_item_with_template() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$params  = $this->set_post_data(
			array(
				'template' => 'page-my-test-template.php',
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( 'page-my-test-template.php', $data['template'] );
		$this->assertSame( 'page-my-test-template.php', get_page_template_slug( $new_post->ID ) );
	}

	public function test_create_page_with_parent() {
		$page_id = self::factory()->post->create(
			array(
				'type' => 'page',
			)
		);
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$params  = $this->set_post_data(
			array(
				'parent' => $page_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$links = $response->get_links();
		$this->assertArrayHasKey( 'up', $links );

		$data     = $response->get_data();
		$new_post = get_post( $data['id'] );
		$this->assertSame( $page_id, $data['parent'] );
		$this->assertSame( $page_id, $new_post->post_parent );
	}

	public function test_create_page_with_invalid_parent() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$params  = $this->set_post_data(
			array(
				'parent' => -1,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 400 );
	}

	/**
	 * @ticket 40538
	 */
	public function test_update_item() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Original title',
				'post_content' => 'Original content',
				'post_name'    => 'original-slug',
			)
		);
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params(
			array(
				'title'   => 'Updated title',
				'content' => 'Updated content',
				'slug'    => 'updated-slug',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'Location', $response->get_headers() );

		$data = $response->get_data();
		$this->assertSame( $page_id, $data['id'] );
		$this->assertSame( 'page', $data['type'] );
		$this->assertSame( 'Updated title', $data['title']['raw'] );
		$this->assertSame( 'Updated content', $data['content']['raw'] );
		$this->assertSame( 'updated-slug', $data['slug'] );
		$this->assertSame( 'publish', $data['status'], 'Fields that are not sent should be unchanged.' );

		$page = get_post( $page_id );
		$this->assertSame( 'page', $page->post_type );
		$this->assertSame( 'Updated title', $page->post_title );
		$this->assertSame( 'Updated content', $page->post_content );
		$this->assertSame( 'updated-slug', $page->post_name );
		$this->assertSame( 'publish', $page->post_status );
	}

	public function test_delete_item() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Deleted page',
			)
		);
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Deleted page', $data['title']['raw'] );
		$this->assertSame( 'trash', $data['status'] );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$editor_id );
		$page_id  = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$endpoint = new WP_REST_Posts_Controller( 'page' );
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,slug' );
		$obj      = get_post( $page_id );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertSame(
			array(
				'id',
				'slug',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_get_pages_params() {
		self::factory()->post->create_many(
			8,
			array(
				'post_type' => 'page',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_query_params(
			array(
				'page'     => 2,
				'per_page' => 4,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertSame( 8, $headers['X-WP-Total'] );
		$this->assertSame( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$this->assertCount( 4, $all_data );
		foreach ( $all_data as $post ) {
			$this->assertSame( 'page', $post['type'] );
		}
	}

	public function test_update_page_menu_order() {

		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $page_id ) );

		$request->set_body_params(
			array(
				'menu_order' => 1,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertSame( 1, $new_data['menu_order'] );
	}

	public function test_update_page_menu_order_to_zero() {

		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'menu_order' => 1,
			)
		);

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $page_id ) );

		$request->set_body_params(
			array(
				'menu_order' => 0,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$new_data = $response->get_data();
		$this->assertSame( 0, $new_data['menu_order'] );
	}

	public function test_update_page_parent_non_zero() {
		$page_id1 = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$page_id2 = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $page_id2 ) );
		$request->set_body_params(
			array(
				'parent' => $page_id1,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( $page_id1, $new_data['parent'] );
	}

	public function test_update_page_parent_zero() {
		$page_id1 = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$page_id2 = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $page_id1,
			)
		);
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $page_id2 ) );
		$request->set_body_params(
			array(
				'parent' => 0,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$this->assertSame( 0, $new_data['parent'] );
	}

	public function test_get_page_with_password() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_password' => '$inthebananastand',
			)
		);

		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( '', $data['content']['rendered'] );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( '', $data['excerpt']['rendered'] );
		$this->assertTrue( $data['excerpt']['protected'] );
	}

	public function test_get_page_with_password_using_password() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_password' => '$inthebananastand',
				'post_content'  => 'Some secret content.',
				'post_excerpt'  => 'Some secret excerpt.',
			)
		);

		$page    = get_post( $page_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$request->set_param( 'password', '$inthebananastand' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertSame( wpautop( $page->post_content ), $data['content']['rendered'] );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( wpautop( $page->post_excerpt ), $data['excerpt']['rendered'] );
		$this->assertTrue( $data['excerpt']['protected'] );
	}

	public function test_get_page_with_password_using_incorrect_password() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_password' => '$inthebananastand',
			)
		);

		$page    = get_post( $page_id );
		$request = new WP_REST_Request( 'GET', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$request->set_param( 'password', 'wrongpassword' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_incorrect_password', $response, 403 );
	}

	public function test_get_page_with_password_without_permission() {
		$page_id  = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_password' => '$inthebananastand',
				'post_content'  => 'Some secret content.',
				'post_excerpt'  => 'Some secret excerpt.',
			)
		);
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/pages/%d', $page_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( '', $data['content']['rendered'] );
		$this->assertTrue( $data['content']['protected'] );
		$this->assertSame( '', $data['excerpt']['rendered'] );
		$this->assertTrue( $data['excerpt']['protected'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/pages' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 25, $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'generated_slug', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'featured_media', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'menu_order', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'permalink_template', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'template', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'class_list', $properties );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_schema_links
	 */
	public function test_homepage_action_ldos_registered() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/pages' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema, 'The schema should have links.' );
		$set_homepage   = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-set-homepage' ) );
		$set_posts_page = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-set-posts-page' ) );

		$this->assertCount( 1, $set_homepage, 'The action-set-homepage LDO should be on the schema once.' );
		$this->assertCount( 1, $set_posts_page, 'The action-set-posts-page LDO should be on the schema once.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_schema_links
	 */
	public function test_homepage_action_ldos_not_registered_for_posts() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/posts' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema, 'The schema should have links.' );
		$set_homepage   = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-set-homepage' ) );
		$set_posts_page = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-set-posts-page' ) );

		$this->assertCount( 0, $set_homepage, 'The action-set-homepage LDO should not be on the schema.' );
		$this->assertCount( 0, $set_posts_page, 'The action-set-posts-page LDO should not be on the schema.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_exist_for_admin() {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$links   = $this->get_response_links( "/wp/v2/pages/{$page_id}" );

		$this->assertArrayHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should exist.' );
		$this->assertArrayHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should exist.' );
		$this->assertSame( $links['self'][0]['href'], $links['https://api.w.org/action-set-homepage'][0]['href'], 'The action-set-homepage link should target the page.' );
		$this->assertSame( $links['self'][0]['href'], $links['https://api.w.org/action-set-posts-page'][0]['href'], 'The action-set-posts-page link should target the page.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_only_available_in_edit_context() {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$links   = $this->get_response_links( "/wp/v2/pages/{$page_id}", 'view' );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should not exist.' );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should not exist.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_do_not_exist_for_editor() {
		wp_set_current_user( self::$editor_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$links   = $this->get_response_links( "/wp/v2/pages/{$page_id}" );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should not exist.' );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should not exist.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @dataProvider data_unpublished_page_statuses
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 *
	 * @param string $status Post status.
	 */
	public function test_homepage_action_links_do_not_exist_for_unpublished_pages( $status ) {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $status,
			)
		);

		$links = $this->get_response_links( "/wp/v2/pages/{$page_id}" );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should not exist.' );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should not exist.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unpublished_page_statuses() {
		return array(
			'draft'   => array( 'draft' ),
			'pending' => array( 'pending' ),
			'private' => array( 'private' ),
		);
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_do_not_exist_for_homepage() {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		// Reload the options from the database, so get_option() returns strings, as on a real request.
		wp_cache_delete( 'alloptions', 'options' );

		$links = $this->get_response_links( "/wp/v2/pages/{$page_id}" );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should not exist.' );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should not exist.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_do_not_exist_for_posts_page() {
		wp_set_current_user( self::$admin_id );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $page_id );

		// Reload the options from the database, so get_option() returns strings, as on a real request.
		wp_cache_delete( 'alloptions', 'options' );

		$links = $this->get_response_links( "/wp/v2/pages/{$page_id}" );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should not exist.' );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should not exist.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_exist_for_other_pages() {
		wp_set_current_user( self::$admin_id );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		update_option( 'page_for_posts', self::factory()->post->create( array( 'post_type' => 'page' ) ) );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$links   = $this->get_response_links( "/wp/v2/pages/{$page_id}" );

		$this->assertArrayHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should exist.' );
		$this->assertArrayHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should exist.' );
	}

	/**
	 * @ticket 66327
	 *
	 * @covers WP_REST_Posts_Controller::get_available_actions
	 */
	public function test_homepage_action_links_do_not_exist_for_posts() {
		wp_set_current_user( self::$admin_id );

		$post_id = self::factory()->post->create();
		$links   = $this->get_response_links( "/wp/v2/posts/{$post_id}" );

		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-homepage', $links, 'The action-set-homepage link should not exist.' );
		$this->assertArrayNotHasKey( 'https://api.w.org/action-set-posts-page', $links, 'The action-set-posts-page link should not exist.' );
	}

	/**
	 * Requests a post and returns the links of the response.
	 *
	 * @param string $route   Route of the post.
	 * @param string $context Optional. Request context. Default 'edit'.
	 * @return array Links of the response.
	 */
	protected function get_response_links( $route, $context = 'edit' ) {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_query_params( array( 'context' => $context ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'The post should be found.' );

		return $response->get_links();
	}

	public function filter_theme_page_templates( $page_templates ) {
		return array(
			'page-my-test-template.php' => 'My Test Template',
		);
		return $page_templates;
	}

	protected function set_post_data( $args = array() ) {
		$args         = parent::set_post_data( $args );
		$args['type'] = 'page';
		return $args;
	}
}
