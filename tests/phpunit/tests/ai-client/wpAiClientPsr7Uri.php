<?php
/**
 * Tests for WP_AI_Client_PSR7_Uri.
 *
 * @group ai-client
 * @covers WP_AI_Client_PSR7_Uri
 */
class Tests_AI_Client_PSR7_Uri extends WP_UnitTestCase {

	/**
	 * Test that the URI implements the scoped PSR-7 UriInterface.
	 *
	 * @ticket TBD
	 */
	public function test_implements_uri_interface() {
		$uri = new WP_AI_Client_PSR7_Uri();
		$this->assertInstanceOf(
			WordPress\AiClientDependencies\Psr\Http\Message\UriInterface::class,
			$uri
		);
	}

	/**
	 * Test full URI parsing.
	 *
	 * @ticket TBD
	 */
	public function test_full_uri_parsing() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://user:pass@example.com:8080/path?query=1#frag' );

		$this->assertSame( 'https', $uri->getScheme() );
		$this->assertSame( 'user:pass', $uri->getUserInfo() );
		$this->assertSame( 'example.com', $uri->getHost() );
		$this->assertSame( 8080, $uri->getPort() );
		$this->assertSame( '/path', $uri->getPath() );
		$this->assertSame( 'query=1', $uri->getQuery() );
		$this->assertSame( 'frag', $uri->getFragment() );
	}

	/**
	 * Test empty constructor produces empty URI.
	 *
	 * @ticket TBD
	 */
	public function test_empty_constructor() {
		$uri = new WP_AI_Client_PSR7_Uri();

		$this->assertSame( '', $uri->getScheme() );
		$this->assertSame( '', $uri->getHost() );
		$this->assertSame( '', $uri->getPath() );
		$this->assertSame( '', $uri->getQuery() );
		$this->assertSame( '', $uri->getFragment() );
		$this->assertSame( '', $uri->getUserInfo() );
		$this->assertNull( $uri->getPort() );
	}

	/**
	 * Test scheme is lowercased.
	 *
	 * @ticket TBD
	 */
	public function test_scheme_lowercase() {
		$uri = new WP_AI_Client_PSR7_Uri( 'HTTPS://example.com' );
		$this->assertSame( 'https', $uri->getScheme() );
	}

	/**
	 * Test authority with all parts.
	 *
	 * @ticket TBD
	 */
	public function test_authority_with_all_parts() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://user:pass@example.com:8080/path' );
		$this->assertSame( 'user:pass@example.com:8080', $uri->getAuthority() );
	}

	/**
	 * Test authority without user info.
	 *
	 * @ticket TBD
	 */
	public function test_authority_without_user() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com:8080/path' );
		$this->assertSame( 'example.com:8080', $uri->getAuthority() );
	}

	/**
	 * Test authority without port.
	 *
	 * @ticket TBD
	 */
	public function test_authority_without_port() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com/path' );
		$this->assertSame( 'example.com', $uri->getAuthority() );
	}

	/**
	 * Test authority is empty when host is empty.
	 *
	 * @ticket TBD
	 */
	public function test_authority_empty_when_no_host() {
		$uri = new WP_AI_Client_PSR7_Uri();
		$this->assertSame( '', $uri->getAuthority() );
	}

	/**
	 * Test user info with password.
	 *
	 * @ticket TBD
	 */
	public function test_user_info_with_password() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://user:pass@example.com' );
		$this->assertSame( 'user:pass', $uri->getUserInfo() );
	}

	/**
	 * Test user info without password.
	 *
	 * @ticket TBD
	 */
	public function test_user_info_without_password() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://user@example.com' );
		$this->assertSame( 'user', $uri->getUserInfo() );
	}

	/**
	 * Test host is lowercased.
	 *
	 * @ticket TBD
	 */
	public function test_host_lowercase() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://EXAMPLE.COM' );
		$this->assertSame( 'example.com', $uri->getHost() );
	}

	/**
	 * Test non-standard port is returned.
	 *
	 * @ticket TBD
	 */
	public function test_non_standard_port_returned() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com:9090' );
		$this->assertSame( 9090, $uri->getPort() );
	}

	/**
	 * Test standard port for HTTP returns null.
	 *
	 * @ticket TBD
	 */
	public function test_standard_http_port_returns_null() {
		$uri = new WP_AI_Client_PSR7_Uri( 'http://example.com:80' );
		$this->assertNull( $uri->getPort() );
	}

	/**
	 * Test standard port for HTTPS returns null.
	 *
	 * @ticket TBD
	 */
	public function test_standard_https_port_returns_null() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com:443' );
		$this->assertNull( $uri->getPort() );
	}

	/**
	 * Test port is null when unset.
	 *
	 * @ticket TBD
	 */
	public function test_port_null_when_unset() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$this->assertNull( $uri->getPort() );
	}

	/**
	 * Test withScheme returns new instance and lowercases.
	 *
	 * @ticket TBD
	 */
	public function test_with_scheme() {
		$uri = new WP_AI_Client_PSR7_Uri( 'http://example.com' );
		$new = $uri->withScheme( 'HTTPS' );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( 'https', $new->getScheme() );
		$this->assertSame( 'http', $uri->getScheme() );
	}

	/**
	 * Test withUserInfo returns new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_user_info() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withUserInfo( 'user', 'pass' );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( 'user:pass', $new->getUserInfo() );
		$this->assertSame( '', $uri->getUserInfo() );
	}

	/**
	 * Test withUserInfo without password.
	 *
	 * @ticket TBD
	 */
	public function test_with_user_info_without_password() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withUserInfo( 'user' );

		$this->assertSame( 'user', $new->getUserInfo() );
	}

	/**
	 * Test withHost returns new instance and lowercases.
	 *
	 * @ticket TBD
	 */
	public function test_with_host() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withHost( 'OTHER.COM' );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( 'other.com', $new->getHost() );
		$this->assertSame( 'example.com', $uri->getHost() );
	}

	/**
	 * Test withPort returns new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_port() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withPort( 9090 );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( 9090, $new->getPort() );
	}

	/**
	 * Test withPort null clears port.
	 *
	 * @ticket TBD
	 */
	public function test_with_port_null() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com:8080' );
		$new = $uri->withPort( null );

		$this->assertNull( $new->getPort() );
	}

	/**
	 * Test withPath returns new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_path() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com/old' );
		$new = $uri->withPath( '/new' );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( '/new', $new->getPath() );
		$this->assertSame( '/old', $uri->getPath() );
	}

	/**
	 * Test withQuery returns new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_query() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withQuery( 'foo=bar' );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( 'foo=bar', $new->getQuery() );
	}

	/**
	 * Test withFragment returns new instance.
	 *
	 * @ticket TBD
	 */
	public function test_with_fragment() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withFragment( 'section' );

		$this->assertNotSame( $uri, $new );
		$this->assertSame( 'section', $new->getFragment() );
	}

	/**
	 * Test __toString reconstructs the full URI.
	 *
	 * @ticket TBD
	 */
	public function test_to_string_reconstruction() {
		$original = 'https://user:pass@example.com:8080/path?query=1#frag';
		$uri      = new WP_AI_Client_PSR7_Uri( $original );
		$this->assertSame( $original, (string) $uri );
	}

	/**
	 * Test path gets leading slash when authority is present.
	 *
	 * @ticket TBD
	 */
	public function test_path_slash_prepended_with_authority() {
		$uri = new WP_AI_Client_PSR7_Uri( 'https://example.com' );
		$new = $uri->withPath( 'no-slash' );

		$this->assertSame( 'https://example.com/no-slash', (string) $new );
	}

	/**
	 * Test double-slash in path collapsed when no authority.
	 *
	 * @ticket TBD
	 */
	public function test_double_slash_collapsed_without_authority() {
		$uri = new WP_AI_Client_PSR7_Uri();
		$new = $uri->withPath( '//path' );

		$this->assertSame( '/path', (string) $new );
	}
}
