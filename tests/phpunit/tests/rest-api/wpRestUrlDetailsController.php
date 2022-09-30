<?php
/**
 * WP_REST_URL_Details_Controller tests.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.9.0
 */

/**
 * Tests for WP_REST_URL_Details_Controller.
 *
 * @since 5.9.0
 *
 * @covers WP_REST_URL_Details_Controller
 *
 * @group url-details
 * @group restapi
 */
class Tests_REST_WpRestUrlDetailsController extends WP_Test_REST_Controller_Testcase {

	/**
	 * Admin user ID.
	 *
	 * @since 5.9.0
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @since 5.5.0
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * The REST API route for the block renderer.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	const REQUEST_ROUTE = '/wp-block-editor/v1/url-details';

	/**
	 * URL placeholder.
	 *
	 * @since 5.9.0
	 *
	 * @var string
	 */
	const URL_PLACEHOLDER = 'https://placeholder-site.com';

	/**
	 * Array of request args.
	 *
	 * @var array
	 */
	protected $request_args = array();

	/**
	 * Set up class test fixtures.
	 *
	 * @since 5.9.0
	 *
	 * @param WP_UnitTest_Factory $factory WordPress unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id      = $factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		self::$subscriber_id = $factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_id );
		self::delete_user( self::$subscriber_id );
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ), 10, 3 );

		// Disables usage of cache during major of tests.
		add_filter( 'pre_site_transient_' . $this->get_transient_name(), '__return_null' );
	}

	public function tear_down() {
		$this->request_args = array();
		parent::tear_down();
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::register_routes
	 *
	 * @ticket 54358
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( static::REQUEST_ROUTE, $routes );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_get_items() {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		/*
		 * Note the data in the subset comes from the fixture HTML returned by
		 * the filter `pre_http_request` (see this class's `set_up` method).
		 */
		$this->assertSame(
			array(
				'title'       => 'Example Website — - with encoded content.',
				'icon'        => 'https://placeholder-site.com/favicon.ico?querystringaddedfortesting',
				'description' => 'Example description text here. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore.',
				'image'       => 'https://placeholder-site.com/images/home/screen-themes.png?3',
			),
			$data
		);
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::permissions_check
	 *
	 * @ticket 54358
	 */
	public function test_get_items_fails_for_unauthenticated_user() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( WP_Http::UNAUTHORIZED, $response->get_status(), 'Response status is not ' . WP_Http::UNAUTHORIZED );

		$this->assertSame( 'rest_cannot_view_url_details', $data['code'], 'Response "code" is not "rest_cannot_view_url_details"' );

