<?php

class WP_URL {
	/**
	 * Contains the original URL string.
	 *
	 * Example:
	 *
	 *     - http://wordpress.org
	 *     - https://wordpress.org/absolute/path
	 *     - javascript:alert(1);
	 *     - /relative/path
	 *     - /?with=query&string
	 *     - /#with-fragment
	 *
	 * @since 6.6.0
	 *
	 * @var string
	 */
	private $raw_url;

	/**
	 * For relative URLs, this provides the base.
	 *
	 * @var string|null
	 */
	private $base_url;

	/**
	 * Indicates if the URL is an absolute or relative reference.
	 *
	 * @var bool
	 */
	private $is_relative;

	private $parser_state = self::STATE_READY;

	private $scheme;

	private $username;

	private $password;

	private $host;

	private $port;

	private $path;

	private $query_params;

	private $fragment;

	/**
	 * Creates a new instance from a potential string URL.
	 *
	 * @since 6.6.0
	 *
	 * @param string $raw_url Possibly represents a URL.
	 * @return WP_URL Newly-created URL.
	 */
	public static function parse( $raw_url, $base_url = null ) {
		if ( ! is_string( $raw_url ) || strlen( $raw_url ) <= 0 ) {
			return null;
		}

		$url          = new WP_URL( $base_url );
		$url->raw_url = $raw_url;
		$at           = 0;

		$first_char  = $raw_url[0];
		$is_relative = '/' === $first_char || '?' === $first_char || '#' === $first_char;

		$url->is_relative = $is_relative;
		if ( ! $is_relative ) {
			$scheme_length = strpos( $raw_url, ':' );
			if ( false === $scheme_length ) {
				return null;
			}

			$scheme = strtolower( substr( $raw_url, 0, $scheme_length ) );
			if ( 'ftp' !== $scheme && 'http' !== $scheme && 'https' !== $scheme && 'javascript' !== $scheme ) {
				return null;
			}

			$url->scheme = $scheme;

			// Validate that `://` follows the scheme.
			$at = $scheme_length + 1;
			if ( '/' !== $raw_url[ $at ] || '/' !== $raw_url[ $at + 1 ] ) {
				return null;
			}
			$at += 2;

			// @todo Detect username and password authentication.

			// @todo Validate domain characters.
			$host_at     = $at + 2;
			$host_length = strspn( $raw_url, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-.', $host_at );
			if ( 0 === $host_length ) {
				return null;
			}

			// Did that end the full host part?
			$after_host = $host_at + $host_length;
			if ( strlen( $raw_url ) === $after_host ) {
				return $url;
			}

			$after_host_char = $raw_url[ $after_host ];
			// Is there a port?
			if ( ':' === $after_host_char ) {
				$port_at     = $after_host + 1;
				$port_zeros  = strspn( $raw_url, '0', $port_at );
				$port_length = strspn( $raw_url, '0123456789', $port_at + $port_zeros );

				// Max port is 65535.
				if ( 0 === $port_length || $port_length > 5 ) {
					return null;
				}

				$port_number = intval( substr( $raw_url, $port_at + $port_zeros, $port_length ), 10 );
				if ( $port_number > 65535 ) {
					return null;
				}

				$url->port = $port_number;
			} elseif (

				/*
				 * If not followed by the next part of the URL, it's
				 * still the host and contains invalid characters.
				 */
				'/' !== $after_host_char &&
				'?' !== $after_host_char &&
				'#' !== $after_host_char
			) {
				return null;
			}

			// Everything to here must be the host part.
			$host = substr( $raw_url, $host_at, $host_length );
			if ( 'x' === $host[0] && 'n' === $host[1] && '-' === $host[2] && '-' === $host[3] ) {
				$host = self::decode_punycode( $host );
			}
		}

		return $url;
	}

	/**
	 * Constructor function.
	 *
	 * @param ?string $base_url Base URL for completing relative URLs.
	 */
	public function __construct( $base_url = null ) {
		$this->base_url = $base_url;
	}

	/**
	 * Decodes punycode-encoded string.
	 *
	 * Example:
	 *
	 *     'ok' === WP_URL::decode_punycode( 'ok' );
	 *     '😅' === WP_URL::decode_punycode( 'xn--i28h' );
	 *     null === WP_URL::decode_punycode( 'xn--i28hz' );
	 *
	 * @since 6.6.0
	 *
	 * @param string $encoded punycode-encoded string.
	 * @return string|null Decoded string, if valid, otherwise `null`.
	 */
	public static function decode_punycode( $encoded ) {
		
	}

	// Constants that would pollute the top of the class.

	const STATE_READY = 0;
	const STATE_SCHEMA = 1;
	const STATE_USERNAME = 2;
	const STATE_PASSWORD = 3;
	const STATE_HOST = 4;
	const STATE_PORT = 5;
	const STATE_PATH = 6;
	const STATE_QUERY = 7;
	const STATE_FRAGMENT = 8;
}
