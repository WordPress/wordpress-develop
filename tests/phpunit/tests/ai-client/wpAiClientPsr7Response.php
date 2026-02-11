<?php
/**
 * Tests for WP_AI_Client_PSR7_Response.
 *
 * @group ai-client
 * @covers WP_AI_Client_PSR7_Response
 */
class Tests_AI_Client_PSR7_Response extends WP_UnitTestCase {

	/**
	 * Test that the response implements the scoped PSR-7 ResponseInterface.
	 *
	 * @ticket TBD
	 */
	public function test_implements_response_interface() {
		$response = new WP_AI_Client_PSR7_Response();
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface::class,
			$response
		);
	}

	/**
	 * Test default status code is 200.
	 *
	 * @ticket TBD
	 */
	public function test_default_status_code() {
		$response = new WP_AI_Client_PSR7_Response();
		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * Test custom status code and reason phrase.
	 *
	 * @ticket TBD
	 */
	public function test_custom_status_and_reason() {
		$response = new WP_AI_Client_PSR7_Response( 404, 'Not Found' );
		$this->assertSame( 404, $response->getStatusCode() );
		$this->assertSame( 'Not Found', $response->getReasonPhrase() );
	}

	/**
	 * Test withStatus returns a new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_status() {
		$response = new WP_AI_Client_PSR7_Response();
		$new      = $response->withStatus( 301, 'Moved Permanently' );

		$this->assertNotSame( $response, $new );
		$this->assertSame( 301, $new->getStatusCode() );
		$this->assertSame( 'Moved Permanently', $new->getReasonPhrase() );
		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * Test withStatus clears reason phrase when not provided.
	 *
	 * @ticket TBD
	 */
	public function test_with_status_clears_reason() {
		$response = new WP_AI_Client_PSR7_Response( 200, 'OK' );
		$new      = $response->withStatus( 204 );

		$this->assertSame( 204, $new->getStatusCode() );
		$this->assertSame( '', $new->getReasonPhrase() );
	}

	/**
	 * Test default protocol version is 1.1.
	 *
	 * @ticket TBD
	 */
	public function test_default_protocol_version() {
		$response = new WP_AI_Client_PSR7_Response();
		$this->assertSame( '1.1', $response->getProtocolVersion() );
	}

	/**
	 * Test withProtocolVersion returns a new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_protocol_version() {
		$response = new WP_AI_Client_PSR7_Response();
		$new      = $response->withProtocolVersion( '2.0' );

		$this->assertNotSame( $response, $new );
		$this->assertSame( '2.0', $new->getProtocolVersion() );
		$this->assertSame( '1.1', $response->getProtocolVersion() );
	}

	/**
	 * Test hasHeader is case-insensitive.
	 *
	 * @ticket TBD
	 */
	public function test_has_header_case_insensitive() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Content-Type', 'text/html' );

		$this->assertTrue( $response->hasHeader( 'content-type' ) );
		$this->assertTrue( $response->hasHeader( 'CONTENT-TYPE' ) );
		$this->assertFalse( $response->hasHeader( 'X-Custom' ) );
	}

	/**
	 * Test getHeader returns values case-insensitively.
	 *
	 * @ticket TBD
	 */
	public function test_get_header() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Content-Type', 'text/html' );

		$this->assertSame( array( 'text/html' ), $response->getHeader( 'content-type' ) );
		$this->assertSame( array(), $response->getHeader( 'X-Missing' ) );
	}

	/**
	 * Test getHeaderLine returns comma-separated values.
	 *
	 * @ticket TBD
	 */
	public function test_get_header_line() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Accept', array( 'text/html', 'application/json' ) );

		$this->assertSame( 'text/html, application/json', $response->getHeaderLine( 'Accept' ) );
		$this->assertSame( '', $response->getHeaderLine( 'X-Missing' ) );
	}

	/**
	 * Test getHeaders preserves original case.
	 *
	 * @ticket TBD
	 */
	public function test_get_headers_preserves_case() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Content-Type', 'text/html' );
		$headers  = $response->getHeaders();

		$this->assertArrayHasKey( 'Content-Type', $headers );
		$this->assertSame( array( 'text/html' ), $headers['Content-Type'] );
	}

	/**
	 * Test withHeader replaces existing header.
	 *
	 * @ticket TBD
	 */
	public function test_with_header_replaces() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Content-Type', 'text/html' );
		$new      = $response->withHeader( 'content-type', 'application/json' );

		$this->assertSame( array( 'application/json' ), $new->getHeader( 'Content-Type' ) );
	}

	/**
	 * Test withAddedHeader appends to existing header.
	 *
	 * @ticket TBD
	 */
	public function test_with_added_header_appends() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Accept', 'text/html' );
		$new      = $response->withAddedHeader( 'Accept', 'application/json' );

		$this->assertNotSame( $response, $new );
		$this->assertSame( array( 'text/html', 'application/json' ), $new->getHeader( 'Accept' ) );
	}

	/**
	 * Test withAddedHeader creates new header if not present.
	 *
	 * @ticket TBD
	 */
	public function test_with_added_header_creates() {
		$response = new WP_AI_Client_PSR7_Response();
		$new      = $response->withAddedHeader( 'X-Custom', 'value' );

		$this->assertSame( array( 'value' ), $new->getHeader( 'X-Custom' ) );
	}

	/**
	 * Test withoutHeader removes header case-insensitively.
	 *
	 * @ticket TBD
	 */
	public function test_without_header() {
		$response = new WP_AI_Client_PSR7_Response();
		$response = $response->withHeader( 'Content-Type', 'text/html' );
		$new      = $response->withoutHeader( 'CONTENT-TYPE' );

		$this->assertNotSame( $response, $new );
		$this->assertFalse( $new->hasHeader( 'Content-Type' ) );
		$this->assertTrue( $response->hasHeader( 'Content-Type' ) );
	}

	/**
	 * Test default body is empty stream.
	 *
	 * @ticket TBD
	 */
	public function test_default_body() {
		$response = new WP_AI_Client_PSR7_Response();
		$this->assertSame( '', (string) $response->getBody() );
	}

	/**
	 * Test withBody returns a new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_body() {
		$response = new WP_AI_Client_PSR7_Response();
		$body     = new WP_AI_Client_PSR7_Stream( 'response body' );
		$new      = $response->withBody( $body );

		$this->assertNotSame( $response, $new );
		$this->assertSame( 'response body', (string) $new->getBody() );
		$this->assertSame( '', (string) $response->getBody() );
	}
}
