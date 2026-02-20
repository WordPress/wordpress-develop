<?php
/**
 * WP AI Client: WP_AI_Client_PSR7_Request class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;

/**
 * Minimal PSR-7 HTTP request implementation.
 *
 * Immutable value object representing an outgoing HTTP request for the AI Client
 * HTTP transport layer.
 *
 * @since 7.0.0
 * @internal
 * @access private
 */
class WP_AI_Client_PSR7_Request implements RequestInterface {

	/**
	 * HTTP method.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $method;

	/**
	 * Request URI.
	 *
	 * @since 7.0.0
	 * @var UriInterface
	 */
	private $uri;

	/**
	 * HTTP protocol version.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $protocol_version = '1.1';

	/**
	 * Headers indexed by lowercase name.
	 *
	 * Each value is an array with 'name' (original case) and 'values' (list of strings).
	 *
	 * @since 7.0.0
	 * @var array<string, array{name: string, values: list<string>}>
	 */
	private $headers = array();

	/**
	 * Request body.
	 *
	 * @since 7.0.0
	 * @var StreamInterface
	 */
	private $body;

	/**
	 * Explicit request target, if set.
	 *
	 * @since 7.0.0
	 * @var string|null
	 */
	private $request_target;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string              $method HTTP method.
	 * @param string|UriInterface $uri    Request URI.
	 */
	public function __construct( string $method, $uri ) {
		$this->method = $method;
		$this->uri    = is_string( $uri ) ? new WP_AI_Client_PSR7_Uri( $uri ) : $uri;
		$this->body   = new WP_AI_Client_PSR7_Stream();

		$host = $this->uri->getHost();
		if ( '' !== $host && ! $this->hasHeader( 'Host' ) ) {
			$this->set_header_internal( 'Host', $host );
		}
	}

	/**
	 * Retrieves the HTTP protocol version.
	 *
	 * @since 7.0.0
	 *
	 * @return string HTTP protocol version.
	 */
	public function getProtocolVersion(): string {
		return $this->protocol_version;
	}

	/**
	 * Returns an instance with the specified HTTP protocol version.
	 *
	 * @since 7.0.0
	 *
	 * @param string $version HTTP protocol version.
	 * @return static
	 */
	public function withProtocolVersion( string $version ): self {
		$new                   = clone $this;
		$new->protocol_version = $version;

		return $new;
	}

	/**
	 * Retrieves all message header values.
	 *
	 * @since 7.0.0
	 *
	 * @return string[][] Associative array of headers.
	 */
	public function getHeaders(): array {
		$result = array();

		foreach ( $this->headers as $entry ) {
			$result[ $entry['name'] ] = $entry['values'];
		}

		return $result;
	}

	/**
	 * Checks if a header exists by the given case-insensitive name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return bool
	 */
	public function hasHeader( string $name ): bool {
		return isset( $this->headers[ strtolower( $name ) ] );
	}

	/**
	 * Retrieves a message header value by the given case-insensitive name.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return string[] Header values.
	 */
	public function getHeader( string $name ): array {
		$normalized = strtolower( $name );

		if ( ! isset( $this->headers[ $normalized ] ) ) {
			return array();
		}

		return $this->headers[ $normalized ]['values'];
	}

	/**
	 * Retrieves a comma-separated string of the values for a single header.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return string
	 */
	public function getHeaderLine( string $name ): string {
		return implode( ', ', $this->getHeader( $name ) );
	}

	/**
	 * Returns an instance with the provided value replacing the specified header.
	 *
	 * @since 7.0.0
	 *
	 * @param string          $name  Case-insensitive header field name.
	 * @param string|string[] $value Header value(s).
	 * @return static
	 */
	public function withHeader( string $name, $value ): self {
		$new = clone $this;
		$new->set_header_internal( $name, $value );

		return $new;
	}

