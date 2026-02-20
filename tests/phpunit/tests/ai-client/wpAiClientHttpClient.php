<?php
/**
 * Tests for WP_AI_Client_HTTP_Client.
 *
 * @group ai-client
 * @covers WP_AI_Client_HTTP_Client
 */
class Tests_AI_Client_HTTP_Client extends WP_UnitTestCase {

	/**
	 * HTTP client instance under test.
	 *
	 * @var WP_AI_Client_HTTP_Client
	 */
	private $client;

	/**
	 * PSR-17 factory instance.
	 *
	 * @var WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory
	 */
	private $psr17_factory;

	/**
	 * Captured URL from the last intercepted HTTP request.
	 *
	 * @var string
	 */
	private $captured_url;

	/**
	 * Captured args from the last intercepted HTTP request.
	 *
	 * @var array
	 */
	private $captured_args;

	/**
	 * Sets up a fresh client instance before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->psr17_factory = new WordPress\AiClientDependencies\Nyholm\Psr7\Factory\Psr17Factory();
		$this->client        = new WP_AI_Client_HTTP_Client( $this->psr17_factory, $this->psr17_factory );
	}

	/**
	 * Test that the client implements ClientInterface.
	 *
	 * @ticket 64591
	 */
	public function test_implements_client_interface() {
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface::class,
			$this->client
		);
	}

	/**
	 * Test that the client implements ClientWithOptionsInterface.
	 *
	 * @ticket 64591
	 */
	public function test_implements_client_with_options_interface() {
		$this->assertInstanceOf(
			WordPress\AiClient\Providers\Http\Contracts\ClientWithOptionsInterface::class,
			$this->client
		);
	}

	/**
	 * Test successful sendRequest maps status, body, and headers.
	 *
	 * @ticket 64591
	 */
	public function test_send_request_success() {
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => '{"result":"ok"}',
				);
			}
		);

		$request  = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com/test' );
		$response = $this->client->sendRequest( $request );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( '{"result":"ok"}', (string) $response->getBody() );
		$this->assertTrue( $response->hasHeader( 'content-type' ) );
	}

	/**
	 * Test request method, headers, body, and httpversion are mapped to WP args.
	 *
	 * @ticket 64591
	 */
	public function test_request_args_mapped() {
		$captured_args = null;
		$captured_url  = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args, $url ) use ( &$captured_args, &$captured_url ) {
				$captured_args = $parsed_args;
				$captured_url  = $url;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => '',
				);
			},
			10,
			3
		);

		$body    = $this->psr17_factory->createStream( '{"key":"value"}' );
		$request = $this->psr17_factory->createRequest( 'POST', 'https://api.example.com/data' );
		$request = $request->withBody( $body );
		$request = $request->withHeader( 'Content-Type', 'application/json' );
		$request = $request->withProtocolVersion( '2.0' );

		$this->client->sendRequest( $request );

		$this->assertSame( 'https://api.example.com/data', $captured_url );
		$this->assertSame( 'POST', $captured_args['method'] );
		$this->assertSame( '2.0', $captured_args['httpversion'] );
		$this->assertSame( '{"key":"value"}', $captured_args['body'] );
		$this->assertArrayHasKey( 'Content-Type', $captured_args['headers'] );
		$this->assertSame( 'application/json', $captured_args['headers']['Content-Type'] );
	}

	/**
	 * Test empty body sends null.
	 *
	 * @ticket 64591
	 */
	public function test_empty_body_sends_null() {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args ) use ( &$captured_args ) {
				$captured_args = $parsed_args;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => '',
				);
			},
			10,
			2
		);

		$request = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com' );
		$this->client->sendRequest( $request );

		$this->assertNull( $captured_args['body'] );
	}

	/**
	 * Test WP_Error throws NetworkException.
	 *
	 * @ticket 64591
	 */
	public function test_wp_error_throws_network_exception() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Connection timed out' );
			}
		);

		$request = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com' );

		$this->expectException( WordPress\AiClient\Providers\Http\Exception\NetworkException::class );
		$this->client->sendRequest( $request );
	}

	/**
	 * Test sendRequestWithOptions applies timeout.
	 *
	 * @ticket 64591
	 */
	public function test_send_request_with_options_timeout() {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args ) use ( &$captured_args ) {
				$captured_args = $parsed_args;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => '',
				);
			},
			10,
			2
		);

		$options = new WordPress\AiClient\Providers\Http\DTO\RequestOptions();
		$options->setTimeout( 30.0 );

		$request = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com' );
		$this->client->sendRequestWithOptions( $request, $options );

		$this->assertSame( 30.0, $captured_args['timeout'] );
	}

	/**
	 * Test sendRequestWithOptions applies redirection.
	 *
	 * @ticket 64591
	 */
	public function test_send_request_with_options_redirection() {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args ) use ( &$captured_args ) {
				$captured_args = $parsed_args;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => '',
				);
			},
			10,
			2
		);

		$options = new WordPress\AiClient\Providers\Http\DTO\RequestOptions();
		$options->setMaxRedirects( 5 );

		$request = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com' );
		$this->client->sendRequestWithOptions( $request, $options );

		$this->assertSame( 5, $captured_args['redirection'] );
	}

	/**
	 * Test sendRequestWithOptions does not override defaults when options are null.
	 *
	 * @ticket 64591
	 */
	public function test_send_request_with_options_null_uses_defaults() {
		$args_with_options    = null;
		$args_without_options = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args ) use ( &$args_with_options, &$args_without_options ) {
				if ( null === $args_without_options ) {
					$args_without_options = $parsed_args;
				} else {
					$args_with_options = $parsed_args;
				}
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => '',
				);
			},
			10,
			2
		);

		$request = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com' );

		// First request: null options should use WordPress defaults.
		$options = new WordPress\AiClient\Providers\Http\DTO\RequestOptions();
		$this->client->sendRequestWithOptions( $request, $options );

		// Second request: explicit options should override defaults.
		$options_explicit = new WordPress\AiClient\Providers\Http\DTO\RequestOptions();
		$options_explicit->setTimeout( 99.0 );
		$options_explicit->setMaxRedirects( 10 );
		$this->client->sendRequestWithOptions( $request, $options_explicit );

		// Null options should retain WordPress default timeout, not 99.
		$this->assertNotSame( 99.0, $args_without_options['timeout'] );
		// Explicit options should apply.
		$this->assertSame( 99.0, $args_with_options['timeout'] );
		$this->assertSame( 10, $args_with_options['redirection'] );
	}

	/**
	 * Test sendRequestWithOptions WP_Error throws NetworkException.
	 *
	 * @ticket 64591
	 */
	public function test_send_request_with_options_wp_error_throws() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Connection refused' );
			}
		);

		$options = new WordPress\AiClient\Providers\Http\DTO\RequestOptions();
		$request = $this->psr17_factory->createRequest( 'GET', 'https://api.example.com' );

		$this->expectException( WordPress\AiClient\Providers\Http\Exception\NetworkException::class );
		$this->client->sendRequestWithOptions( $request, $options );
	}

	/**
	 * Test seekable body is rewound before sending.
	 *
	 * @ticket 64591
	 */
	public function test_seekable_body_rewound() {
		$captured_args = null;

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args ) use ( &$captured_args ) {
				$captured_args = $parsed_args;
				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(),
					'body'     => '',
				);
			},
			10,
			2
		);

		$body = $this->psr17_factory->createStream( 'test body' );
		$body->read( 4 ); // Advance offset past "test".

		$request = $this->psr17_factory->createRequest( 'POST', 'https://api.example.com' );
		$request = $request->withBody( $body );

		$this->client->sendRequest( $request );

		$this->assertSame( 'test body', $captured_args['body'] );
	}
}
