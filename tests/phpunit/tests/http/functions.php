<?php

require_once DIR_TESTDATA . '/../includes/class-wp-http-unit-test-transport.php';

/**
 * @group http
 *
 * @ticket 63914
 */
class Tests_HTTP_Functions extends WP_UnitTestCase {

	/**
	 * Whether the current test is using the fake Requests transport.
	 *
	 * @var bool
	 */
	private $using_mock_transport = false;

	/**
	 * Captured Requests options from the most recent mocked request.
	 *
	 * @var array|null
	 */
	private $captured_requests_options = null;

	/**
	 * Tear down the fake transport hook when used.
	 */
	public function tear_down() {
		if ( $this->using_mock_transport ) {
			remove_action( 'requests-requests.before_request', array( $this, 'inject_mock_transport' ), 10 );
			$this->using_mock_transport      = false;
			$this->captured_requests_options = null;
		}

		parent::tear_down();
	}

	/**
	 * Enables the fake Requests transport for tests that must exercise
	 * WP_Http request handling without live network access.
	 *
	 * Removes the core external-HTTP blocker so the request proceeds past
	 * `pre_http_request` into cookie normalization, transport execution, and
	 * response conversion.
	 */
	private function use_mock_transport() {
		remove_filter( 'pre_http_request', array( $this, 'block_external_http_request' ), PHP_INT_MAX );
		add_action( 'requests-requests.before_request', array( $this, 'inject_mock_transport' ), 10, 5 );
		$this->using_mock_transport      = true;
		$this->captured_requests_options = null;
	}

	/**
	 * Injects the fake Requests transport into request options.
	 *
	 * Also snapshots the Requests options so tests can assert that WP_Http
	 * mapped streaming and size-limit args without reimplementing those
	 * transport behaviors in the fake transport.
	 *
	 * @param string       $url     Request URL.
	 * @param array        $headers Request headers.
	 * @param string|array $data    Request data.
	 * @param string       $type    HTTP method.
	 * @param array        $options Request options (passed by reference).
	 */
	public function inject_mock_transport( $url, $headers, $data, $type, &$options ) {
		$this->captured_requests_options = $options;

		/*
		 * The fake transport returns canned responses and does not stream bodies
		 * to disk. Clear `filename` after capturing so Requests::parse_response()
		 * still splits headers from the canned body. Real Curl/Fsockopen streaming
		 * remains covered by WP_HTTP_UnitTestCase against a local HTTP fixture.
		 */
		$options['filename']  = false;
		$options['transport'] = new WP_Http_Unit_Test_Transport();
	}

