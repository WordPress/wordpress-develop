<?php
/**
 * Note, When running these tests, remember that some things are done differently
 * based on safe_mode. You can run the test in safe_mode like such:
 *
 *   phpunit -d safe_mode=on --group http
 *
 * You may also need `-d safe_mode_gid=1` to relax the safe_mode checks to allow
 * inclusion of PEAR.
 *
 * The WP_Http tests require a class-http.php file of r17550 or later.
 */
abstract class WP_HTTP_UnitTestCase extends WP_UnitTestCase {
	// You can use your own version of data/WPHTTP-testcase-redirection-script.php here.
	public $redirection_script = 'http://api.wordpress.org/core/tests/1.0/redirection.php';

	/**
	 * URL of the local stream/size fixture (set in set_up_before_class()).
	 *
	 * @var string
	 */
	public $file_stream_url = '';

	/**
	 * Byte size of the local stream/size fixture payload.
	 *
	 * @var int
	 */
	protected static $file_stream_size = 20000;

	/**
	 * Directory containing the local fixture payload.
	 *
	 * @var string|null
	 */
	private static $file_stream_fixture_dir = null;

	/**
	 * Process resource for the local PHP built-in server.
	 *
	 * @var resource|null
	 */
	private static $file_stream_fixture_process = null;

	/**
	 * Local fixture URL shared with instance tests after set_up_before_class().
	 *
	 * @var string|null
	 */
	private static $file_stream_fixture_url = null;

	/**
	 * Local redirection fixture URL shared with instance tests after set_up_before_class().
	 *
	 * @var string|null
	 */
	private static $redirection_fixture_url = null;

	/**
	 * Skip reason when a fixture prerequisite is unavailable.
	 *
	 * @var string|null
	 */
	private static $file_stream_fixture_skip_reason = null;

	/**
	 * Failure reason when fixture server startup fails unexpectedly.
	 *
	 * @var string|null
	 */
	private static $file_stream_fixture_fail_reason = null;

	protected $http_request_args;

