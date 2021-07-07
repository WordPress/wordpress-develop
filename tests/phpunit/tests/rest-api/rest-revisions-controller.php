<?php
/**
 * Unit tests covering WP_REST_Revisions_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Revisions_Controller extends WP_Test_REST_Controller_Testcase {
	protected static $post_id;
	protected static $page_id;

	protected static $editor_id;
	protected static $contributor_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
		self::$page_id = $factory->post->create( array( 'post_type' => 'page' ) );

		self::$editor_id      = $factory->user->create(
			array(
				'role' => 'editor',
			)
		);
		self::$contributor_id = $factory->user->create(
			array(
				'role' => 'contributor',
			)
		);

		wp_set_current_user( self::$editor_id );
		wp_update_post(
			array(
				'post_content' => 'This content is better.',
				'ID'           => self::$post_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is marvelous.',
				'ID'           => self::$post_id,
			)
		);
		wp_update_post(
			array(
				'post_content' => 'This content is fantastic.',
				'ID'           => self::$post_id,
			)
		);
		wp_set_current_user( 0 );
	}

	public static function wpTearDownAfterClass() {
		// Also deletes revisions.
		wp_delete_post( self::$post_id, true );
		wp_delete_post( self::$page_id, true );

		self::delete_user( self::$editor_id );
		self::delete_user( self::$contributor_id );
	}

	public function setUp() {
		parent::setUp();

		$revisions             = wp_get_post_revisions( self::$post_id );
		$this->total_revisions = count( $revisions );
		$this->revisions       = $revisions;
		$this->revision_1      = array_pop( $revisions );
		$this->revision_id1    = $this->revision_1->ID;
		$this->revision_2      = array_pop( $revisions );
		$this->revision_id2    = $this->revision_2->ID;
		$this->revision_3      = array_pop( $revisions );
		$this->revision_id3    = $this->revision_3->ID;
	}

	public function _filter_map_meta_cap_remove_no_allow_revisions( $caps, $cap, $user_id, $args ) {
		if ( 'delete_post' !== $cap || empty( $args ) ) {
			return $caps;
		}
		$post = get_post( $args[0] );
		if ( ! $post || 'revision' !== $post->post_type ) {
			return $caps;
		}
		$key = array_search( 'do_not_allow', $caps, true );
		if ( false !== $key ) {
			unset( $caps[ $key ] );
		}
		return $caps;
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<parent>[\d]+)/revisions', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)', $routes );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_1->ID );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_get_items() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( $this->total_revisions, $data );

		// Reverse chronology.
		$this->assertSame( $this->revision_id3, $data[0]['id'] );
		$this->check_get_revision_response( $data[0], $this->revision_3 );

		$this->assertSame( $this->revision_id2, $data[1]['id'] );
		$this->check_get_revision_response( $data[1], $this->revision_2 );

		$this->assertSame( $this->revision_id1, $data[2]['id'] );
		$this->check_get_revision_response( $data[2], $this->revision_1 );
	}

	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
		wp_set_current_user( self::$contributor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_get_items_missing_parent() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_get_items_invalid_parent_post_type() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$page_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_get_item() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->check_get_revision_response( $response, $this->revision_1 );
		$fields = array(
			'author',
			'date',
			'date_gmt',
			'modified',
			'modified_gmt',
			'guid',
			'id',
			'parent',
			'slug',
			'title',
			'excerpt',
			'content',
		);
		$data   = $response->get_data();
		$this->assertSameSets( $fields, array_keys( $data ) );
		$this->assertSame( self::$editor_id, $data['author'] );
	}

	public function test_get_item_embed_context() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$request->set_param( 'context', 'embed' );
		$response = rest_get_server()->dispatch( $request );
		$fields   = array(
			'author',
			'date',
			'id',
			'parent',
			'slug',
			'title',
			'excerpt',
		);
		$data     = $response->get_data();
		$this->assertSameSets( $fields, array_keys( $data ) );
	}

	public function test_get_item_no_permission() {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
		wp_set_current_user( self::$contributor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_get_item_missing_parent() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_get_item_invalid_parent_post_type() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$page_id . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_delete_item() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
		$this->assertNotNull( get_post( $this->revision_id1 ) );
	}

	/**
	 * @ticket 49645
	 */
	public function test_delete_item_parent_check() {
		wp_set_current_user( self::$contributor_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
		$this->assertNotNull( get_post( $this->revision_id1 ) );
	}

	/**
	 * @ticket 43709
	 */
	public function test_delete_item_remove_do_not_allow() {
		wp_set_current_user( self::$editor_id );
		add_filter( 'map_meta_cap', array( $this, '_filter_map_meta_cap_remove_no_allow_revisions' ), 10, 4 );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $this->revision_id1 ) );
	}

	/**
	 * @ticket 43709
	 */
	public function test_delete_item_cannot_delete_parent() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$request->set_param( 'force', true );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
		$this->assertNotNull( get_post( $this->revision_id1 ) );
	}

	/**
	 * @ticket 38494
	 * @ticket 43709
	 */
	public function test_delete_item_no_trash() {
		wp_set_current_user( self::$editor_id );
		add_filter( 'map_meta_cap', array( $this, '_filter_map_meta_cap_remove_no_allow_revisions' ), 10, 4 );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the revision still exists.
		$this->assertNotNull( get_post( $this->revision_id1 ) );
	}

	public function test_delete_item_no_permission() {
		wp_set_current_user( self::$contributor_id );
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->check_get_revision_response( $response, $this->revision_1 );
	}

	public function test_prepare_item_limit_fields() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$endpoint = new WP_REST_Revisions_Controller( 'post' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,slug' );
		$revision = get_post( $this->revision_id1 );
		$response = $endpoint->prepare_item_for_response( $revision, $request );
		$this->assertSame(
			array(
				'id',
				'slug',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 12, $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'excerpt', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'title', $properties );
	}

	public function test_create_item() {
		$request  = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_update_item() {
		$request  = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'post-revision',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/revisions' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		wp_set_current_user( 1 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );

		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $object ) {
		return get_post_meta( $object['id'], 'my_custom_int', true );
	}

	public function additional_field_update_callback( $value, $post ) {
		update_post_meta( $post->ID, 'my_custom_int', $value );
	}

	protected function check_get_revision_response( $response, $revision ) {
		if ( $response instanceof WP_REST_Response ) {
			$links    = $response->get_links();
			$response = $response->get_data();
		} else {
			$this->assertArrayHasKey( '_links', $response );
			$links = $response['_links'];
		}

		$this->assertEquals( $revision->post_author, $response['author'] );

		$rendered_content = apply_filters( 'the_content', $revision->post_content );
		$this->assertSame( $rendered_content, $response['content']['rendered'] );

		$this->assertSame( mysql_to_rfc3339( $revision->post_date ), $response['date'] );
		$this->assertSame( mysql_to_rfc3339( $revision->post_date_gmt ), $response['date_gmt'] );

		$rendered_excerpt = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $revision->post_excerpt, $revision ) );
		$this->assertSame( $rendered_excerpt, $response['excerpt']['rendered'] );

		$rendered_guid = apply_filters( 'get_the_guid', $revision->guid, $revision->ID );
		$this->assertSame( $rendered_guid, $response['guid']['rendered'] );

		$this->assertSame( $revision->ID, $response['id'] );
		$this->assertSame( mysql_to_rfc3339( $revision->post_modified ), $response['modified'] );
		$this->assertSame( mysql_to_rfc3339( $revision->post_modified_gmt ), $response['modified_gmt'] );
		$this->assertSame( $revision->post_name, $response['slug'] );

		$rendered_title = get_the_title( $revision->ID );
		$this->assertSame( $rendered_title, $response['title']['rendered'] );

		$parent            = get_post( $revision->post_parent );
		$parent_controller = new WP_REST_Posts_Controller( $parent->post_type );
		$parent_object     = get_post_type_object( $parent->post_type );
		$parent_base       = ! empty( $parent_object->rest_base ) ? $parent_object->rest_base : $parent_object->name;
		$this->assertSame( rest_url( '/wp/v2/' . $parent_base . '/' . $revision->post_parent ), $links['parent'][0]['href'] );
	}

	public function test_get_item_sets_up_postdata() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions/' . $this->revision_id1 );
		rest_get_server()->dispatch( $request );

		$post           = get_post();
		$parent_post_id = wp_is_post_revision( $post->ID );

		$this->assertSame( $post->ID, $this->revision_id1 );
		$this->assertSame( $parent_post_id, self::$post_id );
	}

	/**
	 * Test the pagination header of the first page.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_pagination_header_of_the_first_page() {
		wp_set_current_user( self::$editor_id );

		$rest_route  = '/wp/v2/posts/' . self::$post_id . '/revisions';
		$per_page    = 2;
		$total_pages = (int) ceil( $this->total_revisions / $per_page );
		$page        = 1;  // First page.

		$request = new WP_REST_Request( 'GET', $rest_route );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $this->total_revisions, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$next_link = add_query_arg(
			array(
				'per_page' => $per_page,
				'page'     => $page + 1,
			),
			rest_url( $rest_route )
		);
		$this->assertFalse( stripos( $headers['Link'], 'rel="prev"' ) );
		$this->assertContains( '<' . $next_link . '>; rel="next"', $headers['Link'] );
	}

	/**
	 * Test the pagination header of the last page.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_pagination_header_of_the_last_page() {
		wp_set_current_user( self::$editor_id );

		$rest_route  = '/wp/v2/posts/' . self::$post_id . '/revisions';
		$per_page    = 2;
		$total_pages = (int) ceil( $this->total_revisions / $per_page );
		$page        = 2;  // Last page.

		$request = new WP_REST_Request( 'GET', $rest_route );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();
		$this->assertSame( $this->total_revisions, $headers['X-WP-Total'] );
		$this->assertSame( $total_pages, $headers['X-WP-TotalPages'] );
		$prev_link = add_query_arg(
			array(
				'per_page' => $per_page,
				'page'     => $page - 1,
			),
			rest_url( $rest_route )
		);
		$this->assertContains( '<' . $prev_link . '>; rel="prev"', $headers['Link'] );
	}

	/**
	 * Test that invalid 'per_page' query should error.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_invalid_per_page_should_error() {
		wp_set_current_user( self::$editor_id );

		$per_page        = -1; // Invalid number.
		$expected_error  = 'rest_invalid_param';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_param( 'per_page', $per_page );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test that out of bounds 'page' query should error.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_out_of_bounds_page_should_error() {
		wp_set_current_user( self::$editor_id );

		$per_page        = 2;
		$total_pages     = (int) ceil( $this->total_revisions / $per_page );
		$page            = $total_pages + 1; // Out of bound page.
		$expected_error  = 'rest_revision_invalid_page_number';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test that impossibly high 'page' query should error.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_invalid_max_pages_should_error() {
		wp_set_current_user( self::$editor_id );

		$per_page        = 2;
		$page            = REST_TESTS_IMPOSSIBLY_HIGH_NUMBER; // Invalid number.
		$expected_error  = 'rest_revision_invalid_page_number';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test the search query.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_search_query() {
		wp_set_current_user( self::$editor_id );

		$search_string    = 'better';
		$expected_count   = 1;
		$expected_content = 'This content is better.';

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_param( 'search', $search_string );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( $expected_count, $data );
		$this->assertContains( $expected_content, $data[0]['content']['rendered'] );
	}

	/**
	 * Test that the default query should fetch all revisions.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_default_query_should_fetch_all_revisons() {
		wp_set_current_user( self::$editor_id );

		$expected_count = $this->total_revisions;

		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Test that 'offset' query shouldn't work without 'per_page' (fallback -1).
	 *
	 * @ticket 40510
	 */
	public function test_get_items_offset_should_not_work_without_per_page() {
		wp_set_current_user( self::$editor_id );

		$offset         = 1;
		$expected_count = $this->total_revisions;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_param( 'offset', $offset );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Test that 'offset' query should work with 'per_page'.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_offset_should_work_with_per_page() {
		wp_set_current_user( self::$editor_id );

		$per_page       = 2;
		$offset         = 1;
		$expected_count = 2;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Test that 'offset' query should take priority over 'page'.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_offset_should_take_priority_over_page() {
		wp_set_current_user( self::$editor_id );

		$per_page       = 2;
		$offset         = 1;
		$page           = 1;
		$expected_count = 2;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}

	/**
	 * Test that 'offset' query, as the total revisions count, should return empty data.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_total_revisions_offset_should_return_empty_data() {
		wp_set_current_user( self::$editor_id );

		$per_page        = 2;
		$offset          = $this->total_revisions;
		$expected_error  = 'rest_revision_invalid_offset_number';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test that out of bound 'offset' query should error.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_out_of_bound_offset_should_error() {
		wp_set_current_user( self::$editor_id );

		$per_page        = 2;
		$offset          = $this->total_revisions + 1;
		$expected_error  = 'rest_revision_invalid_offset_number';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test that impossible high number for 'offset' query should error.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_impossible_high_number_offset_should_error() {
		wp_set_current_user( self::$editor_id );

		$per_page        = 2;
		$offset          = REST_TESTS_IMPOSSIBLY_HIGH_NUMBER;
		$expected_error  = 'rest_revision_invalid_offset_number';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test that invalid 'offset' query should error.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_invalid_offset_should_error() {
		wp_set_current_user( self::$editor_id );

		$per_page        = 2;
		$offset          = 'moreplease';
		$expected_error  = 'rest_invalid_param';
		$expected_status = 400;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => $offset,
				'per_page' => $per_page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( $expected_error, $response, $expected_status );
	}

	/**
	 * Test that out of bounds 'page' query should not error when offset is provided,
	 * because it takes precedence.
	 *
	 * @ticket 40510
	 */
	public function test_get_items_out_of_bounds_page_should_not_error_if_offset() {
		wp_set_current_user( self::$editor_id );

		$per_page       = 2;
		$total_pages    = (int) ceil( $this->total_revisions / $per_page );
		$page           = $total_pages + 1; // Out of bound page.
		$expected_count = 2;

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/revisions' );
		$request->set_query_params(
			array(
				'offset'   => 1,
				'per_page' => $per_page,
				'page'     => $page,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( $expected_count, $response->get_data() );
	}
}
