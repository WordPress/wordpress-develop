<?php

/**
 * Tests for the WP_Http_Encoding class.
 *
 * @group http
 *
 * @coversDefaultClass WP_Http_Encoding
 */
class Tests_HTTP_wpHttpEncoding extends WP_UnitTestCase {

	const CONTENT = 'The quick brown fox jumps over the lazy dog. The quick brown fox jumps over the lazy dog.';

	/**
	 * Builds a gzip stream with the given header flags and optional header fields.
	 *
	 * @param int    $flags  Gzip header flags.
	 * @param string $fields Optional header fields that follow the fixed ten-byte header.
	 * @return string Gzip stream.
	 */
	private static function build_gzip_stream( $flags, $fields ) {
		$header  = "\x1f\x8b\x08" . chr( $flags ) . "\x00\x00\x00\x00\x00\x03";
		$trailer = pack( 'V', crc32( self::CONTENT ) ) . pack( 'V', strlen( self::CONTENT ) );

		return $header . $fields . gzdeflate( self::CONTENT ) . $trailer;
	}

	/**
	 * Tests that compress() returns raw deflate data.
	 *
	 * @ticket 65819
	 *
	 * @covers ::compress
	 */
	public function test_compress_returns_raw_deflate_data() {
		$compressed = WP_Http_Encoding::compress( self::CONTENT );

		$this->assertNotSame( self::CONTENT, $compressed, 'The content should be compressed.' );
		$this->assertLessThan( strlen( self::CONTENT ), strlen( $compressed ), 'The compressed content should be shorter.' );
		$this->assertSame( self::CONTENT, gzinflate( $compressed ), 'The compressed content should be raw deflate data.' );
	}

	/**
	 * Tests that compress() respects the compression level.
	 *
	 * @ticket 65819
	 *
	 * @covers ::compress
	 */
	public function test_compress_uses_compression_level() {
		$stored = WP_Http_Encoding::compress( self::CONTENT, 0 );

		$this->assertStringContainsString( self::CONTENT, $stored, 'Level 0 should store the content without compressing it.' );
		$this->assertSame( self::CONTENT, gzinflate( $stored ), 'The stored content should be raw deflate data.' );
	}

