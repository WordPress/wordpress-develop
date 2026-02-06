<?php
/**
 * WP AI Client: WP_AI_Client_PSR7_Response class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
use WordPress\AiClientDependencies\Psr\Http\Message\StreamInterface;

/**
 * Minimal PSR-7 HTTP response implementation.
 *
 * Immutable value object representing an incoming HTTP response for the AI Client
 * HTTP transport layer.
 *
 * @since 7.0.0
 */
class WP_AI_Client_PSR7_Response implements ResponseInterface {

	/**
	 * HTTP status code.
	 *
	 * @since 7.0.0
	 * @var int
	 */
	private $status_code;

	/**
	 * Reason phrase associated with the status code.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $reason_phrase;

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
	 * Response body.
	 *
	 * @since 7.0.0
	 * @var StreamInterface
	 */
	private $body;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $status_code   HTTP status code.
	 * @param string $reason_phrase Reason phrase to associate with the status code.
	 */
	public function __construct( int $status_code = 200, string $reason_phrase = '' ) {
		$this->status_code   = $status_code;
		$this->reason_phrase = $reason_phrase;
		$this->body          = new WP_AI_Client_PSR7_Stream();
	}

	/**
	 * Gets the response status code.
	 *
	 * @since 7.0.0
	 *
	 * @return int Status code.
	 */
	public function getStatusCode(): int {
		return $this->status_code;
	}

	/**
	 * Returns an instance with the specified status code and reason phrase.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $code         The 3-digit integer result code to set.
	 * @param string $reasonPhrase The reason phrase to use.
	 * @return static
	 */
	public function withStatus( int $code, string $reasonPhrase = '' ): self {
		$new                = clone $this;
		$new->status_code   = $code;
		$new->reason_phrase = $reasonPhrase;

		return $new;
	}

	/**
	 * Gets the response reason phrase associated with the status code.
	 *
	 * @since 7.0.0
	 *
	 * @return string Reason phrase.
	 */
	public function getReasonPhrase(): string {
		return $this->reason_phrase;
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
		$new                                       = clone $this;
		$normalized                                = strtolower( $name );
		$new->headers[ $normalized ]               = array(
			'name'   => $name,
			'values' => is_array( $value ) ? $value : array( $value ),
		);

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
}
