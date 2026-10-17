<?php

/**
 * Tests the legacy WP_Http_Streams transport against a local socket server.
 *
 * @group http
 * @covers WP_Http_Streams::request
 */
class Tests_HTTP_WpHttpStreams extends WP_UnitTestCase {
	/**
	 * @ticket 65817
	 * @dataProvider data_streaming_response
	 *
	 * @param string   $raw_response Raw response sent by the server.
	 * @param int|null $status       Expected HTTP status, or null to expect a WP_Error.
	 * @param string   $body         Expected downloaded body.
	 * @param bool     $stream       Whether to stream the response to a file.
	 */
	public function test_streaming_response( string $raw_response, ?int $status, string $body, bool $stream = true ): void {
		if ( ! function_exists( 'proc_open' ) || ! function_exists( 'stream_socket_server' ) ) {
			$this->markTestSkipped( 'proc_open() and stream_socket_server() are required.' );
		}

		require_once ABSPATH . WPINC . '/class-wp-http-streams.php';

		$command = array( PHP_BINARY, DIR_TESTDATA . '/http/raw-response.php', wp_json_encode( $raw_response ) );
		$process = proc_open(
			$command,
			array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ),
			$pipes
		);
		$this->assertIsResource( $process, 'The local fixture server should start.' );
		$filename = wp_tempnam();

		try {
			fclose( $pipes[0] );
			stream_set_timeout( $pipes[1], 5 );
			$address = trim( (string) fgets( $pipes[1] ) );
			$this->assertNotEmpty( $address, 'The local fixture server should publish its address.' );

			$transport = new WP_Http_Streams();
			$response  = $transport->request(
				'http://' . $address . '/',
				array(
					'stream'          => $stream,
					'filename'        => $filename,
					'timeout'         => 5,
					'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
				)
			);

			if ( null === $status ) {
				$this->assertWPError( $response );
				$this->assertSame( 'http_request_failed', $response->get_error_code() );
			} else {
				$this->assertNotWPError( $response );
				$this->assertSame( $status, $response['response']['code'] );
				$this->assertSame( $body, $stream ? file_get_contents( $filename ) : $response['body'] );
			}
		} finally {
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			proc_terminate( $process );
			proc_close( $process );
			unlink( $filename );
		}
	}

	/**
	 * Includes the legacy buffered behavior for empty responses and incomplete headers.
	 *
	 * @return array<non-falsy-string, array{ 0: string, 1: int|null, 2: string, 3?: bool }>
	 */
	public static function data_streaming_response(): array {
		return array(
			'empty response'              => array( '', null, '' ),
			'incomplete headers'          => array( "HTTP/1.0 200 OK\r\nContent-Type: text/plain\r\n", null, '' ),
			'normal response'             => array( "HTTP/1.0 200 OK\r\nContent-Length: 5\r\n\r\nhello", 200, 'hello' ),
			'empty body'                  => array( "HTTP/1.0 204 No Content\r\n\r\n", 204, '' ),
			'buffered empty response'     => array( '', 0, '', false ),
			'buffered incomplete headers' => array( "HTTP/1.0 200 OK\r\nContent-Type: text/plain\r\n", 200, '', false ),
			'buffered response'           => array( "HTTP/1.0 200 OK\r\nContent-Length: 5\r\n\r\nhello", 200, 'hello', false ),
			'buffered empty body'         => array( "HTTP/1.0 204 No Content\r\n\r\n", 204, '', false ),
		);
	}
}
