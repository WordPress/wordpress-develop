<?php
/**
 * WP AI Client: WP_AI_Client_PSR17_Factory class
 *
 * @package WordPress
 * @subpackage AI
 * @since 6.8.0
 */

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * Combined PSR-17 factory for creating PSR-7 HTTP message objects.
 *
 * Implements all four PSR-17 factory interfaces, delegating to the minimal
 * WP AI Client PSR-7 implementations.
 *
 * @since 6.8.0
 */
class WP_AI_Client_PSR17_Factory implements RequestFactoryInterface, ResponseFactoryInterface, StreamFactoryInterface, UriFactoryInterface {

	/**
	 * Creates a new request.
	 *
	 * @since 6.8.0
	 *
	 * @param string              $method The HTTP method associated with the request.
	 * @param UriInterface|string $uri    The URI associated with the request.
	 * @return RequestInterface
	 */
	public function createRequest( string $method, $uri ): RequestInterface {
		return new WP_AI_Client_PSR7_Request( $method, $uri );
	}

	/**
	 * Creates a new response.
	 *
	 * @since 6.8.0
	 *
	 * @param int    $code         HTTP status code. Defaults to 200.
	 * @param string $reasonPhrase Reason phrase to associate with status code.
	 * @return ResponseInterface
	 */
	public function createResponse( int $code = 200, string $reasonPhrase = '' ): ResponseInterface {
		return new WP_AI_Client_PSR7_Response( $code, $reasonPhrase );
	}

	/**
	 * Creates a new stream from a string.
	 *
	 * @since 6.8.0
	 *
	 * @param string $content String content with which to populate the stream.
	 * @return StreamInterface
	 */
	public function createStream( string $content = '' ): StreamInterface {
		return new WP_AI_Client_PSR7_Stream( $content );
	}

	/**
	 * Creates a stream from an existing file.
	 *
	 * @since 6.8.0
	 *
	 * @param string $filename Filename or stream URI to use as basis of stream.
	 * @param string $mode     Mode with which to open the underlying filename/stream.
	 * @return StreamInterface
	 */
	public function createStreamFromFile( string $filename, string $mode = 'r' ): StreamInterface {
		$content = file_get_contents( $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			$content = '';
		}

		return new WP_AI_Client_PSR7_Stream( $content );
	}

	/**
	 * Creates a new stream from an existing resource.
	 *
	 * @since 6.8.0
	 *
	 * @param resource $resource PHP resource to use as basis of stream.
	 * @return StreamInterface
	 */
	public function createStreamFromResource( $resource ): StreamInterface {
		$content = stream_get_contents( $resource );

		if ( false === $content ) {
			$content = '';
		}

		return new WP_AI_Client_PSR7_Stream( $content );
	}

	/**
	 * Creates a new URI.
	 *
	 * @since 6.8.0
	 *
	 * @param string $uri The URI string.
	 * @return UriInterface
	 */
	public function createUri( string $uri = '' ): UriInterface {
		return new WP_AI_Client_PSR7_Uri( $uri );
	}
}
