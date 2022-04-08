<?php
/**
 * Unit tests covering WP_REST_Server functionality.
 *
 * @package WordPress
 * @subpackage REST API
 */

/**
 * @group restapi
 */
class Tests_REST_Server extends WP_Test_REST_TestCase {
	protected static $icon_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$filename      = DIR_TESTDATA . '/images/test-image-large.jpg';
		self::$icon_id = $factory->attachment->create_upload_object( $filename );
	}

	public function set_up() {
		parent::set_up();

		// Reset REST server to ensure only our routes are registered.
		$GLOBALS['wp_rest_server'] = null;
		add_filter( 'wp_rest_server_class', array( $this, 'filter_wp_rest_server_class' ) );
		$GLOBALS['wp_rest_server'] = rest_get_server();
		remove_filter( 'wp_rest_server_class', array( $this, 'filter_wp_rest_server_class' ) );
	}

	public function tear_down() {
		// Remove our temporary spy server.
		$GLOBALS['wp_rest_server'] = null;
		unset( $_REQUEST['_wpnonce'] );

		parent::tear_down();
	}

	/**
	 * Called before setting up all tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Require files that need to load once.
		require_once DIR_TESTROOT . '/includes/mock-invokable.php';
	}

	public function test_envelope() {
		$data    = array(
			'amount of arbitrary data' => 'alot',
		);
		$status  = 987;
		$headers = array(
			'Arbitrary-Header' => 'value',
			'Multiple'         => 'maybe, yes',
		);

		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Arbitrary-Header', 'value' );

		// Check header concatenation as well.
		$response->header( 'Multiple', 'maybe' );
		$response->header( 'Multiple', 'yes', false );

		$envelope_response = rest_get_server()->envelope_response( $response, false );

		// The envelope should still be a response, but with defaults.
		$this->assertInstanceOf( 'WP_REST_Response', $envelope_response );
		$this->assertSame( 200, $envelope_response->get_status() );
		$this->assertEmpty( $envelope_response->get_headers() );
		$this->assertEmpty( $envelope_response->get_links() );

		$enveloped = $envelope_response->get_data();

		$this->assertSame( $data, $enveloped['body'] );
		$this->assertSame( $status, $enveloped['status'] );
		$this->assertSame( $headers, $enveloped['headers'] );
	}

	/**
	 * @dataProvider data_envelope_params
	 * @ticket 54015
	 */
	public function test_envelope_param( $_embed ) {
		// Register our testing route.
		rest_get_server()->register_route(
			'test',
			'/test/embeddable',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'embedded_response_callback' ),
			)
		);
		$data    = array(
			'amount of arbitrary data' => 'alot',
		);
		$status  = 987;
		$headers = array(
			'Arbitrary-Header' => 'value',
			'Multiple'         => 'maybe, yes',
		);

		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Arbitrary-Header', 'value' );

		// Check header concatenation as well.
		$response->header( 'Multiple', 'maybe' );
		$response->header( 'Multiple', 'yes', false );

		// All others should be embedded.
		$response->add_link( 'alternate', rest_url( '/test/embeddable' ), array( 'embeddable' => true ) );

		$embed             = rest_parse_embed_param( $_embed );
		$envelope_response = rest_get_server()->envelope_response( $response, $embed );

		// The envelope should still be a response, but with defaults.
		$this->assertInstanceOf( WP_REST_Response::class, $envelope_response );
		$this->assertSame( 200, $envelope_response->get_status() );
		$this->assertEmpty( $envelope_response->get_headers() );
		$this->assertEmpty( $envelope_response->get_links() );

		$enveloped = $envelope_response->get_data();

		$this->assertArrayHasKey( 'body', $enveloped );
		$this->assertArrayHasKey( '_links', $enveloped['body'] );
		$this->assertArrayHasKey( '_embedded', $enveloped['body'] );
		$this->assertArrayHasKey( 'alternate', $enveloped['body']['_embedded'] );

		$alternate = $enveloped['body']['_embedded']['alternate'];
		$this->assertCount( 1, $alternate );

		$this->assertSame( $status, $enveloped['status'] );
		$this->assertSame( $headers, $enveloped['headers'] );
	}

	public function test_default_param() {

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'args'                => array(
					'foo' => array(
						'default' => 'bar',
					),
				),
			)
		);

		$request  = new WP_REST_Request( 'GET', '/test-ns/test' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 'bar', $request['foo'] );
	}

	public function test_default_param_is_overridden() {

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'args'                => array(
					'foo' => array(
						'default' => 'bar',
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/test' );
		$request->set_query_params( array( 'foo' => 123 ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( '123', $request['foo'] );
	}

	public function test_optional_param() {
		register_rest_route(
			'optional',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'args'                => array(
					'foo' => array(),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/optional/test' );
		$request->set_query_params( array() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'foo', (array) $request );
	}

	public function test_no_zero_param() {
		register_rest_route(
			'no-zero',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'args'                => array(
					'foo' => array(
						'default' => 'bar',
					),
				),
			)
		);
		$request = new WP_REST_Request( 'GET', '/no-zero/test' );
		rest_get_server()->dispatch( $request );
		$this->assertSame( array( 'foo' => 'bar' ), $request->get_params() );
	}

	public function test_head_request_handled_by_get() {
		register_rest_route(
			'head-request',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => '__return_true',
				'permission_callback' => '__return_true',
			)
		);
		$request  = new WP_REST_Request( 'HEAD', '/head-request/test' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Plugins should be able to register explicit HEAD callbacks before the
	 * GET callback.
	 *
	 * @depends test_head_request_handled_by_get
	 */
	public function test_explicit_head_callback() {
		register_rest_route(
			'head-request',
			'/test',
			array(
				array(
					'methods'             => array( 'HEAD' ),
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => array( 'GET' ),
					'callback'            => '__return_false',
					'permission_callback' => array( $this, 'permission_denied' ),
				),
			)
		);
		$request  = new WP_REST_Request( 'HEAD', '/head-request/test' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_url_params_no_numeric_keys() {

		rest_get_server()->register_route(
			'test',
			'/test/(?P<data>.*)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => '__return_false',
					'permission_callback' => '__return_true',
					'args'                => array(
						'data' => array(),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/test/some-value' );
		rest_get_server()->dispatch( $request );
		$this->assertSame( array( 'data' => 'some-value' ), $request->get_params() );
	}

	/**
	 * Pass a capability which the user does not have, this should
	 * result in a 403 error.
	 */
	public function test_rest_route_capability_authorization_fails() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'should_exist'        => false,
				'permission_callback' => array( $this, 'permission_denied' ),
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/test', array() );
		$result  = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $result->get_status() );
	}

	/**
	 * An editor should be able to get access to an route with the
	 * edit_posts capability.
	 */
	public function test_rest_route_capability_authorization() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'should_exist'        => false,
				'permission_callback' => '__return_true',
			)
		);

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );

		$request = new WP_REST_Request( 'GET', '/test-ns/test', array() );

		wp_set_current_user( $editor );

		$result = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $result->get_status() );
	}

	/**
	 * An "Allow" HTTP header should be sent with a request
	 * for all available methods on that route.
	 */
	public function test_allow_header_sent() {

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'should_exist'        => false,
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/test', array() );

		$result = rest_get_server()->dispatch( $request );
		$result = apply_filters( 'rest_post_dispatch', $result, rest_get_server(), $request );

		$this->assertFalse( $result->get_status() !== 200 );

		$sent_headers = $result->get_headers();
		$this->assertSame( $sent_headers['Allow'], 'GET' );
	}

	/**
	 * The "Allow" HTTP header should include all available
	 * methods that can be sent to a route.
	 */
	public function test_allow_header_sent_with_multiple_methods() {

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'should_exist'        => false,
			)
		);

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'should_exist'        => false,
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/test', array() );

		$result = rest_get_server()->dispatch( $request );

		$this->assertFalse( $result->get_status() !== 200 );

		$result = apply_filters( 'rest_post_dispatch', $result, rest_get_server(), $request );

		$sent_headers = $result->get_headers();
		$this->assertSame( $sent_headers['Allow'], 'GET, POST' );
	}

	/**
	 * The "Allow" HTTP header should NOT include other methods
	 * which the user does not have access to.
	 */
	public function test_allow_header_send_only_permitted_methods() {

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'GET',
				'callback'            => '__return_null',
				'should_exist'        => false,
				'permission_callback' => array( $this, 'permission_denied' ),
			)
		);

		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'should_exist'        => false,
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/test', array() );

		$result = rest_get_server()->dispatch( $request );
		$result = apply_filters( 'rest_post_dispatch', $result, rest_get_server(), $request );

		$this->assertSame( $result->get_status(), 403 );

		$sent_headers = $result->get_headers();
		$this->assertSame( $sent_headers['Allow'], 'POST' );
	}

	/**
	 * @ticket 53063
	 */
	public function test_batched_options() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				array(
					'methods'             => array( 'GET' ),
					'callback'            => '__return_null',
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => array( 'POST' ),
					'callback'            => '__return_null',
					'permission_callback' => '__return_null',
					'allow_batch'         => false,
				),
				'allow_batch' => array( 'v1' => true ),
			)
		);

		$request  = new WP_REST_Request( 'OPTIONS', '/test-ns/test' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		$this->assertSame( array( 'v1' => true ), $data['endpoints'][0]['allow_batch'] );
		$this->assertArrayNotHasKey( 'allow_batch', $data['endpoints'][1] );
	}

	public function test_allow_header_sent_on_options_request() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				array(
					'methods'             => array( 'GET' ),
					'callback'            => '__return_null',
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => array( 'POST' ),
					'callback'            => '__return_null',
					'permission_callback' => '__return_null',
				),
			)
		);

		$request  = new WP_REST_Request( 'OPTIONS', '/test-ns/test' );
		$response = rest_get_server()->dispatch( $request );

		$result = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), rest_get_server(), $request );

		$headers = $result->get_headers();

		$this->assertSame( 'GET', $headers['Allow'] );
	}

	public function permission_denied() {
		return new WP_Error( 'forbidden', 'You are not allowed to do this', array( 'status' => 403 ) );
	}

	public function test_error_to_response() {
		$code    = 'wp-api-test-error';
		$message = 'Test error message for the API';
		$error   = new WP_Error( $code, $message );

		$response = rest_convert_error_to_response( $error );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		// Make sure we default to a 500 error.
		$this->assertSame( 500, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( $code, $data['code'] );
		$this->assertSame( $message, $data['message'] );
	}

	public function test_error_to_response_with_status() {
		$code    = 'wp-api-test-error';
		$message = 'Test error message for the API';
		$error   = new WP_Error( $code, $message, array( 'status' => 400 ) );

		$response = rest_convert_error_to_response( $error );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$this->assertSame( 400, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame( $code, $data['code'] );
		$this->assertSame( $message, $data['message'] );
	}

	public function test_error_to_response_to_error() {
		$code     = 'wp-api-test-error';
		$message  = 'Test error message for the API';
		$code2    = 'wp-api-test-error-2';
		$message2 = 'Another test message';
		$error    = new WP_Error( $code, $message, array( 'status' => 400 ) );
		$error->add( $code2, $message2, array( 'status' => 403 ) );

		$response = rest_convert_error_to_response( $error );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$this->assertSame( 400, $response->get_status() );

		$error = $response->as_error();
		$this->assertInstanceOf( 'WP_Error', $error );
		$this->assertSame( $code, $error->get_error_code() );
		$this->assertSame( $message, $error->get_error_message() );
		$this->assertSame( $message2, $error->errors[ $code2 ][0] );
		$this->assertSame( array( 'status' => 403 ), $error->error_data[ $code2 ] );
	}

	/**
	 * @ticket 46191
	 */
	public function test_error_to_response_with_additional_data() {
		$error = new WP_Error( 'test', 'test', array( 'status' => 400 ) );
		$error->add_data( 'more_data' );

		$response = rest_convert_error_to_response( $error );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'more_data', $response->get_data()['data'] );
		$this->assertSame( array( array( 'status' => 400 ) ), $response->get_data()['additional_data'] );
	}

	public function test_rest_error() {
		$data     = array(
			'code'    => 'wp-api-test-error',
			'message' => 'Message text',
		);
		$expected = wp_json_encode( $data );
		$response = rest_get_server()->json_error( 'wp-api-test-error', 'Message text' );

		$this->assertSame( $expected, $response );
	}

	public function test_json_error_with_status() {
		$stub = $this->getMockBuilder( 'Spy_REST_Server' )
					->setMethods( array( 'set_status' ) )
					->getMock();

		$stub->expects( $this->once() )
			->method( 'set_status' )
			->with( $this->equalTo( 400 ) );

		$data     = array(
			'code'    => 'wp-api-test-error',
			'message' => 'Message text',
		);
		$expected = wp_json_encode( $data );

		$response = $stub->json_error( 'wp-api-test-error', 'Message text', 400 );

		$this->assertSame( $expected, $response );
	}

	public function test_response_to_data_links() {
		$response = new WP_REST_Response();
		$response->add_link( 'self', 'http://example.com/' );
		$response->add_link( 'alternate', 'http://example.org/', array( 'type' => 'application/xml' ) );

		$data = rest_get_server()->response_to_data( $response, false );
		$this->assertArrayHasKey( '_links', $data );

		$self = array(
			'href' => 'http://example.com/',
		);
		$this->assertSame( $self, $data['_links']['self'][0] );

		$alternate = array(
			'type' => 'application/xml',
			'href' => 'http://example.org/',
		);
		$this->assertSame( $alternate, $data['_links']['alternate'][0] );
	}

	public function test_link_embedding() {
		// Register our testing route.
		rest_get_server()->register_route(
			'test',
			'/test/embeddable',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'embedded_response_callback' ),
			)
		);
		$response = new WP_REST_Response();

		// External links should be ignored.
		$response->add_link( 'alternate', 'http://not-api.example.com/', array( 'embeddable' => true ) );

		// All others should be embedded.
		$response->add_link( 'alternate', rest_url( '/test/embeddable' ), array( 'embeddable' => true ) );

		$data = rest_get_server()->response_to_data( $response, true );
		$this->assertArrayHasKey( '_embedded', $data );

		$alternate = $data['_embedded']['alternate'];
		$this->assertCount( 2, $alternate );
		$this->assertEmpty( $alternate[0] );

		$this->assertIsArray( $alternate[1] );
		$this->assertArrayNotHasKey( 'code', $alternate[1] );
		$this->assertTrue( $alternate[1]['hello'] );

		// Ensure the context is set to embed when requesting.
		$this->assertSame( 'embed', $alternate[1]['parameters']['context'] );
	}

	public function test_link_curies() {
		$response = new WP_REST_Response();
		$response->add_link( 'https://api.w.org/term', 'http://example.com/' );

		$data  = rest_get_server()->response_to_data( $response, false );
		$links = $data['_links'];

		$this->assertArrayHasKey( 'wp:term', $links );
		$this->assertArrayHasKey( 'curies', $links );
	}

	public function test_custom_curie_link() {
		$response = new WP_REST_Response();
		$response->add_link( 'http://mysite.com/contact.html', 'http://example.com/' );

		add_filter( 'rest_response_link_curies', array( $this, 'add_custom_curie' ) );

		$data  = rest_get_server()->response_to_data( $response, false );
		$links = $data['_links'];

		$this->assertArrayHasKey( 'my_site:contact', $links );
		$this->assertArrayHasKey( 'curies', $links );
	}

	/**
	 * Helper callback to add a new custom curie via a filter.
	 *
	 * @param array $curies
	 * @return array
	 */
	public function add_custom_curie( $curies ) {
		$curies[] = array(
			'name'      => 'my_site',
			'href'      => 'http://mysite.com/{rel}.html',
			'templated' => true,
		);
		return $curies;
	}

	/**
	 * @depends test_link_embedding
	 * @ticket 47684
	 */
	public function test_link_embedding_self() {
		// Register our testing route.
		rest_get_server()->register_route(
			'test',
			'/test/embeddable',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'embedded_response_callback' ),
			)
		);
		$response = new WP_REST_Response();

		// 'self' should not be special-cased, and may be marked embeddable.
		$response->add_link( 'self', rest_url( '/test/embeddable' ), array( 'embeddable' => true ) );

		$data = rest_get_server()->response_to_data( $response, true );

		$this->assertArrayHasKey( '_embedded', $data );
	}

	/**
	 * @depends test_link_embedding
	 * @ticket 47684
	 */
	public function test_link_embedding_self_non_embeddable() {
		// Register our testing route.
		rest_get_server()->register_route(
			'test',
			'/test/embeddable',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'embedded_response_callback' ),
			)
		);
		$response = new WP_REST_Response();

		// 'self' should not be special-cased, and should be ignored if not marked embeddable.
		$response->add_link( 'self', rest_url( '/test/notembeddable' ) );

		$data = rest_get_server()->response_to_data( $response, true );

		$this->assertArrayNotHasKey( '_embedded', $data );
	}

	/**
	 * @depends test_link_embedding
	 */
	public function test_link_embedding_params() {
		// Register our testing route.
		rest_get_server()->register_route(
			'test',
			'/test/embeddable',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'embedded_response_callback' ),
			)
		);

		$response = new WP_REST_Response();
		$url      = rest_url( '/test/embeddable' );
		$url      = add_query_arg( 'parsed_params', 'yes', $url );
		$response->add_link( 'alternate', $url, array( 'embeddable' => true ) );

		$data = rest_get_server()->response_to_data( $response, true );

		$this->assertArrayHasKey( '_embedded', $data );
		$this->assertArrayHasKey( 'alternate', $data['_embedded'] );
		$data = $data['_embedded']['alternate'][0];

		$this->assertSame( 'yes', $data['parameters']['parsed_params'] );
	}

	/**
	 * @depends test_link_embedding_params
	 */
	public function test_link_embedding_error() {
		// Register our testing route.
		rest_get_server()->register_route(
			'test',
			'/test/embeddable',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'embedded_response_callback' ),
			)
		);

		$response = new WP_REST_Response();
		$url      = rest_url( '/test/embeddable' );
		$url      = add_query_arg( 'error', '1', $url );
		$response->add_link( 'up', $url, array( 'embeddable' => true ) );

		$data = rest_get_server()->response_to_data( $response, true );

		$this->assertArrayHasKey( '_embedded', $data );
		$this->assertArrayHasKey( 'up', $data['_embedded'] );

		// Check that errors are embedded correctly.
		$up = $data['_embedded']['up'];
		$this->assertCount( 1, $up );

		$up_data = $up[0];
		$this->assertSame( 'wp-api-test-error', $up_data['code'] );
		$this->assertSame( 'Test message', $up_data['message'] );
		$this->assertSame( 403, $up_data['data']['status'] );
	}

	/**
	 * @ticket 48838
	 */
	public function test_link_embedding_clears_cache() {
		$post_id = self::factory()->post->create();

		$response = new WP_REST_Response();
		$response->add_link( 'post', rest_url( 'wp/v2/posts/' . $post_id ), array( 'embeddable' => true ) );

		$data = rest_get_server()->response_to_data( $response, true );
		$this->assertArrayHasKey( 'post', $data['_embedded'] );
		$this->assertCount( 1, $data['_embedded']['post'] );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'My Awesome Title',
			)
		);

		$data = rest_get_server()->response_to_data( $response, true );
		$this->assertArrayHasKey( 'post', $data['_embedded'] );
		$this->assertCount( 1, $data['_embedded']['post'] );
		$this->assertSame( 'My Awesome Title', $data['_embedded']['post'][0]['title']['rendered'] );
	}

	/**
	 * @ticket 48838
	 */
	public function test_link_embedding_cache() {
		$response = new WP_REST_Response(
			array(
				'id' => 1,
			)
		);
		$response->add_link(
			'author',
			rest_url( 'wp/v2/users/1' ),
			array( 'embeddable' => true )
		);
		$response->add_link(
			'author',
			rest_url( 'wp/v2/users/1' ),
			array( 'embeddable' => true )
		);

		$mock = new MockAction();
		add_filter( 'rest_post_dispatch', array( $mock, 'filter' ) );

		$data = rest_get_server()->response_to_data( $response, true );

		$this->assertArrayHasKey( '_embedded', $data );
		$this->assertArrayHasKey( 'author', $data['_embedded'] );
		$this->assertCount( 2, $data['_embedded']['author'] );

		$this->assertCount( 1, $mock->get_events() );
	}

	/**
	 * @ticket 48838
	 */
	public function test_link_embedding_cache_collection() {
		$response = new WP_REST_Response(
			array(
				array(
					'id'     => 1,
					'_links' => array(
						'author' => array(
							array(
								'href'       => rest_url( 'wp/v2/users/1' ),
								'embeddable' => true,
							),
						),
					),
				),
				array(
					'id'     => 2,
					'_links' => array(
						'author' => array(
							array(
								'href'       => rest_url( 'wp/v2/users/1' ),
								'embeddable' => true,
							),
						),
					),
				),
			)
		);

		$mock = new MockAction();
		add_filter( 'rest_post_dispatch', array( $mock, 'filter' ) );

		$data = rest_get_server()->response_to_data( $response, true );

		$embeds = wp_list_pluck( $data, '_embedded' );
		$this->assertCount( 2, $embeds );
		$this->assertArrayHasKey( 'author', $embeds[0] );
		$this->assertArrayHasKey( 'author', $embeds[1] );

		$this->assertCount( 1, $mock->get_events() );
	}

	/**
	 * Ensure embedding is a no-op without links in the data.
	 */
	public function test_link_embedding_without_links() {
		$data   = array(
			'untouched' => 'data',
		);
		$result = rest_get_server()->embed_links( $data );

		$this->assertArrayNotHasKey( '_links', $data );
		$this->assertArrayNotHasKey( '_embedded', $data );
		$this->assertSame( 'data', $data['untouched'] );
	}

	public function embedded_response_callback( $request ) {
		$params = $request->get_params();

		if ( isset( $params['error'] ) ) {
			return new WP_Error( 'wp-api-test-error', 'Test message', array( 'status' => 403 ) );
		}

		$data = array(
			'hello'      => true,
			'parameters' => $params,
		);

		return $data;
	}

	public function test_removing_links() {
		$response = new WP_REST_Response();
		$response->add_link( 'self', 'http://example.com/' );
		$response->add_link( 'alternate', 'http://example.org/', array( 'type' => 'application/xml' ) );

		$response->remove_link( 'self' );

		$data = rest_get_server()->response_to_data( $response, false );
		$this->assertArrayHasKey( '_links', $data );

		$this->assertArrayNotHasKey( 'self', $data['_links'] );

		$alternate = array(
			'type' => 'application/xml',
			'href' => 'http://example.org/',
		);
		$this->assertSame( $alternate, $data['_links']['alternate'][0] );
	}

	public function test_removing_links_for_href() {
		$response = new WP_REST_Response();
		$response->add_link( 'self', 'http://example.com/' );
		$response->add_link( 'self', 'https://example.com/' );

		$response->remove_link( 'self', 'https://example.com/' );

		$data = rest_get_server()->response_to_data( $response, false );
		$this->assertArrayHasKey( '_links', $data );

		$this->assertArrayHasKey( 'self', $data['_links'] );

		$self_not_filtered = array(
			'href' => 'http://example.com/',
		);
		$this->assertSame( $self_not_filtered, $data['_links']['self'][0] );
	}

	/**
	 * @dataProvider _dp_response_to_data_embedding
	 */
	public function test_response_to_data_embedding( $expected, $embed ) {
		$response = new WP_REST_Response();
		$response->add_link( 'author', rest_url( '404' ), array( 'embeddable' => true ) );
		$response->add_link( 'https://api.w.org/term', rest_url( '404' ), array( 'embeddable' => true ) );
		$response->add_link( 'https://wordpress.org', rest_url( '404' ), array( 'embeddable' => true ) );
		$response->add_link( 'no-embed', rest_url( '404' ) );

		$data = rest_get_server()->response_to_data( $response, $embed );

		if ( false === $expected ) {
			$this->assertArrayNotHasKey( '_embedded', $data );
		} else {
			$this->assertSameSets( $expected, array_keys( $data['_embedded'] ) );
		}
	}

	public function _dp_response_to_data_embedding() {
		return array(
			array(
				array( 'author', 'wp:term', 'https://wordpress.org' ),
				true,
			),
			array(
				array( 'author', 'wp:term', 'https://wordpress.org' ),
				array( 'author', 'wp:term', 'https://wordpress.org' ),
			),
			array(
				array( 'author' ),
				array( 'author' ),
			),
			array(
				array( 'wp:term' ),
				array( 'wp:term' ),
			),
			array(
				array( 'https://wordpress.org' ),
				array( 'https://wordpress.org' ),
			),
			array(
				array( 'author', 'wp:term' ),
				array( 'author', 'wp:term' ),
			),
			array(
				false,
				false,
			),
			array(
				false,
				array( 'no-embed' ),
			),
			array(
				array( 'author' ),
				array( 'author', 'no-embed' ),
			),
		);
	}

	public function test_get_index() {
		$server = new WP_REST_Server();
		$server->register_route(
			'test/example',
			'/test/example/some-route',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_true',
				),
				array(
					'methods'  => WP_REST_Server::DELETABLE,
					'callback' => '__return_true',
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/' );
		$index   = $server->dispatch( $request );
		$data    = $index->get_data();

		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'description', $data );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertArrayHasKey( 'home', $data );
		$this->assertArrayHasKey( 'gmt_offset', $data );
		$this->assertArrayHasKey( 'timezone_string', $data );
		$this->assertArrayHasKey( 'namespaces', $data );
		$this->assertArrayHasKey( 'authentication', $data );
		$this->assertArrayHasKey( 'routes', $data );

		// Check namespace data.
		$this->assertContains( 'test/example', $data['namespaces'] );

		// Check the route.
		$this->assertArrayHasKey( '/test/example/some-route', $data['routes'] );
		$route = $data['routes']['/test/example/some-route'];
		$this->assertSame( 'test/example', $route['namespace'] );
		$this->assertArrayHasKey( 'methods', $route );
		$this->assertContains( 'GET', $route['methods'] );
		$this->assertContains( 'DELETE', $route['methods'] );
		$this->assertArrayHasKey( '_links', $route );

		$this->assertArrayHasKey( 'help', $index->get_links() );
		$this->assertArrayNotHasKey( 'wp:active-theme', $index->get_links() );

		// Check site logo and icon.
		$this->assertArrayHasKey( 'site_logo', $data );
		$this->assertArrayHasKey( 'site_icon', $data );
	}

	/**
	 * @ticket 50152
	 */
	public function test_index_includes_link_to_active_theme_if_authenticated() {
		$server = new WP_REST_Server();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'GET', '/' );
		$index   = $server->dispatch( $request );

		$this->assertArrayHasKey( 'https://api.w.org/active-theme', $index->get_links() );
	}

	/**
	 * @ticket 52321
	 */
	public function test_index_includes_site_icon() {
		$server = new WP_REST_Server();
		update_option( 'site_icon', self::$icon_id );

		$request = new WP_REST_Request( 'GET', '/' );
		$index   = $server->dispatch( $request );
		$data    = $index->get_data();

		$this->assertArrayHasKey( 'site_icon', $data );
		$this->assertSame( self::$icon_id, $data['site_icon'] );
	}

	public function test_get_namespace_index() {
		$server = new WP_REST_Server();
		$server->register_route(
			'test/example',
			'/test/example/some-route',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_true',
				),
				array(
					'methods'  => WP_REST_Server::DELETABLE,
					'callback' => '__return_true',
				),
			)
		);
		$server->register_route(
			'test/another',
			'/test/another/route',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
				),
			)
		);

		$request = new WP_REST_Request();
		$request->set_param( 'namespace', 'test/example' );
		$index = rest_ensure_response( $server->get_namespace_index( $request ) );
		$data  = $index->get_data();

		// Check top-level.
		$this->assertSame( 'test/example', $data['namespace'] );
		$this->assertArrayHasKey( 'routes', $data );

		// Check we have the route we expect...
		$this->assertArrayHasKey( '/test/example/some-route', $data['routes'] );

		// ...and none we don't.
		$this->assertArrayNotHasKey( '/test/another/route', $data['routes'] );
	}

	public function test_get_namespaces() {
		$server = new WP_REST_Server();
		$server->register_route(
			'test/example',
			'/test/example/some-route',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_true',
				),
			)
		);
		$server->register_route(
			'test/another',
			'/test/another/route',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
				),
			)
		);

		$namespaces = $server->get_namespaces();
		$this->assertContains( 'test/example', $namespaces );
		$this->assertContains( 'test/another', $namespaces );
	}

	/**
	 * @ticket 49147
	 */
	public function test_get_data_for_non_variable_route_includes_links() {
		$expected = array(
			'self' => array(
				array( 'href' => rest_url( 'wp/v2/posts' ) ),
			),
		);

		$actual = rest_get_server()->get_data_for_route(
			'/wp/v2/posts',
			array(
				array(
					'methods'       => array( 'OPTIONS' => 1 ),
					'show_in_index' => true,
				),
			)
		);

		$this->assertSame( $expected, $actual['_links'] );
	}

	public function test_x_robot_tag_header_on_requests() {
		$request = new WP_REST_Request( 'GET', '/', array() );

		$result  = rest_get_server()->serve_request( '/' );
		$headers = rest_get_server()->sent_headers;

		$this->assertSame( 'noindex', $headers['X-Robots-Tag'] );
	}

	/**
	 * @ticket 38446
	 * @expectedDeprecated rest_enabled
	 */
	public function test_rest_enable_filter_is_deprecated() {
		add_filter( 'rest_enabled', '__return_false' );
		rest_get_server()->serve_request( '/' );
		remove_filter( 'rest_enabled', '__return_false' );

		$result = json_decode( rest_get_server()->sent_body );

		$this->assertObjectNotHasAttribute( 'code', $result );
	}

	public function test_link_header_on_requests() {
		$api_root = get_rest_url();

		$request = new WP_REST_Request( 'GET', '/', array() );

		$result  = rest_get_server()->serve_request( '/' );
		$headers = rest_get_server()->sent_headers;

		$this->assertSame( '<' . esc_url_raw( $api_root ) . '>; rel="https://api.w.org/"', $headers['Link'] );
	}

	public function test_nocache_headers_on_authenticated_requests() {
		$editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$request = new WP_REST_Request( 'GET', '/', array() );
		wp_set_current_user( $editor );

		$result  = rest_get_server()->serve_request( '/' );
		$headers = rest_get_server()->sent_headers;

		foreach ( wp_get_nocache_headers() as $header => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$this->assertArrayHasKey( $header, $headers, sprintf( 'Header %s is not present in the response.', $header ) );
			$this->assertSame( $value, $headers[ $header ] );
		}

		// Last-Modified should be unset as per #WP23021.
		$this->assertArrayNotHasKey( 'Last-Modified', $headers, 'Last-Modified should not be sent.' );
	}

	public function test_no_nocache_headers_on_unauthenticated_requests() {
		$editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$request = new WP_REST_Request( 'GET', '/', array() );

		$result  = rest_get_server()->serve_request( '/' );
		$headers = rest_get_server()->sent_headers;

		foreach ( wp_get_nocache_headers() as $header => $value ) {
			$this->assertFalse( isset( $headers[ $header ] ) && $headers[ $header ] === $value, sprintf( 'Header %s is set to nocache.', $header ) );
		}
	}

	public function test_serve_request_url_params_are_unslashed() {

		rest_get_server()->register_route(
			'test',
			'/test/(?P<data>.*)',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
					'args'     => array(
						'data' => array(),
					),
				),
			)
		);

		$result     = rest_get_server()->serve_request( '/test/data\\with\\slashes' );
		$url_params = rest_get_server()->last_request->get_url_params();
		$this->assertSame( 'data\\with\\slashes', $url_params['data'] );
	}

	public function test_serve_request_query_params_are_unslashed() {

		rest_get_server()->register_route(
			'test',
			'/test',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
					'args'     => array(
						'data' => array(),
					),
				),
			)
		);

		// WordPress internally will slash the superglobals on bootstrap.
		$_GET = wp_slash(
			array(
				'data' => 'data\\with\\slashes',
			)
		);

		$result       = rest_get_server()->serve_request( '/test' );
		$query_params = rest_get_server()->last_request->get_query_params();
		$this->assertSame( 'data\\with\\slashes', $query_params['data'] );
	}

	public function test_serve_request_body_params_are_unslashed() {

		rest_get_server()->register_route(
			'test',
			'/test',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
					'args'     => array(
						'data' => array(),
					),
				),
			)
		);

		// WordPress internally will slash the superglobals on bootstrap.
		$_POST = wp_slash(
			array(
				'data' => 'data\\with\\slashes',
			)
		);

		$result = rest_get_server()->serve_request( '/test/data' );

		$body_params = rest_get_server()->last_request->get_body_params();
		$this->assertSame( 'data\\with\\slashes', $body_params['data'] );
	}

	public function test_serve_request_json_params_are_unslashed() {

		rest_get_server()->register_route(
			'test',
			'/test',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
					'args'     => array(
						'data' => array(),
					),
				),
			)
		);

		$_SERVER['HTTP_CONTENT_TYPE']  = 'application/json';
		$GLOBALS['HTTP_RAW_POST_DATA'] = json_encode(
			array(
				'data' => 'data\\with\\slashes',
			)
		);

		$result      = rest_get_server()->serve_request( '/test' );
		$json_params = rest_get_server()->last_request->get_json_params();
		$this->assertSame( 'data\\with\\slashes', $json_params['data'] );
	}

	public function test_serve_request_file_params_are_unslashed() {

		rest_get_server()->register_route(
			'test',
			'/test',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
					'args'     => array(
						'data' => array(),
					),
				),
			)
		);

		// WordPress internally will slash the superglobals on bootstrap.
		$_FILES = array(
			'data' => array(
				'name' => 'data\\with\\slashes',
			),
		);

		$result      = rest_get_server()->serve_request( '/test/data\\with\\slashes' );
		$file_params = rest_get_server()->last_request->get_file_params();
		$this->assertSame( 'data\\with\\slashes', $file_params['data']['name'] );
	}

	public function test_serve_request_headers_are_unslashed() {

		rest_get_server()->register_route(
			'test',
			'/test',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => '__return_false',
					'args'     => array(
						'data' => array(),
					),
				),
			)
		);

		// WordPress internally will slash the superglobals on bootstrap.
		$_SERVER['HTTP_X_MY_HEADER'] = wp_slash( 'data\\with\\slashes' );

		$result = rest_get_server()->serve_request( '/test/data\\with\\slashes' );
		$this->assertSame( 'data\\with\\slashes', rest_get_server()->last_request->get_header( 'x_my_header' ) );
	}

	public function filter_wp_rest_server_class() {
		return 'Spy_REST_Server';
	}

	/**
	 * Refreshed nonce should not be present in header when an invalid nonce is passed for logged in user.
	 *
	 * @ticket 35662
	 */
	public function test_rest_send_refreshed_nonce_invalid_nonce() {
		$this->helper_setup_user_for_rest_send_refreshed_nonce_tests();

		$_REQUEST['_wpnonce'] = 'random invalid nonce';

		$headers = $this->helper_make_request_and_return_headers_for_rest_send_refreshed_nonce_tests();

		$this->assertArrayNotHasKey( 'X-WP-Nonce', $headers );
	}

	/**
	 * Refreshed nonce should be present in header when a valid nonce is
	 * passed for logged in/anonymous user and not present when nonce is not
	 * passed.
	 *
	 * @ticket 35662
	 *
	 * @dataProvider data_rest_send_refreshed_nonce
	 *
	 * @param bool $has_logged_in_user Will there be a logged in user for this test.
	 * @param bool $has_nonce          Are we passing the nonce.
	 */
	public function test_rest_send_refreshed_nonce( $has_logged_in_user, $has_nonce ) {
		if ( true === $has_logged_in_user ) {
			$this->helper_setup_user_for_rest_send_refreshed_nonce_tests();
		}

		if ( $has_nonce ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'wp_rest' );
		}

		$headers = $this->helper_make_request_and_return_headers_for_rest_send_refreshed_nonce_tests();

		if ( $has_nonce ) {
			$this->assertArrayHasKey( 'X-WP-Nonce', $headers );
		} else {
			$this->assertArrayNotHasKey( 'X-WP-Nonce', $headers );
		}
	}

	/**
	 * Make sure that a sanitization that transforms the argument type will not
	 * cause the validation to fail.
	 *
	 * @ticket 37192
	 */
	public function test_rest_validate_before_sanitization() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => '__return_null',
				'permission_callback' => '__return_true',
				'args'                => array(
					'someinteger' => array(
						'validate_callback' => array( $this, '_validate_as_integer_123' ),
						'sanitize_callback' => 'absint',
					),
					'somestring'  => array(
						'validate_callback' => array( $this, '_validate_as_string_foo' ),
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/test' );
		$request->set_query_params(
			array(
				'someinteger' => 123,
				'somestring'  => 'foo',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @ticket 43691
	 */
	public function test_does_not_echo_body_for_null_responses() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => static function () {
					return new WP_REST_Response();
				},
				'permission_callback' => '__return_true',
			)
		);

		$result = rest_get_server()->serve_request( '/test-ns/test' );

		$this->assertNull( $result );
		$this->assertSame( '', rest_get_server()->sent_body );
	}

	/**
	 * @ticket 43691
	 */
	public function test_does_not_echo_body_for_responses_with_204_status() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => static function () {
					return new WP_REST_Response( 'data', 204 );
				},
				'permission_callback' => '__return_true',
			)
		);

		$result = rest_get_server()->serve_request( '/test-ns/test' );

		$this->assertNull( $result );
		$this->assertSame( '', rest_get_server()->sent_body );
	}

	/**
	 * @ticket 47077
	 */
	public function test_http_authorization_header_substitution() {
		$headers        = array( 'HTTP_AUTHORIZATION' => 'foo' );
		$parsed_headers = rest_get_server()->get_headers( $headers );

		$this->assertSame(
			array( 'AUTHORIZATION' => 'foo' ),
			$parsed_headers
		);
	}

	/**
	 * @ticket 47077
	 */
	public function test_redirect_http_authorization_header_substitution() {
		$headers        = array( 'REDIRECT_HTTP_AUTHORIZATION' => 'foo' );
		$parsed_headers = rest_get_server()->get_headers( $headers );

		$this->assertSame(
			array( 'AUTHORIZATION' => 'foo' ),
			$parsed_headers
		);
	}

	/**
	 * @ticket 47077
	 */
	public function test_redirect_http_authorization_with_http_authorization_header_substitution() {
		$headers        = array(
			'HTTP_AUTHORIZATION'          => 'foo',
			'REDIRECT_HTTP_AUTHORIZATION' => 'bar',
		);
		$parsed_headers = rest_get_server()->get_headers( $headers );

		$this->assertSame(
			array( 'AUTHORIZATION' => 'foo' ),
			$parsed_headers
		);
	}

	/**
	 * @ticket 47077
	 */
	public function test_redirect_http_authorization_with_empty_http_authorization_header_substitution() {
		$headers        = array(
			'HTTP_AUTHORIZATION'          => '',
			'REDIRECT_HTTP_AUTHORIZATION' => 'bar',
		);
		$parsed_headers = rest_get_server()->get_headers( $headers );

		$this->assertSame(
			array( 'AUTHORIZATION' => 'bar' ),
			$parsed_headers
		);
	}

	/**
	 * @ticket 48530
	 */
	public function test_get_routes_respects_namespace_parameter() {
		$routes = rest_get_server()->get_routes( 'oembed/1.0' );

		foreach ( $routes as $route => $handlers ) {
			$this->assertStringStartsWith( '/oembed/1.0', $route );
		}
	}

	/**
	 * @ticket 48530
	 */
	public function test_get_routes_no_namespace_overriding() {
		register_rest_route(
			'test-ns',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => static function() {
					return new WP_REST_Response( 'data', 204 );
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'methods'             => array( 'GET' ),
				'callback'            => static function() {
					return new WP_REST_Response( 'data', 204 );
				},
				'permission_callback' => '__return_true',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/test-ns/v1/test' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 204, $response->get_status(), '/test-ns/v1/test' );
	}

	/**
	 * @ticket 50244
	 */
	public function test_no_route() {
		$mock_hook = new MockAction();
		add_filter( 'rest_request_after_callbacks', array( $mock_hook, 'filter' ) );

		$response = rest_do_request( '/test-ns/v1/test' );
		$this->assertErrorResponse( 'rest_no_route', $response, 404 );

		// Verify that the no route error was not filtered.
		$this->assertCount( 0, $mock_hook->get_events() );
	}

	/**
	 * @ticket 50244
	 */
	public function test_invalid_handler() {
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'callback'            => 'invalid_callback',
				'permission_callback' => '__return_true',
			)
		);

		$mock_hook = new MockAction();
		add_filter( 'rest_request_after_callbacks', array( $mock_hook, 'filter' ) );

		$response = rest_do_request( '/test-ns/v1/test' );
		$this->assertErrorResponse( 'rest_invalid_handler', $response, 500 );

		// Verify that the invalid handler error was filtered.
		$events = $mock_hook->get_events();
		$this->assertCount( 1, $events );
		$this->assertWPError( $events[0]['args'][0] );
		$this->assertSame( 'rest_invalid_handler', $events[0]['args'][0]->get_error_code() );
	}

	/**
	 * @ticket 50244
	 */
	public function test_callbacks_are_not_executed_if_request_validation_fails() {
		$callback = $this->createPartialMock( 'Mock_Invokable', array( '__invoke' ) );
		$callback->expects( self::never() )->method( '__invoke' );
		$permission_callback = $this->createPartialMock( 'Mock_Invokable', array( '__invoke' ) );
		$permission_callback->expects( self::never() )->method( '__invoke' );

		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'callback'            => $callback,
				'permission_callback' => $permission_callback,
				'args'                => array(
					'test' => array(
						'validate_callback' => '__return_false',
					),
				),
			)
		);

		$request = new WP_REST_Request( 'GET', '/test-ns/v1/test' );
		$request->set_query_params( array( 'test' => 'world' ) );
		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @ticket 50244
	 */
	public function test_filters_are_executed_if_request_validation_fails() {
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'callback'            => '__return_empty_array',
				'permission_callback' => '__return_true',
				'args'                => array(
					'test' => array(
						'validate_callback' => '__return_false',
					),
				),
			)
		);

		$mock_hook = new MockAction();
		add_filter( 'rest_request_after_callbacks', array( $mock_hook, 'filter' ) );

		$request = new WP_REST_Request( 'GET', '/test-ns/v1/test' );
		$request->set_query_params( array( 'test' => 'world' ) );
		$response = rest_do_request( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );

		// Verify that the invalid param error was filtered.
		$events = $mock_hook->get_events();
		$this->assertCount( 1, $events );
		$this->assertWPError( $events[0]['args'][0] );
		$this->assertSame( 'rest_invalid_param', $events[0]['args'][0]->get_error_code() );
	}

	/**
	 * @ticket       50244
	 * @dataProvider data_batch_v1_optin
	 */
	public function test_batch_v1_optin( $allow_batch, $allowed ) {
		$args = array(
			'methods'             => 'POST',
			'callback'            => static function () {
				return new WP_REST_Response( 'data' );
			},
			'permission_callback' => '__return_true',
		);

		if ( null !== $allow_batch ) {
			$args['allow_batch'] = $allow_batch;
		}

		register_rest_route(
			'test-ns/v1',
			'/test',
			$args
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'requests' => array(
					array(
						'path' => '/test-ns/v1/test',
					),
				),
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 207, $response->get_status() );

		if ( $allowed ) {
			$this->assertSame( 'data', $response->get_data()['responses'][0]['body'] );
		} else {
			$this->assertSame( 'rest_batch_not_allowed', $response->get_data()['responses'][0]['body']['code'] );
		}
	}

	public function data_batch_v1_optin() {
		return array(
			'missing'             => array( null, false ),
			'invalid type'        => array( true, false ),
			'invalid type string' => array( 'v1', false ),
			'wrong version'       => array( array( 'version1' => true ), false ),
			'false version'       => array( array( 'v1' => false ), false ),
			'valid'               => array( array( 'v1' => true ), true ),
		);
	}

	/**
	 * @ticket 50244
	 */
	public function test_batch_v1_pre_validation() {
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $request ) {
					$project = $request['project'];
					update_option( 'test_project', $project );

					return new WP_REST_Response( $project );
				},
				'permission_callback' => '__return_true',
				'allow_batch'         => array( 'v1' => true ),
				'args'                => array(
					'project' => array(
						'type' => 'string',
						'enum' => array( 'gutenberg', 'WordPress' ),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'validation' => 'require-all-validate',
				'requests'   => array(
					array(
						'path' => '/test-ns/v1/test',
						'body' => array(
							'project' => 'gutenberg',
						),
					),
					array(
						'path' => '/test-ns/v1/test',
						'body' => array(
							'project' => 'buddypress',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 207, $response->get_status() );
		$this->assertArrayHasKey( 'failed', $data );
		$this->assertSame( 'validation', $data['failed'] );
		$this->assertCount( 2, $data['responses'] );
		$this->assertNull( $data['responses'][0] );
		$this->assertSame( 400, $data['responses'][1]['status'] );
		$this->assertFalse( get_option( 'test_project' ) );
	}

	/**
	 * @ticket 50244
	 */
	public function test_batch_v1_pre_validation_all_successful() {
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $request ) {
					return new WP_REST_Response( $request['project'] );
				},
				'permission_callback' => '__return_true',
				'allow_batch'         => array( 'v1' => true ),
				'args'                => array(
					'project' => array(
						'type' => 'string',
						'enum' => array( 'gutenberg', 'WordPress' ),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'validation' => 'require-all-validate',
				'requests'   => array(
					array(
						'path' => '/test-ns/v1/test',
						'body' => array(
							'project' => 'gutenberg',
						),
					),
					array(
						'path' => '/test-ns/v1/test',
						'body' => array(
							'project' => 'WordPress',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 207, $response->get_status() );
		$this->assertArrayNotHasKey( 'failed', $data );
		$this->assertCount( 2, $data['responses'] );
		$this->assertSame( 'gutenberg', $data['responses'][0]['body'] );
		$this->assertSame( 'WordPress', $data['responses'][1]['body'] );
	}

	/**
	 * @ticket 50244
	 */
	public function test_batch_v1() {
		register_rest_route(
			'test-ns/v1',
			'/test/(?P<id>[\d+])',
			array(
				'methods'             => array( 'POST', 'DELETE' ),
				'callback'            => function ( WP_REST_Request $request ) {
					$this->assertSame( 'DELETE', $request->get_method() );
					$this->assertSame( '/test-ns/v1/test/5', $request->get_route() );
					$this->assertSame( array( 'id' => '5' ), $request->get_url_params() );
					$this->assertSame( array( 'query' => 'param' ), $request->get_query_params() );
					$this->assertSame( array( 'project' => 'gutenberg' ), $request->get_body_params() );
					$this->assertSame( array( 'my_header' => array( 'my-value' ) ), $request->get_headers() );

					return new WP_REST_Response( 'test' );
				},
				'permission_callback' => '__return_true',
				'allow_batch'         => array( 'v1' => true ),
			)
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'requests' => array(
					array(
						'method'  => 'DELETE',
						'path'    => '/test-ns/v1/test/5?query=param',
						'headers' => array(
							'My-Header' => 'my-value',
						),
						'body'    => array(
							'project' => 'gutenberg',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 207, $response->get_status() );
		$this->assertSame( 'test', $response->get_data()['responses'][0]['body'] );
	}

	/**
	 * @ticket 50244
	 */
	public function test_batch_v1_partial_error() {
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $request ) {
					$project = $request['project'];
					update_option( 'test_project', $project );

					return new WP_REST_Response( $project );
				},
				'permission_callback' => '__return_true',
				'allow_batch'         => array( 'v1' => true ),
				'args'                => array(
					'project' => array(
						'type' => 'string',
						'enum' => array( 'gutenberg', 'WordPress' ),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'requests' => array(
					array(
						'path' => '/test-ns/v1/test',
						'body' => array(
							'project' => 'gutenberg',
						),
					),
					array(
						'path' => '/test-ns/v1/test',
						'body' => array(
							'project' => 'buddypress',
						),
					),
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 207, $response->get_status() );
		$this->assertArrayNotHasKey( 'failed', $data );
		$this->assertCount( 2, $data['responses'] );
		$this->assertSame( 'gutenberg', $data['responses'][0]['body'] );
		$this->assertSame( 400, $data['responses'][1]['status'] );
		$this->assertSame( 'gutenberg', get_option( 'test_project' ) );
	}


	/**
	 * @ticket 50244
	 */
	public function test_batch_v1_max_requests() {
		add_filter(
			'rest_get_max_batch_size',
			static function() {
				return 5;
			}
		);

		$GLOBALS['wp_rest_server'] = null;
		add_filter( 'wp_rest_server_class', array( $this, 'filter_wp_rest_server_class' ) );
		$GLOBALS['wp_rest_server'] = rest_get_server();

		register_rest_route(
			'test-ns/v1',
			'/test/(?P<id>[\d+])',
			array(
				'methods'             => array( 'POST', 'DELETE' ),
				'callback'            => static function ( WP_REST_Request $request ) {
					return new WP_REST_Response( 'test' );
				},
				'permission_callback' => '__return_true',
				'allow_batch'         => array( 'v1' => true ),
			)
		);

		$request = new WP_REST_Request( 'POST', '/batch/v1' );
		$request->set_body_params(
			array(
				'requests' => array_fill( 0, 6, array( 'path' => '/test-ns/v1/test/5' ) ),
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * @ticket 51020
	 */
	public function test_get_data_for_route_includes_permitted_schema_keywords() {
		$keywords = array(
			'title'                => 'Hi',
			'description'          => 'World',
			'type'                 => 'string',
			'default'              => 0,
			'format'               => 'uri',
			'enum'                 => array( 'https://example.org' ),
			'items'                => array( 'type' => 'string' ),
			'properties'           => array( 'a' => array( 'type' => 'string' ) ),
			'additionalProperties' => false,
			'patternProperties'    => array( '\d' => array( 'type' => 'string' ) ),
			'minProperties'        => 1,
			'maxProperties'        => 5,
			'minimum'              => 1,
			'maximum'              => 5,
			'exclusiveMinimum'     => true,
			'exclusiveMaximum'     => false,
			'multipleOf'           => 2,
			'minLength'            => 1,
			'maxLength'            => 5,
			'pattern'              => '\d',
			'minItems'             => 1,
			'maxItems'             => 5,
			'uniqueItems'          => true,
			'anyOf'                => array(
				array( 'type' => 'string' ),
				array( 'type' => 'integer' ),
			),
			'oneOf'                => array(
				array( 'type' => 'string' ),
				array( 'type' => 'integer' ),
			),
		);

		$param            = $keywords;
		$param['invalid'] = true;

		$expected             = $keywords;
		$expected['required'] = false;

		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				'methods'             => 'POST',
				'callback'            => static function () {
					return new WP_REST_Response( 'test' );
				},
				'permission_callback' => '__return_true',
				'args'                => array(
					'param' => $param,
				),
			)
		);

		$response = rest_do_request( new WP_REST_Request( 'OPTIONS', '/test-ns/v1/test' ) );
		$args     = $response->get_data()['endpoints'][0]['args'];

		$this->assertSameSetsWithIndex( $expected, $args['param'] );
	}

	/**
	 * @ticket 53056
	 */
	public function test_json_encode_error_results_in_500_status_code() {
		register_rest_route(
			'test-ns/v1',
			'/test',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => function() {
						return new \WP_REST_Response( INF );
					},
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			)
		);
		rest_get_server()->serve_request( '/test-ns/v1/test' );
		$this->assertSame( 500, rest_get_server()->status );
	}

	public function _validate_as_integer_123( $value, $request, $key ) {
		if ( ! is_int( $value ) ) {
			return new WP_Error( 'some-error', 'This is not valid!' );
		}

		return true;
	}

	public function _validate_as_string_foo( $value, $request, $key ) {
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'some-error', 'This is not valid!' );
		}

		return true;
	}

	/**
	 * @return array {
	 *     @type array {
	 *         @type bool $has_logged_in_user Are we registering a user for the test.
	 *         @type bool $has_nonce          Is the nonce passed.
	 *     }
	 * }
	 */
	public function data_rest_send_refreshed_nonce() {
		return array(
			array( true, true ),
			array( true, false ),
			array( false, true ),
			array( false, false ),
		);
	}

	/**
	 * Helper to setup a users and auth cookie global for the
	 * rest_send_refreshed_nonce related tests.
	 */
	protected function helper_setup_user_for_rest_send_refreshed_nonce_tests() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );

		global $wp_rest_auth_cookie;

		$wp_rest_auth_cookie = true;
	}

	/**
	 * Helper to make the request and get the headers for the
	 * rest_send_refreshed_nonce related tests.
	 *
	 * @return array
	 */
	protected function helper_make_request_and_return_headers_for_rest_send_refreshed_nonce_tests() {
		$request = new WP_REST_Request( 'GET', '/', array() );
		$result  = rest_get_server()->serve_request( '/' );

		return rest_get_server()->sent_headers;
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_envelope_params() {
		return array(
			array( '1' ),
			array( 'true' ),
			array( false ),
			array( 'alternate' ),
			array( array( 'alternate' ) ),
		);
	}
}