	/**
	 * Starts a local HTTP fixture server for stream and response-size tests.
	 *
	 * Uses a deterministic payload and the real Requests transports so coverage
	 * does not depend on a live s.w.org download.
	 *
	 * Missing prerequisites (for example no `proc_open()`) skip fixture-dependent
	 * tests. Unexpected startup failures are recorded and surfaced as test
	 * failures rather than silent skips.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$file_stream_fixture_skip_reason = null;
		self::$file_stream_fixture_fail_reason = null;

		if ( ! function_exists( 'proc_open' ) ) {
			self::$file_stream_fixture_skip_reason = 'proc_open() is not available.';
			return;
		}

		$fixture_dir = get_temp_dir() . 'wp-http-stream-fixture-' . uniqid( '', true );
		if ( ! mkdir( $fixture_dir ) && ! is_dir( $fixture_dir ) ) {
			self::$file_stream_fixture_fail_reason = sprintf(
				'Could not create the local HTTP stream fixture directory: %s',
				$fixture_dir
			);
			return;
		}

		$payload = str_repeat( 'a', self::$file_stream_size );
		if ( false === file_put_contents( $fixture_dir . '/dashboard.bin', $payload ) ) {
			self::remove_file_stream_fixture_dir( $fixture_dir );
			self::$file_stream_fixture_fail_reason = sprintf(
				'Could not write the local HTTP stream fixture payload in: %s',
				$fixture_dir
			);
			return;
		}

		$redirection_copied        = false;
		$redirection_script_source = DIR_TESTDATA . '/WPHTTP-testcase-redirection-script.php';
		if ( file_exists( $redirection_script_source ) ) {
			$redirection_copied = copy( $redirection_script_source, $fixture_dir . '/redirection.php' );
		}

		set_error_handler(
			static function () {
				return true;
			}
		);
		try {
			$socket = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr );
		} finally {
			restore_error_handler();
		}

		if ( ! $socket ) {
			self::remove_file_stream_fixture_dir( $fixture_dir );
			self::$file_stream_fixture_fail_reason = sprintf(
				'Could not reserve a local TCP port for the HTTP stream fixture server (%d: %s).',
				(int) $errno,
				$errstr ? $errstr : 'unknown error'
			);
			return;
		}

		$address = stream_socket_get_name( $socket, false );
		fclose( $socket );

		$port = (int) substr( strrchr( $address, ':' ), 1 );
		if ( $port <= 0 ) {
			self::remove_file_stream_fixture_dir( $fixture_dir );
			self::$file_stream_fixture_fail_reason = sprintf(
				'Could not determine a local TCP port from socket address: %s',
				$address ? $address : '(empty)'
			);
			return;
		}

		$command = array(
			PHP_BINARY,
			'-S',
			'127.0.0.1:' . $port,
			'-t',
			$fixture_dir,
		);

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes, $fixture_dir );
		if ( ! is_resource( $process ) ) {
			self::remove_file_stream_fixture_dir( $fixture_dir );
			self::$file_stream_fixture_fail_reason = sprintf(
				'Could not start the local HTTP stream fixture server with %s on 127.0.0.1:%d.',
				PHP_BINARY,
				$port
			);
			return;
		}

		foreach ( $pipes as $pipe ) {
			if ( is_resource( $pipe ) ) {
				fclose( $pipe );
			}
		}

		$url      = 'http://127.0.0.1:' . $port . '/dashboard.bin';
		$deadline = microtime( true ) + 5.0;
		$ready    = false;

		set_error_handler(
			static function () {
				return true;
			}
		);
		try {
			while ( microtime( true ) < $deadline ) {
				$connection = fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.1 );
				if ( $connection ) {
					fclose( $connection );
					$ready = true;
					break;
				}
				usleep( 50000 );
			}
		} finally {
			restore_error_handler();
		}

		if ( ! $ready ) {
			proc_terminate( $process );
			proc_close( $process );
			self::remove_file_stream_fixture_dir( $fixture_dir );
			self::$file_stream_fixture_fail_reason = sprintf(
				'The local HTTP stream fixture server at 127.0.0.1:%d did not accept connections within 5 seconds.',
				$port
			);
			return;
		}

		self::$file_stream_fixture_dir     = $fixture_dir;
		self::$file_stream_fixture_process = $process;
		self::$file_stream_fixture_url     = $url;
		if ( $redirection_copied ) {
			self::$redirection_fixture_url = 'http://127.0.0.1:' . $port . '/redirection.php';
		}
	}

	/**
	 * Stops the local HTTP fixture server.
	 */
	public static function tear_down_after_class() {
		if ( is_resource( self::$file_stream_fixture_process ) ) {
			proc_terminate( self::$file_stream_fixture_process );
			proc_close( self::$file_stream_fixture_process );
			self::$file_stream_fixture_process = null;
		}

		if ( self::$file_stream_fixture_dir ) {
			self::remove_file_stream_fixture_dir( self::$file_stream_fixture_dir );
			self::$file_stream_fixture_dir = null;
		}

		self::$file_stream_fixture_url         = null;
		self::$redirection_fixture_url         = null;
		self::$file_stream_fixture_skip_reason = null;
		self::$file_stream_fixture_fail_reason = null;

		parent::tear_down_after_class();
	}

