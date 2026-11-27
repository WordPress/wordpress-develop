<?php

/**
 * Tests for the WP_Http::chunkTransferDecode() method.
 *
 * @group http
 *
 * @covers WP_Http::chunkTransferDecode
 */
class Tests_HTTP_WpHttpChunkTransferDecode extends WP_UnitTestCase {

	/**
	 * Tests that a chunked body is decoded.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_chunked_bodies
	 *
	 * @param string $body     Body with the chunked transfer encoding.
	 * @param string $expected Decoded body.
	 */
	public function test_should_decode_a_chunked_body( $body, $expected ) {
		$this->assertSame( $expected, WP_Http::chunkTransferDecode( $body ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_chunked_bodies() {
		return array(
			'one chunk'                      => array( "5\r\nHello\r\n0\r\n\r\n", 'Hello' ),
			'hexadecimal size in lowercase'  => array( "a\r\n0123456789\r\n0\r\n\r\n", '0123456789' ),
			'hexadecimal size in uppercase'  => array( "A\r\n0123456789\r\n0\r\n\r\n", '0123456789' ),
			'size with several digits'       => array( "1a\r\n" . str_repeat( 'x', 26 ) . "\r\n0\r\n\r\n", str_repeat( 'x', 26 ) ),
			'chunk extension'                => array( "5;name=value\r\nHello\r\n0\r\n\r\n", 'Hello' ),
			'chunk with line breaks'         => array( "c\r\nline1\r\nline2\r\n0\r\n\r\n", "line1\r\nline2" ),
			'chunk with the size of a chunk' => array( "6\r\n3\r\nabc\r\n0\r\n\r\n", "3\r\nabc" ),
			'last chunk without the trailer' => array( "3\r\nabc\r\n0", 'abc' ),
		);
	}

	/**
	 * Tests that a body that is not chunked is returned as it is.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_bodies_that_are_not_chunked
	 *
	 * @param string $body Body that is not chunked.
	 */
	public function test_should_return_a_body_that_is_not_chunked_as_it_is( $body ) {
		$this->assertSame( $body, WP_Http::chunkTransferDecode( $body ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_bodies_that_are_not_chunked() {
		return array(
			'empty string'     => array( '' ),
			'plain text'       => array( 'Hello World' ),
			'html'             => array( '<html><body>Hello</body></html>' ),
			'json'             => array( '{"a":1}' ),
			'without a size'   => array( "\r\nHello\r\n0\r\n\r\n" ),
			'line breaks only' => array( "Hello\r\nWorld" ),
		);
	}

	/**
	 * Tests that a body that starts like a chunked body, but is malformed, is returned as it is.
	 *
	 * @ticket 66507
	 *
	 * @dataProvider data_malformed_bodies
	 *
	 * @param string $body Malformed body.
	 */
	public function test_should_return_a_malformed_body_as_it_is( $body ) {
		$this->assertSame( $body, WP_Http::chunkTransferDecode( $body ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_malformed_bodies() {
		return array(
			'a chunk with a size of zero in the middle'   => array( "5\r\nHello\r\n0\r\n\r\n6\r\n World\r\n0\r\n\r\n" ),
			'a chunk that is followed by text'            => array( "5\r\nHello\r\nnot a size" ),
			'a size with a line break and no data'        => array( "5\r\n" ),
			'a size that is not followed by a line break' => array( '5Hello' ),
		);
	}
}
