<?php
/**
 * Tests for WP_AI_Client_PSR7_Request.
 *
 * @group ai-client
 * @covers WP_AI_Client_PSR7_Request
 */
class Tests_AI_Client_PSR7_Request extends WP_UnitTestCase {

	/**
	 * Test that the request implements the scoped PSR-7 RequestInterface.
	 *
	 * @ticket TBD
	 */
	public function test_implements_request_interface() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface::class,
			$request
		);
	}

	/**
	 * Test constructor with string URI.
	 *
	 * @ticket TBD
	 */
	public function test_constructor_with_string_uri() {
		$request = new WP_AI_Client_PSR7_Request( 'POST', 'https://example.com/path' );

		$this->assertSame( 'POST', $request->getMethod() );
		$this->assertSame( 'https://example.com/path', (string) $request->getUri() );
	}

	/**
	 * Test constructor with UriInterface.
	 *
	 * @ticket TBD
	 */
	public function test_constructor_with_uri_interface() {
		$uri     = new WP_AI_Client_PSR7_Uri( 'https://example.com/path' );
		$request = new WP_AI_Client_PSR7_Request( 'GET', $uri );

		$this->assertSame( $uri, $request->getUri() );
	}

	/**
	 * Test constructor auto-sets Host header from URI.
	 *
	 * @ticket TBD
	 */
	public function test_auto_host_header_from_uri() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com/path' );

		$this->assertTrue( $request->hasHeader( 'Host' ) );
		$this->assertSame( 'example.com', $request->getHeaderLine( 'Host' ) );
	}

	/**
	 * Test constructor does not set Host header when host is empty.
	 *
	 * @ticket TBD
	 */
	public function test_no_host_header_for_empty_host() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', '/relative-path' );
		$this->assertFalse( $request->hasHeader( 'Host' ) );
	}

	/**
	 * Test default protocol version is 1.1.
	 *
	 * @ticket TBD
	 */
	public function test_default_protocol_version() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$this->assertSame( '1.1', $request->getProtocolVersion() );
	}

	/**
	 * Test withProtocolVersion returns a new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_protocol_version() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$new     = $request->withProtocolVersion( '2.0' );

		$this->assertNotSame( $request, $new );
		$this->assertSame( '2.0', $new->getProtocolVersion() );
		$this->assertSame( '1.1', $request->getProtocolVersion() );
	}

	/**
	 * Test hasHeader is case-insensitive.
	 *
	 * @ticket TBD
	 */
	public function test_has_header_case_insensitive() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'Content-Type', 'application/json' );

		$this->assertTrue( $request->hasHeader( 'content-type' ) );
		$this->assertTrue( $request->hasHeader( 'CONTENT-TYPE' ) );
	}

	/**
	 * Test getHeader returns values case-insensitively.
	 *
	 * @ticket TBD
	 */
	public function test_get_header() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'Content-Type', 'application/json' );

		$this->assertSame( array( 'application/json' ), $request->getHeader( 'content-type' ) );
		$this->assertSame( array(), $request->getHeader( 'X-Missing' ) );
	}

	/**
	 * Test getHeaderLine returns comma-separated values.
	 *
	 * @ticket TBD
	 */
	public function test_get_header_line() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'Accept', array( 'text/html', 'application/json' ) );

		$this->assertSame( 'text/html, application/json', $request->getHeaderLine( 'Accept' ) );
	}

	/**
	 * Test getHeaders preserves original case.
	 *
	 * @ticket TBD
	 */
	public function test_get_headers_preserves_case() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'Content-Type', 'application/json' );
		$headers = $request->getHeaders();

		$this->assertArrayHasKey( 'Content-Type', $headers );
		$this->assertArrayHasKey( 'Host', $headers );
	}

	/**
	 * Test withHeader replaces existing header.
	 *
	 * @ticket TBD
	 */
	public function test_with_header_replaces() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'Accept', 'text/html' );
		$new     = $request->withHeader( 'accept', 'application/json' );

		$this->assertNotSame( $request, $new );
		$this->assertSame( array( 'application/json' ), $new->getHeader( 'Accept' ) );
	}

	/**
	 * Test withAddedHeader appends to existing header.
	 *
	 * @ticket TBD
	 */
	public function test_with_added_header_appends() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'Accept', 'text/html' );
		$new     = $request->withAddedHeader( 'Accept', 'application/json' );

		$this->assertNotSame( $request, $new );
		$this->assertSame( array( 'text/html', 'application/json' ), $new->getHeader( 'Accept' ) );
	}

	/**
	 * Test withAddedHeader creates new header if not present.
	 *
	 * @ticket TBD
	 */
	public function test_with_added_header_creates() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$new     = $request->withAddedHeader( 'X-Custom', 'value' );

		$this->assertSame( array( 'value' ), $new->getHeader( 'X-Custom' ) );
	}

	/**
	 * Test withoutHeader removes header case-insensitively.
	 *
	 * @ticket TBD
	 */
	public function test_without_header() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$request = $request->withHeader( 'X-Custom', 'value' );
		$new     = $request->withoutHeader( 'x-custom' );

		$this->assertNotSame( $request, $new );
		$this->assertFalse( $new->hasHeader( 'X-Custom' ) );
		$this->assertTrue( $request->hasHeader( 'X-Custom' ) );
	}

	/**
	 * Test default body is empty stream.
	 *
	 * @ticket TBD
	 */
	public function test_default_body() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$this->assertSame( '', (string) $request->getBody() );
	}

	/**
	 * Test withBody returns a new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_body() {
		$request = new WP_AI_Client_PSR7_Request( 'POST', 'https://example.com' );
		$body    = new WP_AI_Client_PSR7_Stream( '{"key":"value"}' );
		$new     = $request->withBody( $body );

		$this->assertNotSame( $request, $new );
		$this->assertSame( '{"key":"value"}', (string) $new->getBody() );
		$this->assertSame( '', (string) $request->getBody() );
	}

	/**
	 * Test request target derived from URI path and query.
	 *
	 * @ticket TBD
	 */
	public function test_request_target_from_uri() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com/path?query=1' );
		$this->assertSame( '/path?query=1', $request->getRequestTarget() );
	}

	/**
	 * Test request target defaults to / for empty path.
	 *
	 * @ticket TBD
	 */
	public function test_request_target_defaults_to_slash() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$this->assertSame( '/', $request->getRequestTarget() );
	}

	/**
	 * Test withRequestTarget overrides URI-derived target.
	 *
	 * @ticket TBD
	 */
	public function test_with_request_target() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com/path' );
		$new     = $request->withRequestTarget( '*' );

		$this->assertNotSame( $request, $new );
		$this->assertSame( '*', $new->getRequestTarget() );
		$this->assertSame( '/path', $request->getRequestTarget() );
	}

	/**
	 * Test getMethod returns the HTTP method.
	 *
	 * @ticket TBD
	 */
	public function test_get_method() {
		$request = new WP_AI_Client_PSR7_Request( 'DELETE', 'https://example.com' );
		$this->assertSame( 'DELETE', $request->getMethod() );
	}

	/**
	 * Test withMethod returns a new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_method() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$new     = $request->withMethod( 'PUT' );

		$this->assertNotSame( $request, $new );
		$this->assertSame( 'PUT', $new->getMethod() );
		$this->assertSame( 'GET', $request->getMethod() );
	}

	/**
	 * Test getUri returns the URI instance.
	 *
	 * @ticket TBD
	 */
	public function test_get_uri() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com/path' );
		$uri     = $request->getUri();

		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\UriInterface::class,
			$uri
		);
		$this->assertSame( 'example.com', $uri->getHost() );
	}

	/**
	 * Test withUri updates the Host header.
	 *
	 * @ticket TBD
	 */
	public function test_with_uri_updates_host() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$new_uri = new WP_AI_Client_PSR7_Uri( 'https://other.com/path' );
		$new     = $request->withUri( $new_uri );

		$this->assertNotSame( $request, $new );
		$this->assertSame( 'other.com', $new->getHeaderLine( 'Host' ) );
		$this->assertSame( 'example.com', $request->getHeaderLine( 'Host' ) );
	}

	/**
	 * Test withUri with preserveHost keeps original Host header.
	 *
	 * @ticket TBD
	 */
	public function test_with_uri_preserve_host() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', 'https://example.com' );
		$new_uri = new WP_AI_Client_PSR7_Uri( 'https://other.com/path' );
		$new     = $request->withUri( $new_uri, true );

		$this->assertSame( 'example.com', $new->getHeaderLine( 'Host' ) );
	}

	/**
	 * Test withUri with preserveHost sets Host when original is empty.
	 *
	 * @ticket TBD
	 */
	public function test_with_uri_preserve_host_sets_if_empty() {
		$request = new WP_AI_Client_PSR7_Request( 'GET', '/relative' );
		$new_uri = new WP_AI_Client_PSR7_Uri( 'https://example.com/path' );
		$new     = $request->withUri( $new_uri, true );

		$this->assertSame( 'example.com', $new->getHeaderLine( 'Host' ) );
	}
}
