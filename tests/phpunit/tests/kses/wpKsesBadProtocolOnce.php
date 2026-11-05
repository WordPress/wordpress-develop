<?php

/**
 * Tests for wp_kses_bad_protocol_once().
 *
 * @group kses
 *
 * @covers ::wp_kses_bad_protocol_once
 * @covers ::wp_kses_bad_protocol_once2
 */
class Tests_Kses_wpKsesBadProtocolOnce extends WP_UnitTestCase {

	/**
	 * Tests that the protocol is kept when it is allowed, and removed when it is not.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_protocols
	 *
	 * @param string   $content           URL to filter.
	 * @param string[] $allowed_protocols Allowed protocols.
	 * @param string   $expected          Expected URL.
	 */
	public function test_should_keep_allowed_protocols_and_remove_others( $content, $allowed_protocols, $expected ) {
		$this->assertSame( $expected, wp_kses_bad_protocol_once( $content, $allowed_protocols ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_protocols() {
		$web = array( 'http', 'https' );

		return array(
			'allowed protocol'                        => array( 'http://example.org/', $web, 'http://example.org/' ),
			'another allowed protocol'                => array( 'https://example.org/', $web, 'https://example.org/' ),
			'protocol that is not allowed'            => array( 'javascript:alert(1)', $web, 'alert(1)' ),
			'data protocol that is not allowed'       => array( 'data:text/html,<p>x</p>', $web, 'text/html,<p>x</p>' ),
			'no allowed protocols'                    => array( 'http://example.org/', array(), '//example.org/' ),
			'uppercase protocol is lowercased'        => array( 'HTTP://example.org/', $web, 'http://example.org/' ),
			'mixed case protocol is lowercased'       => array( 'HtTpS://example.org/', $web, 'https://example.org/' ),
			'mixed case protocol that is not allowed' => array( 'JaVaScRiPt:alert(1)', $web, 'alert(1)' ),
			'allowed protocols in uppercase'          => array( 'http://example.org/', array( 'HTTP' ), 'http://example.org/' ),
			'mailto'                                  => array( 'mailto:me@example.org', array( 'mailto' ), 'mailto:me@example.org' ),
			'mailto that is not allowed'              => array( 'mailto:me@example.org', $web, 'me@example.org' ),
			'whitespace after the colon is trimmed'   => array( 'javascript: alert(1)', $web, 'alert(1)' ),
			'only the first colon is used'            => array( 'javascript:alert("a:b")', $web, 'alert("a:b")' ),
		);
	}

	/**
	 * Tests that content without a protocol is not changed.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_content_without_protocol
	 *
	 * @param string $content URL to filter.
	 */
	public function test_should_not_change_content_without_protocol( $content ) {
		$this->assertSame( $content, wp_kses_bad_protocol_once( $content, array( 'http', 'https' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_without_protocol() {
		return array(
			'empty string'                  => array( '' ),
			'domain and path'               => array( 'example.org/path' ),
			'relative path'                 => array( '/path/to/page' ),
			'protocol relative URL'         => array( '//example.org/path' ),
			'fragment'                      => array( '#section' ),
			'query'                         => array( '?a=b' ),
			'colon after a query'           => array( 'page/?a=b:c' ),
			'colon after a slash and query' => array( 'path/to/?a=b:c' ),
		);
	}

	/**
	 * Tests that encoded colons and whitespace or NULL characters in the protocol are handled.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_obfuscated_protocols
	 *
	 * @param string $content  URL to filter.
	 * @param string $expected Expected URL.
	 */
	public function test_should_handle_obfuscated_protocols( $content, $expected ) {
		$this->assertSame( $expected, wp_kses_bad_protocol_once( $content, array( 'http', 'https' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_obfuscated_protocols() {
		return array(
			'decimal colon'                                => array( 'javascript&#58;alert(1)', 'alert(1)' ),
			'decimal colon with leading zeros'             => array( 'javascript&#0058;alert(1)', 'alert(1)' ),
			'decimal colon without semicolon'              => array( 'javascript&#58alert(1)', 'alert(1)' ),
			'hexadecimal colon'                            => array( 'javascript&#x3a;alert(1)', 'alert(1)' ),
			'uppercase hexadecimal colon'                  => array( 'javascript&#x3A;alert(1)', 'alert(1)' ),
			'hexadecimal colon without semicolon'          => array( 'javascript&#x3a(1)', '(1)' ),
			'hexadecimal digit after the colon'            => array( 'javascript&#x3aalert(1)', 'javascript&#x3aalert(1)' ),
			'named colon'                                  => array( 'javascript&colon;alert(1)', 'alert(1)' ),
			'uppercase named colon'                        => array( 'javascript&COLON;alert(1)', 'alert(1)' ),
			'allowed protocol with encoded colon'          => array( 'http&#58;//example.org/', 'http://example.org/' ),
			'allowed protocol with named colon'            => array( 'https&colon;//example.org/', 'https://example.org/' ),
			'whitespace in the protocol'                   => array( 'java script:alert(1)', 'alert(1)' ),
			'tab in the protocol'                          => array( "java\tscript:alert(1)", 'alert(1)' ),
			'new line in the protocol'                     => array( "java\nscript:alert(1)", 'alert(1)' ),
			'NULL character in the protocol'               => array( "java\0script:alert(1)", 'alert(1)' ),
			'encoded character in the protocol'            => array( 'java&#115;cript:alert(1)', 'alert(1)' ),
			'whitespace in an allowed protocol'            => array( 'ht tp://example.org/', 'http://example.org/' ),
			'NULL character in an allowed protocol'        => array( "ht\0tp://example.org/", 'http://example.org/' ),
			'encoded character in an allowed protocol'     => array( 'h&#116;tp://example.org/', 'http://example.org/' ),
			'hexadecimal character in an allowed protocol' => array( 'h&#x74;tp://example.org/', 'http://example.org/' ),
		);
	}

	/**
	 * Tests that a `feed:` protocol is followed by the protocol of the feed URL.
	 *
	 * @ticket 66492
	 *
	 * @dataProvider data_feed_protocols
	 *
	 * @param string   $content           URL to filter.
	 * @param string[] $allowed_protocols Allowed protocols.
	 * @param string   $expected          Expected URL.
	 */
	public function test_should_check_protocol_of_feed_urls( $content, $allowed_protocols, $expected ) {
		$this->assertSame( $expected, wp_kses_bad_protocol_once( $content, $allowed_protocols ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_feed_protocols() {
		return array(
			'feed with an allowed protocol'            => array( 'feed:http://example.org/feed', array( 'feed', 'http' ), 'feed:http://example.org/feed' ),
			'feed with a protocol that is not allowed' => array( 'feed:javascript:alert(1)', array( 'feed', 'http' ), 'feed:alert(1)' ),
			'feed that is not allowed'                 => array( 'feed:http://example.org/feed', array( 'http' ), 'http://example.org/feed' ),
			'feed nested twice'                        => array( 'feed:feed:http://example.org/feed', array( 'feed', 'http' ), 'feed:feed:http://example.org/feed' ),
			'feed nested three times'                  => array( 'feed:feed:feed:http://example.org/feed', array( 'feed', 'http' ), '' ),
			'feed nested many times'                   => array( 'feed:feed:feed:feed:feed:http://example.org/', array( 'feed', 'http' ), '' ),
		);
	}

	/**
	 * Tests that the count argument limits how deep a `feed:` protocol is followed.
	 *
	 * @ticket 66492
	 */
	public function test_should_stop_following_feed_protocols_after_the_count_is_reached() {
		$allowed = array( 'feed', 'http' );

		$this->assertSame( 'feed:http://example.org/', wp_kses_bad_protocol_once( 'feed:http://example.org/', $allowed, 2 ) );
		$this->assertSame( '', wp_kses_bad_protocol_once( 'feed:http://example.org/', $allowed, 3 ) );
	}
}
