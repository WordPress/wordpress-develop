<?php
/**
 * Unit tests covering WP_REST_Attachments_Controller functionality
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class WP_Test_REST_Attachments_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {

	protected static $superadmin_id;
	protected static $editor_id;
	protected static $author_id;
	protected static $contributor_id;
	protected static $uploader_id;
	protected static $rest_after_insert_attachment_count;
	protected static $rest_insert_attachment_count;

	/**
	 * @var string The path to a test file.
	 */
	private $test_file;

	/**
	 * @var string The path to a second test file.
	 */
	private $test_file2;

	/**
	 * @var array The recorded posts query clauses.
	 */
	protected $posts_clauses;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
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
		self::$uploader_id    = $factory->user->create(
			array(
				'role' => 'uploader',
			)
		);

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$editor_id );
		self::delete_user( self::$author_id );
		self::delete_user( self::$contributor_id );
		self::delete_user( self::$uploader_id );
	}

	public function set_up() {
		parent::set_up();

		// Add an uploader role to test upload capabilities.
		add_role( 'uploader', 'File upload role' );
		$role = get_role( 'uploader' );
		$role->add_cap( 'upload_files' );
		$role->add_cap( 'read' );
		$role->add_cap( 'level_0' );

		$orig_file       = DIR_TESTDATA . '/images/canola.jpg';
		$this->test_file = get_temp_dir() . 'canola.jpg';
		copy( $orig_file, $this->test_file );
		$orig_file2       = DIR_TESTDATA . '/images/codeispoetry.png';
		$this->test_file2 = get_temp_dir() . 'codeispoetry.png';
		copy( $orig_file2, $this->test_file2 );

		add_filter( 'rest_pre_dispatch', array( $this, 'wpSetUpBeforeRequest' ), 10, 3 );
		add_filter( 'posts_clauses', array( $this, 'save_posts_clauses' ), 10, 2 );
	}

	public function wpSetUpBeforeRequest( $result ) {
		$this->posts_clauses = array();
		return $result;
	}

	public function save_posts_clauses( $clauses ) {
		$this->posts_clauses[] = $clauses;
		return $clauses;
	}

	public function tear_down() {
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}
		if ( file_exists( $this->test_file2 ) ) {
			unlink( $this->test_file2 );
		}

		$this->remove_added_uploads();

		if ( class_exists( WP_Image_Editor_Mock::class ) ) {
			WP_Image_Editor_Mock::$spy         = array();
			WP_Image_Editor_Mock::$edit_return = array();
			WP_Image_Editor_Mock::$size_return = null;
		}

		parent::tear_down();
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/media', $routes );
		$this->assertCount( 2, $routes['/wp/v2/media'] );
		$this->assertArrayHasKey( '/wp/v2/media/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/media/(?P<id>[\d]+)'] );
	}

	public static function disposition_provider() {
		return array(
			// Types.
			array( 'attachment; filename="foo.jpg"', 'foo.jpg' ),
			array( 'inline; filename="foo.jpg"', 'foo.jpg' ),
			array( 'form-data; filename="foo.jpg"', 'foo.jpg' ),

			// Formatting.
			array( 'attachment; filename="foo.jpg"', 'foo.jpg' ),
			array( 'attachment; filename=foo.jpg', 'foo.jpg' ),
			array( 'attachment;filename="foo.jpg"', 'foo.jpg' ),
			array( 'attachment;filename=foo.jpg', 'foo.jpg' ),
			array( 'attachment; filename = "foo.jpg"', 'foo.jpg' ),
			array( 'attachment; filename = foo.jpg', 'foo.jpg' ),
			array( "attachment;\tfilename\t=\t\"foo.jpg\"", 'foo.jpg' ),
			array( "attachment;\tfilename\t=\tfoo.jpg", 'foo.jpg' ),
			array( 'attachment; filename = my foo picture.jpg', 'my foo picture.jpg' ),

			// Extensions.
			array( 'form-data; name="myfile"; filename="foo.jpg"', 'foo.jpg' ),
			array( 'form-data; name="myfile"; filename="foo.jpg"; something="else"', 'foo.jpg' ),
			array( 'form-data; name=myfile; filename=foo.jpg; something=else', 'foo.jpg' ),
			array( 'form-data; name=myfile; filename=my foo.jpg; something=else', 'my foo.jpg' ),

			// Invalid.
			array( 'filename="foo.jpg"', null ),
			array( 'filename-foo.jpg', null ),
			array( 'foo.jpg', null ),
			array( 'unknown; notfilename="foo.jpg"', null ),
		);
	}

	/**
	 * @dataProvider disposition_provider
	 */
	public function test_parse_disposition( $header, $expected ) {
		$header_list = array( $header );
		$parsed      = WP_REST_Attachments_Controller::get_filename_from_disposition( $header_list );
		$this->assertSame( $expected, $parsed );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayNotHasKey( 'allow_batch', $data['endpoints'][0] );
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request       = new WP_REST_Request( 'OPTIONS', '/wp/v2/media/' . $attachment_id );
		$response      = rest_get_server()->dispatch( $request );
		$data          = $response->get_data();
		$this->assertArrayNotHasKey( 'allow_batch', $data['endpoints'][0] );
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	public function test_registered_query_params() {
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
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
				'context',
				'exclude',
				'include',
				'media_type',
				'mime_type',
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
				'slug',
				'status',
			),
			$keys
		);
		$media_types = array(
			'application',
			'video',
			'image',
			'audio',
		);
		if ( ! is_multisite() ) {
			$media_types[] = 'text';
		}
		$this->assertSameSets( $media_types, $data['endpoints'][0]['args']['media_type']['enum'] );
	}

	public function test_registered_get_item_params() {
		$id1      = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/media/%d', $id1 ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$keys     = array_keys( $data['endpoints'][0]['args'] );
		sort( $keys );
		$this->assertSame( array( 'context', 'id' ), $keys );
	}

	/**
	 * @ticket 43701
	 */
	public function test_allow_header_sent_on_options_request() {
		$id1      = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/media/%d', $id1 ) );
		$response = rest_get_server()->dispatch( $request );
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$headers  = $response->get_headers();

		$this->assertNotEmpty( $headers['Allow'] );
		$this->assertSame( $headers['Allow'], 'GET' );

		wp_set_current_user( self::$editor_id );
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( '/wp/v2/media/%d', $id1 ) );
		$response = rest_get_server()->dispatch( $request );
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
		$headers  = $response->get_headers();

		$this->assertNotEmpty( $headers['Allow'] );
		$this->assertSame( $headers['Allow'], 'GET, POST, PUT, PATCH, DELETE' );
	}

	public function test_get_items() {
		wp_set_current_user( 0 );
		$id1            = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$draft_post     = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$id2            = $this->factory->attachment->create_object(
			$this->test_file,
			$draft_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$published_post = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3            = $this->factory->attachment->create_object(
			$this->test_file,
			$published_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request        = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response       = rest_get_server()->dispatch( $request );
		$data           = $response->get_data();
		$this->assertCount( 2, $data );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertContains( $id1, $ids );
		$this->assertNotContains( $id2, $ids );
		$this->assertContains( $id3, $ids );

		$this->check_get_posts_response( $response );
	}

	public function test_get_items_logged_in_editor() {
		wp_set_current_user( self::$editor_id );
		$id1            = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$draft_post     = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$id2            = $this->factory->attachment->create_object(
			$this->test_file,
			$draft_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$published_post = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$id3            = $this->factory->attachment->create_object(
			$this->test_file,
			$published_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request        = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response       = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertCount( 3, $data );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertContains( $id1, $ids );
		$this->assertContains( $id2, $ids );
		$this->assertContains( $id3, $ids );
	}

	public function test_get_items_media_type() {
		$id1      = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $id1, $data[0]['id'] );
		// 'media_type' => 'video'.
		$request->set_param( 'media_type', 'video' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );
		// 'media_type' => 'image'.
		$request->set_param( 'media_type', 'image' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $id1, $data[0]['id'] );
	}

	public function test_get_items_mime_type() {
		$id1      = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $id1, $data[0]['id'] );
		// 'mime_type' => 'image/png'.
		$request->set_param( 'mime_type', 'image/png' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 0, $response->get_data() );
		// 'mime_type' => 'image/jpeg'.
		$request->set_param( 'mime_type', 'image/jpeg' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( $id1, $data[0]['id'] );
	}

	public function test_get_items_parent() {
		$post_id        = $this->factory->post->create( array( 'post_title' => 'Test Post' ) );
		$attachment_id  = $this->factory->attachment->create_object(
			$this->test_file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$attachment_id2 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		// All attachments.
		$request  = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertCount( 2, $response->get_data() );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		// Attachments without a parent.
		$request->set_param( 'parent', 0 );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $attachment_id2, $data[0]['id'] );
		// Attachments with parent=post_id.
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'parent', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $attachment_id, $data[0]['id'] );
		// Attachments with invalid parent.
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'parent', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 0, $data );
	}

	public function test_get_items_invalid_status_param_is_error_response() {
		wp_set_current_user( self::$editor_id );
		$this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'status', 'publish' );
		$request->set_param( 'context', 'edit' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response );
	}

	public function test_get_items_private_status() {
		// Logged out users can't make the request.
		wp_set_current_user( 0 );
		$attachment_id1 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_status'    => 'private',
			)
		);
		$request        = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'status', 'private' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		// Properly authorized users can make the request.
		wp_set_current_user( self::$editor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $attachment_id1, $data[0]['id'] );
	}

	public function test_get_items_multiple_statuses() {
		// Logged out users can't make the request.
		wp_set_current_user( 0 );
		$attachment_id1 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_status'    => 'private',
			)
		);
		$attachment_id2 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_status'    => 'trash',
			)
		);
		$request        = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'status', array( 'private', 'trash' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		// Properly authorized users can make the request.
		wp_set_current_user( self::$editor_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data );
		$ids = array(
			$data[0]['id'],
			$data[1]['id'],
		);
		sort( $ids );
		$this->assertSame( array( $attachment_id1, $attachment_id2 ), $ids );
	}

	public function test_get_items_invalid_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'after', 'foo' );
		$request->set_param( 'before', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function test_get_items_valid_date() {
		$id1     = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2016-01-15T00:00:00Z',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id2     = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2016-01-16T00:00:00Z',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id3     = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2016-01-17T00:00:00Z',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );
	}

	/**
	 * @ticket 50617
	 */
	public function test_get_items_invalid_modified_date() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'modified_after', 'foo' );
		$request->set_param( 'modified_before', 'bar' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 50617
	 */
	public function test_get_items_valid_modified_date() {
		$id1 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2016-01-01 00:00:00',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id2 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2016-01-02 00:00:00',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id3 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2016-01-03 00:00:00',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$this->update_post_modified( $id1, '2016-01-15 00:00:00' );
		$this->update_post_modified( $id2, '2016-01-16 00:00:00' );
		$this->update_post_modified( $id3, '2016-01-17 00:00:00' );
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'modified_after', '2016-01-15T00:00:00Z' );
		$request->set_param( 'modified_before', '2016-01-17T00:00:00Z' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );
	}

	/**
	 * @ticket 55677
	 */
	public function test_get_items_avoid_duplicated_count_query_if_no_items() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'media_type', 'video' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertCount( 1, $this->posts_clauses );

		$headers = $response->get_headers();

		$this->assertSame( 0, $headers['X-WP-Total'] );
		$this->assertSame( 0, $headers['X-WP-TotalPages'] );
	}

	/**
	 * @ticket 55677
	 */
	public function test_get_items_with_empty_page_runs_count_query_after() {
		$this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_date'      => '2022-06-12T00:00:00Z',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'media_type', 'image' );
		$request->set_param( 'page', 2 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertCount( 2, $this->posts_clauses );

		$this->assertErrorResponse( 'rest_post_invalid_page_number', $response, 400 );
	}

	public function test_get_item() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Sample alt text' );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->check_get_post_response( $response );
		$data = $response->get_data();
		$this->assertSame( 'image/jpeg', $data['mime_type'] );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_get_item_sizes() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			),
			$this->test_file
		);

		add_image_size( 'rest-api-test', 119, 119, true );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_file ) );

		$request            = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response           = rest_get_server()->dispatch( $request );
		$data               = $response->get_data();
		$image_src          = wp_get_attachment_image_src( $attachment_id, 'rest-api-test' );
		$original_image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
		remove_image_size( 'rest-api-test' );

		$this->assertIsArray( $data['media_details']['sizes'], 'Could not retrieve the sizes data.' );
		$this->assertSame( $image_src[0], $data['media_details']['sizes']['rest-api-test']['source_url'] );
		$this->assertSame( 'image/jpeg', $data['media_details']['sizes']['rest-api-test']['mime_type'] );
		$this->assertSame( $original_image_src[0], $data['media_details']['sizes']['full']['source_url'] );
		$this->assertSame( 'image/jpeg', $data['media_details']['sizes']['full']['mime_type'] );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_get_item_sizes_with_no_url() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			),
			$this->test_file
		);

		add_image_size( 'rest-api-test', 119, 119, true );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $this->test_file ) );

		add_filter( 'wp_get_attachment_image_src', '__return_false' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		remove_filter( 'wp_get_attachment_image_src', '__return_false' );
		remove_image_size( 'rest-api-test' );

		$this->assertIsArray( $data['media_details']['sizes'], 'Could not retrieve the sizes data.' );
		$this->assertArrayNotHasKey( 'source_url', $data['media_details']['sizes']['rest-api-test'] );
	}

	public function test_get_item_private_post_not_authenticated() {
		wp_set_current_user( 0 );
		$draft_post = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$id1        = $this->factory->attachment->create_object(
			$this->test_file,
			$draft_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request    = new WP_REST_Request( 'GET', '/wp/v2/media/' . $id1 );
		$response   = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_get_item_inherit_status_with_invalid_parent() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request       = new WP_REST_Request( 'GET', sprintf( '/wp/v2/media/%d', $attachment_id ) );
		$response      = rest_get_server()->dispatch( $request );
		$data          = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $attachment_id, $data['id'] );
	}

	public function test_get_item_auto_status_with_invalid_parent_not_authenticated_returns_error() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_status'    => 'auto-draft',
			)
		);
		$request       = new WP_REST_Request( 'GET', sprintf( '/wp/v2/media/%d', $attachment_id ) );
		$response      = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_forbidden', $response, 401 );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_create_item() {
		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );

		$request->set_body( file_get_contents( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'image', $data['media_type'] );

		$attachment = get_post( $data['id'] );
		$this->assertSame( 'My title is very cool', $data['title']['raw'] );
		$this->assertSame( 'My title is very cool', $attachment->post_title );
		$this->assertSame( 'This is a better caption.', $data['caption']['raw'] );
		$this->assertSame( 'This is a better caption.', $attachment->post_excerpt );
		$this->assertSame( 'Without a description, my attachment is descriptionless.', $data['description']['raw'] );
		$this->assertSame( 'Without a description, my attachment is descriptionless.', $attachment->post_content );
		$this->assertSame( 'Alt text is stored outside post schema.', $data['alt_text'] );
		$this->assertSame( 'Alt text is stored outside post schema.', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
	}

	public function test_create_item_default_filename_title() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file2 ),
					'name'     => 'codeispoetry.png',
					'size'     => filesize( $this->test_file2 ),
					'tmp_name' => $this->test_file2,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( $this->test_file2 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'codeispoetry', $data['title']['raw'] );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_create_item_with_files() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_create_item_with_upload_files_role() {
		wp_set_current_user( self::$uploader_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
	}

	public function test_create_item_empty_body() {
		wp_set_current_user( self::$author_id );
		$request  = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_data', $response, 400 );
	}

	public function test_create_item_missing_content_type() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_content_type', $response, 400 );
	}

	public function test_create_item_missing_content_disposition() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_content_disposition', $response, 400 );
	}

	public function test_create_item_bad_md5_header() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_header( 'Content-MD5', 'abc123' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_hash_mismatch', $response, 412 );
	}

	public function test_create_item_with_files_bad_md5_header() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
				),
			)
		);
		$request->set_header( 'Content-MD5', 'abc123' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_hash_mismatch', $response, 412 );
	}

	public function test_create_item_invalid_upload_files_capability() {
		wp_set_current_user( self::$contributor_id );
		$request  = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_create', $response, 403 );
	}

	public function test_create_item_invalid_edit_permissions() {
		$post_id = $this->factory->post->create( array( 'post_author' => self::$editor_id ) );
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_create_item_invalid_upload_permissions() {
		$post_id = $this->factory->post->create( array( 'post_author' => self::$editor_id ) );
		wp_set_current_user( self::$uploader_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_create_item_invalid_post_type() {
		$attachment_id = $this->factory->post->create(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => 0,
			)
		);
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'post', $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_create_item_alt_text() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );

		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'alt_text', 'test alt text' );
		$response   = rest_get_server()->dispatch( $request );
		$attachment = $response->get_data();
		$this->assertSame( 'test alt text', $attachment['alt_text'] );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_create_item_unsafe_alt_text() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'alt_text', '<script>alert(document.cookie)</script>' );
		$response   = rest_get_server()->dispatch( $request );
		$attachment = $response->get_data();
		$this->assertSame( '', $attachment['alt_text'] );
	}

	/**
	 * @ticket 40861
	 * @requires function imagejpeg
	 */
	public function test_create_item_ensure_relative_path() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$response   = rest_get_server()->dispatch( $request );
		$attachment = $response->get_data();
		$this->assertStringNotContainsString( ABSPATH, get_post_meta( $attachment['id'], '_wp_attached_file', true ) );
	}

	public function test_update_item() {
		wp_set_current_user( self::$editor_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$attachment = get_post( $data['id'] );
		$this->assertSame( 'My title is very cool', $data['title']['raw'] );
		$this->assertSame( 'My title is very cool', $attachment->post_title );
		$this->assertSame( 'This is a better caption.', $data['caption']['raw'] );
		$this->assertSame( 'This is a better caption.', $attachment->post_excerpt );
		$this->assertSame( 'Without a description, my attachment is descriptionless.', $data['description']['raw'] );
		$this->assertSame( 'Without a description, my attachment is descriptionless.', $attachment->post_content );
		$this->assertSame( 'Alt text is stored outside post schema.', $data['alt_text'] );
		$this->assertSame( 'Alt text is stored outside post schema.', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
	}

	public function test_update_item_parent() {
		wp_set_current_user( self::$editor_id );
		$original_parent = $this->factory->post->create( array() );
		$attachment_id   = $this->factory->attachment->create_object(
			$this->test_file,
			$original_parent,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => $this->editor_id,
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertSame( $original_parent, $attachment->post_parent );

		$new_parent = $this->factory->post->create( array() );
		$request    = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'post', $new_parent );
		rest_get_server()->dispatch( $request );

		$attachment = get_post( $attachment_id );
		$this->assertSame( $new_parent, $attachment->post_parent );
	}

	public function test_update_item_invalid_permissions() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'caption', 'This is a better caption.' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_update_item_invalid_post_type() {
		$attachment_id = $this->factory->post->create(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => 0,
			)
		);
		wp_set_current_user( self::$editor_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'post', $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 40399
	 */
	public function test_update_item_with_existing_inherit_status() {
		wp_set_current_user( self::$editor_id );
		$parent_id     = self::factory()->post->create( array() );
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_file,
			$parent_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'status', 'inherit' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertNotWPError( $response->as_error() );
		$this->assertSame( 'inherit', $response->get_data()['status'] );
	}

	/**
	 * @ticket 40399
	 */
	public function test_update_item_with_new_inherit_status() {
		wp_set_current_user( self::$editor_id );
		$attachment_id = self::factory()->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
				'post_status'    => 'private',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'status', 'inherit' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	public function verify_attachment_roundtrip( $input = array(), $expected_output = array() ) {
		// Create the post.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );

		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$actual_output = $response->get_data();

		// Remove <p class="attachment"> from rendered description.
		// See https://core.trac.wordpress.org/ticket/38679
		$content = $actual_output['description']['rendered'];
		$content = explode( "\n", trim( $content ) );
		if ( preg_match( '/^<p class="attachment">/', $content[0] ) ) {
			$content                                  = implode( "\n", array_slice( $content, 1 ) );
			$actual_output['description']['rendered'] = $content;
		}

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['title']['raw'], $actual_output['title']['raw'] );
		$this->assertSame( $expected_output['title']['rendered'], trim( $actual_output['title']['rendered'] ) );
		$this->assertSame( $expected_output['description']['raw'], $actual_output['description']['raw'] );
		$this->assertSame( $expected_output['description']['rendered'], trim( $actual_output['description']['rendered'] ) );
		$this->assertSame( $expected_output['caption']['raw'], $actual_output['caption']['raw'] );
		$this->assertSame( $expected_output['caption']['rendered'], trim( $actual_output['caption']['rendered'] ) );

		// Compare expected API output to WP internal values.
		$post = get_post( $actual_output['id'] );
		$this->assertSame( $expected_output['title']['raw'], $post->post_title );
		$this->assertSame( $expected_output['description']['raw'], $post->post_content );
		$this->assertSame( $expected_output['caption']['raw'], $post->post_excerpt );

		// Update the post.
		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/media/%d', $actual_output['id'] ) );
		foreach ( $input as $name => $value ) {
			$request->set_param( $name, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$actual_output = $response->get_data();

		// Remove <p class="attachment"> from rendered description.
		// See https://core.trac.wordpress.org/ticket/38679
		$content = $actual_output['description']['rendered'];
		$content = explode( "\n", trim( $content ) );
		if ( preg_match( '/^<p class="attachment">/', $content[0] ) ) {
			$content                                  = implode( "\n", array_slice( $content, 1 ) );
			$actual_output['description']['rendered'] = $content;
		}

		// Compare expected API output to actual API output.
		$this->assertSame( $expected_output['title']['raw'], $actual_output['title']['raw'] );
		$this->assertSame( $expected_output['title']['rendered'], trim( $actual_output['title']['rendered'] ) );
		$this->assertSame( $expected_output['description']['raw'], $actual_output['description']['raw'] );
		$this->assertSame( $expected_output['description']['rendered'], trim( $actual_output['description']['rendered'] ) );
		$this->assertSame( $expected_output['caption']['raw'], $actual_output['caption']['raw'] );
		$this->assertSame( $expected_output['caption']['rendered'], trim( $actual_output['caption']['rendered'] ) );

		// Compare expected API output to WP internal values.
		$post = get_post( $actual_output['id'] );
		$this->assertSame( $expected_output['title']['raw'], $post->post_title );
		$this->assertSame( $expected_output['description']['raw'], $post->post_content );
		$this->assertSame( $expected_output['caption']['raw'], $post->post_excerpt );
	}

	public static function attachment_roundtrip_provider() {
		return array(
			array(
				// Raw values.
				array(
					'title'       => '\o/ ¯\_(ツ)_/¯',
					'description' => '\o/ ¯\_(ツ)_/¯',
					'caption'     => '\o/ ¯\_(ツ)_/¯',
				),
				// Expected returned values.
				array(
					'title'       => array(
						'raw'      => '\o/ ¯\_(ツ)_/¯',
						'rendered' => '\o/ ¯\_(ツ)_/¯',
					),
					'description' => array(
						'raw'      => '\o/ ¯\_(ツ)_/¯',
						'rendered' => '<p>\o/ ¯\_(ツ)_/¯</p>',
					),
					'caption'     => array(
						'raw'      => '\o/ ¯\_(ツ)_/¯',
						'rendered' => '<p>\o/ ¯\_(ツ)_/¯</p>',
					),
				),
			),
			array(
				// Raw values.
				array(
					'title'       => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
					'description' => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
					'caption'     => '\\\&\\\ &amp; &invalid; < &lt; &amp;lt;',
				),
				// Expected returned values.
				array(
					'title'       => array(
						'raw'      => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
						'rendered' => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
					),
					'description' => array(
						'raw'      => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
						'rendered' => '<p>\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;</p>',
					),
					'caption'     => array(
						'raw'      => '\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;',
						'rendered' => '<p>\\\&amp;\\\ &amp; &amp;invalid; &lt; &lt; &amp;lt;</p>',
					),
				),
			),
			array(
				// Raw values.
				array(
					'title'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'caption'     => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				// Expected returned values.
				array(
					'title'       => array(
						'raw'      => 'div <strong>strong</strong> oh noes',
						'rendered' => 'div <strong>strong</strong> oh noes',
					),
					'description' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
					'caption'     => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
				),
			),
			array(
				// Raw values.
				array(
					'title'       => '<a href="#" target="_blank" unfiltered=true>link</a>',
					'description' => '<a href="#" target="_blank" unfiltered=true>link</a>',
					'caption'     => '<a href="#" target="_blank" unfiltered=true>link</a>',
				),
				// Expected returned values.
				array(
					'title'       => array(
						'raw'      => '<a href="#">link</a>',
						'rendered' => '<a href="#">link</a>',
					),
					'description' => array(
						'raw'      => '<a href="#" target="_blank" rel="noopener">link</a>',
						'rendered' => '<p><a href="#" target="_blank" rel="noopener">link</a></p>',
					),
					'caption'     => array(
						'raw'      => '<a href="#" target="_blank" rel="noopener">link</a>',
						'rendered' => '<p><a href="#" target="_blank" rel="noopener">link</a></p>',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider attachment_roundtrip_provider
	 * @requires function imagejpeg
	 */
	public function test_post_roundtrip_as_author( $raw, $expected ) {
		wp_set_current_user( self::$author_id );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$this->verify_attachment_roundtrip( $raw, $expected );
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_attachment_roundtrip_as_editor_unfiltered_html() {
		wp_set_current_user( self::$editor_id );
		if ( is_multisite() ) {
			$this->assertFalse( current_user_can( 'unfiltered_html' ) );
			$this->verify_attachment_roundtrip(
				array(
					'title'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'caption'     => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'title'       => array(
						'raw'      => 'div <strong>strong</strong> oh noes',
						'rendered' => 'div <strong>strong</strong> oh noes',
					),
					'description' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
					'caption'     => array(
						'raw'      => '<div>div</div> <strong>strong</strong> oh noes',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> oh noes</p>",
					),
				)
			);
		} else {
			$this->assertTrue( current_user_can( 'unfiltered_html' ) );
			$this->verify_attachment_roundtrip(
				array(
					'title'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'caption'     => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				array(
					'title'       => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					),
					'description' => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
					),
					'caption'     => array(
						'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
						'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
					),
				)
			);
		}
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_attachment_roundtrip_as_superadmin_unfiltered_html() {
		wp_set_current_user( self::$superadmin_id );
		$this->assertTrue( current_user_can( 'unfiltered_html' ) );
		$this->verify_attachment_roundtrip(
			array(
				'title'       => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'description' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				'caption'     => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
			),
			array(
				'title'       => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
				),
				'description' => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
				),
				'caption'     => array(
					'raw'      => '<div>div</div> <strong>strong</strong> <script>oh noes</script>',
					'rendered' => "<div>div</div>\n<p> <strong>strong</strong> <script>oh noes</script></p>",
				),
			)
		);
	}

	public function test_delete_item() {
		wp_set_current_user( self::$editor_id );
		$attachment_id    = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$request          = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $attachment_id );
		$request['force'] = true;
		$response         = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_delete_item_no_trash() {
		wp_set_current_user( self::$editor_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);

		// Attempt trashing.
		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		$request->set_param( 'force', 'false' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );

		// Ensure the post still exists.
		$post = get_post( $attachment_id );
		$this->assertNotEmpty( $post );
	}

	public function test_delete_item_invalid_delete_permissions() {
		wp_set_current_user( self::$author_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		$request       = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $attachment_id );
		$response      = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_delete', $response, 403 );
	}

	public function test_prepare_item() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);

		$attachment = get_post( $attachment_id );
		$request    = new WP_REST_Request( 'GET', sprintf( '/wp/v2/media/%d', $attachment_id ) );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$this->check_post_data( $attachment, $data, 'view', $response->get_links() );
		$this->check_post_data( $attachment, $data, 'embed', $response->get_links() );
	}

	public function test_prepare_item_limit_fields() {
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		wp_set_current_user( self::$editor_id );
		$endpoint = new WP_REST_Attachments_Controller( 'post' );
		$request  = new WP_REST_Request( 'GET', sprintf( '/wp/v2/media/%d', $attachment_id ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( '_fields', 'id,slug' );
		$obj      = get_post( $attachment_id );
		$response = $endpoint->prepare_item_for_response( $obj, $request );
		$this->assertSame(
			array(
				'id',
				'slug',
			),
			array_keys( $response->get_data() )
		);
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];
		$this->assertCount( 27, $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'alt_text', $properties );
		$this->assertArrayHasKey( 'caption', $properties );
		$this->assertArrayHasKey( 'raw', $properties['caption']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['caption']['properties'] );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'raw', $properties['description']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['description']['properties'] );
		$this->assertArrayHasKey( 'comment_status', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'date_gmt', $properties );
		$this->assertArrayHasKey( 'generated_slug', $properties );
		$this->assertArrayHasKey( 'guid', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'media_type', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'mime_type', $properties );
		$this->assertArrayHasKey( 'media_details', $properties );
		$this->assertArrayHasKey( 'modified', $properties );
		$this->assertArrayHasKey( 'modified_gmt', $properties );
		$this->assertArrayHasKey( 'post', $properties );
		$this->assertArrayHasKey( 'ping_status', $properties );
		$this->assertArrayHasKey( 'permalink_template', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'source_url', $properties );
		$this->assertArrayHasKey( 'template', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'raw', $properties['title']['properties'] );
		$this->assertArrayHasKey( 'rendered', $properties['title']['properties'] );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'missing_image_sizes', $properties );
	}

	public function test_get_additional_field_registration() {

		$schema = array(
			'type'        => 'integer',
			'description' => 'Some integer of mine',
			'enum'        => array( 1, 2, 3, 4 ),
			'context'     => array( 'view', 'edit' ),
		);

		register_rest_field(
			'attachment',
			'my_custom_int',
			array(
				'schema'       => $schema,
				'get_callback' => array( $this, 'additional_field_get_callback' ),
			)
		);

		$request = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'my_custom_int', $data['schema']['properties'] );
		$this->assertSame( $schema, $data['schema']['properties']['my_custom_int'] );

		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );

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
			'attachment',
			'my_custom_int',
			array(
				'schema'          => $schema,
				'get_callback'    => array( $this, 'additional_field_get_callback' ),
				'update_callback' => array( $this, 'additional_field_update_callback' ),
			)
		);

		wp_set_current_user( self::$editor_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		// Check for error on update.
		$request = new WP_REST_Request( 'POST', sprintf( '/wp/v2/media/%d', $attachment_id ) );
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

	public function test_search_item_by_filename() {
		$id1 = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		$id2 = $this->factory->attachment->create_object(
			$this->test_file2,
			0,
			array(
				'post_mime_type' => 'image/png',
			)
		);

		$filename = wp_basename( $this->test_file2 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'search', $filename );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );
		$this->assertSame( 'image/png', $data[0]['mime_type'] );
	}

	public function additional_field_get_callback( $object, $request ) {
		return 123;
	}

	public function additional_field_update_callback( $value, $attachment ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
	}

	public function test_links_exist() {

		wp_set_current_user( self::$editor_id );

		$post = self::factory()->attachment->create( array( 'post_author' => self::$editor_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/media/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'self', $links );
		$this->assertArrayHasKey( 'author', $links );

		$this->assertCount( 1, $links['author'] );
		$this->assertArrayHasKey( 'embeddable', $links['author'][0]['attributes'] );
		$this->assertTrue( $links['author'][0]['attributes']['embeddable'] );
	}

	public function test_publish_action_ldo_not_registered() {

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/wp/v2/media' ) );
		$data     = $response->get_data();
		$schema   = $data['schema'];

		$this->assertArrayHasKey( 'links', $schema );
		$publish = wp_list_filter( $schema['links'], array( 'rel' => 'https://api.w.org/action-publish' ) );

		$this->assertCount( 0, $publish, 'LDO not found on schema.' );
	}

	public function test_publish_action_link_does_not_exists() {

		wp_set_current_user( self::$editor_id );

		$post = self::factory()->attachment->create( array( 'post_author' => self::$editor_id ) );
		$this->assertGreaterThan( 0, $post );

		$request = new WP_REST_Request( 'GET', "/wp/v2/media/{$post}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayNotHasKey( 'https://api.w.org/action-publish', $links );
	}

	protected function check_post_data( $attachment, $data, $context = 'view', $links = array() ) {
		parent::check_post_data( $attachment, $data, $context, $links );

		$this->assertArrayNotHasKey( 'content', $data );
		$this->assertArrayNotHasKey( 'excerpt', $data );

		$this->assertSame( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ), $data['alt_text'] );
		if ( 'edit' === $context ) {
			$this->assertSame( $attachment->post_excerpt, $data['caption']['raw'] );
			$this->assertSame( $attachment->post_content, $data['description']['raw'] );
		} else {
			$this->assertArrayNotHasKey( 'raw', $data['caption'] );
			$this->assertArrayNotHasKey( 'raw', $data['description'] );
		}
		$this->assertArrayHasKey( 'media_details', $data );

		if ( $attachment->post_parent ) {
			$this->assertSame( $attachment->post_parent, $data['post'] );
		} else {
			$this->assertNull( $data['post'] );
		}

		$this->assertSame( wp_get_attachment_url( $attachment->ID ), $data['source_url'] );

	}

	/**
	 * @ticket 43751
	 * @group multisite
	 * @group ms-required
	 */
	public function test_create_item_with_file_exceeds_multisite_max_filesize() {
		wp_set_current_user( self::$author_id );
		update_site_option( 'fileupload_maxk', 1 );
		update_site_option( 'upload_space_check_disabled', false );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'error'    => '0',
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
				),
			)
		);
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_header( 'Content-MD5', md5_file( $this->test_file ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_file_too_big', $response, 400 );
	}

	/**
	 * @ticket 43751
	 * @group multisite
	 * @group ms-required
	 */
	public function test_create_item_with_data_exceeds_multisite_max_filesize() {
		wp_set_current_user( self::$author_id );
		update_site_option( 'fileupload_maxk', 1 );
		update_site_option( 'upload_space_check_disabled', false );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_file_too_big', $response, 400 );
	}

	/**
	 * @ticket 43751
	 * @group multisite
	 * @group ms-required
	 */
	public function test_create_item_with_file_exceeds_multisite_site_upload_space() {
		wp_set_current_user( self::$author_id );
		add_filter( 'get_space_allowed', '__return_zero' );
		update_site_option( 'upload_space_check_disabled', false );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'error'    => '0',
					'file'     => file_get_contents( $this->test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( $this->test_file ),
					'tmp_name' => $this->test_file,
				),
			)
		);
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_header( 'Content-MD5', md5_file( $this->test_file ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_limited_space', $response, 400 );
	}

	/**
	 * @ticket 43751
	 * @group multisite
	 * @group ms-required
	 */
	public function test_create_item_with_data_exceeds_multisite_site_upload_space() {
		wp_set_current_user( self::$author_id );
		add_filter( 'get_space_allowed', '__return_zero' );
		update_site_option( 'upload_space_check_disabled', false );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( $this->test_file ) );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_limited_space', $response, 400 );
	}

	/**
	 * Ensure the `rest_after_insert_attachment` and `rest_insert_attachment` hooks only fire
	 * once when attachments are created.
	 *
	 * @ticket 45269
	 * @requires function imagejpeg
	 */
	public function test_rest_insert_attachment_hooks_fire_once_on_create() {
		self::$rest_insert_attachment_count       = 0;
		self::$rest_after_insert_attachment_count = 0;
		add_action( 'rest_insert_attachment', array( $this, 'filter_rest_insert_attachment' ) );
		add_action( 'rest_after_insert_attachment', array( $this, 'filter_rest_after_insert_attachment' ) );

		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );

		$request->set_body( file_get_contents( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 201, $response->get_status() );

		$this->assertSame( 1, self::$rest_insert_attachment_count );
		$this->assertSame( 1, self::$rest_after_insert_attachment_count );
	}

	/**
	 * Ensure the `rest_after_insert_attachment` and `rest_insert_attachment` hooks only fire
	 * once when attachments are updated.
	 *
	 * @ticket 45269
	 */
	public function test_rest_insert_attachment_hooks_fire_once_on_update() {
		self::$rest_insert_attachment_count       = 0;
		self::$rest_after_insert_attachment_count = 0;
		add_action( 'rest_insert_attachment', array( $this, 'filter_rest_insert_attachment' ) );
		add_action( 'rest_after_insert_attachment', array( $this, 'filter_rest_after_insert_attachment' ) );

		wp_set_current_user( self::$editor_id );
		$attachment_id = $this->factory->attachment->create_object(
			$this->test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);
		$request       = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'title', 'My title is very cool' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 1, self::$rest_insert_attachment_count );
		$this->assertSame( 1, self::$rest_after_insert_attachment_count );
	}

	/**
	 * @ticket 44567
	 * @requires function imagejpeg
	 */
	public function test_create_item_with_meta_values() {
		register_post_meta(
			'attachment',
			'best_cannoli',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);

		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=cannoli.jpg' );
		$request->set_param( 'meta', array( 'best_cannoli' => 'Chocolate-dipped, no filling' ) );

		$request->set_body( file_get_contents( $this->test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Chocolate-dipped, no filling', get_post_meta( $response->get_data()['id'], 'best_cannoli', true ) );
	}

	public function filter_rest_insert_attachment( $attachment ) {
		self::$rest_insert_attachment_count++;
	}

	public function filter_rest_after_insert_attachment( $attachment ) {
		self::$rest_after_insert_attachment_count++;
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_logged_out() {
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( array( 'src' => wp_get_attachment_image_url( $attachment, 'full' ) ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_edit_image', $response, 401 );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_cannot_upload() {
		$user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$user->add_cap( 'upload_files', false );

		wp_set_current_user( $user->ID );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( array( 'src' => wp_get_attachment_image_url( $attachment, 'full' ) ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_edit_image', $response, 403 );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_cannot_edit() {
		wp_set_current_user( self::$uploader_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( array( 'src' => wp_get_attachment_image_url( $attachment, 'full' ) ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * @ticket 44405
	 */
	public function test_edit_image_returns_error_if_no_attachment() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create();

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( array( 'src' => '/wp-content/uploads/2020/07/canola.jpg' ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_unknown_attachment', $response, 404 );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_unsupported_mime_type() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );
		wp_update_post(
			array(
				'ID'             => $attachment,
				'post_mime_type' => 'image/invalid',
			)
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( array( 'src' => wp_get_attachment_image_url( $attachment, 'full' ) ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_cannot_edit_file_type', $response, 400 );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_no_edits() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( array( 'src' => wp_get_attachment_image_url( $attachment, 'full' ) ) );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_not_edited', $response, 400 );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_rotate() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$this->setup_mock_editor();
		WP_Image_Editor_Mock::$edit_return['rotate'] = new WP_Error();

		$params = array(
			'rotation' => 60,
			'src'      => wp_get_attachment_image_url( $attachment, 'full' ),
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_rotation_failed', $response, 500 );

		$this->assertCount( 1, WP_Image_Editor_Mock::$spy['rotate'] );
		$this->assertSame( array( -60 ), WP_Image_Editor_Mock::$spy['rotate'][0] );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_crop() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$this->setup_mock_editor();
		WP_Image_Editor_Mock::$size_return = array(
			'width'  => 640,
			'height' => 480,
		);

		WP_Image_Editor_Mock::$edit_return['crop'] = new WP_Error();

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params(
			array(
				'x'      => 50,
				'y'      => 10,
				'width'  => 10,
				'height' => 5,
				'src'    => wp_get_attachment_image_url( $attachment, 'full' ),

			)
		);
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_crop_failed', $response, 500 );

		$this->assertCount( 1, WP_Image_Editor_Mock::$spy['crop'] );
		$this->assertSame(
			array( 320.0, 48.0, 64.0, 24.0 ),
			WP_Image_Editor_Mock::$spy['crop'][0]
		);
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$params = array(
			'rotation' => 60,
			'src'      => wp_get_attachment_image_url( $attachment, 'full' ),
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		$item     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( rest_url( '/wp/v2/media/' . $item['id'] ), $response->get_headers()['Location'] );

		$this->assertStringEndsWith( '-edited.jpg', $item['media_details']['file'] );
		$this->assertArrayHasKey( 'parent_image', $item['media_details'] );
		$this->assertEquals( $attachment, $item['media_details']['parent_image']['attachment_id'] );
		$this->assertStringContainsString( 'canola', $item['media_details']['parent_image']['file'] );
	}

	/**
	 * @ticket 52192
	 * @requires function imagejpeg
	 */
	public function test_batch_edit_image() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( $this->test_file );

		$params = array(
			'modifiers' => array(
				array(
					'type' => 'rotate',
					'args' => array(
						'angle' => 60,
					),
				),
				array(
					'type' => 'crop',
					'args' => array(
						'left'   => 50,
						'top'    => 10,
						'width'  => 10,
						'height' => 5,
					),
				),
			),
			'src'       => wp_get_attachment_image_url( $attachment, 'full' ),
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		$item     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( rest_url( '/wp/v2/media/' . $item['id'] ), $response->get_headers()['Location'] );

		$this->assertStringEndsWith( '-edited.jpg', $item['media_details']['file'] );
		$this->assertArrayHasKey( 'parent_image', $item['media_details'] );
		$this->assertEquals( $attachment, $item['media_details']['parent_image']['attachment_id'] );
		$this->assertStringContainsString( 'canola', $item['media_details']['parent_image']['file'] );
	}

	/**
	 * @ticket 50565
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_mismatched_src() {
		wp_set_current_user( self::$superadmin_id );
		$attachment_id_image1 = self::factory()->attachment->create_upload_object( $this->test_file );
		$attachment_id_image2 = self::factory()->attachment->create_upload_object( $this->test_file2 );
		$attachment_id_file   = self::factory()->attachment->create();

		// URL to the first uploaded image.
		$image_src = wp_get_attachment_image_url( $attachment_id_image1, 'large' );

		// Test: attachment ID points to a different, non-image attachment.
		$request_1 = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id_file}/edit" );
		$request_1->set_body_params( array( 'src' => $image_src ) );

		$response_1 = rest_do_request( $request_1 );
		$this->assertErrorResponse( 'rest_unknown_attachment', $response_1, 404 );

		// Test: attachment ID points to a different image attachment.
		$request_2 = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id_image2}/edit" );
		$request_2->set_body_params( array( 'src' => $image_src ) );

		$response_2 = rest_do_request( $request_2 );
		$this->assertErrorResponse( 'rest_unknown_attachment', $response_2, 404 );

		// Test: attachment src points to a sub-size of the image.
		$request_3 = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id_image1}/edit" );
		$request_3->set_body_params( array( 'src' => wp_get_attachment_image_url( $attachment_id_image1, 'medium' ) ) );

		$response_3 = rest_do_request( $request_3 );
		// 'rest_image_not_edited' as the file wasn't edited.
		$this->assertErrorResponse( 'rest_image_not_edited', $response_3, 400 );
	}

	/**
	 * Sets up the mock image editor.
	 *
	 * @since 5.5.0
	 */
	protected function setup_mock_editor() {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once DIR_TESTDATA . '/../includes/mock-image-editor.php';

		add_filter(
			'wp_image_editors',
			static function () {
				return array( 'WP_Image_Editor_Mock' );
			}
		);
	}

	/**
	 * @ticket 55443
	 */
	public function test_image_sources_to_rest_response() {

		$attachment_id = self::factory()->attachment->create_upload_object( $this->test_file );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$request       = new WP_REST_Request();
		$request['id'] = $attachment_id;
		$controller    = new WP_REST_Attachments_Controller( 'attachment' );
		$response      = $controller->get_item( $request );

		$this->assertNotWPError( $response );

		$data       = $response->get_data();
		$mime_types = array(
			'image/jpeg',
		);

		if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			array_push( $mime_types, 'image/webp' );
		}

		foreach ( $data['media_details']['sizes'] as $size_name => $properties ) {
			if ( ! isset( $metadata['sizes'][ $size_name ]['sources'] ) ) {
				continue;
			}

			$this->assertArrayHasKey( 'sources', $properties );
			$this->assertIsArray( $properties['sources'] );

			foreach ( $mime_types as $mime_type ) {
				$this->assertArrayHasKey( $mime_type, $properties['sources'] );
				$this->assertArrayHasKey( 'filesize', $properties['sources'][ $mime_type ] );
				$this->assertArrayHasKey( 'file', $properties['sources'][ $mime_type ] );
				$this->assertArrayHasKey( 'source_url', $properties['sources'][ $mime_type ] );
				$this->assertNotFalse( filter_var( $properties['sources'][ $mime_type ]['source_url'], FILTER_VALIDATE_URL ) );
			}
		}
		$this->assertArrayNotHasKey( 'sources', $data['media_details'] );
	}
}
