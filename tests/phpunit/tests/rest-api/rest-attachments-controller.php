<?php
/**
 * Unit tests covering WP_REST_Attachments_Controller functionality
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Attachments_Controller extends WP_Test_REST_Post_Type_Controller_Testcase {

	protected static int $superadmin_id;
	protected static int $editor_id;
	protected static int $author_id;
	protected static int $contributor_id;
	protected static int $uploader_id;
	protected static int $rest_after_insert_attachment_count;
	protected static int $rest_insert_attachment_count;

	/**
	 * @var string The path to a test file.
	 */
	private static string $test_file;

	/**
	 * @var string The path to a second test file.
	 */
	private static string $test_file2;

	/**
	 * @var string The path to the AVIF test image.
	 */
	private static string $test_avif_file;

	/**
	 * @var string The path to the SVG test image.
	 */
	private static string $test_svg_file;

	/**
	 * @var string The path to the test video.
	 */
	private static string $test_video_file;

	/**
	 * @var string The path to the test audio.
	 */
	private static string $test_audio_file;

	/**
	 * @var string The path to the test RTF file.
	 */
	private static string $test_rtf_file;

	/**
	 * @var array[] The recorded posts query clauses. Each entry is the array of
	 *              SQL clause fragments passed to the `posts_clauses` filter.
	 */
	protected array $posts_clauses;

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
		if ( file_exists( self::$test_file ) ) {
			unlink( self::$test_file );
		}
		if ( file_exists( self::$test_file2 ) ) {
			unlink( self::$test_file2 );
		}
		if ( file_exists( self::$test_avif_file ) ) {
			unlink( self::$test_avif_file );
		}
		if ( file_exists( self::$test_video_file ) ) {
			unlink( self::$test_video_file );
		}
		if ( file_exists( self::$test_audio_file ) ) {
			unlink( self::$test_audio_file );
		}
		if ( file_exists( self::$test_rtf_file ) ) {
			unlink( self::$test_rtf_file );
		}

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
		self::$test_file = get_temp_dir() . 'canola.jpg';
		if ( ! file_exists( self::$test_file ) ) {
			copy( $orig_file, self::$test_file );
		}

		$orig_file2       = DIR_TESTDATA . '/images/codeispoetry.png';
		self::$test_file2 = get_temp_dir() . 'codeispoetry.png';
		if ( ! file_exists( self::$test_file2 ) ) {
			copy( $orig_file2, self::$test_file2 );
		}

		$orig_avif_file       = DIR_TESTDATA . '/images/avif-lossy.avif';
		self::$test_avif_file = get_temp_dir() . 'avif-lossy.avif';
		if ( ! file_exists( self::$test_avif_file ) ) {
			copy( $orig_avif_file, self::$test_avif_file );
		}

		$test_svg_file       = DIR_TESTDATA . '/uploads/video-play.svg';
		self::$test_svg_file = get_temp_dir() . 'video-play.svg';
		if ( ! file_exists( self::$test_svg_file ) ) {
			copy( $test_svg_file, self::$test_svg_file );
		}

		$test_video_file       = DIR_TESTDATA . '/uploads/small-video.mp4';
		self::$test_video_file = get_temp_dir() . 'small-video.mp4';
		if ( ! file_exists( self::$test_video_file ) ) {
			copy( $test_video_file, self::$test_video_file );
		}

		$test_audio_file       = DIR_TESTDATA . '/uploads/small-audio.mp3';
		self::$test_audio_file = get_temp_dir() . 'small-audio.mp3';
		if ( ! file_exists( self::$test_audio_file ) ) {
			copy( $test_audio_file, self::$test_audio_file );
		}

		$test_rtf_file       = DIR_TESTDATA . '/uploads/test.rtf';
		self::$test_rtf_file = get_temp_dir() . 'test.rtf';
		if ( ! file_exists( self::$test_rtf_file ) ) {
			copy( $test_rtf_file, self::$test_rtf_file );
		}

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
		$this->remove_added_uploads();

		if ( class_exists( WP_Image_Editor_Mock::class ) ) {
			WP_Image_Editor_Mock::$spy         = array();
			WP_Image_Editor_Mock::$edit_return = array();
			WP_Image_Editor_Mock::$size_return = null;
		}

		parent::tear_down();
	}

	/**
	 * Enables client-side media processing and reinitializes the REST server
	 * so that the sideload and finalize routes are registered.
	 */
	private function enable_client_side_media_processing(): void {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_true' );

		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Turns client-side media processing off and rebuilds the REST server so the
	 * routes are registered with the feature disabled.
	 */
	private function disable_client_side_media_processing(): void {
		add_filter( 'wp_client_side_media_processing_enabled', '__return_false' );

		global $wp_rest_server;
		$wp_rest_server = new Spy_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/media', $routes );
		$this->assertCount( 2, $routes['/wp/v2/media'] );
		$this->assertArrayHasKey( '/wp/v2/media/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/media/(?P<id>[\d]+)'] );
	}

	/**
	 * @dataProvider data_parse_disposition
	 */
	public function test_parse_disposition( $header, $expected ) {
		$header_list = array( $header );
		$parsed      = WP_REST_Attachments_Controller::get_filename_from_disposition( $header_list );
		$this->assertSame( $expected, $parsed );
	}

	public static function data_parse_disposition() {
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

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertArrayNotHasKey( 'allow_batch', $data['endpoints'][0] );
		$this->assertSame( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertSame( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single.
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
				'search_columns',
				'search_semantics',
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
			'text',
		);
		$this->assertSameSets( $media_types, $data['endpoints'][0]['args']['media_type']['items']['enum'] );
	}

	public function test_registered_get_item_params() {
		$id1      = self::factory()->attachment->create_object(
			self::$test_file,
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
		$this->assertEqualSets( array( 'context', 'id' ), $keys );
	}

	/**
	 * @ticket 43701
	 */
	public function test_allow_header_sent_on_options_request() {
		$id1      = self::factory()->attachment->create_object(
			self::$test_file,
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
		$id1            = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$draft_post     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$id2            = self::factory()->attachment->create_object(
			self::$test_file,
			$draft_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$published_post = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$id3            = self::factory()->attachment->create_object(
			self::$test_file,
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
		$id1            = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$draft_post     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$id2            = self::factory()->attachment->create_object(
			self::$test_file,
			$draft_post,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$published_post = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$id3            = self::factory()->attachment->create_object(
			self::$test_file,
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
		$id1      = self::factory()->attachment->create_object(
			self::$test_file,
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

	/**
	 * Test multiple media types support with various input formats.
	 *
	 * @ticket 63668
	 */
	public function test_get_items_multiple_media_types() {
		$image_id = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$video_id = self::factory()->attachment->create_object(
			self::$test_video_file,
			0,
			array(
				'post_mime_type' => 'video/mp4',
			)
		);

		$audio_id = self::factory()->attachment->create_object(
			self::$test_audio_file,
			0,
			array(
				'post_mime_type' => 'audio/mpeg',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );

		// Test single media type.
		$request->set_param( 'media_type', 'image' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data, 'Response count for single media type is not 1' );
		$this->assertSame( $image_id, $data[0]['id'], 'Image ID not found in response for single media type' );

		// Test multiple media types with comma-separated string.
		$request->set_param( 'media_type', 'image,video' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data, 'Response count for multiple media types with comma-separated string is not 2' );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertContains( $image_id, $ids, 'Image ID not found in response for multiple media types with comma-separated string' );
		$this->assertContains( $video_id, $ids, 'Video ID not found in response for multiple media types with comma-separated string' );
		$this->assertNotContains( $audio_id, $ids, 'Audio ID found in response for multiple media types with comma-separated string' );

		// Test multiple media types with array format.
		$request->set_param( 'media_type', array( 'image', 'video', 'audio' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 3, $data, 'Response count for multiple media types with array format is not 3' );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertContains( $image_id, $ids, 'Image ID not found in response for multiple media types with array format' );
		$this->assertContains( $video_id, $ids, 'Video ID not found in response for multiple media types with array format' );
		$this->assertContains( $audio_id, $ids, 'Audio ID not found in response for multiple media types with array format' );

		// Test invalid media type mixed with valid ones.
		$request->set_param( 'media_type', 'image,invalid,video' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test multiple MIME types support and combination with media types.
	 *
	 * @ticket 63668
	 */
	public function test_get_items_multiple_mime_types_and_combination() {
		$jpeg_id = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$png_id = self::factory()->attachment->create_object(
			self::$test_file2,
			0,
			array(
				'post_mime_type' => 'image/png',
			)
		);

		$mp4_id = self::factory()->attachment->create_object(
			self::$test_video_file,
			0,
			array(
				'post_mime_type' => 'video/mp4',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );

		// Test single MIME type
		$request->set_param( 'mime_type', 'image/jpeg' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 1, $data, 'Response count for single MIME type is not 1' );
		$this->assertSame( $jpeg_id, $data[0]['id'], 'JPEG ID not found in response for single MIME type' );

		// Test multiple MIME types with comma-separated string.
		$request->set_param( 'mime_type', 'image/jpeg,image/png' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data, 'Response count for multiple MIME types with comma-separated string is not 2' );
		$ids = wp_list_pluck( $data, 'id' );
		$this->assertContains( $jpeg_id, $ids, 'JPEG ID not found in response for multiple MIME types with comma-separated string' );
		$this->assertContains( $png_id, $ids, 'PNG ID not found in response for multiple MIME types with comma-separated string' );

		// Test multiple MIME types with array format.
		$request->set_param( 'mime_type', array( 'image/jpeg', 'video/mp4' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertCount( 2, $data, 'Response count for multiple MIME types with array format is not 2' );
		$ids = wp_list_pluck( $data, 'id' );

		$this->assertContains( $jpeg_id, $ids, 'JPEG ID not found in response for multiple MIME types with array format' );
		$this->assertContains( $mp4_id, $ids, 'MP4 ID not found in response for multiple MIME types with array format' );
	}

	/**
	 * Test combination of media type and mime type parameters.
	 *
	 * @ticket 63668
	 */
	public function test_get_items_with_media_type_and_media_types() {
		$audio_id = self::factory()->attachment->create_object(
			self::$test_audio_file,
			0,
			array(
				'post_mime_type' => 'audio/mpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);

		$jpeg_id = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);

		$png_id = self::factory()->attachment->create_object(
			self::$test_file2,
			0,
			array(
				'post_mime_type' => 'image/png',
			)
		);

		$video_id = self::factory()->attachment->create_object(
			self::$test_video_file,
			0,
			array(
				'post_mime_type' => 'video/mp4',
			)
		);

		$rtf_id = self::factory()->attachment->create_object(
			self::$test_rtf_file,
			0,
			array(
				'post_mime_type' => 'application/rtf',
			)
		);

		// Test combination of single media type and single mime type parameters.
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'media_type', 'image' );
		$request->set_param( 'mime_type', 'audio/mpeg' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );

		$this->assertCount( 3, $data, 'Response count for combination of single media type and single mime type parameters is not 3' );
		$this->assertContains( $jpeg_id, $ids, 'JPEG ID not found in response' );
		$this->assertContains( $png_id, $ids, 'PNG ID not found in response' );
		$this->assertContains( $audio_id, $ids, 'Audio ID found in response' );

		// Test combination of single media type and multiple mime type parameters.
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'media_type', 'audio' );
		$request->set_param( 'mime_type', array( 'image/jpeg', 'image/png' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );

		$this->assertCount( 3, $data, 'Response count for combination of single media type and multiple mime type parameters is not 3' );
		$this->assertContains( $audio_id, $ids, 'Audio ID not found in response' );
		$this->assertContains( $jpeg_id, $ids, 'JPEG ID not found in response' );
		$this->assertContains( $png_id, $ids, 'PNG ID not found in response' );

		// Test combination of multiple media types and single mime type parameters.
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'media_type', 'audio,video' );
		$request->set_param( 'mime_type', array( 'image/jpeg' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );

		$this->assertCount( 3, $data, 'Response count for combination of multiple media type and multiple mime type parameters is not 3' );
		$this->assertContains( $audio_id, $ids, 'Audio ID not found in response' );
		$this->assertContains( $jpeg_id, $ids, 'JPEG ID not found in response' );
		$this->assertContains( $video_id, $ids, 'Video ID not found in response' );

		// Test combination of multiple media types and multiple mime type parameters.
		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'media_type', 'audio,video' );
		$request->set_param( 'mime_type', array( 'image/jpeg', 'image/png', 'application/rtf' ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$ids      = wp_list_pluck( $data, 'id' );

		$this->assertCount( 5, $data, 'Response count for combination of multiple media type and multiple mime type parameters is not 3' );
		$this->assertContains( $audio_id, $ids, 'Audio ID not found in response' );
		$this->assertContains( $jpeg_id, $ids, 'JPEG ID not found in response' );
		$this->assertContains( $video_id, $ids, 'Video ID not found in response' );
		$this->assertContains( $png_id, $ids, 'PNG ID not found in response' );
		$this->assertContains( $rtf_id, $ids, 'RTF ID not found in response' );
	}

	public function test_get_items_mime_type() {
		$id1      = self::factory()->attachment->create_object(
			self::$test_file,
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
		$post_id        = self::factory()->post->create( array( 'post_title' => 'Test Post' ) );
		$attachment_id  = self::factory()->attachment->create_object(
			self::$test_file,
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$attachment_id2 = self::factory()->attachment->create_object(
			self::$test_file,
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
		self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id1 = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id1 = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_status'    => 'private',
			)
		);
		$attachment_id2 = self::factory()->attachment->create_object(
			self::$test_file,
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
		$id1     = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_date'      => '2016-01-15T00:00:00Z',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id2     = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_date'      => '2016-01-16T00:00:00Z',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id3     = self::factory()->attachment->create_object(
			self::$test_file,
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
		$id1 = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_date'      => '2016-01-01 00:00:00',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id2 = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_date'      => '2016-01-02 00:00:00',
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);
		$id3 = self::factory()->attachment->create_object(
			self::$test_file,
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
		self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
	 * Ensures int-castable `filesize` values in attachment metadata are normalized
	 * to an integer in the response.
	 *
	 * Attachment metadata is untyped, so plugins that populate `filesize` from
	 * a remote storage API may store it as a string.
	 *
	 * @ticket 65670
	 *
	 * @dataProvider data_valid_filesize_meta
	 *
	 * @param mixed $stored_filesize   Valid `filesize` metadata value.
	 * @param int   $expected_filesize Expected `filesize` value in the REST response after normalization.
	 */
	public function test_get_item_normalizes_int_castable_filesize_meta( $stored_filesize, int $expected_filesize ) {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => self::$test_file,
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->assertIsInt( $attachment_id );

		$meta             = wp_get_attachment_metadata( $attachment_id );
		$meta             = is_array( $meta ) ? $meta : array();
		$meta['filesize'] = $stored_filesize;
		$this->assertNotFalse( wp_update_attachment_metadata( $attachment_id, $meta ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected_filesize, $data['filesize'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: mixed, 1: int }>
	 */
	public function data_valid_filesize_meta(): array {
		return array(
			'integer string'      => array( '123456', 123456 ),
			'float string'        => array( '123.4', 123 ),
			'scientific notation' => array( '1e3', 1000 ),
			'float'               => array( 123.0, 123 ),
		);
	}

	/**
	 * Ensures a `filesize` metadata value that is not a positive number does not
	 * cause a fatal TypeError or a bogus size cast from a non-numeric value,
	 * falling back to the actual file size instead.
	 *
	 * Numeric values are trusted and cast to an integer instead, per
	 * {@see self::test_get_item_normalizes_int_castable_filesize_meta()}.
	 *
	 * @ticket 65670
	 *
	 * @dataProvider data_invalid_filesize_meta
	 *
	 * @param mixed $filesize Invalid `filesize` metadata value.
	 */
	public function test_get_item_recovers_from_invalid_filesize_meta( $filesize ) {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => self::$test_file,
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->assertIsInt( $attachment_id );

		$meta             = wp_get_attachment_metadata( $attachment_id );
		$meta             = is_array( $meta ) ? $meta : array();
		$meta['filesize'] = $filesize;
		$this->assertNotFalse( wp_update_attachment_metadata( $attachment_id, $meta ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsInt( $data['filesize'] );
		$attached_file = wp_get_original_image_path( $attachment_id );
		$attached_file = $attached_file ? $attached_file : get_attached_file( $attachment_id );
		$this->assertIsString( $attached_file );
		$this->assertSame( filesize( $attached_file ), $data['filesize'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: mixed }>
	 */
	public function data_invalid_filesize_meta(): array {
		return array(
			'non-numeric string'      => array( 'corrupt' ),
			'boolean'                 => array( true ),
			'zero'                    => array( 0 ),
			'zero string'             => array( '0' ),
			'negative integer'        => array( -5 ),
			'negative integer string' => array( '-5' ),
		);
	}

	/**
	 * @requires function imagejpeg
	 */
	public function test_get_item_sizes() {
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			),
			self::$test_file
		);

		add_image_size( 'rest-api-test', 119, 119, true );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, self::$test_file ) );

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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			),
			self::$test_file
		);

		add_image_size( 'rest-api-test', 119, 119, true );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, self::$test_file ) );

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
		$draft_post = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$id1        = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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

		$request->set_body( file_get_contents( self::$test_file ) );
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
					'file'     => file_get_contents( self::$test_file2 ),
					'name'     => 'codeispoetry.png',
					'size'     => filesize( self::$test_file2 ),
					'tmp_name' => self::$test_file2,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( self::$test_file2 ) );
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
					'file'     => file_get_contents( self::$test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( self::$test_file ),
					'tmp_name' => self::$test_file,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( self::$test_file ) );
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
					'file'     => file_get_contents( self::$test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( self::$test_file ),
					'tmp_name' => self::$test_file,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( self::$test_file ) );
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
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_content_type', $response, 400 );
	}

	public function test_create_item_missing_content_disposition() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_no_content_disposition', $response, 400 );
	}

	public function test_create_item_bad_md5_header() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_header( 'Content-MD5', 'abc123' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_upload_hash_mismatch', $response, 412 );
	}

	public function test_create_item_with_files_bad_md5_header() {
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( self::$test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( self::$test_file ),
					'tmp_name' => self::$test_file,
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
		$post_id = self::factory()->post->create( array( 'post_author' => self::$editor_id ) );
		wp_set_current_user( self::$author_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_create_item_invalid_upload_permissions() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$editor_id ) );
		wp_set_current_user( self::$uploader_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'post', $post_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_create_item_invalid_post_type() {
		$attachment_id = self::factory()->post->create(
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
		$request->set_body( file_get_contents( self::$test_file ) );
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

		$request->set_body( file_get_contents( self::$test_file ) );
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
		$request->set_body( file_get_contents( self::$test_file ) );
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
		$request->set_body( file_get_contents( self::$test_file ) );
		$response   = rest_get_server()->dispatch( $request );
		$attachment = $response->get_data();
		$this->assertStringNotContainsString( ABSPATH, get_post_meta( $attachment['id'], '_wp_attached_file', true ) );
	}

	/**
	 * @ticket 57897
	 *
	 * @requires function imagejpeg
	 */
	public function test_create_item_with_terms() {
		wp_set_current_user( self::$author_id );
		register_taxonomy_for_object_type( 'category', 'attachment' );
		$category = wp_insert_term( 'Media Category', 'category' );
		$request  = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );

		$request->set_body( file_get_contents( self::$test_file ) );
		$request->set_param( 'categories', array( $category['term_id'] ) );
		$response   = rest_get_server()->dispatch( $request );
		$attachment = $response->get_data();

		$term = wp_get_post_terms( $attachment['id'], 'category' );
		$this->assertSame( $category['term_id'], $term[0]->term_id );
	}

	/**
	 * @ticket 41692
	 */
	public function test_create_update_post_with_featured_media() {
		// Add support for thumbnails on all attachment types to avoid incorrect-usage notice.
		add_post_type_support( 'attachment', 'thumbnail' );

		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( self::$test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( self::$test_file ),
					'tmp_name' => self::$test_file,
				),
			)
		);
		$request->set_header( 'Content-MD5', md5_file( self::$test_file ) );

		$file          = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = self::factory()->attachment->create_object(
			$file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'menu_order'     => rand( 1, 100 ),
			)
		);

		$request->set_param( 'featured_media', $attachment_id );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );

		$new_attachment = get_post( $data['id'] );

		$this->assertSame( $attachment_id, get_post_thumbnail_id( $new_attachment->ID ) );
		$this->assertSame( $attachment_id, $data['featured_media'] );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/media/' . $new_attachment->ID );
		$params  = $this->set_post_data(
			array(
				'featured_media' => 0,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 0, $data['featured_media'] );
		$this->assertSame( 0, get_post_thumbnail_id( $new_attachment->ID ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/media/' . $new_attachment->ID );
		$params  = $this->set_post_data(
			array(
				'featured_media' => $attachment_id,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $attachment_id, $data['featured_media'] );
		$this->assertSame( $attachment_id, get_post_thumbnail_id( $new_attachment->ID ) );
	}

	public function test_update_item() {
		wp_set_current_user( self::$editor_id );
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$original_parent = self::factory()->post->create( array() );
		$attachment_id   = self::factory()->attachment->create_object(
			self::$test_file,
			$original_parent,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_author'    => self::$editor_id,
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertSame( $original_parent, $attachment->post_parent );

		$new_parent = self::factory()->post->create( array() );
		$request    = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'post', $new_parent );
		rest_get_server()->dispatch( $request );

		$attachment = get_post( $attachment_id );
		$this->assertSame( $new_parent, $attachment->post_parent );
	}

	public function test_update_item_invalid_permissions() {
		wp_set_current_user( self::$author_id );
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->post->create(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_parent' => 0,
			)
		);
		wp_set_current_user( self::$editor_id );
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
			self::$test_file,
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
			self::$test_file,
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
		$request->set_body( file_get_contents( self::$test_file ) );

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

	/**
	 * @dataProvider data_attachment_roundtrip_as_author
	 * @requires function imagejpeg
	 */
	public function test_attachment_roundtrip_as_author( $raw, $expected ) {
		wp_set_current_user( self::$author_id );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$this->verify_attachment_roundtrip( $raw, $expected );
	}

	public static function data_attachment_roundtrip_as_author() {
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
						'raw'      => '<a href="#" target="_blank">link</a>',
						'rendered' => '<p><a href="#" target="_blank">link</a></p>',
					),
					'caption'     => array(
						'raw'      => '<a href="#" target="_blank">link</a>',
						'rendered' => '<p><a href="#" target="_blank">link</a></p>',
					),
				),
			),
		);
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
		$attachment_id    = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$this->assertCount( 35, $properties );
		$this->assertArrayHasKey( 'author', $properties );
		$this->assertArrayHasKey( 'alt_text', $properties );
		$this->assertArrayHasKey( 'exif_orientation', $properties );
		$this->assertArrayHasKey( 'image_quality', $properties );
		$this->assertArrayHasKey( 'image_output_format', $properties );
		$this->assertArrayHasKey( 'image_save_progressive', $properties );
		$this->assertArrayHasKey( 'filename', $properties );
		$this->assertArrayHasKey( 'filesize', $properties );
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
		$this->assertArrayHasKey( 'featured_media', $properties );
		$this->assertArrayHasKey( 'class_list', $properties );
	}

	/**
	 * @ticket 65262
	 */
	public function test_image_quality_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( 'image_quality', $properties );
		$this->assertSame( 'object', $properties['image_quality']['type'] );
		$this->assertContains( 'edit', $properties['image_quality']['context'] );
		$this->assertTrue( $properties['image_quality']['readonly'] );

		$default = $properties['image_quality']['properties']['default'];
		$this->assertSame( 'integer', $default['type'] );
		$this->assertSame( 1, $default['minimum'] );
		$this->assertSame( 100, $default['maximum'] );

		$sizes = $properties['image_quality']['properties']['sizes'];
		$this->assertSame( 'object', $sizes['type'] );
		// Sizes are enumerated from the registered sub-sizes, each bounded 1-100.
		$this->assertArrayHasKey( 'thumbnail', $sizes['properties'] );
		$this->assertSame( 'integer', $sizes['properties']['thumbnail']['type'] );
		$this->assertSame( 1, $sizes['properties']['thumbnail']['minimum'] );
		$this->assertSame( 100, $sizes['properties']['thumbnail']['maximum'] );
	}

	/**
	 * @ticket 65262
	 * @requires function imagejpeg
	 */
	public function test_image_quality_default_in_response() {
		wp_set_current_user( self::$editor_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		$request = new WP_REST_Request( 'GET', "/wp/v2/media/{$attachment}" );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'image_quality', $data );
		// JPEG default quality is 82; no filter, so no per-size overrides.
		$this->assertSame( 82, $data['image_quality']['default'] );
		$this->assertSame( array(), $data['image_quality']['sizes'] );
	}

	/**
	 * @ticket 65262
	 * @requires function imagejpeg
	 */
	public function test_image_quality_with_size_aware_filter() {
		wp_set_current_user( self::$editor_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		// Lower the quality for small images (e.g. thumbnails) only.
		$filter = static function ( $quality, $mime_type, $size ) {
			if ( is_array( $size ) && ! empty( $size['width'] ) && $size['width'] <= 300 ) {
				return 60;
			}
			return $quality;
		};
		add_filter( 'wp_editor_set_quality', $filter, 10, 3 );

		$request = new WP_REST_Request( 'GET', "/wp/v2/media/{$attachment}" );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		remove_filter( 'wp_editor_set_quality', $filter, 10 );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'image_quality', $data );
		// The full-size image (> 300px wide) keeps the default quality.
		$this->assertSame( 82, $data['image_quality']['default'] );
		// The thumbnail size (150x150) is <= 300px and diverges to 60.
		$this->assertArrayHasKey( 'thumbnail', $data['image_quality']['sizes'] );
		$this->assertSame( 60, $data['image_quality']['sizes']['thumbnail'] );
	}

	/**
	 * The reported quality must include the legacy jpeg_quality filter, the same
	 * way WP_Image_Editor::set_quality() applies it for JPEG output.
	 *
	 * @ticket 65262
	 * @requires function imagejpeg
	 */
	public function test_image_quality_honors_jpeg_quality_filter() {
		wp_set_current_user( self::$editor_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		$filter = static function () {
			return 70;
		};
		add_filter( 'jpeg_quality', $filter );

		$request = new WP_REST_Request( 'GET', "/wp/v2/media/{$attachment}" );
		$request->set_param( 'context', 'edit' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		remove_filter( 'jpeg_quality', $filter );

		$this->assertSame( 200, $response->get_status() );
		// JPEG output, so the jpeg_quality filter overrides the 82 default.
		$this->assertSame( 70, $data['image_quality']['default'] );
	}

	/**
	 * Tests the image_output_format / image_save_progressive schema properties.
	 *
	 * @ticket 65367
	 *
	 * @covers WP_REST_Attachments_Controller::get_item_schema
	 */
	public function test_image_output_format_and_progressive_schema(): void {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/media' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertArrayHasKey( 'image_output_format', $properties );
		$this->assertSame( array( 'string', 'null' ), $properties['image_output_format']['type'] );
		$this->assertSame( array( 'edit' ), $properties['image_output_format']['context'] );
		$this->assertTrue( $properties['image_output_format']['readonly'] );

		$this->assertArrayHasKey( 'image_save_progressive', $properties );
		$this->assertSame( 'boolean', $properties['image_save_progressive']['type'] );
		$this->assertSame( array( 'edit' ), $properties['image_save_progressive']['context'] );
		$this->assertTrue( $properties['image_save_progressive']['readonly'] );
	}

	/**
	 * Verifies image_output_format is null by default (no conversion needed) and
	 * image_save_progressive defaults to false on a freshly uploaded JPEG.
	 *
	 * @ticket 65367
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::prepare_item_for_response
	 */
	public function test_image_output_format_and_progressive_defaults_in_create_response(): void {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertArrayHasKey( 'image_output_format', $data );
		$this->assertNull( $data['image_output_format'] );
		$this->assertArrayHasKey( 'image_save_progressive', $data );
		$this->assertFalse( $data['image_save_progressive'] );
	}

	/**
	 * Uploads an image through the REST API, optionally as an edit of another attachment.
	 *
	 * @param int|null $parent_image Attachment the upload was edited from.
	 * @return WP_REST_Response Response.
	 */
	private function create_edited_image_response( ?int $parent_image = null ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-edited.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		if ( null !== $parent_image ) {
			$request->set_param( 'parent_image', $parent_image );
		}
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * A client-side edit records its source attachment like the edit endpoint does.
	 *
	 * @ticket 66027
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 */
	public function test_create_item_records_parent_image(): void {
		wp_set_current_user( self::$superadmin_id );

		$parent_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$parent_metadata                              = wp_get_attachment_metadata( $parent_id );
		$parent_metadata['image_meta']['credit']      = 'Photographer';
		$parent_metadata['image_meta']['orientation'] = 6;
		wp_update_attachment_metadata( $parent_id, $parent_metadata );

		$response = $this->create_edited_image_response( $parent_id );
		$this->assertSame( 201, $response->get_status() );

		$data     = $response->get_data();
		$metadata = wp_get_attachment_metadata( $data['id'] );
		$expected = array(
			'attachment_id' => $parent_id,
			'file'          => _wp_relative_upload_path( wp_get_original_image_path( $parent_id ) ),
		);

		$this->assertSame( $expected, $metadata['parent_image'], 'The metadata records the source attachment.' );
		$this->assertSame( $expected, $data['media_details']['parent_image'], 'The response reflects the source attachment.' );
		$this->assertSame( 'Photographer', $metadata['image_meta']['credit'], 'EXIF fields the new file lacks are copied from the source.' );
		$this->assertSame( 1, $metadata['image_meta']['orientation'], 'The edited pixels are upright.' );
	}

	/**
	 * @ticket 66027
	 *
	 * @covers WP_REST_Attachments_Controller::record_parent_image
	 */
	public function test_create_item_filters_the_edited_image_metadata(): void {
		wp_set_current_user( self::$superadmin_id );

		$parent_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$received  = array();

		add_filter(
			'wp_edited_image_metadata',
			static function ( $new_image_meta, $new_attachment_id, $attachment_id ) use ( &$received ) {
				$received                        = compact( 'new_image_meta', 'new_attachment_id', 'attachment_id' );
				$new_image_meta['original_root'] = $attachment_id;
				return $new_image_meta;
			},
			10,
			3
		);

		$response = $this->create_edited_image_response( $parent_id );
		$this->assertSame( 201, $response->get_status() );

		$new_id = $response->get_data()['id'];

		$this->assertSame( $new_id, $received['new_attachment_id'], 'The filter receives the new attachment.' );
		$this->assertSame( $parent_id, $received['attachment_id'], 'The filter receives the source attachment.' );
		$this->assertSame( $parent_id, $received['new_image_meta']['parent_image']['attachment_id'], 'The filter sees the recorded source.' );
		$this->assertSame( $parent_id, wp_get_attachment_metadata( $new_id )['original_root'], 'The filtered metadata is what gets saved.' );
	}

	/**
	 * @ticket 66027
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 */
	public function test_create_item_without_parent_image_records_nothing(): void {
		wp_set_current_user( self::$superadmin_id );

		$response = $this->create_edited_image_response();
		$this->assertSame( 201, $response->get_status() );

		$metadata = wp_get_attachment_metadata( $response->get_data()['id'] );
		$this->assertArrayNotHasKey( 'parent_image', $metadata );
	}

	/**
	 * @ticket 66027
	 *
	 * @covers WP_REST_Attachments_Controller::get_endpoint_args_for_item_schema
	 */
	public function test_create_item_rejects_a_parent_image_that_is_not_an_image(): void {
		wp_set_current_user( self::$superadmin_id );

		$post_id  = self::factory()->post->create();
		$response = $this->create_edited_image_response( $post_id );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 66027
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_permissions_check
	 */
	public function test_create_item_requires_permission_to_edit_the_parent_image(): void {
		wp_set_current_user( self::$superadmin_id );
		$parent_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		// Authors can upload, but cannot edit another user's attachment.
		wp_set_current_user( self::$author_id );

		$response = $this->create_edited_image_response( $parent_id );

		$this->assertErrorResponse( 'rest_cannot_edit_image', $response, 403 );
	}

	/**
	 * Verifies image_output_format reflects an image_editor_output_format filter
	 * that remaps JPEG to WebP, and that the filter sees the real attached
	 * filename and MIME type.
	 *
	 * @ticket 65367
	 *
	 * @covers WP_REST_Attachments_Controller::prepare_item_for_response
	 */
	public function test_image_output_format_with_custom_filter(): void {
		wp_set_current_user( self::$superadmin_id );

		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		$captured = array();
		add_filter(
			'image_editor_output_format',
			static function ( $formats, $filename, $mime_type ) use ( &$captured ) {
				$captured['filename']  = $filename;
				$captured['mime_type'] = $mime_type;
				$formats['image/jpeg'] = 'image/webp';
				return $formats;
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'image_output_format', $data );
		$this->assertSame( 'image/webp', $data['image_output_format'] );

		// The filter must be invoked with the real attached filename and MIME type.
		$this->assertStringEndsWith( '.jpg', (string) $captured['filename'] );
		$this->assertSame( 'image/jpeg', $captured['mime_type'] );
	}

	/**
	 * Verifies image_save_progressive surfaces the filter result for the
	 * attachment's MIME type.
	 *
	 * @ticket 65367
	 *
	 * @covers WP_REST_Attachments_Controller::prepare_item_for_response
	 */
	public function test_image_save_progressive_with_custom_filter(): void {
		wp_set_current_user( self::$superadmin_id );

		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		add_filter(
			'image_save_progressive',
			static function ( $progressive, $mime_type ) {
				return 'image/jpeg' === $mime_type;
			},
			10,
			2
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'image_save_progressive', $data );
		$this->assertTrue( $data['image_save_progressive'] );
	}

	/**
	 * Non-image attachments must not surface the image_* fields.
	 *
	 * @ticket 65367
	 *
	 * @covers WP_REST_Attachments_Controller::prepare_item_for_response
	 */
	public function test_image_output_format_skipped_for_non_image(): void {
		wp_set_current_user( self::$superadmin_id );

		$attachment_id = self::factory()->attachment->create_object(
			DIR_TESTDATA . '/uploads/dashicons.woff',
			0,
			array(
				'post_mime_type' => 'application/font-woff',
				'post_type'      => 'attachment',
			)
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id );
		$request->set_param( 'context', 'edit' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'image_output_format', $data );
		$this->assertArrayNotHasKey( 'image_save_progressive', $data );
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

		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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

	public function additional_field_get_callback( $response_data, $field_name ) {
		return 123;
	}

	public function additional_field_update_callback( $value, $attachment ) {
		if ( 'returnError' === $value ) {
			return new WP_Error( 'rest_invalid_param', 'Testing an error.', array( 'status' => 400 ) );
		}
	}

	public function test_search_item_by_filename() {
		$id1 = self::factory()->attachment->create_object(
			self::$test_file,
			0,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		$id2 = self::factory()->attachment->create_object(
			self::$test_file2,
			0,
			array(
				'post_mime_type' => 'image/png',
			)
		);

		$filename = wp_basename( self::$test_file2 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'search', $filename );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertSame( $id2, $data[0]['id'] );
		$this->assertSame( 'image/png', $data[0]['mime_type'] );
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
		$this->assertArrayNotHasKey( 'post', $links );

		$this->assertCount( 1, $links['author'] );
		$this->assertArrayHasKey( 'embeddable', $links['author'][0]['attributes'] );
		$this->assertTrue( $links['author'][0]['attributes']['embeddable'] );
	}

	/**
	 * @ticket 64034
	 */
	public function test_links_contain_parent() {
		wp_set_current_user( self::$editor_id );

		$post       = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			)
		);
		$attachment = self::factory()->attachment->create_object(
			array(
				'file'           => self::$test_file,
				'post_author'    => self::$editor_id,
				'post_parent'    => $post,
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->assertGreaterThan( 0, $attachment );

		$request = new WP_REST_Request( 'GET', "/wp/v2/media/{$attachment}" );
		$request->set_query_params( array( 'context' => 'edit' ) );

		$response = rest_get_server()->dispatch( $request );
		$links    = $response->get_links();

		$this->assertArrayHasKey( 'self', $links );
		$this->assertArrayHasKey( 'author', $links );
		$this->assertArrayHasKey( 'https://api.w.org/attached-to', $links );

		$this->assertCount( 1, $links['author'] );
		$this->assertSame( rest_url( '/wp/v2/posts/' . $post ), $links['https://api.w.org/attached-to'][0]['href'] );
		$this->assertSame( 'post', $links['https://api.w.org/attached-to'][0]['attributes']['post_type'] );
		$this->assertSame( $post, $links['https://api.w.org/attached-to'][0]['attributes']['id'] );
		$this->assertTrue( $links['https://api.w.org/attached-to'][0]['attributes']['embeddable'] );
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
					'file'     => file_get_contents( self::$test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( self::$test_file ),
					'tmp_name' => self::$test_file,
				),
			)
		);
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_header( 'Content-MD5', md5_file( self::$test_file ) );

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
		$request->set_body( file_get_contents( self::$test_file ) );
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
					'file'     => file_get_contents( self::$test_file ),
					'name'     => 'canola.jpg',
					'size'     => filesize( self::$test_file ),
					'tmp_name' => self::$test_file,
				),
			)
		);
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_header( 'Content-MD5', md5_file( self::$test_file ) );

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
		$request->set_body( file_get_contents( self::$test_file ) );
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

		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 201, $response->get_status() );

		$this->assertSame( 1, self::$rest_insert_attachment_count );
		$this->assertSame( 1, self::$rest_after_insert_attachment_count );
	}

	/**
	 * Tests that the naming behavior of REST media uploads matches core media uploads.
	 *
	 * In particular, filenames with spaces should maintain the spaces rather than
	 * replacing them with hyphens.
	 *
	 * @ticket 57957
	 *
	 * @covers WP_REST_Attachments_Controller::insert_attachment
	 * @dataProvider rest_upload_filename_spaces
	 */
	public function test_rest_upload_filename_spaces( $filename, $expected ) {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( self::$test_file2 ),
					'name'     => $filename,
					'size'     => filesize( self::$test_file2 ),
					'tmp_name' => self::$test_file2,
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertSame( 201, $response->get_status(), 'The file was not uploaded.' );
		$this->assertSame( $expected, $data['title']['raw'], 'An incorrect filename was returned.' );
	}

	/**
	 * Data provider for text_rest_upload_filename_spaces.
	 *
	 * @return array
	 */
	public function rest_upload_filename_spaces() {
		return array(
			'filename with spaces'  => array(
				'Filename With Spaces.jpg',
				'Filename With Spaces',
			),
			'filename.with.periods' => array(
				'Filename.With.Periods.jpg',
				'Filename.With.Periods',
			),
			'filename-with-dashes'  => array(
				'Filename-With-Dashes.jpg',
				'Filename-With-Dashes',
			),
		);
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
		$attachment_id = self::factory()->attachment->create_object(
			self::$test_file,
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

		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Chocolate-dipped, no filling', get_post_meta( $response->get_data()['id'], 'best_cannoli', true ) );
	}

	/**
	 * @ticket 61189
	 * @requires function imagejpeg
	 */
	public function test_create_item_year_month_based_folders() {
		update_option( 'uploads_use_yearmonth_folders', 1 );

		wp_set_current_user( self::$editor_id );

		$published_post = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_date'     => '2017-02-14 00:00:00',
				'post_date_gmt' => '2017-02-14 00:00:00',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );
		$request->set_param( 'post', $published_post );

		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		update_option( 'uploads_use_yearmonth_folders', 0 );

		$this->assertSame( 201, $response->get_status() );

		$attachment = get_post( $data['id'] );

		$this->assertSame( $attachment->post_parent, $data['post'] );
		$this->assertSame( $attachment->post_parent, $published_post );
		$this->assertSame( wp_get_attachment_url( $attachment->ID ), $data['source_url'] );
		$this->assertStringContainsString( '2017/02', $data['source_url'] );
	}


	/**
	 * @ticket 61189
	 * @requires function imagejpeg
	 */
	public function test_create_item_year_month_based_folders_page_post_type() {
		update_option( 'uploads_use_yearmonth_folders', 1 );

		wp_set_current_user( self::$editor_id );

		$published_post = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_date'     => '2017-02-14 00:00:00',
				'post_date_gmt' => '2017-02-14 00:00:00',
			)
		);

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );
		$request->set_param( 'post', $published_post );

		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		update_option( 'uploads_use_yearmonth_folders', 0 );

		$time   = current_time( 'mysql' );
		$y      = substr( $time, 0, 4 );
		$m      = substr( $time, 5, 2 );
		$subdir = "/$y/$m";

		$this->assertSame( 201, $response->get_status() );

		$attachment = get_post( $data['id'] );

		$this->assertSame( $attachment->post_parent, $data['post'] );
		$this->assertSame( $attachment->post_parent, $published_post );
		$this->assertSame( wp_get_attachment_url( $attachment->ID ), $data['source_url'] );
		$this->assertStringNotContainsString( '2017/02', $data['source_url'] );
		$this->assertStringContainsString( $subdir, $data['source_url'] );
	}

	public function filter_rest_insert_attachment( $attachment ) {
		++self::$rest_insert_attachment_count;
	}

	public function filter_rest_after_insert_attachment( $attachment ) {
		++self::$rest_after_insert_attachment_count;
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_logged_out() {
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );
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
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
			array( 320, 48, 64, 24 ),
			WP_Image_Editor_Mock::$spy['crop'][0]
		);
	}

	/**
	 * @ticket 61514
	 * @requires function imagejpeg
	 */
	public function test_edit_image_crop_one_axis() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
				'y'      => 0,
				'width'  => 10,
				'height' => 100,
				'src'    => wp_get_attachment_image_url( $attachment, 'full' ),

			)
		);
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_crop_failed', $response, 500 );

		$this->assertCount( 1, WP_Image_Editor_Mock::$spy['crop'] );
		$this->assertSame(
			array( 320, 0, 64, 480 ),
			WP_Image_Editor_Mock::$spy['crop'][0]
		);
	}

	/**
	 * @ticket 65618
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_no_image_editor() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		add_filter( 'wp_image_editors', '__return_empty_array' );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params(
			array(
				'rotation' => 60,
				'src'      => wp_get_attachment_image_url( $attachment, 'full' ),
			)
		);
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_unknown_image_file_type', $response, 500 );
	}

	/**
	 * @ticket 65618
	 * @requires function imagejpeg
	 */
	public function test_edit_image_applies_unbaked_exif_orientation_before_edits() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		$this->setup_mock_editor();
		add_filter(
			'wp_image_maybe_exif_rotate',
			static function () {
				return 6;
			}
		);
		WP_Image_Editor_Mock::$edit_return['rotate'] = new WP_Error();

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params(
			array(
				'rotation' => 60,
				'src'      => wp_get_attachment_image_url( $attachment, 'full' ),
			)
		);
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_rotation_failed', $response, 500 );

		// The EXIF orientation correction (orientation 6 => rotate 270) must run before the requested edit.
		$this->assertSame( array( array( 270 ), array( -60 ) ), WP_Image_Editor_Mock::$spy['rotate'] );
	}

	/**
	 * @ticket 65618
	 * @requires extension exif
	 * @requires function imagejpeg
	 */
	public function test_edit_image_rotate_with_unbaked_exif_orientation() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image-rotated-90ccw.jpg' );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params(
			array(
				'rotation' => 90,
				'src'      => wp_get_attachment_image_url( $attachment, 'full' ),
			)
		);
		$response = rest_do_request( $request );
		$item     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );

		/*
		 * The original file is 1200x1800 raw pixels with an unapplied EXIF orientation of 6,
		 * so clients preview it upright as 1800x1200. Rotating that upright frame 90 degrees
		 * must produce 1200x1800. Without the orientation correction the edit rotates the raw
		 * pixels instead and produces 1800x1200.
		 */
		$this->assertSame( 1200, $item['media_details']['width'] );
		$this->assertSame( 1800, $item['media_details']['height'] );
	}

	/**
	 * @ticket 44405
	 * @requires function imagejpeg
	 */
	public function test_edit_image() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$this->assertSame( (string) $attachment, $item['media_details']['parent_image']['attachment_id'] );
		$this->assertStringContainsString( 'canola', $item['media_details']['parent_image']['file'] );
	}

	/**
	 * @ticket 52192
	 * @requires function imagejpeg
	 */
	public function test_batch_edit_image() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

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
		$this->assertSame( (string) $attachment, $item['media_details']['parent_image']['attachment_id'] );
		$this->assertStringContainsString( 'canola', $item['media_details']['parent_image']['file'] );
	}

	/**
	 * @ticket 50565
	 * @requires function imagejpeg
	 */
	public function test_edit_image_returns_error_if_mismatched_src() {
		wp_set_current_user( self::$superadmin_id );
		$attachment_id_image1 = self::factory()->attachment->create_upload_object( self::$test_file );
		$attachment_id_image2 = self::factory()->attachment->create_upload_object( self::$test_file2 );
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
	 * Test that uploading unsupported image types throws a `rest_upload_image_type_not_supported` error.
	 *
	 * @ticket 61167
	 */
	public function test_upload_unsupported_image_type() {

		// Only run this test when the editor doesn't support AVIF.
		if ( wp_image_editor_supports( array( 'AVIF' ) ) ) {
			$this->markTestSkipped( 'The image editor suppports AVIF.' );
		}

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );

		wp_set_current_user( self::$author_id );
		$request->set_header( 'Content-Type', 'image/avif' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=avif-lossy.avif' );
		$request->set_body( file_get_contents( self::$test_avif_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_upload_image_type_not_supported', $response, 400 );
	}

	/**
	 * Test that the `wp_prevent_unsupported_image_uploads` filter enables uploading of unsupported image types.
	 *
	 * @ticket 61167
	 */
	public function test_upload_unsupported_image_type_with_filter() {

		// Only run this test when the editor doesn't support AVIF.
		if ( wp_image_editor_supports( array( 'AVIF' ) ) ) {
			$this->markTestSkipped( 'The image editor suppports AVIF.' );
		}

		add_filter( 'wp_prevent_unsupported_image_uploads', '__return_false' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );

		wp_set_current_user( self::$author_id );
		$request->set_header( 'Content-Type', 'image/avif' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=avif-lossy.avif' );
		$request->set_body( file_get_contents( self::$test_avif_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
	}

	/**
	 * Test that unsupported image type check is skipped when not generating sub-sizes.
	 *
	 * When the client handles image processing (generate_sub_sizes is false),
	 * the server should not check image editor support.
	 *
	 * Tests the permissions check directly with file params set, since the core
	 * check uses get_file_params() which is only populated for multipart uploads.
	 *
	 * The check is only relaxed when client-side media processing is enabled,
	 * since that is what makes the client able to handle the image, so the
	 * feature is enabled here.
	 *
	 * @ticket 64836
	 * @ticket 65517
	 */
	public function test_upload_unsupported_image_type_skipped_when_not_generating_sub_sizes() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		add_filter( 'wp_image_editors', '__return_empty_array' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'avif-lossy.avif',
					'type'     => 'image/avif',
					'tmp_name' => self::$test_avif_file,
					'error'    => 0,
					'size'     => filesize( self::$test_avif_file ),
				),
			)
		);
		$request->set_param( 'generate_sub_sizes', false );

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$result     = $controller->create_item_permissions_check( $request );

		// Should pass because generate_sub_sizes is false (client handles processing).
		$this->assertTrue( $result );
	}

	/**
	 * Test that unsupported image type check is enforced when generating sub-sizes.
	 *
	 * When the server handles image processing (generate_sub_sizes is true),
	 * the server should still check image editor support.
	 *
	 * Tests the permissions check directly with file params set, since the core
	 * check uses get_file_params() which is only populated for multipart uploads.
	 *
	 * @ticket 64836
	 */
	public function test_upload_unsupported_image_type_enforced_when_generating_sub_sizes() {
		wp_set_current_user( self::$author_id );

		add_filter( 'wp_image_editors', '__return_empty_array' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'avif-lossy.avif',
					'type'     => 'image/avif',
					'tmp_name' => self::$test_avif_file,
					'error'    => 0,
					'size'     => filesize( self::$test_avif_file ),
				),
			)
		);

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$result     = $controller->create_item_permissions_check( $request );

		// Should fail because the server needs to generate sub-sizes but can't.
		$this->assertWPError( $result );
		$this->assertSame( 'rest_upload_image_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Test that still HEIC/HEIF uploads bypass the image editor support check.
	 *
	 * The browser's canvas fallback can always decode still HEIC/HEIF, so the
	 * upload is allowed even when the server has no editor that supports it.
	 *
	 * @ticket 64915
	 *
	 * @dataProvider data_still_heic_mime_types
	 *
	 * @param string $mime_type Still HEIC/HEIF mime type.
	 */
	public function test_upload_still_heic_bypasses_unsupported_image_type_check( $mime_type ) {
		wp_set_current_user( self::$author_id );

		add_filter( 'wp_image_editors', '__return_empty_array' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'canola.heic',
					'type'     => $mime_type,
					'tmp_name' => DIR_TESTDATA . '/images/test-image.heic',
					'error'    => 0,
					'size'     => filesize( DIR_TESTDATA . '/images/test-image.heic' ),
				),
			)
		);

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$result     = $controller->create_item_permissions_check( $request );

		// Should pass because the browser can decode still HEIC/HEIF client-side.
		$this->assertTrue( $result );
	}

	/**
	 * Data provider for still HEIC/HEIF mime types.
	 *
	 * @return array[]
	 */
	public function data_still_heic_mime_types() {
		return array(
			'heic' => array( 'image/heic' ),
			'heif' => array( 'image/heif' ),
		);
	}

	/**
	 * Test that HEIC/HEIF sequence uploads do not bypass the editor support check.
	 *
	 * The multi-frame '-sequence' variants (Live Photos) cannot be processed by
	 * the server or decoded by the browser fallback, so they should fall through
	 * to the standard unsupported mime-type error rather than be stored.
	 *
	 * @ticket 64915
	 *
	 * @dataProvider data_heic_sequence_mime_types
	 *
	 * @param string $mime_type HEIC/HEIF sequence mime type.
	 */
	public function test_upload_heic_sequence_is_not_bypassed( $mime_type ) {
		wp_set_current_user( self::$author_id );

		add_filter( 'wp_image_editors', '__return_empty_array' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'live-photo.heic',
					'type'     => $mime_type,
					'tmp_name' => DIR_TESTDATA . '/images/test-image.heic',
					'error'    => 0,
					'size'     => filesize( DIR_TESTDATA . '/images/test-image.heic' ),
				),
			)
		);

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$result     = $controller->create_item_permissions_check( $request );

		// Should fail: sequences are unsupported by both the server and the fallback.
		$this->assertWPError( $result );
		$this->assertSame( 'rest_upload_image_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Data provider for HEIC/HEIF sequence mime types.
	 *
	 * @return array[]
	 */
	public function data_heic_sequence_mime_types() {
		return array(
			'heic-sequence' => array( 'image/heic-sequence' ),
			'heif-sequence' => array( 'image/heif-sequence' ),
		);
	}

	/**
	 * Test that uploading an SVG image doesn't throw a `rest_upload_image_type_not_supported` error.
	 *
	 * @ticket 63302
	 */
	public function test_upload_svg_image() {
		wp_set_current_user( self::$editor_id );
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/svg+xml' );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( self::$test_svg_file ),
					'name'     => 'video-play.svg',
					'size'     => filesize( self::$test_svg_file ),
					'tmp_name' => self::$test_svg_file,
					'type'     => 'image/svg+xml',
				),
			)
		);
		$rest_controller = new WP_REST_Attachments_Controller( 'attachment' );
		$result          = $rest_controller->create_item_permissions_check( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that the attachment fields caption, description, and title, post and alt_text are updated correctly.
	 *
	 * @ticket 64035
	 * @requires function imagejpeg
	 */
	public function test_edit_image_updates_attachment_fields() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		// In order to test the edit endpoint editable fields, we need to create a new attachment.
		$params = array(
			'src'         => wp_get_attachment_image_url( $attachment, 'full' ),
			'modifiers'   => array(
				array(
					'type' => 'crop',
					'args' => array(
						'left'   => 10,
						'top'    => 10,
						'width'  => 80,
						'height' => 80,
					),
				),
			),
			'caption'     => 'Test Caption',
			'description' => 'Test Description',
			'title'       => 'Test Title',
			'post'        => 1,
			'alt_text'    => 'Test Alt Text',
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );

		// The edit endpoint creates a new attachment, so we expect a 201 status.
		$this->assertEquals( 201, $response->get_status() );

		$data              = $response->get_data();
		$new_attachment_id = $data['id'];

		$updated_attachment = get_post( $new_attachment_id );

		$this->assertSame( 'Test Title', $updated_attachment->post_title, 'Title of the updated attachment is not identical.' );

		$this->assertSame( 'Test Caption', $updated_attachment->post_excerpt, 'Caption of the updated attachment is not identical.' );

		$this->assertSame( 'Test Description', $updated_attachment->post_content, 'Description of the updated attachment is not identical.' );

		$this->assertSame( 1, $updated_attachment->post_parent, 'Post parent of the updated attachment is not identical.' );

		$this->assertSame( 'Test Alt Text', get_post_meta( $new_attachment_id, '_wp_attachment_image_alt', true ), 'Alt text of the updated attachment is not identical.' );
	}

	/**
	 * Tests that the image is flipped correctly vertically and horizontally.
	 *
	 * @ticket 64035
	 * @requires function imagejpeg
	 */
	public function test_edit_image_vertical_and_horizontal_flip() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		$this->setup_mock_editor();
		WP_Image_Editor_Mock::$edit_return['flip'] = new WP_Error();

		$params = array(
			'flip' => array(
				'vertical'   => true,
				'horizontal' => true,
			),
			'src'  => wp_get_attachment_image_url( $attachment, 'full' ),
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_flip_failed', $response, 500 );

		$this->assertCount( 1, WP_Image_Editor_Mock::$spy['flip'] );
		// The controller converts the integer values to booleans: 0 !== (int) 1 = true.
		$this->assertSame( array( true, true ), WP_Image_Editor_Mock::$spy['flip'][0], 'Vertical and horizontal flip of the image is not identical.' );
	}

	/**
	 * Tests that the image is flipped correctly vertically only.
	 *
	 * @ticket 64035
	 * @requires function imagejpeg
	 */
	public function test_edit_image_vertical_flip_with_horizontal_false() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		$this->setup_mock_editor();
		WP_Image_Editor_Mock::$edit_return['flip'] = new WP_Error();

		$params = array(
			'flip' => array(
				'vertical'   => true,
				'horizontal' => false,
			),
			'src'  => wp_get_attachment_image_url( $attachment, 'full' ),
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_flip_failed', $response, 500 );

		$this->assertCount( 1, WP_Image_Editor_Mock::$spy['flip'] );
		// The controller converts the integer values to booleans: 0 !== (int) 1 = true.
		$this->assertSame( array( true, false ), WP_Image_Editor_Mock::$spy['flip'][0], 'Vertical flip of the image is not identical.' );
	}

	/**
	 * Tests that the image is flipped correctly with only vertical flip in arguments.
	 *
	 * @ticket 64035
	 * @requires function imagejpeg
	 */
	public function test_edit_image_vertical_flip_only() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		$this->setup_mock_editor();
		WP_Image_Editor_Mock::$edit_return['flip'] = new WP_Error();

		$params = array(
			'flip' => array(
				'vertical' => true,
			),
			'src'  => wp_get_attachment_image_url( $attachment, 'full' ),
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );
		$this->assertErrorResponse( 'rest_image_flip_failed', $response, 500 );

		$this->assertCount( 1, WP_Image_Editor_Mock::$spy['flip'] );
		// The controller converts the integer values to booleans: 0 !== (int) 1 = true.
		$this->assertSame( array( true, false ), WP_Image_Editor_Mock::$spy['flip'][0], 'Vertical flip of the image is not identical.' );
	}

	/**
	 * Test that wp_slash() is properly applied when creating edited images.
	 *
	 * This test verifies that the object returned by prepare_item_for_database()
	 * is properly cast to an array before being passed to wp_slash(), ensuring
	 * that string values are properly escaped for database insertion.
	 *
	 * @ticket 64149
	 * @requires function imagejpeg
	 */
	public function test_edit_image_wp_slash_with_object_cast() {
		wp_set_current_user( self::$superadmin_id );
		$attachment = self::factory()->attachment->create_upload_object( self::$test_file );

		// Create a mock to capture the data passed to wp_insert_attachment.
		$captured_data = null;

		// Mock wp_insert_attachment to capture the data being passed.
		add_filter(
			'wp_insert_attachment_data',
			static function ( $data ) use ( &$captured_data ) {
				$captured_data = $data;
				return $data;
			},
			10,
			1
		);

		$params = array(
			'rotation'    => 60,
			'src'         => wp_get_attachment_image_url( $attachment, 'full' ),
			'title'       => 'Test Title with "quotes" and \'apostrophes\'',
			'caption'     => 'Test Caption with "quotes" and \'apostrophes\'',
			'description' => 'Test Description with "quotes" and \'apostrophes\'',
		);

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment}/edit" );
		$request->set_body_params( $params );
		$response = rest_do_request( $request );

		$this->assertSame( 201, $response->get_status() );

		// Verify that the data was properly slashed (escaped)
		$this->assertNotNull( $captured_data, 'wp_insert_attachment was not called with data' );

		// Check that quotes are properly escaped in the captured data.
		$this->assertStringContainsString( 'Test Title with \"quotes\"', $captured_data['post_title'] ?? '', 'Title quotes not properly escaped' );
		$this->assertStringContainsString( 'Test Caption with \"quotes\"', $captured_data['post_excerpt'] ?? '', 'Caption quotes not properly escaped' );
		$this->assertStringContainsString( 'Test Description with \"quotes\"', $captured_data['post_content'] ?? '', 'Description quotes not properly escaped' );

		// Verify that the data is an array (not an object).
		$this->assertIsArray( $captured_data, 'Data passed to wp_insert_attachment should be an array' );
	}

	/**
	 * Tests sideloading a scaled image for an existing attachment.
	 *
	 * @ticket 64737
	 * @ticket 65329
	 * @requires function imagejpeg
	 */
	public function test_sideload_scaled_image() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// First, create an attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$data          = $response->get_data();
		$attachment_id = $data['id'];

		$this->assertSame( 201, $response->get_status() );

		$original_file = get_attached_file( $attachment_id, true );

		// Sideload a "scaled" version of the image.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Sideloading scaled image should succeed.' );

		// The sideload endpoint returns lightweight sub-size data; the metadata
		// is written later by the finalize endpoint.
		$sub_size = $response->get_data();
		$this->assertSame( 'scaled', $sub_size['image_size'], 'Response should echo the image_size.' );
		$this->assertSame( wp_basename( $original_file ), $sub_size['original_image'], 'Response original_image should be the basename of the original attached file.' );
		$this->assertGreaterThan( 0, $sub_size['width'], 'Response width should be positive.' );
		$this->assertGreaterThan( 0, $sub_size['height'], 'Response height should be positive.' );
		$this->assertGreaterThan( 0, $sub_size['filesize'], 'Response filesize should be positive.' );
		$this->assertStringContainsString( 'scaled', $sub_size['file'], 'Response file should reference the scaled version.' );

		// The attached file is still repointed to the scaled version during sideload.
		$new_file = get_attached_file( $attachment_id, true );
		$this->assertStringContainsString( 'scaled', wp_basename( $new_file ), 'Attached file should now be the scaled version.' );

		// Finalize with the collected sub-size, which writes the metadata.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $sub_size ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// The original file should now be recorded as original_image.
		$this->assertArrayHasKey( 'original_image', $metadata, 'Metadata should contain original_image.' );
		$this->assertSame( wp_basename( $original_file ), $metadata['original_image'], 'original_image should be the basename of the original attached file.' );

		// Metadata should have width, height, filesize, and file updated.
		$this->assertArrayHasKey( 'width', $metadata, 'Metadata should contain width.' );
		$this->assertArrayHasKey( 'height', $metadata, 'Metadata should contain height.' );
		$this->assertArrayHasKey( 'filesize', $metadata, 'Metadata should contain filesize.' );
		$this->assertArrayHasKey( 'file', $metadata, 'Metadata should contain file.' );
		$this->assertStringContainsString( 'scaled', $metadata['file'], 'Metadata file should reference the scaled version.' );
		$this->assertGreaterThan( 0, $metadata['width'], 'Width should be positive.' );
		$this->assertGreaterThan( 0, $metadata['height'], 'Height should be positive.' );
		$this->assertGreaterThan( 0, $metadata['filesize'], 'Filesize should be positive.' );
	}

	/**
	 * When the client generates sub-sizes (generate_sub_sizes is false), the
	 * server must not perform its own "big image" downscaling on upload.
	 *
	 * Otherwise the server creates a `-scaled` file and records the upload as
	 * `original_image`. The client's subsequent scaled sideload then collides
	 * with that `-scaled` file and is renamed `-scaled-1`, the thumbnails
	 * inherit the numbered name, and the server-generated full-size file is
	 * left orphaned on disk.
	 *
	 * @ticket 65708
	 * @requires function imagejpeg
	 */
	public function test_create_item_skips_big_image_scaling_when_client_generates_sub_sizes() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Force the threshold below the image's dimensions so scaling would be
		// triggered were it not suppressed for client-side processing.
		add_filter(
			'big_image_size_threshold',
			static function () {
				return 1000;
			}
		);

		// Upload a large image with the client handling sub-size generation.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=33772.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/33772.jpg' ) );
		$response      = rest_get_server()->dispatch( $request );
		$data          = $response->get_data();
		$attachment_id = $data['id'];

		$this->assertSame( 201, $response->get_status(), 'Uploading the image should succeed.' );

		// The uploaded full-size image should be stored untouched: no
		// server-side "-scaled" file and no original_image swap.
		$original_file      = get_attached_file( $attachment_id, true );
		$original_basename  = wp_basename( $original_file );
		$original_name_stem = pathinfo( $original_basename, PATHINFO_FILENAME );
		$this->assertStringNotContainsString( '-scaled', $original_basename, 'The server should not create a -scaled file when the client generates sub-sizes.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayNotHasKey( 'original_image', $metadata, 'The server should not record an original_image when it does not scale the upload.' );

		// The client's scaled sideload should now record the untouched upload as
		// original_image and keep the -scaled name without a numeric suffix.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', "attachment; filename={$original_name_stem}-scaled.jpg" );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/33772.jpg' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Sideloading the scaled image should succeed.' );

		$sub_size = $response->get_data();
		$this->assertSame( $original_basename, $sub_size['original_image'], 'The untouched upload should be recorded as original_image.' );
		$this->assertSame( "{$original_name_stem}-scaled.jpg", wp_basename( $sub_size['file'] ), 'The scaled sideload should keep the -scaled name without a numeric collision suffix.' );
	}

	/**
	 * The complete client-side flow for an image over the "big image" threshold
	 * should write only files that the metadata tracks, so that deleting the
	 * attachment removes all of them.
	 *
	 * When the server scales the upload as well, its own full-size file is
	 * never referenced by the metadata and survives "Delete Permanently", the
	 * client's scaled sideload collides with the server's "-scaled" file and is
	 * stored as "-scaled-1", and the sub-sizes inherit the numbered name.
	 *
	 * @ticket 65708
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_client_side_big_image_flow_leaves_no_orphaned_files() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Force the threshold below the uploaded image's dimensions so scaling
		// would be triggered were it not suppressed for client-side processing.
		add_filter(
			'big_image_size_threshold',
			static function () {
				return 1000;
			}
		);

		$upload_dir   = wp_upload_dir();
		$files_before = (array) glob( $upload_dir['path'] . '/*' );

		// 1. Upload the full-size image; the client owns all the derivatives.
		//    33772.jpg is 1920x1080, so it exceeds the threshold above.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=big-photo.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/33772.jpg' ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status(), 'Uploading the image should succeed.' );

		/*
		 * 2. Sideload a thumbnail, as the client does for each sub-size. The
		 *    client names it after the file it uploaded, so a server-side
		 *    rename of that file is what pushes this into a collision.
		 *    test-image.jpg is 50x50, within the registered thumbnail maximum.
		 */
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=big-photo-150x150.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/test-image.jpg' ) );
		$response       = rest_get_server()->dispatch( $request );
		$thumbnail_data = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Sideloading the thumbnail should succeed.' );
		$this->assertSame( 'big-photo-150x150.jpg', wp_basename( $thumbnail_data['file'] ), 'The thumbnail should not inherit a numeric collision suffix.' );

		// 3. Sideload the scaled full-size image. canola.jpg is 640x480, the
		//    size the client would have downscaled the upload to.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=big-photo-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response    = rest_get_server()->dispatch( $request );
		$scaled_data = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Sideloading the scaled image should succeed.' );

		// 4. Finalize, which writes the collected sub-size metadata in one pass.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $thumbnail_data, $scaled_data ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$this->assertSame( 'big-photo.jpg', $metadata['original_image'], 'The untouched upload should be recorded as original_image.' );
		$this->assertSame( 'big-photo-scaled.jpg', wp_basename( $metadata['file'] ), 'The client-supplied scaled image should become the attached file.' );
		$this->assertSame( 'big-photo-150x150.jpg', $metadata['sizes']['thumbnail']['file'], 'The thumbnail should keep its dimension-based name.' );

		// Every file written for this attachment must be reachable from the
		// metadata, otherwise it is orphaned on disk.
		$written = array_map( 'wp_basename', array_diff( (array) glob( $upload_dir['path'] . '/*' ), $files_before ) );
		sort( $written );
		$this->assertSame(
			array( 'big-photo-150x150.jpg', 'big-photo-scaled.jpg', 'big-photo.jpg' ),
			$written,
			'The flow should write only the full-size upload, its scaled copy, and the sub-sizes.'
		);

		// Deleting the attachment should therefore clean all of them up.
		wp_delete_attachment( $attachment_id, true );

		$remaining = array_diff( (array) glob( $upload_dir['path'] . '/*' ), $files_before );
		$this->assertSame( array(), array_values( $remaining ), 'Deleting the attachment should leave no files behind.' );
	}

	/**
	 * Tests that sideloading scaled image requires authentication.
	 *
	 * @ticket 64737
	 * @requires function imagejpeg
	 */
	public function test_sideload_scaled_image_requires_auth() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// Try sideloading without authentication.
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_image', $response, 401 );
	}

	/**
	 * Tests that the sideload endpoint accepts the expected image sizes and does
	 * not expose a generate_sub_sizes arg.
	 *
	 * The image_size argument accepts either a single size name or an array of
	 * size names, so it validates via a custom callback rather than an enum. The
	 * callback must accept 'scaled' and the 'source_original' source-format size,
	 * reject unknown sizes, and reject a special size sent as an array, since an
	 * array registers one file under several regular sub-sizes.
	 *
	 * sideload_item() never reads generate_sub_sizes, so advertising it on the
	 * route would silently mislead clients into expecting server-side sub-size
	 * generation. That arg only does real work on create_item() (POST /wp/v2/media).
	 *
	 * @ticket 64737
	 * @ticket 64915
	 */
	public function test_sideload_route_accepts_expected_image_sizes() {
		$this->enable_client_side_media_processing();

		$routes = rest_get_server()->get_routes();
		$path   = '/wp/v2/media/(?P<id>[\d]+)/sideload';
		$this->assertArrayHasKey( $path, $routes, 'Sideload route should exist.' );
		$this->assertIsArray( $routes[ $path ] );
		$endpoint = array_first( $routes[ $path ] );
		$this->assertIsArray( $endpoint );
		$this->assertArrayHasKey( 'args', $endpoint, 'Route endpoint should declare args.' );
		$args = $endpoint['args'];
		$this->assertIsArray( $args );

		$param_name = 'image_size';
		$this->assertArrayHasKey( $param_name, $args, 'Route should have image_size arg.' );
		$this->assertIsArray( $args[ $param_name ] );
		$this->assertArrayHasKey(
			'validate_callback',
			$args[ $param_name ],
			'image_size arg should validate via a callback.'
		);

		$validate = $args[ $param_name ]['validate_callback'];
		$request  = new WP_REST_Request( 'POST', '/wp/v2/media/1/sideload' );

		$this->assertTrue(
			$validate( 'scaled', $request, $param_name ),
			'image_size validation should accept the scaled size.'
		);
		$this->assertTrue(
			$validate( WP_REST_Attachments_Controller::IMAGE_SIZE_SOURCE_ORIGINAL, $request, $param_name ),
			'image_size validation should accept the source_original source-format size.'
		);
		$this->assertTrue(
			$validate( array( 'thumbnail', 'medium' ), $request, $param_name ),
			'image_size validation should accept an array of size names.'
		);
		$this->assertTrue(
			$validate( array( 'full', 'large' ), $request, $param_name ),
			'image_size validation should accept the full size grouped with a registered size.'
		);
		$this->assertWPError(
			$validate( 'not-a-real-size', $request, $param_name ),
			'image_size validation should reject an unknown size.'
		);
		$this->assertWPError(
			$validate( array( 'scaled' ), $request, $param_name ),
			'image_size validation should reject a special size sent as an array.'
		);

		$this->assertArrayNotHasKey( 'generate_sub_sizes', $args, 'Sideload route should not advertise the unused generate_sub_sizes arg.' );
	}

	/**
	 * Tests sideloading a 'source_original' companion file alongside its JPEG
	 * derivative. The HEIC filename is recorded under $metadata['source_image']
	 * so it does not collide with 'original_image', which the scaled-sideload
	 * flow owns. Metadata is written by the finalize endpoint, not the sideload.
	 *
	 * @ticket 64915
	 * @requires function imagejpeg
	 */
	public function test_sideload_source_original_writes_metadata_source_image(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create the JPEG attachment that the HEIC will be a companion to.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$attachment_id = $data['id'];
		$this->assertIsInt( $attachment_id );

		$this->assertSame( 201, $response->get_status() );

		/*
		 * Sideload the HEIC companion using the real HEIC fixture. `convert_format`
		 * is disabled so the default HEIC -> JPEG output mapping does not rename
		 * the file or append an alt-extension suffix. The sideload returns
		 * lightweight sub-size data; metadata is written by finalize.
		 */
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/heic' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.heic' );
		$request->set_param( 'image_size', WP_REST_Attachments_Controller::IMAGE_SIZE_SOURCE_ORIGINAL );
		$request->set_param( 'convert_format', false );
		$request->set_body( (string) file_get_contents( DIR_TESTDATA . '/images/test-image.heic' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Sideloading source_original should succeed.' );

		$sub_size = $response->get_data();
		$this->assertIsArray( $sub_size );
		$this->assertSame( WP_REST_Attachments_Controller::IMAGE_SIZE_SOURCE_ORIGINAL, $sub_size['image_size'], 'Response should echo the image_size.' );
		$this->assertMatchesRegularExpression( '/canola.*\.heic$/', $sub_size['file'], 'Response file should reference the HEIC filename.' );

		// Sideload must not write metadata; that happens in finalize.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertArrayNotHasKey( 'source_image', $metadata, 'Sideload should not write source_image metadata.' );

		// Finalize with the collected sub-size, which writes the metadata.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $sub_size ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->assertArrayHasKey( 'source_image', $metadata, "Metadata should contain 'source_image' for the HEIC companion." );
		$this->assertMatchesRegularExpression( '/canola.*\.heic$/', $metadata['source_image'], "Metadata 'source_image' should reference the HEIC filename." );
		$this->assertArrayNotHasKey( 'original_image', $metadata, "Metadata 'original_image' should be untouched by the HEIC sideload." );
	}

	/**
	 * Tests sideloading the animated-GIF video companions ('animated_video' and
	 * 'animated_video_poster'). Each filename is recorded under its own metadata
	 * key by the finalize endpoint and does not collide with 'original_image',
	 * which keeps pointing at the GIF.
	 *
	 * The uploaded bytes are a JPEG stand-in: the sideload branch only records
	 * wp_basename() of the stored file, so the metadata plumbing can be exercised
	 * without depending on video upload support in the test environment.
	 *
	 * @ticket 65549
	 * @requires function imagejpeg
	 */
	public function test_sideload_animated_video_companions_write_metadata(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create the (GIF) attachment the companions belong to.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];
		$this->assertSame( 201, $response->get_status() );

		// Sideload the converted-video companion.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-video.jpg' );
		$request->set_param( 'image_size', 'animated_video' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$video_response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $video_response->get_status(), 'Sideloading animated_video should succeed.' );
		$video_sub_size = $video_response->get_data();
		$this->assertIsArray( $video_sub_size );
		$this->assertSame( 'animated_video', $video_sub_size['image_size'], 'Response should echo the image_size.' );

		// Sideload the poster companion.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-poster.jpg' );
		$request->set_param( 'image_size', 'animated_video_poster' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$poster_response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $poster_response->get_status(), 'Sideloading animated_video_poster should succeed.' );
		$poster_sub_size = $poster_response->get_data();
		$this->assertIsArray( $poster_sub_size );
		$this->assertSame( 'animated_video_poster', $poster_sub_size['image_size'], 'Response should echo the image_size.' );

		// Sideload must not write metadata; that happens in finalize.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertArrayNotHasKey( 'animated_video', $metadata, 'Sideload should not write animated_video metadata.' );
		$this->assertArrayNotHasKey( 'animated_video_poster', $metadata, 'Sideload should not write animated_video_poster metadata.' );

		// Finalize with both collected sub-sizes, which writes the metadata.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $video_sub_size, $poster_sub_size ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->assertArrayHasKey( 'animated_video', $metadata, "Metadata should contain 'animated_video'." );
		$this->assertMatchesRegularExpression( '/canola-video.*\.jpg$/', $metadata['animated_video'], "Metadata 'animated_video' should reference the video companion filename." );
		$this->assertArrayHasKey( 'animated_video_poster', $metadata, "Metadata should contain 'animated_video_poster'." );
		$this->assertMatchesRegularExpression( '/canola-poster.*\.jpg$/', $metadata['animated_video_poster'], "Metadata 'animated_video_poster' should reference the poster filename." );
		$this->assertArrayNotHasKey( 'original_image', $metadata, "Metadata 'original_image' should be untouched by the companion sideloads." );
	}

	/**
	 * Tests the filter_wp_unique_filename method handles the -scaled suffix.
	 *
	 * @ticket 64737
	 * @requires function imagejpeg
	 */
	public function test_sideload_scaled_unique_filename() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// Sideload with the -scaled suffix.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Sideloading scaled image should succeed.' );

		// The filename should retain the -scaled suffix without numeric disambiguation.
		$new_file = get_attached_file( $attachment_id, true );
		$basename = wp_basename( $new_file );
		$this->assertMatchesRegularExpression( '/canola-scaled\.jpg$/', $basename, 'Scaled filename should not have numeric suffix appended.' );
	}

	/**
	 * Tests that sideloading a scaled image for a different attachment retains the numeric suffix
	 * when a file with the same name already exists on disk.
	 *
	 * @ticket 64737
	 * @requires function imagejpeg
	 */
	public function test_sideload_scaled_unique_filename_conflict() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create the first attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response        = rest_get_server()->dispatch( $request );
		$attachment_id_a = $response->get_data()['id'];

		// Sideload a scaled image for attachment A, creating canola-scaled.jpg on disk.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id_a}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'First sideload should succeed.' );

		// Create a second, different attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=other.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response        = rest_get_server()->dispatch( $request );
		$attachment_id_b = $response->get_data()['id'];

		// Sideload scaled for attachment B using the same filename that already exists on disk.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id_b}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Second sideload should succeed.' );

		// The filename should have a numeric suffix since the base name does not match this attachment.
		$new_file = get_attached_file( $attachment_id_b, true );
		$basename = wp_basename( $new_file );
		$this->assertMatchesRegularExpression( '/canola-scaled-\d+\.jpg$/', $basename, 'Scaled filename should have numeric suffix when file conflicts with a different attachment.' );
	}

	/**
	 * Tests that sideloading rejects an image whose dimensions exceed the
	 * registered maximum for the target image size.
	 *
	 * @ticket 64798
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::validate_image_dimensions
	 * @requires function imagejpeg
	 */
	public function test_sideload_item_rejects_oversized_dimensions() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment from canola.jpg (640x480).
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// Sideload the 640x480 image claiming it is a thumbnail (150x150 max).
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-150x150.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Oversized sideload should be rejected.' );
		$this->assertSame( 'rest_upload_dimension_mismatch', $response->get_data()['code'] );
	}

	/**
	 * Tests that sideloading accepts an image whose dimensions fit within the
	 * registered maximum for the target image size.
	 *
	 * @ticket 64798
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::validate_image_dimensions
	 * @requires function imagejpeg
	 */
	public function test_sideload_item_accepts_valid_dimensions() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment from canola.jpg (640x480).
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// test-image.jpg is 50x50, well within the thumbnail maximum (150x150).
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=test-thumbnail.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/test-image.jpg' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Valid thumbnail sideload should succeed.' );
	}

	/**
	 * Tests that sideloading the 'original' size rejects an image whose
	 * dimensions do not match the original attachment dimensions.
	 *
	 * @ticket 64798
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::validate_image_dimensions
	 * @requires function imagejpeg
	 */
	public function test_sideload_item_rejects_original_dimension_mismatch() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment from canola.jpg (640x480).
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// Sideload a 50x50 image as the original; it does not match 640x480.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'image_size', 'original' );
		$request->set_body( file_get_contents( DIR_TESTDATA . '/images/test-image.jpg' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Mismatched original sideload should be rejected.' );
		$this->assertSame( 'rest_upload_dimension_mismatch', $response->get_data()['code'] );
	}

	/**
	 * Tests that sideloading the 'original' size accepts an image whose
	 * dimensions match the original attachment dimensions.
	 *
	 * @ticket 64798
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::validate_image_dimensions
	 * @requires function imagejpeg
	 */
	public function test_sideload_item_accepts_matching_original_dimensions() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment from canola.jpg (640x480).
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// Sideload the same 640x480 image as the original; dimensions match.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-original.jpg' );
		$request->set_param( 'image_size', 'original' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Matching original sideload should succeed.' );
	}

	/**
	 * Tests that sideloading a file whose dimensions cannot be read is rejected
	 * rather than stored with zero dimensions.
	 *
	 * The body is a JFIF header with no frame data: its magic bytes identify it
	 * as a JPEG so the upload itself succeeds, but wp_getimagesize() cannot
	 * determine dimensions, which is the corrupted/unsupported-format case.
	 *
	 * @ticket 64798
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 */
	public function test_sideload_item_rejects_unreadable_image() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment from canola.jpg (640x480).
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// A JPEG SOI + JFIF APP0 marker followed immediately by EOI: valid magic
		// bytes, but no SOF marker, so wp_getimagesize() returns false.
		$unreadable = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-thumbnail.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( $unreadable );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Unreadable image sideload should be rejected.' );
		$this->assertSame( 'rest_upload_invalid_image', $response->get_data()['code'] );
	}

	/**
	 * Tests that the finalize endpoint triggers wp_generate_attachment_metadata.
	 *
	 * @ticket 62243
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_finalize_item(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		// Track whether wp_generate_attachment_metadata filter fires.
		$filter_metadata = null;
		$filter_id       = null;
		$filter_context  = null;
		add_filter(
			'wp_generate_attachment_metadata',
			function ( array $metadata, int $id, string $context ) use ( &$filter_metadata, &$filter_id, &$filter_context ) {
				$filter_metadata = $metadata;
				$filter_id       = $id;
				$filter_context  = $context;
				$metadata['foo'] = 'bar';
				return $metadata;
			},
			10,
			3
		);

		// Call the finalize endpoint.
		$request  = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize endpoint should return 200.' );
		$this->assertIsArray( $filter_metadata );
		$this->assertStringContainsString( 'canola', $filter_metadata['file'], 'Expected the canola image to have been had its metadata updated.' );
		$this->assertSame( $attachment_id, $filter_id, 'Expected the post ID to be passed to the filter.' );
		$this->assertSame( 'update', $filter_context, 'Filter context should be "update".' );
		$resulting_metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $resulting_metadata );
		$this->assertArrayHasKey( 'foo', $resulting_metadata, 'Expected new metadata key to have been added.' );
		$this->assertSame( 'bar', $resulting_metadata['foo'], 'Expected filtered metadata to be updated.' );
	}

	/**
	 * Tests that the finalize endpoint requires authentication.
	 *
	 * @ticket 62243
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_finalize_item_requires_auth(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		// Try finalizing without authentication.
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_edit_image', $response, 401 );
	}

	/**
	 * Tests that the finalize endpoint returns error for invalid attachment ID.
	 *
	 * @ticket 62243
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 */
	public function test_finalize_item_invalid_id(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		$invalid_id = PHP_INT_MAX;
		$this->assertNull( get_post( $invalid_id ), 'Expected invalid ID to not exist for an existing post.' );
		$request  = new WP_REST_Request( 'POST', "/wp/v2/media/$invalid_id/finalize" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_id', $response, 404 );
	}

	/**
	 * Tests that the finalize endpoint writes regular sub-size metadata
	 * collected from sideload responses.
	 *
	 * @ticket 65329
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_finalize_writes_regular_sub_sizes(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment without generating sub-sizes server-side.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		// Sideload a thumbnail sub-size; the response carries its metadata.
		// test-image.jpg is 50x50, within the registered thumbnail maximum
		// (150x150), so it passes sideload dimension validation.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-thumb.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( (string) file_get_contents( DIR_TESTDATA . '/images/test-image.jpg' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Sideloading a thumbnail should succeed.' );

		$sub_size = $response->get_data();
		$this->assertSame( 'thumbnail', $sub_size['image_size'], 'Response should echo the image_size.' );

		// Finalize with the collected sub-size, which writes it into metadata.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $sub_size ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'sizes', $metadata, 'Metadata should contain sizes.' );
		$this->assertArrayHasKey( 'thumbnail', $metadata['sizes'], 'Metadata sizes should contain the sideloaded thumbnail.' );
		$this->assertSame( 'image/jpeg', $metadata['sizes']['thumbnail']['mime-type'], 'Thumbnail mime-type should be recorded.' );
		$this->assertGreaterThan( 0, $metadata['sizes']['thumbnail']['filesize'], 'Thumbnail filesize should be positive.' );
	}

	/**
	 * Tests that sideloading the 'original' size makes the supplied (rotated)
	 * file the attachment's main file and that finalize records the previous
	 * attached file as original_image, mirroring the swap
	 * _wp_image_meta_replace_original() performs when the server rotates an
	 * image on upload.
	 *
	 * @ticket 65643
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_finalize_writes_original_metadata(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment without generating sub-sizes server-side.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		$attached_file_before = get_attached_file( $attachment_id, true );

		// Sideload the 'original' version (simulating a rotated image).
		// canola.jpg is 640x480, matching the stored dimensions, so
		// validation passes.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-original.jpg' );
		$request->set_param( 'image_size', 'original' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$original_data = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Sideloading the original should succeed.' );
		$this->assertSame( 'original', $original_data['image_size'], 'Response should echo the image_size.' );
		$this->assertSame( wp_basename( $attached_file_before ), $original_data['original_image'], 'Response original_image should be the basename of the previous attached file.' );
		$this->assertSame( 640, $original_data['width'], 'Response width should be the sideloaded image width.' );
		$this->assertSame( 480, $original_data['height'], 'Response height should be the sideloaded image height.' );
		$this->assertGreaterThan( 0, $original_data['filesize'], 'Response filesize should be positive.' );

		// The attached file is repointed to the sideloaded original.
		$attached_file_after = get_attached_file( $attachment_id, true );
		$this->assertSame( wp_basename( $original_data['file'] ), wp_basename( $attached_file_after ), 'Attached file should be the sideloaded original.' );
		$this->assertNotSame( $attached_file_before, $attached_file_after, 'Attached file should be replaced by the sideloaded original.' );

		// Finalize with the collected original sub-size.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $original_data ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertSame( wp_basename( $attached_file_before ), $metadata['original_image'], 'Finalize should record the previous attached file as original_image.' );
		$this->assertSame( 640, $metadata['width'], 'Finalize should record the sideloaded image width.' );
		$this->assertSame( 480, $metadata['height'], 'Finalize should record the sideloaded image height.' );
		$this->assertSame( $original_data['file'], $metadata['file'], 'Finalize should record the sideloaded original as the main file.' );
	}

	/**
	 * Tests that sideloading the 'original' size accepts a rotated file whose
	 * dimensions are the transpose of the stored dimensions (EXIF orientations
	 * 5/6/7/8 swap width and height) and makes it the main file.
	 *
	 * A strict equality check would reject quarter-turn rotations with
	 * rest_upload_dimension_mismatch.
	 *
	 * @ticket 65643
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::validate_image_dimensions
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_sideload_item_accepts_transposed_original_dimensions(): void {
		if ( ! wp_image_editor_supports( array( 'methods' => array( 'rotate' ) ) ) ) {
			$this->markTestSkipped( 'This test requires an image editor with rotation support.' );
		}

		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create a 640x480 attachment without generating sub-sizes server-side.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		$uploaded_basename = wp_basename( get_attached_file( $attachment_id, true ) );

		// Build a rotated (transposed) version of the source: 640x480 -> 480x640.
		$editor = wp_get_image_editor( self::$test_file );
		$this->assertNotWPError( $editor );
		$editor->rotate( 90 );
		$saved = $editor->save( wp_tempnam( 'rotated.jpg' ), 'image/jpeg' );
		$this->assertNotWPError( $saved );
		$rotated_path = $saved['path'];

		// Sideload the rotated file as the original.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-rotated.jpg' );
		$request->set_param( 'image_size', 'original' );
		$request->set_body( (string) file_get_contents( $rotated_path ) );
		$response      = rest_get_server()->dispatch( $request );
		$original_data = $response->get_data();

		unlink( $rotated_path );

		// The transposed dimensions must be accepted, not rejected with a 400.
		$this->assertSame( 200, $response->get_status(), 'Transposed original sideload should succeed.' );
		$this->assertSame( 480, $original_data['width'], 'Response width should be the transposed width.' );
		$this->assertSame( 640, $original_data['height'], 'Response height should be the transposed height.' );
		$this->assertSame( $uploaded_basename, $original_data['original_image'], 'Response original_image should be the basename of the uploaded file.' );

		// Finalize and confirm the rotated dimensions replace the stored ones.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $original_data ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertSame( 480, $metadata['width'], 'Finalize should record the transposed width.' );
		$this->assertSame( 640, $metadata['height'], 'Finalize should record the transposed height.' );
		$this->assertSame( $uploaded_basename, $metadata['original_image'], 'Finalize should keep the uploaded file as original_image.' );
		$this->assertSame( $original_data['file'], $metadata['file'], 'Finalize should record the rotated file as the main file.' );
	}

	/**
	 * Tests that the client-side 'original' sideload of an EXIF-rotated image
	 * produces the same attachment metadata as a normal server-side upload of
	 * the same file.
	 *
	 * Uses test-image-rotated-90ccw.jpg (1200x1800, EXIF orientation 6), a
	 * quarter turn that swaps width and height to 1800x1200. The browser
	 * rotates and strips the EXIF orientation; wp_get_image_editor() does the
	 * same here, so no JavaScript is required.
	 *
	 * @ticket 65643
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @covers WP_REST_Attachments_Controller::validate_image_dimensions
	 * @requires function imagejpeg
	 * @requires extension exif
	 */
	public function test_original_sideload_matches_server_side_rotation(): void {
		if ( ! wp_image_editor_supports( array( 'methods' => array( 'rotate' ) ) ) ) {
			$this->markTestSkipped( 'This test requires an image editor with rotation support.' );
		}

		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		$fixture = DIR_TESTDATA . '/images/test-image-rotated-90ccw.jpg';

		/*
		 * Reference: a normal upload with server-side processing (the classic
		 * path). generate_sub_sizes defaults to true, so
		 * wp_create_image_subsizes() applies the EXIF rotation.
		 */
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=reference.jpg' );
		$request->set_body( (string) file_get_contents( $fixture ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 201, $response->get_status() );
		$reference_meta = wp_get_attachment_metadata( $response->get_data()['id'], true );

		// Sanity check that the reference actually rotated.
		$this->assertSame( 1800, $reference_meta['width'], 'Server-side rotation should swap the dimensions.' );
		$this->assertSame( 1200, $reference_meta['height'], 'Server-side rotation should swap the dimensions.' );
		$this->assertNotEmpty( $reference_meta['original_image'], 'Server-side rotation should keep the original file.' );
		$this->assertSame( 1, (int) $reference_meta['image_meta']['orientation'], 'Server-side rotation should reset the stored orientation.' );

		/*
		 * Client-side path: upload without server-side processing, so the
		 * stored dimensions stay at the un-rotated 1200x1800 and orientation
		 * stays 6.
		 */
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=client.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( $fixture ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];
		$this->assertSame( 201, $response->get_status() );

		// Simulate the browser: apply the EXIF orientation and strip the tag.
		$editor = wp_get_image_editor( $fixture );
		$this->assertNotWPError( $editor );
		$editor->maybe_exif_rotate();
		$saved = $editor->save( wp_tempnam( 'client-rotated.jpg' ), 'image/jpeg' );
		$this->assertNotWPError( $saved );

		// Sideload the rotated file as the original, then finalize.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=client-rotated.jpg' );
		$request->set_param( 'image_size', 'original' );
		$request->set_body( (string) file_get_contents( $saved['path'] ) );
		$response      = rest_get_server()->dispatch( $request );
		$original_data = $response->get_data();

		unlink( $saved['path'] );

		$this->assertSame( 200, $response->get_status(), 'Sideloading the rotated original should succeed.' );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $original_data ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$client_meta = wp_get_attachment_metadata( $attachment_id, true );

		/*
		 * The two paths should produce the same metadata. The filenames differ,
		 * so compare dimensions, orientation, and original_image instead of
		 * exact filenames.
		 */
		$this->assertSame( $reference_meta['width'], $client_meta['width'], 'Client-side rotation should record the same width as server-side rotation.' );
		$this->assertSame( $reference_meta['height'], $client_meta['height'], 'Client-side rotation should record the same height as server-side rotation.' );
		$this->assertSame(
			(int) $reference_meta['image_meta']['orientation'],
			(int) $client_meta['image_meta']['orientation'],
			'Client-side rotation should reset the stored orientation like server-side rotation.'
		);
		$this->assertNotEmpty( $client_meta['original_image'], 'The original file must be preserved as original_image.' );

		// original_image must resolve to the un-rotated 1200x1800 source.
		$client_original = getimagesize( wp_get_original_image_path( $attachment_id ) );
		$this->assertSame( 1200, $client_original[0], 'original_image should be the un-rotated source width.' );
		$this->assertSame( 1800, $client_original[1], 'original_image should be the un-rotated source height.' );
	}

	/**
	 * Tests that finalize resets the stored EXIF orientation for 'scaled'
	 * sub-sizes. The client applies the EXIF rotation when scaling, so the
	 * stored orientation must be reset to 1 as wp_create_image_subsizes()
	 * does, or exif_orientation would keep reporting the pre-rotation value.
	 *
	 * @ticket 65643
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_finalize_scaled_resets_orientation(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=big-rotated-photo.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		// Simulate an EXIF-rotated upload: canola.jpg carries no orientation
		// tag, so store the pre-rotation value directly.
		$metadata                              = wp_get_attachment_metadata( $attachment_id, true );
		$metadata['image_meta']['orientation'] = 6;
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// Sideload the client-scaled image so finalize has a provenance-backed
		// 'scaled' entry to store.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=big-rotated-photo-scaled.jpg' );
		$request->set_param( 'image_size', 'scaled' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Sideloading the scaled image should succeed.' );
		$sub_size = $response->get_data();

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $sub_size ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertSame( 1, (int) $metadata['image_meta']['orientation'], 'Finalizing a scaled sub-size should reset the stored EXIF orientation.' );
	}

	/**
	 * Tests that finalize ignores an 'original'/'scaled' entry that is missing
	 * the file name, so a malformed payload cannot blank out the main file
	 * metadata.
	 *
	 * @ticket 65643
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_finalize_ignores_main_file_entry_without_file(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=guarded.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		$metadata_before = wp_get_attachment_metadata( $attachment_id, true );

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param(
			'sub_sizes',
			array(
				array(
					'image_size' => 'original',
					'width'      => 9999,
					'height'     => 9999,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		$this->assertSame( $metadata_before['file'], $metadata['file'], 'The main file must not be blanked by an entry without a file.' );
		$this->assertSame( $metadata_before['width'], $metadata['width'], 'The width must not be changed by an entry without a file.' );
		$this->assertSame( $metadata_before['height'], $metadata['height'], 'The height must not be changed by an entry without a file.' );
		$this->assertArrayNotHasKey( 'original_image', $metadata, 'No original_image should be recorded from an entry without a file.' );
	}

	/**
	 * Tests that the finalize endpoint preserves existing image_meta (EXIF)
	 * when adding sub-sizes collected from sideload responses.
	 *
	 * @ticket 65329
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 * @requires extension exif
	 */
	public function test_finalize_preserves_image_meta(): void {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		$exif_file = DIR_TESTDATA . '/images/2004-07-22-DSC_0008.jpg';

		// Create an attachment without generating sub-sizes server-side.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=2004-07-22-DSC_0008.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( $exif_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		$original_image_meta = wp_get_attachment_metadata( $attachment_id, true )['image_meta'];

		// Sideload a thumbnail sub-size so finalize has a provenance-backed file
		// to store. test-image.jpg is 50x50, within the thumbnail maximum.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=2004-07-22-DSC_0008-thumb.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( (string) file_get_contents( DIR_TESTDATA . '/images/test-image.jpg' ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Sideloading a thumbnail should succeed.' );
		$sub_size = $response->get_data();

		// Finalize with the sideloaded thumbnail sub-size.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $sub_size ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		// The sub-size should have been added.
		$this->assertArrayHasKey( 'thumbnail', $metadata['sizes'], 'Finalize should add the thumbnail sub-size.' );

		// The EXIF image_meta should be unchanged.
		$this->assertSame( $original_image_meta['aperture'], $metadata['image_meta']['aperture'], 'Aperture should be preserved.' );
		$this->assertSame( $original_image_meta['camera'], $metadata['image_meta']['camera'], 'Camera should be preserved.' );
		$this->assertSame( $original_image_meta['focal_length'], $metadata['image_meta']['focal_length'], 'Focal length should be preserved.' );
		$this->assertSame( $original_image_meta['iso'], $metadata['image_meta']['iso'], 'ISO should be preserved.' );
	}

	/**
	 * Tests that the sideload route declares `convert_format` as a boolean arg.
	 *
	 * Without this declaration, multipart/form-data requests deliver the value as
	 * a string ("false") which evaluates truthy in PHP, so the sideload handler's
	 * `if ( ! $request['convert_format'] )` check never fires and the
	 * `image_editor_output_format` filter is never suppressed - meaning the
	 * server still performs the format conversion the client opted out of.
	 *
	 * @ticket 65329
	 * @covers WP_REST_Attachments_Controller::register_routes
	 */
	public function test_sideload_route_declares_convert_format_boolean() {
		$this->enable_client_side_media_processing();

		$routes   = rest_get_server()->get_routes();
		$endpoint = '/wp/v2/media/(?P<id>[\d]+)/sideload';
		$this->assertArrayHasKey( $endpoint, $routes, 'Sideload route should exist.' );

		$args = $routes[ $endpoint ][0]['args'];

		$this->assertArrayHasKey( 'convert_format', $args, 'Route should declare convert_format.' );
		$this->assertSame( 'boolean', $args['convert_format']['type'], 'convert_format should be a boolean.' );
		$this->assertTrue( $args['convert_format']['default'], 'convert_format should default to true.' );
	}

	/**
	 * Tests that sideloading with `convert_format=false` (sent as the string
	 * "false", matching multipart/form-data semantics) suppresses the
	 * alt-extension collision check in `wp_unique_filename()`, so a companion
	 * file sharing the attachment basename does not get a numeric suffix.
	 *
	 * Mirrors the HEIC companion upload flow: a JPEG derivative is created via
	 * the media endpoint, then the original is sideloaded under the same stem.
	 * Without the arg declared as boolean, "false" coerces truthy, the filter
	 * is never added, and the companion is bumped to `-1` while the JPEG stays
	 * unsuffixed. PNG stands in for HEIC because core's default
	 * `image_editor_output_format` only maps HEIC/HEIF to JPEG; a local filter
	 * adds a PNG to JPEG mapping to trigger the same alt-ext check.
	 *
	 * @ticket 65329
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::register_routes
	 * @requires function imagejpeg
	 */
	public function test_sideload_convert_format_false_suppresses_alt_ext_suffix() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Upload a JPEG "parent" attachment the way client-side uploads do.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=heic-companion.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );

		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];
		$this->assertSame( 201, $response->get_status() );

		/*
		 * Simulate an alt-ext conversion mapping so an alt-extension companion
		 * (PNG here, HEIC in production) would otherwise get a `-1` suffix.
		 */
		$add_png_mapping = static function ( $formats ) {
			$formats['image/png'] = 'image/jpeg';
			return $formats;
		};
		add_filter( 'image_editor_output_format', $add_png_mapping, 5 );

		/*
		 * Sideload a companion sharing the same basename. Use the source-format
		 * original size: a source-format companion (HEIC in production, PNG
		 * here) kept beside its JPEG derivative is exactly what this size
		 * represents, and it is exempt from the sideload dimension validation
		 * that would otherwise reject a companion whose dimensions differ from
		 * the derivative. Pass convert_format as the string "false" to match
		 * multipart/form-data request semantics.
		 */
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/png' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=heic-companion.png' );
		$request->set_param( 'image_size', WP_REST_Attachments_Controller::IMAGE_SIZE_SOURCE_ORIGINAL );
		$request->set_param( 'convert_format', 'false' );
		$request->set_body( (string) file_get_contents( DIR_TESTDATA . '/images/one-blue-pixel-100x100.png' ) );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'image_editor_output_format', $add_png_mapping, 5 );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame(
			'heic-companion.png',
			$data['file'],
			'Companion file should share the attachment basename without a numeric suffix.'
		);
	}

	/**
	 * Tests that sideloading with an array of image sizes registers the single
	 * file under each size name when finalized.
	 *
	 * @ticket 64737
	 * @covers WP_REST_Attachments_Controller::sideload_item
	 * @covers WP_REST_Attachments_Controller::finalize_item
	 * @requires function imagejpeg
	 */
	public function test_sideload_image_size_array() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		// Create an attachment without generating sub-sizes server-side.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$this->assertSame( 201, $response->get_status() );

		/*
		 * Sideload a single file registered under multiple sizes. The file is
		 * 50x50 so that it satisfies the registered maximum for every size in
		 * the group, which is what sharing one file among them requires.
		 */
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-dup.jpg' );
		$request->set_param( 'image_size', array( 'thumbnail', 'medium' ) );
		$request->set_body( (string) file_get_contents( DIR_TESTDATA . '/images/test-image.jpg' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Sideloading with an array of sizes should succeed.' );

		$sub_size = $response->get_data();
		$this->assertSame( array( 'thumbnail', 'medium' ), $sub_size['image_size'], 'Response should echo the array of sizes.' );

		// Finalize with the collected sub-size.
		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/finalize" );
		$request->set_param( 'sub_sizes', array( $sub_size ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Finalize should succeed.' );

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertArrayHasKey( 'thumbnail', $metadata['sizes'], 'Metadata should register the thumbnail size.' );
		$this->assertArrayHasKey( 'medium', $metadata['sizes'], 'Metadata should register the medium size.' );
		$this->assertSame(
			$metadata['sizes']['thumbnail']['file'],
			$metadata['sizes']['medium']['file'],
			'Both sizes should reference the same physical file.'
		);
	}

	/**
	 * Tests that the sideload endpoint rejects an invalid image size name.
	 *
	 * @ticket 64737
	 * @requires function imagejpeg
	 */
	public function test_sideload_image_size_invalid() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola.jpg' );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response      = rest_get_server()->dispatch( $request );
		$attachment_id = $response->get_data()['id'];

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$attachment_id}/sideload" );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=canola-x.jpg' );
		$request->set_param( 'image_size', array( 'thumbnail', 'not-a-real-size' ) );
		$request->set_body( (string) file_get_contents( self::$test_file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'An unknown size name should be rejected.' );
	}

	/**
	 * The URL requested by the most recent mocked HTTP download.
	 *
	 * @var string|null
	 */
	protected $last_download_url = null;

	/**
	 * Short-circuits download_url()'s HTTP request, writing a local fixture into
	 * the streamed temp file so media_handle_sideload() has a real image to process.
	 *
	 * Mirrors the approach core's media_sideload_image() tests use: returning a
	 * non-false value from `pre_http_request` skips the network, so the mock must
	 * copy the fixture into the `filename` the request would have streamed to.
	 *
	 * @param false|array|WP_Error $response A preempted response, or false to continue.
	 * @param array                $args     HTTP request arguments.
	 * @param string               $url      The request URL.
	 * @return array A faked 200 response.
	 */
	public function mock_image_download( $response, $args, $url ) {
		$this->last_download_url = $url;

		if ( ! empty( $args['filename'] ) ) {
			copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );
		}

		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(),
			'cookies'  => array(),
			'body'     => '',
		);
	}

	/**
	 * Verifies that supplying a `url` to the create endpoint sideloads the remote
	 * image on the server and, with generate_sub_sizes=false, creates no sub-sizes.
	 *
	 * This is the cross-origin-isolation fallback path: the server fetches the
	 * remote image so the browser does not have to, and only the original is kept.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_sideloads_without_subsizes() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/photo.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$data = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'image', $data['media_type'] );
		$this->assertSame( 'https://example.com/photo.jpg', $this->last_download_url );

		// No sub-sizes should have been generated; only the original is stored.
		$metadata = wp_get_attachment_metadata( $data['id'], true );
		$this->assertEmpty( $metadata['sizes'] ?? array(), 'Sideloaded external image should have no sub-sizes.' );
	}

	/**
	 * Verifies that, with the default generate_sub_sizes (true), sideloading an
	 * external image generates sub-sizes, so the filters applied in create_item()
	 * still govern derivative generation on the URL path.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_generates_subsizes_by_default() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		// Note: generate_sub_sizes is intentionally not set, so it defaults to true.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/full.jpg' );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$data = $response->get_data();

		$this->assertSame( 201, $response->get_status() );

		$metadata = wp_get_attachment_metadata( $data['id'], true );
		$this->assertNotEmpty( $metadata['sizes'] ?? array(), 'Sub-sizes should be generated when generate_sub_sizes is true.' );
	}

	/**
	 * Verifies that the REST-specific rest_after_insert_attachment action fires on
	 * the URL sideload path, for parity with the uploaded-file path.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_fires_rest_after_insert_attachment() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		$fired = array();
		$spy   = static function ( $attachment, $request, $creating ) use ( &$fired ) {
			$fired = array(
				'id'       => $attachment->ID,
				'creating' => $creating,
			);
		};

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );
		add_action( 'rest_after_insert_attachment', $spy, 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/hooked.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_action( 'rest_after_insert_attachment', $spy, 10 );
		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$data = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $data['id'], $fired['id'] ?? null, 'rest_after_insert_attachment should fire with the new attachment.' );
		$this->assertTrue( $fired['creating'] ?? null, 'rest_after_insert_attachment should report creating=true.' );
	}

	/**
	 * Verifies that a sideloaded external image is attached to the post passed in
	 * the `post` parameter.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_attaches_to_post() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		$parent_post = self::factory()->post->create();

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/attached.jpg' );
		$request->set_param( 'generate_sub_sizes', false );
		$request->set_param( 'post', $parent_post );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$data = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $parent_post, get_post( $data['id'] )->post_parent );
	}

	/**
	 * Verifies that a failed download propagates the WP_Error from download_url()
	 * rather than creating an attachment.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_returns_error_on_download_failure() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		$fail_download = static function () {
			return new WP_Error( 'http_request_failed', 'Could not resolve host.' );
		};
		add_filter( 'pre_http_request', $fail_download );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/missing.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', $fail_download );

		$this->assertSame( 'http_request_failed', $response->get_data()['code'] );
		$this->assertSame( 500, $response->get_status() );
	}

	/**
	 * Verifies that the URL sideload path enforces the multisite maximum file
	 * size, for parity with the multipart and raw-body upload paths.
	 *
	 * @ticket 65517
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 * @covers WP_REST_Attachments_Controller::check_upload_size
	 */
	public function test_create_item_from_url_exceeds_multisite_max_filesize() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );
		update_site_option( 'fileupload_maxk', 1 );
		update_site_option( 'upload_space_check_disabled', false );

		// Ensure ample space is available so the file-size limit is what rejects it.
		add_filter( 'pre_get_space_used', '__return_zero' );
		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/too-big.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$this->assertErrorResponse( 'rest_upload_file_too_big', $response, 400 );
	}

	/**
	 * Verifies that the URL sideload path enforces the multisite site upload
	 * space quota, for parity with the multipart and raw-body upload paths.
	 *
	 * @ticket 65517
	 * @group multisite
	 * @group ms-required
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 * @covers WP_REST_Attachments_Controller::check_upload_size
	 */
	public function test_create_item_from_url_exceeds_multisite_site_upload_space() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );
		add_filter( 'get_space_allowed', '__return_zero' );
		update_site_option( 'upload_space_check_disabled', false );

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/no-space.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$this->assertErrorResponse( 'rest_upload_limited_space', $response, 400 );
	}

	/**
	 * Verifies that the URL sideload path enforces the site's maximum upload
	 * size on single site as well as multisite.
	 *
	 * check_upload_size() returns early when ! is_multisite(), so before this
	 * check a single site had no ceiling at all on this path.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_exceeds_max_upload_size() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		// The fixture the download is mocked with is comfortably larger than this.
		add_filter( 'upload_size_limit', array( $this, 'filter_small_upload_size_limit' ), 20 );
		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/too-big.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$this->assertErrorResponse( 'rest_upload_file_too_big', $response, 400 );
	}

	/**
	 * Verifies that the download itself is bounded, so an oversized remote file
	 * is not written to disk in full before the size check rejects it.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_limits_the_download_size() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		$request_args = null;

		$capture_args = static function ( $response, $args, $url ) use ( &$request_args ) {
			$request_args = $args;

			if ( ! empty( $args['filename'] ) ) {
				copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );
			}

			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'headers'  => array(),
				'cookies'  => array(),
				'body'     => '',
			);
		};

		add_filter( 'pre_http_request', $capture_args, 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/photo.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', $capture_args, 10 );

		$this->assertIsArray( $request_args, 'The download request should have been made.' );
		$this->assertSame(
			(int) wp_max_upload_size() + 1,
			$request_args['limit_response_size'],
			'The download should be capped one byte past the maximum upload size.'
		);
	}

	/**
	 * Filters the maximum upload size down to a value smaller than the image
	 * fixture used to mock the download.
	 *
	 * @return int A deliberately small upload size limit, in bytes.
	 */
	public function filter_small_upload_size_limit() {
		return 1024;
	}

	/**
	 * Verifies that a URL with no usable path bails with a 400 before any
	 * download is attempted, rather than handing an empty filename to the
	 * sideload handler.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_rejects_url_without_filename() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		// Fail loudly if the guard does not bail and a download is attempted.
		$downloaded = false;
		$track      = static function () use ( &$downloaded ) {
			$downloaded = true;
			return new WP_Error( 'http_request_failed', 'Should not be reached.' );
		};
		add_filter( 'pre_http_request', $track );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/?img=123' );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', $track );

		$this->assertSame( 'rest_invalid_url', $response->get_data()['code'] );
		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $downloaded, 'No download should be attempted for a URL without a filename.' );
	}

	/**
	 * Verifies that a URL pointing to a file without an allowed image extension,
	 * such as a PHP script, is rejected before any download is attempted.
	 *
	 * @ticket 65517
	 *
	 * @dataProvider data_create_item_from_url_rejects_non_image_extension
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 *
	 * @param string $url URL with a disallowed file extension.
	 */
	public function test_create_item_from_url_rejects_non_image_extension( $url ) {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		// Fail loudly if the guard does not bail and a download is attempted.
		$downloaded = false;
		$track      = static function () use ( &$downloaded ) {
			$downloaded = true;
			return new WP_Error( 'http_request_failed', 'Should not be reached.' );
		};
		add_filter( 'pre_http_request', $track );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', $url );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', $track );

		$this->assertSame( 'rest_invalid_url', $response->get_data()['code'] );
		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $downloaded, 'No download should be attempted for a non-image URL.' );
	}

	/**
	 * Data provider for test_create_item_from_url_rejects_non_image_extension().
	 *
	 * @return array[]
	 */
	public function data_create_item_from_url_rejects_non_image_extension() {
		return array(
			'PHP script'       => array( 'https://example.com/evil.php' ),
			'HTML document'    => array( 'https://example.com/page.html' ),
			'video file'       => array( 'https://example.com/clip.mp4' ),
			'no extension'     => array( 'https://example.com/image' ),
			'double extension' => array( 'https://example.com/photo.jpg.php' ),
		);
	}

	/**
	 * Verifies that a user without the `upload_files` capability cannot sideload
	 * an external image and that the request bails before any download happens.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_requires_upload_capability() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// Fail loudly if the guard does not bail and a download is attempted.
		$downloaded = false;
		$track      = static function () use ( &$downloaded ) {
			$downloaded = true;
			return new WP_Error( 'http_request_failed', 'Should not be reached.' );
		};
		add_filter( 'pre_http_request', $track );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/denied.jpg' );

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$method     = new ReflectionMethod( $controller, 'create_item_from_url' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$result = $method->invoke( $controller, $request );

		remove_filter( 'pre_http_request', $track );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_cannot_create', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertFalse( $downloaded, 'No download should be attempted without upload_files.' );
	}

	/**
	 * Verifies that schema validation still applies to the `url` argument even
	 * though it registers a custom `validate_callback`, which replaces the
	 * default rest_validate_request_arg() unless re-applied.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::get_endpoint_args_for_item_schema
	 */
	public function test_create_item_from_url_rejects_non_string_url() {
		$this->enable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', array( 'https://example.com/image.jpg' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Verifies that the `url` argument is registered on the creatable media route
	 * so requests can supply an external image URL to sideload.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::get_endpoint_args_for_item_schema
	 */
	public function test_url_registered_as_creatable_arg() {
		$this->enable_client_side_media_processing();

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/media', $routes );

		$creatable = null;
		foreach ( $routes['/wp/v2/media'] as $route ) {
			if ( ! empty( $route['methods'][ WP_REST_Server::CREATABLE ] ) ) {
				$creatable = $route;
				break;
			}
		}

		$this->assertNotNull( $creatable, 'The media route should register a CREATABLE handler.' );
		$this->assertArrayHasKey( 'url', $creatable['args'] );
		$this->assertSame( 'string', $creatable['args']['url']['type'] );
		$this->assertSame( 'uri', $creatable['args']['url']['format'] );
	}

	/**
	 * Verifies that the media creation arguments are registered even when
	 * client-side media processing is disabled.
	 *
	 * The feature is determined per request, from the scheme and host, so gating
	 * the schema on it would advertise different arguments for the same site
	 * depending on how it was reached.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::get_endpoint_args_for_item_schema
	 */
	public function test_creatable_args_registered_without_client_side_media_processing() {
		$this->disable_client_side_media_processing();

		$routes    = rest_get_server()->get_routes();
		$creatable = null;
		foreach ( $routes['/wp/v2/media'] as $route ) {
			if ( ! empty( $route['methods'][ WP_REST_Server::CREATABLE ] ) ) {
				$creatable = $route;
				break;
			}
		}

		$this->assertNotNull( $creatable, 'The media route should register a CREATABLE handler.' );
		$this->assertArrayHasKey( 'url', $creatable['args'] );
		$this->assertArrayHasKey( 'generate_sub_sizes', $creatable['args'] );
		$this->assertArrayHasKey( 'convert_format', $creatable['args'] );
	}

	/**
	 * Verifies that sideloading an external image works when client-side media
	 * processing is disabled.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 * @covers WP_REST_Attachments_Controller::create_item_from_url
	 */
	public function test_create_item_from_url_without_client_side_media_processing() {
		$this->disable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/photo.jpg' );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$data = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'image', $data['media_type'] );
		$this->assertSame( 'https://example.com/photo.jpg', $this->last_download_url );
	}

	/**
	 * Verifies that the `url` argument's validation runs when client-side media
	 * processing is disabled, so an unsafe URL is rejected with a 400 rather than
	 * reaching the download.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::get_endpoint_args_for_item_schema
	 */
	public function test_url_arg_rejects_unsafe_urls_without_client_side_media_processing() {
		$this->disable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'http://127.0.0.1/private.jpg' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Verifies that `generate_sub_sizes` is honored when client-side media
	 * processing is disabled.
	 *
	 * Skipping sub-size generation is a request the server can carry out on its
	 * own, so it does not depend on the feature. Sub-sizes can still be added
	 * later with wp_update_image_subsizes().
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item
	 */
	public function test_generate_sub_sizes_honored_without_client_side_media_processing() {
		$this->disable_client_side_media_processing();

		wp_set_current_user( self::$superadmin_id );

		add_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10, 3 );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_param( 'url', 'https://example.com/photo.jpg' );
		$request->set_param( 'generate_sub_sizes', false );

		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'pre_http_request', array( $this, 'mock_image_download' ), 10 );

		$data = $response->get_data();

		$this->assertSame( 201, $response->get_status() );

		$metadata = wp_get_attachment_metadata( $data['id'], true );
		$this->assertEmpty(
			$metadata['sizes'] ?? array(),
			'Sub-sizes should not be generated when generate_sub_sizes is false.'
		);
	}

	/**
	 * Verifies that `generate_sub_sizes` does not relax the unsupported image
	 * type check when client-side media processing is disabled.
	 *
	 * That check exists because the server cannot process the image, so it should
	 * only be relaxed when the client can process it instead. Otherwise the
	 * upload is stored unprocessable.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::create_item_permissions_check
	 */
	public function test_unsupported_image_type_still_checked_without_client_side_media_processing() {
		$this->disable_client_side_media_processing();

		wp_set_current_user( self::$author_id );

		add_filter( 'wp_image_editors', '__return_empty_array' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'avif-lossy.avif',
					'type'     => 'image/avif',
					'tmp_name' => self::$test_avif_file,
					'error'    => 0,
					'size'     => filesize( self::$test_avif_file ),
				),
			)
		);
		$request->set_param( 'generate_sub_sizes', false );

		$controller = new WP_REST_Attachments_Controller( 'attachment' );
		$result     = $controller->create_item_permissions_check( $request );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_upload_image_type_not_supported', $result->get_error_code() );
	}

	/**
	 * Verifies that the `url` argument rejects values that are not safe to
	 * request server-side, guarding the sideload against SSRF.
	 *
	 * @ticket 65517
	 *
	 * @covers WP_REST_Attachments_Controller::get_endpoint_args_for_item_schema
	 */
	public function test_url_arg_rejects_unsafe_urls() {
		$this->enable_client_side_media_processing();

		$routes    = rest_get_server()->get_routes();
		$creatable = null;
		foreach ( $routes['/wp/v2/media'] as $route ) {
			if ( ! empty( $route['methods'][ WP_REST_Server::CREATABLE ] ) ) {
				$creatable = $route;
				break;
			}
		}

		$this->assertNotNull( $creatable, 'The media route should register a CREATABLE handler.' );
		$this->assertArrayHasKey( 'validate_callback', $creatable['args']['url'] );

		$validate = $creatable['args']['url']['validate_callback'];
		$request  = new WP_REST_Request( 'POST', '/wp/v2/media' );

		// A well-formed URL on the site's own host passes validation.
		$this->assertTrue( $validate( home_url( '/image.jpg' ), $request, 'url' ), 'A safe URL should pass validation.' );

		// A disallowed scheme and a malformed URL are both rejected.
		$invalid_urls = array(
			'ftp://example.org/image.jpg',
			'javascript:alert(1)',
			'not-a-url',
		);

		foreach ( $invalid_urls as $invalid ) {
			$result = $validate( $invalid, $request, 'url' );
			$this->assertWPError( $result, 'An unsafe URL should be rejected.' );
			$this->assertSame( 'rest_invalid_url', $result->get_error_code() );
			$this->assertSame( 400, $result->get_error_data()['status'] );
		}
	}
}