	/**
	 * @covers ::wp_remote_head
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_head_request() {
		$this->use_mock_transport();

		// This URL gives a direct 200 response.
		$url      = 'https://s.w.org/screenshots/3.9/dashboard.png';
		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );

		$headers = wp_remote_retrieve_headers( $response );

		$this->assertIsArray( $response );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
	}

	/**
	 * @covers ::wp_remote_head
	 */
	public function test_head_redirect() {
		$this->use_mock_transport();

		// This URL will 301 redirect. HEAD requests do not follow redirects by default.
		$url      = 'https://wp.org/screenshots/3.9/dashboard.png';
		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );
		$this->assertSame( 301, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_head
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_head_404() {
		$this->use_mock_transport();

		$url      = 'https://wordpress.org/screenshots/3.9/awefasdfawef.jpg';
		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );
		$this->assertSame( 404, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_get_request() {
		$this->use_mock_transport();

		$url = 'https://s.w.org/screenshots/3.9/dashboard.png';

		$response = wp_remote_get( $url );

		$this->assertNotWPError( $response );

		$headers = wp_remote_retrieve_headers( $response );

		// Should return the same headers as a HEAD request.
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
	}

	/**
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_headers
	 * @covers ::wp_remote_retrieve_response_code
	 */
	public function test_get_redirect() {
		$this->use_mock_transport();

		// This will redirect to wordpress.org.
		$url = 'https://wp.org/screenshots/3.9/dashboard.png';

		$response = wp_remote_get( $url );

		$this->assertNotWPError( $response );

		$headers = wp_remote_retrieve_headers( $response );

		// GET follows the redirect and returns the final image response.
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( 'image/png', $headers['Content-Type'] );
		$this->assertSame( '153204', $headers['Content-Length'] );
	}

	/**
	 * @covers ::wp_remote_get
	 */
	public function test_get_redirect_limit_exceeded() {
		$this->use_mock_transport();

		// This will redirect to wordpress.org.
		$url = 'https://wp.org/screenshots/3.9/dashboard.png';

		// Pretend we've already redirected 5 times.
		$response = wp_remote_get( $url, array( 'redirection' => -1 ) );

		$this->assertWPError( $response );
		$this->assertSame( 'http_request_failed', $response->get_error_code() );
	}

	/**
	 * @ticket 33711
	 *
	 * @covers ::wp_remote_head
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 * @covers ::wp_remote_retrieve_cookie_value
	 * @covers WP_HTTP_Requests_Response::get_cookies
	 */
	public function test_get_response_cookies() {
		$this->use_mock_transport();

		$url = 'https://login.wordpress.org/wp-login.php';

		$response = wp_remote_head( $url );

		$this->assertNotWPError( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'wordpress_test_cookie' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'wordpress_test_cookie', $cookie->name );
		$this->assertSame( 'WP Cookie check', $cookie->value );

		$value = wp_remote_retrieve_cookie_value( $response, 'wordpress_test_cookie' );
		$this->assertSame( 'WP Cookie check', $value );

		$no_value = wp_remote_retrieve_cookie_value( $response, 'not_a_cookie' );
		$this->assertSame( '', $no_value );

		$no_cookie = wp_remote_retrieve_cookie( $response, 'not_a_cookie' );
		$this->assertSame( '', $no_cookie );
	}

	/**
	 * @ticket 37437
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 * @covers WP_Http::normalize_cookies
	 */
	public function test_get_response_cookies_with_wp_http_cookie_object() {
		$this->use_mock_transport();

		$url = 'https://login.wordpress.org/wp-login.php';

		$response = wp_remote_get(
			$url,
			array(
				'cookies' => array(
					new WP_Http_Cookie(
						array(
							'name'  => 'test',
							'value' => 'foo',
						)
					),
				),
			)
		);

		$this->assertNotWPError( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'test', $cookie->name );
		$this->assertSame( 'foo', $cookie->value );
	}

	/**
	 * @ticket 37437
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 * @covers WP_Http::normalize_cookies
	 */
	public function test_get_response_cookies_with_name_value_array() {
		$this->use_mock_transport();

		$url = 'https://login.wordpress.org/wp-login.php';

		$response = wp_remote_get(
			$url,
			array(
				'cookies' => array(
					'test' => 'foo',
				),
			)
		);

		$this->assertNotWPError( $response );

		$cookies = wp_remote_retrieve_cookies( $response );

		$this->assertNotEmpty( $cookies );

		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertInstanceOf( 'WP_Http_Cookie', $cookie );
		$this->assertSame( 'test', $cookie->name );
		$this->assertSame( 'foo', $cookie->value );
	}

	/**
	 * Verifies WP_Http maps `stream` to a Requests `filename` option.
	 *
	 * Real Curl/Fsockopen streaming behavior remains covered by
	 * WP_HTTP_UnitTestCase::test_file_stream().
	 *
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_stream_passes_filename_option_to_requests() {
		$this->use_mock_transport();

		$url = 'https://s.w.org/screenshots/3.9/dashboard.png';
		$res = wp_remote_request(
			$url,
			array(
				'stream'  => true,
				'timeout' => 30,
			)
		);

		$this->assertNotWPError( $res );
		$this->assertIsArray( $this->captured_requests_options );
		$this->assertArrayHasKey( 'filename', $this->captured_requests_options );
		$this->assertSame( get_temp_dir() . 'dashboard.png', $this->captured_requests_options['filename'] );
	}

	/**
	 * Verifies WP_Http maps `limit_response_size` to Requests `max_bytes`.
	 *
	 * Real Curl/Fsockopen truncation remains covered by
	 * WP_HTTP_UnitTestCase::test_request_limited_size().
	 *
	 * @ticket 31172
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_limit_response_size_passes_max_bytes_option_to_requests() {
		$this->use_mock_transport();

		$size = 10000;
		$res  = wp_remote_request(
			'https://s.w.org/screenshots/3.9/dashboard.png',
			array(
				'timeout'             => 30,
				'limit_response_size' => $size,
			)
		);

		$this->assertNotWPError( $res );
		$this->assertIsArray( $this->captured_requests_options );
		$this->assertArrayHasKey( 'max_bytes', $this->captured_requests_options );
		$this->assertSame( $size, $this->captured_requests_options['max_bytes'] );
	}

	/**
	 * Verifies WP_Http maps stream + size limit options together.
	 *
	 * Real Curl/Fsockopen behavior remains covered by
	 * WP_HTTP_UnitTestCase::test_file_stream_limited_size().
	 *
	 * @ticket 26726
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_stream_with_limit_passes_filename_and_max_bytes_to_requests() {
		$this->use_mock_transport();

		$size = 10000;
		$res  = wp_remote_request(
			'https://s.w.org/screenshots/3.9/dashboard.png',
			array(
				'stream'              => true,
				'timeout'             => 30,
				'limit_response_size' => $size,
			)
		);

		$this->assertNotWPError( $res );
		$this->assertIsArray( $this->captured_requests_options );
		$this->assertArrayHasKey( 'filename', $this->captured_requests_options );
		$this->assertSame( get_temp_dir() . 'dashboard.png', $this->captured_requests_options['filename'] );
		$this->assertArrayHasKey( 'max_bytes', $this->captured_requests_options );
		$this->assertSame( $size, $this->captured_requests_options['max_bytes'] );
	}

	/**
	 * @ticket 43231
	 *
	 * @covers WP_HTTP_Requests_Response::__construct
	 * @covers WP_Http_Cookie::__construct
	 * @covers WP_Http::normalize_cookies
	 * @covers ::wp_remote_retrieve_cookies
	 * @covers ::wp_remote_retrieve_cookie
	 */
	public function test_get_cookie_host_only() {
		// Emulate WP_Http::request() internals.
		$requests_response = new WpOrg\Requests\Response();

		$requests_response->cookies['test'] = WpOrg\Requests\Cookie::parse( 'test=foo; domain=.wordpress.org' );

		$requests_response->cookies['test']->flags['host-only'] = false; // https://github.com/WordPress/Requests/issues/306

		$http_response = new WP_HTTP_Requests_Response( $requests_response );

		$response = $http_response->to_array();

		// Check the host_only flag in the resulting WP_Http_Cookie.
		$cookie = wp_remote_retrieve_cookie( $response, 'test' );
		$this->assertSame( $cookie->domain, 'wordpress.org' );
		$this->assertFalse( $cookie->host_only, 'host-only flag not set' );

		// Regurgitate (WpOrg\Requests\Cookie -> WP_Http_Cookie -> WpOrg\Requests\Cookie).
		$cookies = WP_Http::normalize_cookies( wp_remote_retrieve_cookies( $response ) );
		$this->assertFalse( $cookies['test']->flags['host-only'], 'host-only flag data lost' );
	}
}
