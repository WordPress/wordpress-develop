<?php
/**
 * Unit tests covering WP_REST_Autosaves_Controller functionality.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi-autosave
 * @group restapi
 */
class WP_Test_REST_Autosaves_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {
	protected static $post_id;
	protected static $page_id;
	protected static $draft_page_id;

	protected static $autosave_post_id;
	protected static $autosave_page_id;

	protected static $editor_id;
	protected static $contributor_id;

	protected static $parent_page_id;
	protected static $child_page_id;
	protected static $child_draft_page_id;

	private $post_autosave;

	protected function set_post_data( $args = array() ) {
		$defaults = array(
			'title'   => 'Post Title',
			'content' => 'Post content',
			'excerpt' => 'Post excerpt',
			'name'    => 'test',
			'author'  => get_current_user_id(),
		);

		return wp_parse_args( $args, $defaults );
	}

	protected function check_create_autosave_response( $response ) {
		$this->assertNotWPError( $response );
		$response = rest_ensure_response( $response );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'content', $data );
		$this->assertArrayHasKey( 'excerpt', $data );
		$this->assertArrayHasKey( 'title', $data );
	}

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

		// Create an autosave.
		self::$autosave_post_id = wp_create_post_autosave(
			array(
				'post_content' => 'This content is better.',
				'post_ID'      => self::$post_id,
				'post_type'    => 'post',
			)
		);

		self::$autosave_page_id = wp_create_post_autosave(
			array(
				'post_content' => 'This content is better.',
				'post_ID'      => self::$page_id,
				'post_type'    => 'post',
			)
		);

		self::$draft_page_id       = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		self::$parent_page_id      = $factory->post->create(
			array(
				'post_type' => 'page',
			)
		);
		self::$child_page_id       = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => self::$parent_page_id,
			)
		);
		self::$child_draft_page_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => self::$parent_page_id,
				// The "update post" behavior of the autosave endpoint only occurs
				// when saving a draft/auto-draft authored by the current user.
				'post_status' => 'draft',
				'post_author' => self::$editor_id,
			)
		);
	}

	public static function wpTearDownAfterClass() {
		// Also deletes revisions.
		wp_delete_post( self::$post_id, true );
		wp_delete_post( self::$page_id, true );

		self::delete_user( self::$editor_id );
		self::delete_user( self::$contributor_id );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$editor_id );

		$this->post_autosave = wp_get_post_autosave( self::$post_id );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<id>[\d]+)/autosaves', $routes );
		$this->assertArrayHasKey( '/wp/v2/posts/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<id>[\d]+)/autosaves', $routes );
		$this->assertArrayHasKey( '/wp/v2/pages/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)', $routes );
	}

	public function test_context_param() {

		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSameSets( array( 'view', 'edit', 'embed' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame(
			array(
				'context',
				'parent',
			),
			$keys
		);
	}

	public function test_get_items() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );

		$this->assertSame( self::$autosave_post_id, $data[0]['id'] );

		$this->check_get_autosave_response( $data[0], $this->post_autosave );
	}

	public function test_get_items_no_permission() {
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 401 );
		wp_set_current_user( self::$contributor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_get_items_missing_parent() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_get_items_invalid_parent_post_type() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$page_id . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_get_item() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->check_get_autosave_response( $response, $this->post_autosave );
		$fields = array(
			'author',
			'date',
			'date_gmt',
			'id',
			'meta',
			'modified',
			'modified_gmt',
			'parent',
			'slug',
			'guid',
			'title',
			'excerpt',
			'content',
		);
		$this->assertSameSets( $fields, array_keys( $data ) );
		$this->assertSame( self::$editor_id, $data['author'] );
	}

	public function test_get_item_embed_context() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );
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
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );
		wp_set_current_user( self::$contributor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_read', $response, 403 );
	}

	public function test_get_item_missing_parent() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/autosaves/' . self::$autosave_post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	public function test_get_item_invalid_parent_post_type() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$page_id . '/autosaves' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_post_invalid_parent', $response, 404 );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->check_get_autosave_response( $response, $this->post_autosave );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 14, $properties );
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
		$this->assertArrayHasKey( 'preview_link', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
	}

	public function test_create_item() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );

		$params = $this->set_post_data(
			array(
				'id' => self::$post_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_autosave_response( $response );
	}

	public function test_update_item() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );

		$params = $this->set_post_data(
			array(
				'id'     => self::$post_id,
				'author' => self::$contributor_id,
			)
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_autosave_response( $response );
	}

	public function test_update_item_with_meta() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		register_post_meta(
			'post',
			'foo',
			array(
				'show_in_rest'      => true,
				'revisions_enabled' => true,
				'single'            => true,
			)
		);
		$params = $this->set_post_data(
			array(
				'id'     => self::$post_id,
				'author' => self::$contributor_id,
				'meta'   => array(
					'foo' => 'bar',
				),
			)
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_autosave_response( $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'foo', $data['meta'] );
		$this->assertSame( 'bar', $data['meta']['foo'] );
	}

	public function test_update_item_with_json_meta() {
		$meta = '[{\"content\":\"foot 1\",\"id\":\"fa97a10d-7401-42b9-ac54-df8f4510749a\"},{\"content\":\"fdddddoot 2\\\"\",\"id\":\"2216d0aa-34b8-42b4-b441-84dedc0406e0\"}]';
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		register_post_meta(
			'post',
			'foo',
			array(
				'show_in_rest'      => true,
				'revisions_enabled' => true,
				'single'            => true,
			)
		);
		$params = $this->set_post_data(
			array(
				'id'     => self::$post_id,
				'author' => self::$contributor_id,
				'meta'   => array(
					'foo' => $meta,
				),
			)
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->check_create_autosave_response( $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'foo', $data['meta'] );
		$values = json_decode( wp_unslash( $data['meta']['foo'] ), true );
		$this->assertNotNull( $values );
	}

	public function test_update_item_nopriv() {
		wp_set_current_user( self::$contributor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );

		$params = $this->set_post_data(
			array(
				'id'     => self::$post_id,
				'author' => self::$editor_id,
			)
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_rest_autosave_published_post() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/json' );

		$current_post = get_post( self::$post_id );

		$autosave_data = $this->set_post_data(
			array(
				'id'      => self::$post_id,
				'content' => 'Updated post \ content',
				'excerpt' => $current_post->post_excerpt,
				'title'   => $current_post->post_title,
			)
		);

		$request->set_body( wp_json_encode( $autosave_data ) );
		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();

		$this->assertSame( $current_post->ID, $new_data['parent'] );
		$this->assertSame( $current_post->post_title, $new_data['title']['raw'] );
		$this->assertSame( $current_post->post_excerpt, $new_data['excerpt']['raw'] );

		// Updated post_content.
		$this->assertNotEquals( $current_post->post_content, $new_data['content']['raw'] );

		$autosave_post = wp_get_post_autosave( self::$post_id );
		$this->assertSame( $autosave_data['title'], $autosave_post->post_title );
		$this->assertSame( $autosave_data['content'], $autosave_post->post_content );
		$this->assertSame( $autosave_data['excerpt'], $autosave_post->post_excerpt );
	}

	public function test_rest_autosave_draft_post_same_author() {
		wp_set_current_user( self::$editor_id );

		$post_data = array(
			'post_content' => 'Test post content',
			'post_title'   => 'Test post title',
			'post_excerpt' => 'Test post excerpt',
		);
		$post_id   = wp_insert_post( $post_data );

		$autosave_data = array(
			'id'      => $post_id,
			'content' => 'Updated post \ content',
			'title'   => 'Updated post title',
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $autosave_data ) );

		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();
		$post     = get_post( $post_id );

		$this->assertSame( $post_id, $new_data['id'] );
		// The draft post should be updated.
		$this->assertSame( $autosave_data['content'], $new_data['content']['raw'] );
		$this->assertSame( $autosave_data['title'], $new_data['title']['raw'] );
		$this->assertSame( $autosave_data['content'], $post->post_content );
		$this->assertSame( $autosave_data['title'], $post->post_title );

		// Not updated.
		$this->assertSame( $post_data['post_excerpt'], $post->post_excerpt );

		wp_delete_post( $post_id );
	}

	public function test_rest_autosave_draft_post_different_author() {
		wp_set_current_user( self::$editor_id );

		$post_data = array(
			'post_content' => 'Test post content',
			'post_title'   => 'Test post title',
			'post_excerpt' => 'Test post excerpt',
			'post_author'  => self::$editor_id + 1,
		);
		$post_id   = wp_insert_post( $post_data );

		$autosave_data = array(
			'id'      => $post_id,
			'content' => 'Updated post content',
			'excerpt' => $post_data['post_excerpt'],
			'title'   => $post_data['post_title'],
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $autosave_data ) );

		$response     = rest_get_server()->dispatch( $request );
		$new_data     = $response->get_data();
		$current_post = get_post( $post_id );

		$this->assertSame( $current_post->ID, $new_data['parent'] );

		// The draft post shouldn't change.
		$this->assertSame( $current_post->post_title, $post_data['post_title'] );
		$this->assertSame( $current_post->post_content, $post_data['post_content'] );
		$this->assertSame( $current_post->post_excerpt, $post_data['post_excerpt'] );

		$autosave_post = wp_get_post_autosave( $post_id );

		// No changes.
		$this->assertSame( $current_post->post_title, $autosave_post->post_title );
		$this->assertSame( $current_post->post_excerpt, $autosave_post->post_excerpt );

		// Has changes.
		$this->assertSame( $autosave_data['content'], $autosave_post->post_content );

		wp_delete_post( $post_id );
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

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/posts/' . self::$post_id . '/autosaves' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		wp_set_current_user( 1 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );

		$response = rest_get_server()->dispatch( $request );
		$this->assertArrayHasKey( 'my_custom_int', $response->data );

		global $wp_rest_additional_fields;
		$wp_rest_additional_fields = array();
	}

	public function additional_field_get_callback( $response_data, $field_name ) {
		return get_post_meta( $response_data['id'], $field_name, true );
	}

	public function additional_field_update_callback( $value, $post, $field_name ) {
		update_post_meta( $post->ID, $field_name, $value );
	}

	protected function check_get_autosave_response( $response, $autosave ) {
		if ( $response instanceof WP_REST_Response ) {
			$links    = $response->get_links();
			$response = $response->get_data();
		} else {
			$this->assertArrayHasKey( '_links', $response );
			$links = $response['_links'];
		}

		$this->assertEquals( $autosave->post_author, $response['author'] );

		$rendered_content = apply_filters( 'the_content', $autosave->post_content );
		$this->assertSame( $rendered_content, $response['content']['rendered'] );

		$this->assertSame( mysql_to_rfc3339( $autosave->post_date ), $response['date'] ); //@codingStandardsIgnoreLine
		$this->assertSame( mysql_to_rfc3339( $autosave->post_date_gmt ), $response['date_gmt'] ); //@codingStandardsIgnoreLine

		$rendered_guid = apply_filters( 'get_the_guid', $autosave->guid, $autosave->ID );
		$this->assertSame( $rendered_guid, $response['guid']['rendered'] );

		$this->assertSame( $autosave->ID, $response['id'] );
		$this->assertSame( mysql_to_rfc3339( $autosave->post_modified ), $response['modified'] ); //@codingStandardsIgnoreLine
		$this->assertSame( mysql_to_rfc3339( $autosave->post_modified_gmt ), $response['modified_gmt'] ); //@codingStandardsIgnoreLine
		$this->assertSame( $autosave->post_name, $response['slug'] );

		$rendered_title = get_the_title( $autosave->ID );
		$this->assertSame( $rendered_title, $response['title']['rendered'] );

		$parent            = get_post( $autosave->post_parent );
		$parent_controller = new WP_REST_Posts_Controller( $parent->post_type );
		$parent_object     = get_post_type_object( $parent->post_type );
		$parent_base       = ! empty( $parent_object->rest_base ) ? $parent_object->rest_base : $parent_object->name;
		$this->assertSame( rest_url( '/wp/v2/' . $parent_base . '/' . $autosave->post_parent ), $links['parent'][0]['href'] );
	}

	public function test_get_item_sets_up_postdata() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . self::$post_id . '/autosaves/' . self::$autosave_post_id );
		rest_get_server()->dispatch( $request );

		$post           = get_post();
		$parent_post_id = wp_is_post_revision( $post->ID );

		$this->assertSame( $post->ID, self::$autosave_post_id );
		$this->assertSame( $parent_post_id, self::$post_id );
	}

	public function test_update_item_draft_page_with_parent() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . self::$child_draft_page_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );

		$params = $this->set_post_data(
			array(
				'id'     => self::$child_draft_page_id,
				'author' => self::$editor_id,
			)
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( self::$child_draft_page_id, $data['id'] );
		$this->assertSame( self::$parent_page_id, $data['parent'] );
	}

	public function test_schema_validation_is_applied() {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . self::$draft_page_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );

		$params = $this->set_post_data(
			array(
				'id'             => self::$draft_page_id,
				'comment_status' => 'garbage',
			)
		);

		$request->set_body_params( $params );

		$response = rest_get_server()->dispatch( $request );
		$this->assertNotEquals( 'garbage', get_post( self::$draft_page_id )->comment_status );
	}

	/**
	 * Test ensuring that autosave from the original author doesn't overwrite changes after it has been taken over by a 2nd author.
	 *
	 * @ticket 55659
	 */
	public function test_rest_autosave_draft_post_locked_to_different_author() {

		// Create a post by the editor.
		$post_data = array(
			'post_content' => 'Test post content',
			'post_title'   => 'Test post title',
			'post_excerpt' => 'Test post excerpt',
			'post_author'  => self::$editor_id,
			'post_status'  => 'draft',
		);
		$post_id   = wp_insert_post( $post_data );

		// Set the post lock to the contributor, simulating a takeover of the post.
		wp_set_current_user( self::$contributor_id );
		wp_set_post_lock( $post_id );

		// Update the post with new data from the contributor.
		$updated_post_data = array(
			'ID'           => $post_id,
			'post_content' => 'New post content from the contributor',
			'post_title'   => 'New post title',
		);
		wp_update_post( $updated_post_data );

		// Set the current user to the editor and initiate an autosave with some new data.
		wp_set_current_user( self::$editor_id );
		$autosave_data = array(
			'id'      => $post_id,
			'content' => 'Updated post content',
			'excerpt' => 'A new excerpt to test',
			'title'   => $post_data['post_title'],
		);

		// Initiate an autosave via the REST API as Gutenberg does.
		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $autosave_data ) );

		$response = rest_get_server()->dispatch( $request );
		$new_data = $response->get_data();

		// The current version of our test post.
		$current_post = get_post( $post_id );

		// The new data from the autosave should have its parent ID set to the original post ID.
		$this->assertSame( $post_id, $new_data['parent'] );

		// The post title and content should still be the updated versions from the contributor.
		$this->assertSame( $current_post->post_title, $updated_post_data['post_title'] );
		$this->assertSame( $current_post->post_content, $updated_post_data['post_content'] );

		// The excerpt should have stayed the same.
		$this->assertSame( $current_post->post_excerpt, $post_data['post_excerpt'] );

		$autosave_post = wp_get_post_autosave( $post_id );

		// Has changes.
		$this->assertSame( $autosave_data['content'], $autosave_post->post_content );

		wp_delete_post( $post_id );
	}

	/**
	 * @ticket 49532
	 *
	 * @covers WP_REST_Autosaves_Controller::create_post_autosave
	 */
	public function test_rest_autosave_do_not_create_autosave_when_post_is_unchanged() {
		// Create a post by the editor.
		$post_data = array(
			'post_content' => 'Test post content',
			'post_title'   => 'Test post title',
			'post_excerpt' => 'Test post excerpt',
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
		);
		$post_id   = wp_insert_post( $post_data );
		wp_set_current_user( self::$editor_id );

		// Make a small change create the initial autosave.
		$autosave_data = array(
			'post_content' => 'Test post content changed',
		);
		$request       = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $autosave_data ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// Store the first autosave ID.
		$autosave = $response->get_data();

		// Try creating an autosave using the REST endpoint with unchanged content.
		$request->set_body( wp_json_encode( $autosave_data ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $autosave['id'], $data['id'], 'Original autosave was not returned' );
	}

	/**
	 * @ticket 52925
	 *
	 * @dataProvider data_invalid_post_id
	 *
	 * @covers WP_REST_Autosaves_Controller::create_item
	 * @covers WP_REST_Autosaves_Controller::get_post
	 *
	 * @param int $autosave_revision_id The autosave revision ID.
	 */
	public function test_invalid_autosave_revision_id_should_trigger_error_response( $autosave_revision_id ) {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . self::$post_id . '/autosaves' );
		$request->add_header( 'Content-Type', 'application/x-www-form-urlencoded' );
		$body_parameters = $this->set_post_data(
			array(
				'id' => self::$post_id,
			)
		);
		$request->set_body_params( $body_parameters );

		/**
		 * It's hard to programmatically create an invalid $autosave_id,
		 * so mocking the ::create_post_autosave() method seems like a more reasonable solution.
		 */
		$autosaves_controller = $this->getMockBuilder( WP_REST_Autosaves_Controller::class )
									->setConstructorArgs( array( 'post' ) )
									->onlyMethods( array( 'create_post_autosave' ) )
									->getMock();
		$autosaves_controller->method( 'create_post_autosave' )
							->willReturn( $autosave_revision_id );

		$response = $autosaves_controller->create_item( $request );
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * @ticket 52925
	 *
	 * @dataProvider data_invalid_post_id
	 *
	 * @covers WP_REST_Autosaves_Controller::create_post_autosave
	 * @covers WP_REST_Autosaves_Controller::get_post
	 *
	 * @param int $parent_post_id Parent post ID.
	 */
	public function test_invalid_parent_post_id_should_trigger_error_response( $parent_post_id ) {
		$autosaves_controller = new WP_REST_Autosaves_Controller( 'post' );
		$response             = $autosaves_controller->create_post_autosave(
			array(
				'ID' => $parent_post_id,
			)
		);
		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_invalid_post_id() {
		return array(
			'impossibly high post ID' => array( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ),
			'negative post ID'        => array( -1 ),
			'zero post ID'            => array( 0 ),
		);
	}
}
