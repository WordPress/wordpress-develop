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
		$url = 'http://api.wordpress.org/core/tests/1.0/redirection.php?201-location=1';

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

		$headers = array();
		foreach ( explode( "\n", $res['body'] ) as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$parts = explode( ':', $value, 2 );
			unset( $headers[ $key ] );
			$headers[ $parts[0] ] = $parts[1];
		}

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
		$url = 'http://api.wordpress.org/core/tests/1.0/redirection.php?post-redirect-to-method=1';

		$res = $this->wp_remote_post( add_query_arg( 'response_code', $response_code, $url ), array( 'timeout' => 30 ) );

		$this->assertNotWPError( $res );
		$this->assertSame( $method, wp_remote_retrieve_body( $res ) );
	}

	public function data_post_redirect_to_method_300() {
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
	 * @ticket 24182
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_ip_url_with_host_header() {
		$ip   = gethostbyname( 'api.wordpress.org' );
		$url  = 'http://' . $ip . '/core/tests/1.0/redirection.php?print-pass=1';
		$args = array(
			'headers'     => array(
				'Host' => 'api.wordpress.org',
			),
			'timeout'     => 30,
			'redirection' => 0,
		);

		$res = $this->wp_remote_get( $url, $args );

		$this->assertNotWPError( $res );
		$this->assertSame( 'PASS', wp_remote_retrieve_body( $res ) );
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
	 * @ticket 21182
	 *
	 * @covers ::wp_remote_get
	 * @covers ::wp_remote_retrieve_body
	 */
	public function test_cookie_handling() {
		$url = 'http://api.wordpress.org/core/tests/1.0/redirection.php?cookie-test=1';

		$res = $this->wp_remote_get( $url );

		$this->assertNotWPError( $res );
		$this->assertSame( 'PASS', wp_remote_retrieve_body( $res ) );
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
