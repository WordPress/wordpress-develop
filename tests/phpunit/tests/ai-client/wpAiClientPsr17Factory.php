<?php
/**
 * Tests for WP_AI_Client_PSR17_Factory.
 *
 * @group ai-client
 * @covers WP_AI_Client_PSR17_Factory
 */
class Tests_AI_Client_PSR17_Factory extends WP_UnitTestCase {

	/**
	 * Factory instance under test.
	 *
	 * @var WP_AI_Client_PSR17_Factory
	 */
	private $psr17;

	/**
	 * Sets up a fresh factory instance before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->psr17 = new WP_AI_Client_PSR17_Factory();
	}

	/**
	 * Test that the factory implements all four PSR-17 factory interfaces.
	 *
	 * @ticket 64591
	 */
	public function test_implements_factory_interfaces() {
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\RequestFactoryInterface::class,
			$this->psr17
		);
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface::class,
			$this->psr17
		);
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface::class,
			$this->psr17
		);
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\UriFactoryInterface::class,
			$this->psr17
		);
	}

	/**
	 * Test createRequest returns RequestInterface with correct method and URI.
	 *
	 * @ticket 64591
	 */
	public function test_create_request() {
		$request = $this->psr17->createRequest( 'POST', 'https://example.com/api' );

		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface::class,
			$request
		);
		$this->assertSame( 'POST', $request->getMethod() );
		$this->assertSame( 'https://example.com/api', (string) $request->getUri() );
	}

	/**
	 * Test createRequest accepts a UriInterface.
	 *
	 * @ticket 64591
	 */
	public function test_create_request_with_uri_interface() {
		$uri     = $this->psr17->createUri( 'https://example.com/api' );
		$request = $this->psr17->createRequest( 'GET', $uri );

		$this->assertSame( 'https://example.com/api', (string) $request->getUri() );
	}

	/**
	 * Test createResponse with default status code.
	 *
	 * @ticket 64591
	 */
	public function test_create_response_default() {
		$response = $this->psr17->createResponse();

		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface::class,
			$response
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * Test createResponse with custom status code.
	 *
	 * @ticket 64591
	 */
	public function test_create_response_custom() {
		$response = $this->psr17->createResponse( 404, 'Not Found' );

		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertSame( 'Not Found', $response->getReasonPhrase() );
	}

	/**
	 * Test createStream with content.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream() {
		$stream = $this->psr17->createStream( 'test content' );

		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface::class,
			$stream
		);
		$this->assertSame( 'test content', (string) $stream );
	}

	/**
	 * Test createStream with empty content.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream_empty() {
		$stream = $this->psr17->createStream();
		$this->assertSame( '', (string) $stream );
	}

	/**
	 * Test createStreamFromFile reads file content.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream_from_file() {
		$tmp = wp_tempnam( 'psr17test' );
		file_put_contents( $tmp, 'file content' );

		$stream = $this->psr17->createStreamFromFile( $tmp );
		$this->assertSame( 'file content', (string) $stream );

		unlink( $tmp );
	}

	/**
	 * Test createStreamFromFile throws RuntimeException for non-read modes.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream_from_file_throws_for_write_mode() {
		$this->expectException( RuntimeException::class );

		$this->psr17->createStreamFromFile( 'irrelevant.txt', 'w' );
	}

	/**
	 * Test createStreamFromFile throws RuntimeException for nonexistent file.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream_from_file_throws_for_nonexistent_file() {
		$this->expectException( RuntimeException::class );

		$this->psr17->createStreamFromFile( '/nonexistent/path/file.txt' );
	}

	/**
	 * Test createStreamFromFile accepts binary read mode.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream_from_file_accepts_binary_read_mode() {
		$tmp = wp_tempnam( 'psr17test' );
		file_put_contents( $tmp, 'binary content' );

		$stream = $this->psr17->createStreamFromFile( $tmp, 'rb' );
		$this->assertSame( 'binary content', (string) $stream );

		unlink( $tmp );
	}

	/**
	 * Test createStreamFromResource reads resource content.
	 *
	 * @ticket 64591
	 */
	public function test_create_stream_from_resource() {
		$resource = fopen( 'php://memory', 'r+' );
		fwrite( $resource, 'resource content' );
		rewind( $resource );

		$stream = $this->psr17->createStreamFromResource( $resource );
		$this->assertSame( 'resource content', (string) $stream );

		fclose( $resource );
	}

	/**
	 * Test createUri parses a URI string.
	 *
	 * @ticket 64591
	 */
	public function test_create_uri() {
		$uri = $this->psr17->createUri( 'https://example.com/path?q=1' );

		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\UriInterface::class,
			$uri
		);
		$this->assertSame( 'https', $uri->getScheme() );
		$this->assertSame( 'example.com', $uri->getHost() );
		$this->assertSame( '/path', $uri->getPath() );
		$this->assertSame( 'q=1', $uri->getQuery() );
	}

	/**
	 * Test createUri with empty string.
	 *
	 * @ticket 64591
	 */
	public function test_create_uri_empty() {
		$uri = $this->psr17->createUri();
		$this->assertSame( '', (string) $uri );
	}
}