	/**
	 * Returns an instance with the specified header appended with the given value.
	 *
	 * @since 7.0.0
	 *
	 * @param string          $name  Case-insensitive header field name to add.
	 * @param string|string[] $value Header value(s).
	 * @return static
	 */
	public function withAddedHeader( string $name, $value ): self {
		$new        = clone $this;
		$normalized = strtolower( $name );
		$values     = is_array( $value ) ? $value : array( $value );

		if ( isset( $new->headers[ $normalized ] ) ) {
			$new->headers[ $normalized ]['values'] = array_merge(
				$new->headers[ $normalized ]['values'],
				$values
			);
		} else {
			$new->headers[ $normalized ] = array(
				'name'   => $name,
				'values' => $values,
			);
		}

		return $new;
	}

	/**
	 * Returns an instance without the specified header.
	 *
	 * @since 7.0.0
	 *
	 * @param string $name Case-insensitive header field name to remove.
	 * @return static
	 */
	public function withoutHeader( string $name ): self {
		$new = clone $this;
		unset( $new->headers[ strtolower( $name ) ] );

		return $new;
	}

	/**
	 * Gets the body of the message.
	 *
	 * @since 7.0.0
	 *
	 * @return StreamInterface
	 */
	public function getBody(): StreamInterface {
		return $this->body;
	}

	/**
	 * Returns an instance with the specified message body.
	 *
	 * @since 7.0.0
	 *
	 * @param StreamInterface $body Body.
	 * @return static
	 */
	public function withBody( StreamInterface $body ): self {
		$new       = clone $this;
		$new->body = $body;

		return $new;
	}

	/**
	 * Retrieves the message's request target.
	 *
	 * @since 7.0.0
	 *
	 * @return string
	 */
	public function getRequestTarget(): string {
		if ( null !== $this->request_target ) {
			return $this->request_target;
		}

		$target = $this->uri->getPath();

		if ( '' === $target ) {
			$target = '/';
		}

		$query = $this->uri->getQuery();

		if ( '' !== $query ) {
			$target .= '?' . $query;
		}

		return $target;
	}

	/**
	 * Returns an instance with the specific request-target.
	 *
	 * @since 7.0.0
	 *
	 * @param string $requestTarget Request target.
	 * @return static
	 */
	public function withRequestTarget( string $requestTarget ): self {
		$new                 = clone $this;
		$new->request_target = $requestTarget;

		return $new;
	}

	/**
	 * Retrieves the HTTP method of the request.
	 *
	 * @since 7.0.0
	 *
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Returns an instance with the provided HTTP method.
	 *
	 * @since 7.0.0
	 *
	 * @param string $method Case-sensitive method.
	 * @return static
	 */
	public function withMethod( string $method ): self {
		$new         = clone $this;
		$new->method = $method;

		return $new;
	}

	/**
	 * Retrieves the URI instance.
	 *
	 * @since 7.0.0
	 *
	 * @return UriInterface
	 */
	public function getUri(): UriInterface {
		return $this->uri;
	}

	/**
	 * Returns an instance with the provided URI.
	 *
	 * @since 7.0.0
	 *
	 * @param UriInterface $uri          New request URI to use.
	 * @param bool         $preserveHost Preserve the original state of the Host header.
	 * @return static
	 */
	public function withUri( UriInterface $uri, bool $preserveHost = false ): self {
		$new      = clone $this;
		$new->uri = $uri;

		$host = $uri->getHost();

		if ( ! $preserveHost ) {
			if ( '' !== $host ) {
				$new->set_header_internal( 'Host', $host );
			}
		} elseif ( '' !== $host && ( ! $new->hasHeader( 'Host' ) || '' === $new->getHeaderLine( 'Host' ) ) ) {
			$new->set_header_internal( 'Host', $host );
		}

		return $new;
	}

	/**
	 * Sets a header internally (mutating, for use in constructor and clone methods).
	 *
	 * @since 7.0.0
	 *
	 * @param string          $name  Header name.
	 * @param string|string[] $value Header value(s).
	 */
	private function set_header_internal( string $name, $value ): void {
		$normalized                   = strtolower( $name );
		$this->headers[ $normalized ] = array(
			'name'   => $name,
			'values' => is_array( $value ) ? $value : array( $value ),
		);
	}
}
