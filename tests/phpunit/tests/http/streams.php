<?php

require_once __DIR__ . '/base.php';

/**
 * @group http
 *
 * @ticket 63914
 */
class Tests_HTTP_streams extends WP_HTTP_UnitTestCase {
	public $transport = 'streams';

	/**
	 * Ensures a streamed response whose headers span more than one read is
	 * written to the destination file without any header bytes.
	 *
	 * @ticket 66484
	 *
	 * @covers WP_Http_Streams::request
	 */
	public function test_file_stream_headers_span_multiple_reads() {
		$this->require_file_stream_fixture();

		list( $res, $contents ) = $this->stream_fixture_to_file( $this->file_stream_large_headers_url );

		$this->assertNotWPError( $res );
		$this->assertSame( 'hello', $contents );
	}

	/**
	 * Ensures a streamed response whose header/body separator straddles the
	 * 4 KB read boundary is written to the destination file without any header
	 * bytes.
	 *
	 * @ticket 66484
	 *
	 * @covers WP_Http_Streams::request
	 */
	public function test_file_stream_header_separator_straddles_read_boundary() {
		$this->require_file_stream_fixture();

		$base_length = $this->get_response_header_length( $this->file_stream_large_headers_url );
		$fill        = 4098 - $base_length;
		$this->assertGreaterThan( 0, $fill );

		list( $res, $contents ) = $this->stream_fixture_to_file( $this->file_stream_large_headers_url . '?fill=' . $fill );

		$this->assertNotWPError( $res );
		$this->assertSame( 'hello', $contents );
	}

	/**
	 * Streams a fixture URL to a file and returns the response and file contents.
	 *
	 * @param string $url Fixture URL.
	 * @return array{0: array|WP_Error, 1: string} Response and file contents.
	 */
	private function stream_fixture_to_file( $url ) {
		$filename = wp_tempnam( 'wp-http-streams-66484' );
		$http     = new WP_Http_Streams();

		$res = $http->request(
			$url,
			array(
				'stream'          => true,
				'filename'        => $filename,
				'timeout'         => 30,
				'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( $res, '' );
		}

		$contents = file_get_contents( $res['filename'] );
		unlink( $res['filename'] );

		return array( $res, $contents );
	}

	/**
	 * Measures the length of a fixture response's header block.
	 *
	 * Requests the fixture with an empty `X-Fill` header and measures the raw
	 * response, so the length can be used to place the header/body separator at
	 * a chosen offset.
	 *
	 * @param string $url Fixture URL.
	 * @return int Header block length, including the trailing CRLFCRLF.
	 */
	private function get_response_header_length( $url ) {
		$parts = wp_parse_url( $url . '?fill=0' );
		$host  = $parts['host'];
		$port  = isset( $parts['port'] ) ? (int) $parts['port'] : 80;

		$socket = fsockopen( $host, $port, $errno, $errstr, 5 );
		if ( ! $socket ) {
			$this->fail( "Could not connect to the fixture server: $errstr" );
		}

		$request  = "GET {$parts['path']}?fill=0 HTTP/1.0\r\n";
		$request .= "Host: {$host}:{$port}\r\n";
		$request .= "\r\n";
		fwrite( $socket, $request );

		$response = '';
		while ( ! feof( $socket ) ) {
			$chunk = fread( $socket, 8192 );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$response .= $chunk;
			if ( false !== strpos( $response, "\r\n\r\n" ) ) {
				break;
			}
		}
		fclose( $socket );

		$separator = strpos( $response, "\r\n\r\n" );
		$this->assertNotFalse( $separator, 'The fixture response did not contain a header/body separator.' );

		return $separator + 4;
	}
}