		$expected = 'you are not allowed to process remote urls';
		$this->assertStringContainsString( $expected, strtolower( $data['message'] ), 'Response "message" does not contain  "' . $expected . '"' );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::permissions_check
	 *
	 * @ticket 54358
	 */
	public function test_get_items_fails_for_user_with_insufficient_permissions() {
		wp_set_current_user( self::$subscriber_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( WP_Http::FORBIDDEN, $response->get_status(), 'Response status is not ' . WP_Http::FORBIDDEN );

		$this->assertSame( 'rest_cannot_view_url_details', $data['code'], 'Response "code" is not "rest_cannot_view_url_details"' );

		$expected = 'you are not allowed to process remote urls';
		$this->assertStringContainsString( $expected, strtolower( $data['message'] ), 'Response "message" does not contain "' . $expected . '"' );
	}

	/**
	 * @dataProvider data_get_items_fails_for_invalid_url
	 *
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 *
	 * @param mixed $invalid_url Given invalid URL to test.
	 */
	public function test_get_items_fails_for_invalid_url( $invalid_url ) {
		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => $invalid_url,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( WP_Http::BAD_REQUEST, $response->get_status(), 'Response status is not ' . WP_Http::BAD_REQUEST );

		$this->assertSame( 'rest_invalid_param', $data['code'], 'Response "code" is not "rest_invalid_param"' );

		$expected = 'invalid parameter(s): url';
		$this->assertStringContainsString( $expected, strtolower( $data['message'] ), 'Response "message" does not contain "' . $expected . '"' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_items_fails_for_invalid_url() {
		return array(
			'empty string'   => array( '' ),
			'numeric'        => array( 1234456 ),
			'invalid scheme' => array( 'invalid.proto://wordpress.org' ),
		);
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_get_items_fails_for_url_which_returns_a_non_200_status_code() {
		// Force HTTP request to remote site to fail.
		remove_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ), 10 );
		add_filter( 'pre_http_request', array( $this, 'mock_failed_request_to_remote_url' ), 10, 3 );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER, // note: `pre_http_request` causes request to 404.
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 404, $response->get_status(), 'Response status is not 404' );

		$this->assertSame( 'no_response', $data['code'], 'Response "code" is not "no_response"' );

		$this->assertStringContainsString( 'not found', strtolower( $data['message'] ), 'Response "message" does not contain "not found"' );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_get_items_fails_for_url_which_returns_empty_body_for_success() {
		// Force HTTP request to remote site to return an empty body in response.
		remove_filter( 'pre_http_request', array( $this, 'mock_success_request_to_remote_url' ) );
		add_filter( 'pre_http_request', array( $this, 'mock_request_to_remote_url_with_empty_body_response' ), 10, 3 );

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER, // note: `pre_http_request` causes request to 404.
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 404, $response->get_status(), 'Response status is not 404' );

		$this->assertSame( 'no_content', $data['code'], 'Response "code" is not "no_content"' );

		$expected = strtolower( 'Unable to retrieve body from response at this URL' );
		$this->assertStringContainsString( $expected, strtolower( $data['message'] ), 'Response "message" does not contain "' . $expected . '"' );

	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_can_filter_http_request_args_via_filter() {
		wp_set_current_user( self::$admin_id );

		add_filter(
			'rest_url_details_http_request_args',
			static function( $args, $url ) {
				return array_merge(
					$args,
					array(
						'timeout' => 27, // modify default timeout.
						'body'    => $url, // add new and allow to assert on $url arg passed.
					)
				);
			},
			10,
			2
		);

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);

		rest_get_server()->dispatch( $request );

		// Check the args were filtered as expected.
		$this->assertArrayHasKey( 'timeout', $this->request_args, 'Request args do not contain a "timeout" key' );
		$this->assertArrayHasKey( 'limit_response_size', $this->request_args, 'Request args do not contain a "limit_response_size" key' );
		$this->assertArrayHasKey( 'body', $this->request_args, 'Request args do not contain a "body" key' );
		$this->assertSame( 27, $this->request_args['timeout'], 'Request args "timeout" is not 27' );
		$this->assertSame( 153600, $this->request_args['limit_response_size'], 'Request args "limit_response_size" is not 153600' );
		$this->assertSame( static::URL_PLACEHOLDER, $this->request_args['body'], 'Request args "body" is not "' . static::URL_PLACEHOLDER . '"' );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_will_return_from_cache_if_populated() {
		$transient_name = $this->get_transient_name();
		remove_filter( "pre_site_transient_{$transient_name}", '__return_null' );

		// Force cache to return a known value as the remote URL http response body.
		add_filter(
			"pre_site_transient_{$transient_name}",
			static function() {
				return '<html><head><title>This value from cache.</title></head><body></body></html>';
			}
		);

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Data should be that from cache not from mocked network response.
		$this->assertStringContainsString( 'This value from cache', $data['title'] );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_allows_filtering_data_retrieved_for_a_given_url() {
		add_filter(
			'rest_prepare_url_details',
			static function( $response ) {

				$data = $response->get_data();

				$response->set_data(
					array_merge(
						$data,
						array(
							'og_title' => 'This was manually added to the data via filter',
						)
					)
				);

				return $response;

			}
		);

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		/*
		 * Instead of the default data retrieved we expect to see the modified
		 * data we provided via the filter.
		 */
		$expected = 'Example Website — - with encoded content.';
		$this->assertSame( $expected, $data['title'], 'Response "title" is not "' . $expected . '"' );
		$expected = 'This was manually added to the data via filter';
		$this->assertSame( $expected, $data['og_title'], 'Response "og_title" is not "' . $expected . '"' );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::parse_url_details
	 *
	 * @ticket 54358
	 */
	public function test_allows_filtering_response() {
		/*
		 * Filter the response to known set of values changing only
		 * based on whether the response came from the cache or not.
		 */
		add_filter(
			'rest_prepare_url_details',
			static function( $response, $url ) {
				return new WP_REST_Response(
					array(
						'status'        => 418,
						'response'      => "Response for URL $url altered via rest_prepare_url_details filter",
						'body_response' => array(),
					)
				);
			},
			10,
			3
		);

		wp_set_current_user( self::$admin_id );

		$request = new WP_REST_Request( 'GET', static::REQUEST_ROUTE );
		$request->set_query_params(
			array(
				'url' => static::URL_PLACEHOLDER,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		$this->assertSame( 418, $data['status'], 'Response "status" is not 418' );

		$expected = 'Response for URL https://placeholder-site.com altered via rest_prepare_url_details filter';
		$this->assertSame( $expected, $data['response'], 'Response "response" is not "' . $expected . '"' );
	}

	/**
	 * @covers WP_REST_URL_Details_Controller::get_item_schema
	 *
	 * @ticket 54358
	 */
	public function test_get_item_schema() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'OPTIONS', static::REQUEST_ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$endpoint = $data['endpoints'][0];

		$this->assertArrayHasKey( 'url', $endpoint['args'], 'Endpoint "args" does not contain a "url" key' );
		$this->assertSame(
			array(
				'description' => 'The URL to process.',
				'type'        => 'string',
				'format'      => 'uri',
				'required'    => true,
			),
			$endpoint['args']['url'],
			'Response endpoint "[args][url]" does not contain expected schema'
		);
	}

	/**
	 * @dataProvider data_get_title
	 *
	 * @covers WP_REST_URL_Details_Controller::get_title
	 *
	 * @ticket 54358
	 *
	 * @param string $html     Given HTML string.
	 * @param string $expected Expected found title.
	 */
	public function test_get_title( $html, $expected ) {
		$controller = new WP_REST_URL_Details_Controller();
		$method     = $this->get_reflective_method( 'get_title' );

		$actual = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html )
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_title() {
		return array(

			// Happy path for default.
			'default'                        => array(
				'<title>Testing &lt;title&gt;</title>',
				'Testing',
			),
			'with attributes'                => array(
				'<title data-test-title-attr-one="test" data-test-title-attr-two="test2">Testing &lt;title&gt;</title>',
				'Testing',
			),
			'with text whitespace'           => array(
				'<title data-test-title-attr-one="test" data-test-title-attr-two="test2">   Testing &lt;title&gt;	</title>',
				'Testing',
			),
			'with whitespace in opening tag' => array(
				'<title >Testing &lt;title&gt;: with whitespace in opening tag</title>',
				'Testing : with whitespace in opening tag',
			),
			'when whitepace in closing tag'  => array(
				'<title>Testing &lt;title&gt;: with whitespace in closing tag</ title>',
				'Testing : with whitespace in closing tag',
			),
			'with other elements'            => array(
				'<meta name="viewport" content="width=device-width">
				<title>Testing &lt;title&gt;</title>
				<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" />',
				'Testing',
			),
			'multiline'                      => array(
				'<title>
					Testing &lt;title&gt;
				</title>',
				'Testing',
			),

			// Unhappy paths.
			'when opening tag is malformed'  => array(
				'< title>Testing &lt;title&gt;: when opening tag is invalid</title>',
				'',
			),
		);
	}

	/**
	 * @dataProvider data_get_icon
	 *
	 * @covers WP_REST_URL_Details_Controller::get_icon
	 *
	 * @ticket 54358
	 *
	 * @param string $html       Given HTML string.
	 * @param string $expected   Expected found icon.
	 * @param string $target_url Optional. Target URL. Default 'https://wordpress.org'.
	 */
	public function test_get_icon( $html, $expected, $target_url = 'https://wordpress.org' ) {
		$controller = new WP_REST_URL_Details_Controller();
		$method     = $this->get_reflective_method( 'get_icon' );

		$actual = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html ),
			$target_url
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_icon() {
		return array(

			// Happy path for default.
			'default'                               => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'default with no closing whitespace'    => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico"/>',
				'https://wordpress.org/favicon.ico',
			),
			'default without self-closing'          => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico">',
				'https://wordpress.org/favicon.ico',
			),
			'default with href first'               => array(
				'<link href="https://wordpress.org/favicon.ico" rel="shortcut icon" />',
				'https://wordpress.org/favicon.ico',
			),
			'default with type last'                => array(
				'<link href="https://wordpress.org/favicon.png" rel="icon" type="image/png" />',
				'https://wordpress.org/favicon.png',
			),
			'default with type first'               => array(
				'<link type="image/png" href="https://wordpress.org/favicon.png" rel="icon" />',
				'https://wordpress.org/favicon.png',
			),
			'default with single quotes'            => array(
				'<link type="image/png" href=\'https://wordpress.org/favicon.png\' rel=\'icon\' />',
				'https://wordpress.org/favicon.png',
			),

			// Happy paths.
			'with query string'                     => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico?somequerystring=foo&another=bar" />',
				'https://wordpress.org/favicon.ico?somequerystring=foo&another=bar',
			),
			'with another link'                     => array(
				'<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" /><link rel="canonical" href="https://example.com">',
				'https://wordpress.org/favicon.ico',
			),
			'with multiple links'                   => array(
				'<link rel="manifest" href="/manifest.56b1cedc.json">
				<link rel="shortcut icon" href="https://wordpress.org/favicon.ico" />
				<link rel="canonical" href="https://example.com">',
				'https://wordpress.org/favicon.ico',
			),
			'relative url'                          => array(
				'<link rel="shortcut icon" href="/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'relative url no slash'                 => array(
				'<link rel="shortcut icon" href="favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'relative url with path'                => array(
				'<link rel="shortcut icon" href="favicon.ico" />',
				'https://wordpress.org/favicon.ico',
				'https://wordpress.org/my/path/here/',
			),
			'rel reverse order'                     => array(
				'<link rel="icon shortcut" href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'rel icon only'                         => array(
				'<link rel="icon" href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'rel icon only with whitespace'         => array(
				'<link rel=" icon " href="https://wordpress.org/favicon.ico" />',
				'https://wordpress.org/favicon.ico',
			),
			'multiline attributes'                  => array(
				'<link
					rel="icon"
					href="https://wordpress.org/favicon.ico"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'multiline attributes in reverse order' => array(
				'<link
					rel="icon"
					href="https://wordpress.org/favicon.ico"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'multiline attributes with type'        => array(
				'<link
					rel="icon"
					href="https://wordpress.org/favicon.ico"
					type="image/x-icon"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'multiline with type first'             => array(
				'<link
					type="image/x-icon"
					rel="icon"
					href="https://wordpress.org/favicon.ico"
				/>',
				'https://wordpress.org/favicon.ico',
			),
			'with data URL x-icon type'             => array(
				'<link rel="icon" href="data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=" type="image/x-icon" />',
				'data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQEAYAAABPYyMiAAAABmJLR0T///////8JWPfcAAAACXBIWXMAAABIAAAASABGyWs+AAAAF0lEQVRIx2NgGAWjYBSMglEwCkbBSAcACBAAAeaR9cIAAAAASUVORK5CYII=',
			),
			'with data URL png type'                => array(
				'<link href="data:image/png;base64,iVBORw0KGgo=" rel="icon" type="image/png" />',
				'data:image/png;base64,iVBORw0KGgo=',
			),

			// Unhappy paths.
			'empty rel'                             => array(
				'<link rel="" href="https://wordpress.org/favicon.ico" />',
				'',
			),
			'empty href'                            => array(
				'<link rel="icon" href="" />',
				'',
			),
			'no rel'                                => array(
				'<link href="https://wordpress.org/favicon.ico" />',
				'',
			),
			'link to external stylesheet'           => array(
				'<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'',
				'https://example.com',
			),
			'multiline with no href'                => array(
				'<link
					rel="icon"
					href=""
				/>',
				'',
			),
			'multiline with no rel'                 => array(
				'<link
					rel=""
					href="https://wordpress.org/favicon.ico"
				/>',
				'',
			),
		);
	}

	/**
	 * @dataProvider data_get_description
	 *
	 * @covers WP_REST_URL_Details_Controller::get_description
	 *
	 * @ticket 54358
	 *
	 * @param string $html     Given HTML string.
	 * @param string $expected Expected found icon.
	 */
	public function test_get_description( $html, $expected ) {
		$controller = new WP_REST_URL_Details_Controller();

		// Parse the meta elements from the given HTML.
		$method        = $this->get_reflective_method( 'get_meta_with_content_elements' );
		$meta_elements = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html )
		);

		$method = $this->get_reflective_method( 'get_description' );
		$actual = $method->invoke( $controller, $meta_elements );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_description() {
		return array(

			// Happy paths.
			'default'                                    => array(
				'<meta name="description" content="This is a description.">',
				'This is a description.',
			),
			'with whitespace'                            => array(
				'<meta  name=" description "   content=" This is a description.  "   >',
				'This is a description.',
			),
			'with self-closing'                          => array(
				'<meta name="description" content="This is a description."/>',
				'This is a description.',
			),
			'with self-closing and whitespace'           => array(
				'<meta  name=" description "   content=" This is a description.  "   />',
				'This is a description.',
			),
			'with content first'                         => array(
				'<meta content="Content is first" name="description">',
				'Content is first',
			),
			'with single quotes'                         => array(
				'<meta name=\'description\' content=\'with single quotes\'>',
				'with single quotes',
			),
			'with another element'                       => array(
				'<meta name="description" content="This is a description."><meta name="viewport" content="width=device-width, initial-scale=1">',
				'This is a description.',
			),
			'with multiple elements'                     => array(
				'<meta property="og:image" content="https://wordpress.org/images/myimage.jpg" />
				<link rel="stylesheet" href="https://example.com/assets/style.css" />
				<meta name="description" content="This is a description.">
				<meta name="viewport" content="width=device-width, initial-scale=1">',
				'This is a description.',
			),
			'with other attributes'                      => array(
				'<meta first="first" name="description" third="third" content="description with other attributes" fifth="fifth">',
				'description with other attributes',
			),
			'with open graph'                            => array(
				'<meta name="og:description" content="This is a OG description." />
				<meta name="description" content="This is a description.">',
				'This is a OG description.',
			),

			// Happy paths with multiline attributes.
			'with multiline attributes'                  => array(
				'<meta
					name="description"
					content="with multiline attributes"
				>',
				'with multiline attributes',
			),
			'with multiline attributes in reverse order' => array(
				'<meta
					content="with multiline attributes in reverse order"
					name="description"
				>',
				'with multiline attributes in reverse order',
			),
			'with multiline attributes and another element' => array(
				'<meta
					name="description"
					content="with multiline attributes"
				>
				<meta name="viewport" content="width=device-width, initial-scale=1">',
				'with multiline attributes',
			),
			'with multiline and other attributes'        => array(
				'<meta
					first="first"
					name="description"
					third="third"
					content="description with multiline and other attributes"
					fifth="fifth"
				>',
				'description with multiline and other attributes',
			),

			// Happy paths with HTML tags or entities in the description.
			'with HTML tags'                             => array(
				'<meta name="description" content="<strong>Description</strong>: has <em>HTML</em> tags">',
				'Description: has HTML tags',
			),
			'with content first and HTML tags'           => array(
				'<meta content="<strong>Description</strong>: has <em>HTML</em> tags" name="description">',
				'Description: has HTML tags',
			),
			'with HTML tags and other attributes'        => array(
				'<meta first="first" name="description" third="third" content="<strong>Description</strong>: has <em>HTML</em> tags" fifth="fifth>',
				'Description: has HTML tags',
			),
			'with HTML entities'                         => array(
				'<meta name="description" content="The &lt;strong&gt;description&lt;/strong&gt; meta &amp; its attribute value"',
				'The description meta & its attribute value',
			),

			// Unhappy paths.
			'with empty content'                         => array(
				'<meta name="description" content="">',
				'',
			),
			'with empty name'                            => array(
				'<meta name="" content="name is empty">',
				'',
			),
			'without a name attribute'                   => array(
				'<meta content="without a name attribute">',
				'',
			),
			'without a content attribute'                => array(
				'<meta name="description">',
				'',
			),
		);
	}

	/**
	 * @dataProvider data_get_image
	 *
	 * @covers WP_REST_URL_Details_Controller::get_image
	 *
	 * @ticket 54358
	 *
	 * @param string $html       Given HTML string.
	 * @param string $expected   Expected found image.
	 * @param string $target_url Optional. Target URL. Default 'https://wordpress.org'.
	 */
	public function test_get_image( $html, $expected, $target_url = 'https://wordpress.org' ) {
		$controller = new WP_REST_URL_Details_Controller();

		// Parse the meta elements from the given HTML.
		$method        = $this->get_reflective_method( 'get_meta_with_content_elements' );
		$meta_elements = $method->invoke(
			$controller,
			$this->wrap_html_in_doc( $html )
		);

		$method = $this->get_reflective_method( 'get_image' );
		$actual = $method->invoke( $controller, $meta_elements, $target_url );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_image() {
		return array(

			// Happy paths.
			'default'                                      => array(
				'<meta property="og:image" content="https://wordpress.org/images/myimage.jpg">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with whitespace'                              => array(
				'<meta  property=" og:image "   content="  https://wordpress.org/images/myimage.jpg "  >',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with self-closing'                            => array(
				'<meta property="og:image" content="https://wordpress.org/images/myimage.jpg"/>',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with self-closing and whitespace'             => array(
				'<meta  property=" og:image "   content="  https://wordpress.org/images/myimage.jpg "  />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with single quotes'                           => array(
				"<meta property='og:image' content='https://wordpress.org/images/myimage.jpg'>",
				'https://wordpress.org/images/myimage.jpg',
			),
			'without quotes'                               => array(
				'<meta property=og:image content="https://wordpress.org/images/myimage.jpg">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with url modifier'                            => array(
				'<meta property="og:image:url" content="https://wordpress.org/images/url-modifier.jpg" />
				<meta property="og:image" content="https://wordpress.org/images/myimage.jpg">',
				'https://wordpress.org/images/url-modifier.jpg',
			),
			'with query string'                            => array(
				'<meta property="og:image" content="https://wordpress.org/images/withquerystring.jpg?foo=bar&bar=foo" />',
				'https://wordpress.org/images/withquerystring.jpg?foo=bar&bar=foo',
			),

			// Happy paths with changing attributes order or adding attributes.
			'with content first'                           => array(
				'<meta content="https://wordpress.org/images/myimage.jpg" property="og:image">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with other attributes'                        => array(
				'<meta first="first" property="og:image" third="third" content="https://wordpress.org/images/myimage.jpg" fifth="fifth">',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with other og meta'                           => array(
				'<meta property="og:image:height" content="720" />
				<meta property="og:image:alt" content="Ignore this please" />
				<meta property="og:image" content="https://wordpress.org/images/myimage.jpg" />
				<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'https://wordpress.org/images/myimage.jpg',
			),

			// Happy paths with relative url.
			'with relative url'                            => array(
				'<meta property="og:image" content="/images/myimage.jpg" />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with relative url without starting slash'     => array(
				'<meta property="og:image" content="images/myimage.jpg" />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with relative url and path'                   => array(
				'<meta property="og:image" content="images/myimage.jpg" />',
				'https://wordpress.org/images/myimage.jpg',
				'https://wordpress.org/my/path/here/',
			),

			// Happy paths with multiline attributes.
			'with multiline attributes'                    => array(
				'<meta
					property="og:image"
					content="https://wordpress.org/images/myimage.jpg"
				>',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with multiline attributes in reverse order'   => array(
				'<meta
					content="https://wordpress.org/images/myimage.jpg"
					property="og:image"
				>',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with multiline attributes and other elements' => array(
				'<meta
					property="og:image:height"
					content="720"
				/>
				<meta
					property="og:image:alt"
					content="Ignore this please"
				/>
				<meta
					property="og:image"
					content="https://wordpress.org/images/myimage.jpg"
				>
				<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'https://wordpress.org/images/myimage.jpg',
			),
			'with multiline and other attributes'          => array(
				'<meta
					first="first"
					property="og:image:url"
					third="third"
					content="https://wordpress.org/images/myimage.jpg"
					fifth="fifth"
				>',
				'https://wordpress.org/images/myimage.jpg',
			),

			// Happy paths with HTML tags in the content.
			'with other og meta'                           => array(
				'<meta property="og:image:height" content="720" />
				<meta property="og:image:alt" content="<em>ignore this please</em>" />
				<meta property="og:image" content="https://wordpress.org/images/myimage.jpg" />
				<link rel="stylesheet" href="https://example.com/assets/style.css" />',
				'https://wordpress.org/images/myimage.jpg',
			),

			// Unhappy paths.
			'with empty content'                           => array(
				'<meta property="og:image" content="">',
				'',
			),
			'without a property attribute'                 => array(
				'<meta content="https://wordpress.org/images/myimage.jpg">',
				'',
			),
			'without a content attribute empty property'   => array(
				'<meta property="og:image" href="https://wordpress.org/images/myimage.jpg">',
				'',
			),
		);
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
		// Controller does not use get_context_param().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_create_item() {
		// Controller does not implement create_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Controller does not implement update_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Controller does not implement delete_item().
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Controller does not implement prepare_item().
	}

	/**
	 * Mocks the HTTP response for the `wp_safe_remote_get()` which
	 * would otherwise make a call to a real website.
	 *
	 * @return array faux/mocked response.
	 */
	public function mock_success_request_to_remote_url( $response, $args ) {
		return $this->mock_request_to_remote_url( 'success', $args );
	}

	public function mock_failed_request_to_remote_url( $response, $args ) {
		return $this->mock_request_to_remote_url( 'failure', $args );
	}

	public function mock_request_to_remote_url_with_empty_body_response( $response, $args ) {
		return $this->mock_request_to_remote_url( 'empty_body', $args );
	}

	private function mock_request_to_remote_url( $result_type, $args ) {
		$this->request_args = $args;

		$types = array(
			'success',
			'failure',
			'empty_body',
		);

		// Default to success.
		if ( ! in_array( $result_type, $types, true ) ) {
			$result_type = $types[0];
		}

		// Both should return 200 for the HTTP response.
		$should_200 = 'success' === $result_type || 'empty_body' === $result_type;

		return array(
			'headers'     => array(),
			'cookies'     => array(),
			'filename'    => null,
			'response'    => array( 'code' => ( $should_200 ? 200 : 404 ) ),
			'status_code' => $should_200 ? 200 : 404,
			'success'     => $should_200 ? 1 : 0,
			'body'        => 'success' === $result_type ? $this->get_example_website() : '',
		);
	}

	private function get_example_website() {
		return '
			<!DOCTYPE html>
			<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
			<head>
			<meta charset="utf-8" />
			<title data-test-title-attr="test">Example Website &mdash; - with encoded content.</title>

			<link rel="shortcut icon" href="/favicon.ico?querystringaddedfortesting" type="image/x-icon" />

			<link rel="canonical" href="https://example.com">

			<meta name="description" content="Example description text here. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore." />

			<!-- Open Graph Tags -->
			<meta property="og:type" content="website" />
			<meta property="og:title" content="Example Website" />
			<meta property="og:url" content="https://example.com" />
			<meta property="og:site_name" content="Example Website" />
			<meta property="og:image:alt" content="Attempt to break image parsing" />
			<meta property="og:image" content="/images/home/screen-themes.png?3" />

			</head>
			<body>
				<h1>Example Website</h1>
			    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
			</body>
			</html>';
	}

	private function wrap_html_in_doc( $html, $with_body = false ) {
		$doc = '<!DOCTYPE html>
				<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
				<head>
				<meta charset="utf-8" />' . $html . "\n" . '</head>';

		if ( $with_body ) {
			$doc .= '
				<body>
					<h1>Example Website</h1>
					<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
				</body>
			</html>';
		}

		return $doc;
	}

	/**
	 * Gets the transient name.
	 *
	 * @return string
	 */
	private function get_transient_name() {
		return 'g_url_details_response_' . md5( static::URL_PLACEHOLDER );
	}

	/**
	 * Get reflective access to a private/protected method on
	 * the WP_REST_URL_Details_Controller class.
	 *
	 * @param string $method_name Method name for which to gain access.
	 * @return ReflectionMethod
	 * @throws ReflectionException Throws an exception if method does not exist.
	 */
	protected function get_reflective_method( $method_name ) {
		$class  = new ReflectionClass( WP_REST_URL_Details_Controller::class );
		$method = $class->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}
}