	/**
	 * Deletes a fixture directory and its contents.
	 *
	 * @param string $directory Directory path.
	 */
	private static function remove_file_stream_fixture_dir( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		foreach ( scandir( $directory ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $directory . '/' . $item;
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}

		rmdir( $directory );
	}

	public function set_up() {
		parent::set_up();

		$class = 'WP_Http_' . ucfirst( $this->transport );
		if ( ! call_user_func( array( $class, 'test' ) ) ) {
			$this->markTestSkipped( sprintf( 'The transport %s is not supported on this system.', $this->transport ) );
		}

		// Disable all transports aside from this one.
		foreach ( array( 'curl', 'streams', 'fsockopen' ) as $t ) {
			remove_filter( "use_{$t}_transport", '__return_false' );  // Just strip them all...
			if ( $t !== $this->transport ) {
				add_filter( "use_{$t}_transport", '__return_false' ); // ...and add it back if need be.
			}
		}

		if ( self::$file_stream_fixture_url ) {
			$this->file_stream_url = self::$file_stream_fixture_url;
		}

		if ( self::$redirection_fixture_url ) {
			$this->redirection_script = self::$redirection_fixture_url;
		}
	}

	public function filter_http_request_args( array $args ) {
		$this->http_request_args = $args;
		return $args;
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_redirect_on_301() {
		// 5 : 5 & 301.
		$res = $this->wp_remote_request( $this->redirection_script . '?code=301&rt=' . 5, array( 'redirection' => 5 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 200, (int) $res['response']['code'] );
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_redirect_on_302() {
		// 5 : 5 & 302.
		$res = $this->wp_remote_request( $this->redirection_script . '?code=302&rt=' . 5, array( 'redirection' => 5 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 200, (int) $res['response']['code'] );
	}

	/**
	 * @ticket 16855
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_redirect_on_301_no_redirect() {
		// 5 > 0 & 301.
		$res = $this->wp_remote_request( $this->redirection_script . '?code=301&rt=' . 5, array( 'redirection' => 0 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 301, (int) $res['response']['code'] );
	}

	/**
	 * @ticket 16855
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_redirect_on_302_no_redirect() {
		// 5 > 0 & 302.
		$res = $this->wp_remote_request( $this->redirection_script . '?code=302&rt=' . 5, array( 'redirection' => 0 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 302, (int) $res['response']['code'] );
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_redirections_equal() {
		// 5 - 5.
		$res = $this->wp_remote_request( $this->redirection_script . '?rt=' . 5, array( 'redirection' => 5 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 200, (int) $res['response']['code'] );
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_no_head_redirections() {
		// No redirections on HEAD request.
		$res = $this->wp_remote_request( $this->redirection_script . '?code=302&rt=' . 1, array( 'method' => 'HEAD' ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 302, (int) $res['response']['code'] );
	}

	/**
	 * @ticket 16855
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_redirect_on_head() {
		// Redirections on HEAD request when Requested.
		$res = $this->wp_remote_request(
			$this->redirection_script . '?rt=' . 5,
			array(
				'redirection' => 5,
				'method'      => 'HEAD',
			)
		);

		$this->assertNotWPError( $res );
		$this->assertSame( 200, (int) $res['response']['code'] );
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_redirections_greater() {
		// 10 > 5.
		$res = $this->wp_remote_request( $this->redirection_script . '?rt=' . 10, array( 'redirection' => 5 ) );

		$this->assertWPError( $res );
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_redirections_greater_edgecase() {
		// 6 > 5 (close edge case).
		$res = $this->wp_remote_request( $this->redirection_script . '?rt=' . 6, array( 'redirection' => 5 ) );

		$this->assertWPError( $res );
	}

	/**
	 * @covers ::wp_remote_request
	 */
	public function test_redirections_less_edgecase() {
		// 4 < 5 (close edge case).
		$res = $this->wp_remote_request( $this->redirection_script . '?rt=' . 4, array( 'redirection' => 5 ) );

		$this->assertNotWPError( $res );
	}

	/**
	 * @ticket 16855
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_redirections_zero_redirections_specified() {
		// 0 redirections asked for, should return the document?
		$res = $this->wp_remote_request( $this->redirection_script . '?code=302&rt=' . 5, array( 'redirection' => 0 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( 302, (int) $res['response']['code'] );
	}

	/**
	 * Do not redirect on non 3xx status codes.
	 *
	 * @ticket 16889
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_location_header_on_201() {
		// Prints PASS on initial load, FAIL if the client follows the specified redirection.
		$res = $this->wp_remote_request( $this->redirection_script . '?201-location=true' );

		$this->assertNotWPError( $res );
		$this->assertSame( 'PASS', $res['body'] );
	}

	/**
	 * Test handling of PUT requests on redirects.
	 *
	 * @ticket 16889
	 *
	 * @covers ::wp_remote_request
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_no_redirection_on_PUT() {
		$url = $this->redirection_script . '?201-location=1';

		// Test 301 - POST to POST.
		$res = $this->wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 30,
			)
		);

		$this->assertNotWPError( $res );
		$this->assertSame( 'PASS', wp_remote_retrieve_body( $res ) );
		$this->assertNotEmpty( $res['headers']['location'] );
	}

	/**
	 * Parses the plain-text body from the redirection script `header-check` endpoint.
	 *
	 * @param string $body Response body.
	 * @return array Header name => value pairs.
	 */
	protected function parse_header_check_body( $body ) {
		$headers = array();

		foreach ( explode( "\n", $body ) as $line ) {
			if ( empty( $line ) ) {
				continue;
			}

			$parts = explode( ':', $line, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$headers[ $parts[0] ] = $parts[1];
		}

		return $headers;
	}

	/**
	 * @ticket 11888
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_send_headers() {
		// Test that the headers sent are received by the server.
		$headers = array(
			'test1' => 'test',
			'test2' => 0,
			'test3' => '',
		);
		$res     = $this->wp_remote_request( $this->redirection_script . '?header-check', array( 'headers' => $headers ) );

		$this->assertNotWPError( $res );

		$headers = $this->parse_header_check_body( $res['body'] );

		$this->assertArrayHasKey( 'test1', $headers );
		$this->assertSame( 'test', $headers['test1'] );
		$this->assertArrayHasKey( 'test2', $headers );
		$this->assertSame( '0', $headers['test2'] );
		// cURL/HTTP Extension Note: Will never pass, cURL does not pass headers with an empty value.
		// Should it be that empty headers with empty values are NOT sent?
		// $this->assertArrayHasKey( 'test3', $headers );
		// $this->assertSame( '', $headers['test3'] );
	}

	/**
	 * Ensures the local stream fixture server is available.
	 *
	 * Skips when a prerequisite is missing. Fails with a diagnostic when
	 * fixture startup failed unexpectedly.
	 */
	protected function require_file_stream_fixture() {
		if ( ! empty( $this->file_stream_url ) ) {
			return;
		}

		if ( self::$file_stream_fixture_skip_reason ) {
			$this->markTestSkipped( self::$file_stream_fixture_skip_reason );
		}

		if ( self::$file_stream_fixture_fail_reason ) {
			$this->fail( self::$file_stream_fixture_fail_reason );
		}

		$this->fail( 'The local HTTP stream fixture server was not started.' );
	}

	/**
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_file_stream() {
		$this->require_file_stream_fixture();

		$url  = $this->file_stream_url;
		$size = self::$file_stream_size;
		$res  = $this->wp_remote_request(
			$url,
			array(
				'stream'  => true,
				'timeout' => 30,
			)
		); // Auto generate the filename.

		// Cleanup before we assert, as it'll return early.
		if ( ! is_wp_error( $res ) ) {
			$filesize = filesize( $res['filename'] );
			unlink( $res['filename'] );
		}

		$this->assertNotWPError( $res );
		$this->assertSame( '', $res['body'] ); // The body should be empty.
		$this->assertSame( (string) $size, $res['headers']['Content-Length'] );   // Check the headers are returned (and the size is the same).
		$this->assertSame( $size, $filesize ); // Check that the file is written to disk correctly without any extra characters.
		$this->assertStringStartsWith( get_temp_dir(), $res['filename'] ); // Check it's saving within the temp directory.
	}

	/**
	 * @ticket 26726
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_file_stream_limited_size() {
		$this->require_file_stream_fixture();

		$url  = $this->file_stream_url;
		$size = 10000;
		$res  = $this->wp_remote_request(
			$url,
			array(
				'stream'              => true,
				'timeout'             => 30,
				'limit_response_size' => $size,
			)
		); // Auto generate the filename.

		// Cleanup before we assert, as it'll return early.
		if ( ! is_wp_error( $res ) ) {
			$filesize = filesize( $res['filename'] );
			unlink( $res['filename'] );
		}

		$this->assertNotWPError( $res );
		$this->assertSame( $size, $filesize ); // Check that the file is written to disk correctly without any extra characters.
	}

	/**
	 * Tests limiting the response size when returning strings.
	 *
	 * @ticket 31172
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_request_limited_size() {
		$this->require_file_stream_fixture();

		$url  = $this->file_stream_url;
		$size = 10000;

		$res = $this->wp_remote_request(
			$url,
			array(
				'timeout'             => 30,
				'limit_response_size' => $size,
			)
		);

		$this->assertNotWPError( $res );
		$this->assertSame( $size, strlen( $res['body'] ) );
	}

	/**
	 * Test POST redirection methods.
	 *
	 * @dataProvider data_post_redirect_to_method_300
	 *
	 * @ticket 17588
	 *
	 * @covers ::wp_remote_post
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_post_redirect_to_method_300( $response_code, $method ) {
		$url = $this->redirection_script . '?post-redirect-to-method=1';

		$res = $this->wp_remote_post( add_query_arg( 'response_code', $response_code, $url ), array( 'timeout' => 30 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( $method, wp_remote_retrieve_body( $res ) );
	}

	public static function data_post_redirect_to_method_300() {
		return array(
			// Test 300 - POST to POST.
			array(
				300,
				'POST',
			),
			// Test 301 - POST to POST.
			array(
				301,
				'POST',
			),
			// Test 302 - POST to GET.
			array(
				302,
				'GET',
			),
			// Test 303 - POST to GET.
			array(
				303,
				'GET',
			),
		);
	}

	/**
	 * Test HTTP Requests using an IP URL, with a HOST header specified.
	 *
	 * Prefers the local redirection fixture when available. Falls back to the
	 * live api.wordpress.org script only when the fixture was not started.
	 *
	 * Uses `header-check` so the assertion proves the custom Host header reached
	 * the server, not only that some response body was returned.
	 *
	 * @ticket 24182
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_ip_url_with_host_header() {
		$parts = wp_parse_url( $this->redirection_script );
		$this->assertIsArray( $parts );
		$this->assertNotEmpty( $parts['host'] );
		$this->assertNotEmpty( $parts['path'] );

		$host = $parts['host'];
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : null;

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			/*
			 * Local fixture host is already an IP. Send a different Host value so
			 * the test fails if the transport ignores the caller-supplied header.
			 */
			$ip          = $host;
			$host_header = 'localhost';
		} else {
			$ip          = gethostbyname( $host );
			$host_header = $host;
		}

		$url = 'http://' . $ip;
		if ( $port ) {
			$url         .= ':' . $port;
			$host_header .= ':' . $port;
		}
		$url .= $parts['path'] . '?header-check';

		$args = array(
			'headers'     => array(
				'Host' => $host_header,
			),
			'timeout'     => 30,
			'redirection' => 0,
		);

		$res = $this->wp_remote_get( $url, $args );

		$this->assertNotWPError( $res );

		$received_headers = $this->parse_header_check_body( wp_remote_retrieve_body( $res ) );

		$this->assertArrayHasKey( 'host', $received_headers );
		$this->assertSame( $host_header, $received_headers['host'] );
	}

	/**
	 * Test HTTP requests where SSL verification is disabled but the CA bundle is still populated.
	 *
	 * @ticket 33978
	 *
	 * @covers ::wp_remote_head
	 */
	public function test_https_url_without_ssl_verification() {
		$url  = 'https://wordpress.org/';
		$args = array(
			'sslverify' => false,
		);

		add_filter( 'http_request_args', array( $this, 'filter_http_request_args' ) );

		$res = $this->wp_remote_head( $url, $args );

		remove_filter( 'http_request_args', array( $this, 'filter_http_request_args' ) );

		$this->assertNotEmpty( $this->http_request_args['sslcertificates'] );
		$this->assertNotWPError( $res );
	}

	/**
	 * Test HTTP Cookie handling.
	 *
	 * Uses the local redirection fixture when available so cookie acceptance and
	 * rejection checks do not depend on a live api.wordpress.org request.
	 *
	 * Parent-domain cookie acceptance is covered by
	 * test_parent_domain_cookie_is_sent_on_redirect().
	 *
	 * @ticket 21182
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_cookie_handling() {
		$url = $this->redirection_script . '?cookie-test=1';

		$res = $this->wp_remote_get( $url );

		$this->assertNotWPError( $res );
		$this->assertSame( 'PASS', wp_remote_retrieve_body( $res ) );
	}

	/**
	 * Verifies parent-domain cookies are accepted and sent on redirect.
	 *
	 * Complements test_cookie_handling(): the IP-based local fixture intentionally
	 * omits parent-domain cookies, so this named-origin mock keeps Domain=.example.test
	 * acceptance deterministic without a live network request.
	 *
	 * @ticket 21182
	 * @ticket 63914
	 *
	 * @covers ::wp_remote_get
	 * @covers WP_Http::normalize_cookies
	 */
	public function test_parent_domain_cookie_is_sent_on_redirect() {
		add_action( 'requests-requests.before_request', array( $this, 'inject_parent_domain_cookie_test_transport' ), 10, 5 );

		try {
			$res = $this->wp_remote_get( 'http://api.example.test/cookie-wildcard-test' );
		} finally {
			remove_action( 'requests-requests.before_request', array( $this, 'inject_parent_domain_cookie_test_transport' ), 10 );
		}

		$this->assertNotWPError( $res );
		$this->assertSame( 'PASS', wp_remote_retrieve_body( $res ) );
	}

	/**
	 * Injects the parent-domain cookie test transport into Requests options.
	 *
	 * @param string       $url     Request URL.
	 * @param array        $headers Request headers.
	 * @param string|array $data    Request data.
	 * @param string       $type    HTTP method.
	 * @param array        $options Request options (passed by reference).
	 */
	public function inject_parent_domain_cookie_test_transport( $url, $headers, $data, $type, &$options ) {
		$options['transport'] = new WP_HTTP_Parent_Domain_Cookie_Test_Transport();
	}

	/**
	 * Test if HTTPS support works.
	 *
	 * @group ssl
	 * @ticket 25007
	 *
	 * @covers ::wp_remote_get
	 */
	public function test_ssl() {
		if ( ! wp_http_supports( array( 'ssl' ) ) ) {
			$this->fail( 'This installation of PHP does not support SSL.' );
		}

		$res = $this->wp_remote_get( 'https://wordpress.org/' );

		$this->assertNotWPError( $res );
	}

	/**
	 * @ticket 37733
	 *
	 * @covers ::wp_remote_request
	 */
	public function test_url_with_double_slashes_path() {
		$url = $this->redirection_script . '?rt=' . 0;

		$path = parse_url( $url, PHP_URL_PATH );
		$url  = str_replace( $path, '/' . $path, $url );

		$res = $this->wp_remote_request( $url );

		$this->assertNotWPError( $res );
	}
}

/**
 * Test-only Requests transport for parent-domain cookie acceptance.
 *
 * Used by WP_HTTP_UnitTestCase::test_parent_domain_cookie_is_sent_on_redirect().
 */
class WP_HTTP_Parent_Domain_Cookie_Test_Transport implements WpOrg\Requests\Transport {

	/**
	 * Performs a request against the canned cookie-redirect responses.
	 *
	 * @param string       $url     URL to request.
	 * @param array        $headers Associative array of request headers.
	 * @param string|array $data    Request data.
	 * @param array        $options Request options.
	 * @return string Raw HTTP response.
	 *
	 * @throws WpOrg\Requests\Exception When no canned response exists for the URL.
	 */
	public function request( $url, $headers = array(), $data = array(), $options = array() ) {
		if ( 'http://api.example.test/cookie-wildcard-test' === $url ) {
			return "HTTP/1.1 302 Found\r\n"
				. "Set-Cookie: api_test_wildcard_domain=value; Domain=.example.test; Path=/\r\n"
				. "Set-Cookie: api_test_cookie_wrong_host=value; Domain=example.com; Path=/\r\n"
				. "Location: http://api.example.test/cookie-wildcard-test?done=1\r\n\r\n";
		}

		if ( 'http://api.example.test/cookie-wildcard-test?done=1' === $url ) {
			$cookie_header = '';
			foreach ( $headers as $header_name => $header_value ) {
				if ( 0 !== strcasecmp( (string) $header_name, 'Cookie' ) ) {
					continue;
				}

				$cookie_header = is_array( $header_value ) ? implode( '; ', $header_value ) : (string) $header_value;
				break;
			}

			if ( false === strpos( $cookie_header, 'api_test_wildcard_domain=value' ) ) {
				return "HTTP/1.1 200 OK\r\n\r\nFAIL_NO_WILDCARD";
			}

			if ( false !== strpos( $cookie_header, 'api_test_cookie_wrong_host=' ) ) {
				return "HTTP/1.1 200 OK\r\n\r\nFAIL_WRONG_HOST";
			}

			return "HTTP/1.1 200 OK\r\n\r\nPASS";
		}

		throw new WpOrg\Requests\Exception(
			sprintf( 'Unexpected HTTP request for URL: %s', $url ),
			'testtransport.unhandled'
		);
	}

	/**
	 * Performs multiple requests sequentially.
	 *
	 * @param array $requests Request data.
	 * @param array $options  Global options.
	 * @return array Array of responses.
	 */
	public function request_multiple( $requests, $options ) {
		$responses = array();

		foreach ( $requests as $key => $request ) {
			$responses[ $key ] = $this->request(
				$request['url'],
				$request['headers'],
				$request['data'],
				$request['options']
			);
		}

		return $responses;
	}

	/**
	 * Self-test whether the transport can be used.
	 *
	 * @param array<string, bool> $capabilities Optional. Capabilities to test against.
	 * @return bool Always true for the test transport.
	 */
	public static function test( $capabilities = array() ) {
		return true;
	}
}