	/**
	 * Tests that decompress() handles each supported compression format.
	 *
	 * @ticket 65819
	 *
	 * @covers ::decompress
	 * @covers ::compatible_gzinflate
	 *
	 * @dataProvider data_compressed_content
	 *
	 * @param string $compressed Compressed content.
	 */
	public function test_decompress( $compressed ) {
		$this->assertSame( self::CONTENT, WP_Http_Encoding::decompress( $compressed ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_compressed_content() {
		return array(
			'raw deflate'                     => array( gzdeflate( self::CONTENT ) ),
			'raw deflate without compression' => array( gzdeflate( self::CONTENT, 0 ) ),
			'zlib'                            => array( gzcompress( self::CONTENT ) ),
			'gzip'                            => array( gzencode( self::CONTENT ) ),
			'gzip with a file name'           => array( self::build_gzip_stream( 8, "content.txt\0" ) ),
			'WP_Http_Encoding::compress()'    => array( WP_Http_Encoding::compress( self::CONTENT ) ),
		);
	}

	/**
	 * Tests that decompress() returns content it cannot decompress unchanged.
	 *
	 * @ticket 65819
	 *
	 * @covers ::decompress
	 *
	 * @dataProvider data_content_that_is_not_compressed
	 *
	 * @param mixed $content Content.
	 */
	public function test_decompress_returns_uncompressed_content_unchanged( $content ) {
		$this->assertSame( $content, WP_Http_Encoding::decompress( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content_that_is_not_compressed() {
		return array(
			'plain text'   => array( self::CONTENT ),
			'HTML'         => array( '<!DOCTYPE html><html><body>Hello</body></html>' ),
			'empty string' => array( '' ),
			'zero string'  => array( '0' ),
			'false'        => array( false ),
			'null'         => array( null ),
		);
	}

	/**
	 * Tests that compatible_gzinflate() skips the optional gzip header fields.
	 *
	 * @ticket 65819
	 *
	 * @covers ::compatible_gzinflate
	 *
	 * @dataProvider data_gzip_streams
	 *
	 * @param string $gz_data Gzip stream.
	 */
	public function test_compatible_gzinflate_decodes_gzip_streams( $gz_data ) {
		$this->assertSame( self::CONTENT, WP_Http_Encoding::compatible_gzinflate( $gz_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_gzip_streams() {
		return array(
			'gzencode()'                             => array( gzencode( self::CONTENT ) ),
			'no optional fields'                     => array( self::build_gzip_stream( 0, '' ) ),
			'header checksum'                        => array( self::build_gzip_stream( 2, "\x12\x34" ) ),
			'file name'                              => array( self::build_gzip_stream( 8, "content.txt\0" ) ),
			'comment'                                => array( self::build_gzip_stream( 16, "A comment\0" ) ),
			'file name, comment and header checksum' => array( self::build_gzip_stream( 26, "content.txt\0" . "A comment\0" . "\x12\x34" ) ),
			'zlib stream with a two-byte header'     => array( gzcompress( self::CONTENT ) ),
		);
	}

	/**
	 * Tests that compatible_gzinflate() returns false for data it cannot decode.
	 *
	 * @ticket 65819
	 *
	 * @covers ::compatible_gzinflate
	 *
	 * @dataProvider data_invalid_gzip_streams
	 *
	 * @param string $gz_data Data.
	 */
	public function test_compatible_gzinflate_returns_false_for_invalid_data( $gz_data ) {
		$this->assertFalse( WP_Http_Encoding::compatible_gzinflate( $gz_data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_gzip_streams() {
		return array(
			'plain text'                  => array( self::CONTENT ),
			'empty string'                => array( '' ),
			'gzip header without content' => array( "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\x03" ),
		);
	}

	/**
	 * Tests the Accept-Encoding header value for different request arguments.
	 *
	 * @ticket 65819
	 *
	 * @covers ::accept_encoding
	 *
	 * @dataProvider data_accept_encoding
	 *
	 * @param array  $args     Request arguments.
	 * @param string $expected Expected header value.
	 */
	public function test_accept_encoding( $args, $expected ) {
		$this->assertSame( $expected, WP_Http_Encoding::accept_encoding( 'http://example.org/', $args ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_accept_encoding() {
		$all = 'deflate;q=1.0, compress;q=0.5, gzip;q=0.5';

		return array(
			'decompression enabled'         => array(
				array(
					'decompress' => true,
					'stream'     => false,
				),
				$all,
			),
			'limit_response_size is null'   => array(
				array(
					'decompress'          => true,
					'stream'              => false,
					'limit_response_size' => null,
				),
				$all,
			),
			'decompression disabled'        => array(
				array(
					'decompress' => false,
					'stream'     => false,
				),
				'',
			),
			'streaming to a file'           => array(
				array(
					'decompress' => true,
					'stream'     => true,
				),
				'',
			),
			'limited response size'         => array(
				array(
					'decompress'          => true,
					'stream'              => false,
					'limit_response_size' => 1024,
				),
				'',
			),
			'response size limited to zero' => array(
				array(
					'decompress'          => true,
					'stream'              => false,
					'limit_response_size' => 0,
				),
				'',
			),
		);
	}

	/**
	 * Tests that the accepted encodings are filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::accept_encoding
	 */
	public function test_accept_encoding_is_filterable() {
		$args = array(
			'decompress' => true,
			'stream'     => false,
		);

		$filter = new MockAction();
		add_filter( 'wp_http_accept_encoding', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'wp_http_accept_encoding',
			static function ( $type ) {
				return array( $type[0], 'br;q=0.9' );
			},
			20
		);

		$this->assertSame( 'deflate;q=1.0, br;q=0.9', WP_Http_Encoding::accept_encoding( 'http://example.org/path', $args ), 'The filtered encodings should be used.' );
		$this->assertSame(
			array(
				array(
					array( 'deflate;q=1.0', 'compress;q=0.5', 'gzip;q=0.5' ),
					'http://example.org/path',
					$args,
				),
			),
			$filter->get_args(),
			'The filter should receive the encodings, the URL and the request arguments.'
		);
	}

	/**
	 * Tests the content encoding used for compressed request bodies.
	 *
	 * @ticket 65819
	 *
	 * @covers ::content_encoding
	 */
	public function test_content_encoding() {
		$this->assertSame( 'deflate', WP_Http_Encoding::content_encoding() );
	}

	/**
	 * Tests whether response headers indicate an encoded body.
	 *
	 * @ticket 65819
	 *
	 * @covers ::should_decode
	 *
	 * @dataProvider data_should_decode
	 *
	 * @param mixed $headers  Response headers.
	 * @param bool  $expected Whether the body should be decoded.
	 */
	public function test_should_decode( $headers, $expected ) {
		$this->assertSame( $expected, WP_Http_Encoding::should_decode( $headers ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_decode() {
		return array(
			'array with content-encoding'          => array( array( 'content-encoding' => 'gzip' ), true ),
			'array with other headers'             => array(
				array(
					'content-type'     => 'text/html',
					'content-encoding' => 'deflate',
				),
				true,
			),
			'array without content-encoding'       => array( array( 'content-type' => 'text/html' ), false ),
			'array with empty content-encoding'    => array( array( 'content-encoding' => '' ), false ),
			'array with null content-encoding'     => array( array( 'content-encoding' => null ), false ),
			'array with zero content-encoding'     => array( array( 'content-encoding' => '0' ), false ),
			'array with capitalized header name'   => array( array( 'Content-Encoding' => 'gzip' ), false ),
			'empty array'                          => array( array(), false ),
			'string with content-encoding'         => array( "Content-Type: text/html\r\nContent-Encoding: gzip\r\n", true ),
			'string with lowercase header name'    => array( 'content-encoding: gzip', true ),
			'string with uppercase header name'    => array( 'CONTENT-ENCODING: gzip', true ),
			'string without content-encoding'      => array( "Content-Type: text/html\r\n", false ),
			'string with header name but no colon' => array( 'content-encoding', false ),
			'empty string'                         => array( '', false ),
			'null'                                 => array( null, false ),
			'false'                                => array( false, false ),
			'true'                                 => array( true, false ),
			'integer'                              => array( 1, false ),
		);
	}

	/**
	 * Tests that compression is available when the zlib functions exist.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_available
	 *
	 * @requires function gzinflate
	 */
	public function test_is_available() {
		$this->assertTrue( WP_Http_Encoding::is_available() );
	}
}
