<?php
/**
 * WP AI Client: WP_AI_Client_PSR7_Uri class
 *
 * @package WordPress
 * @subpackage AI
 * @since 7.0.0
 */

use WordPress\AiClientDependencies\Psr\Http\Message\UriInterface;

/**
 * Minimal PSR-7 URI implementation.
 *
 * Wraps PHP's parse_url() components into an immutable UriInterface value object.
 *
 * @since 7.0.0
 */
class WP_AI_Client_PSR7_Uri implements UriInterface {

	/**
	 * Standard ports for HTTP and HTTPS.
	 *
	 * @since 7.0.0
	 * @var array<string, int>
	 */
	private static $default_ports = array(
		'http'  => 80,
		'https' => 443,
	);

	/**
	 * URI scheme (e.g. "http", "https").
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $scheme = '';

	/**
	 * URI user info (e.g. "user:password").
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $user_info = '';

	/**
	 * URI host.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $host = '';

	/**
	 * URI port.
	 *
	 * @since 7.0.0
	 * @var int|null
	 */
	private $port;

	/**
	 * URI path.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $path = '';

	/**
	 * URI query string.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $query = '';

	/**
	 * URI fragment.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $fragment = '';

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 *
	 * @param string $uri URI string to parse.
	 */
	public function __construct( string $uri = '' ) {
		if ( '' !== $uri ) {
			$parts = wp_parse_url( $uri );

			if ( false !== $parts ) {
				$this->scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
				$this->host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
				$this->port   = isset( $parts['port'] ) ? (int) $parts['port'] : null;
				$this->path   = $parts['path'] ?? '';
				$this->query  = $parts['query'] ?? '';

				$this->fragment = $parts['fragment'] ?? '';

				if ( isset( $parts['user'] ) ) {
					$this->user_info = $parts['user'];
					if ( isset( $parts['pass'] ) ) {
						$this->user_info .= ':' . $parts['pass'];
					}
				}
			}
		}
	}

	/**
	 * Retrieves the scheme component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI scheme.
	 */
	public function getScheme(): string {
		return $this->scheme;
	}

	/**
	 * Retrieves the authority component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI authority, in "[user-info@]host[:port]" format.
	 */
	public function getAuthority(): string {
		if ( '' === $this->host ) {
			return '';
		}

		$authority = $this->host;

		if ( '' !== $this->user_info ) {
			$authority = $this->user_info . '@' . $authority;
		}

		if ( null !== $this->port && ! $this->is_standard_port() ) {
			$authority .= ':' . $this->port;
		}

		return $authority;
	}

	/**
	 * Retrieves the user information component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI user information.
	 */
	public function getUserInfo(): string {
		return $this->user_info;
	}

	/**
	 * Retrieves the host component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI host.
	 */
	public function getHost(): string {
		return $this->host;
	}

	/**
	 * Retrieves the port component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return int|null The URI port, or null if standard or not set.
	 */
	public function getPort(): ?int {
		if ( $this->is_standard_port() ) {
			return null;
		}

		return $this->port;
	}

	/**
	 * Retrieves the path component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI path.
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * Retrieves the query string of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI query string.
	 */
	public function getQuery(): string {
		return $this->query;
	}

	/**
	 * Retrieves the fragment component of the URI.
	 *
	 * @since 7.0.0
	 *
	 * @return string The URI fragment.
	 */
	public function getFragment(): string {
		return $this->fragment;
	}

	/**
	 * Returns an instance with the specified scheme.
	 *
	 * @since 7.0.0
	 *
	 * @param string $scheme The scheme to use with the new instance.
	 * @return static A new instance with the specified scheme.
	 */
	public function withScheme( string $scheme ): UriInterface {
		$new         = clone $this;
		$new->scheme = strtolower( $scheme );

		return $new;
	}

	/**
	 * Returns an instance with the specified user information.
	 *
	 * @since 7.0.0
	 *
	 * @param string      $user     The user name to use for authority.
	 * @param string|null $password The password associated with $user.
	 * @return static A new instance with the specified user information.
	 */
	public function withUserInfo( string $user, ?string $password = null ): UriInterface {
		$new            = clone $this;
		$new->user_info = $user;

		if ( null !== $password && '' !== $password ) {
			$new->user_info .= ':' . $password;
		}

		return $new;
	}

	/**
	 * Returns an instance with the specified host.
	 *
	 * @since 7.0.0
	 *
	 * @param string $host The hostname to use with the new instance.
	 * @return static A new instance with the specified host.
	 */
	public function withHost( string $host ): UriInterface {
		$new       = clone $this;
		$new->host = strtolower( $host );

		return $new;
	}

	/**
	 * Returns an instance with the specified port.
	 *
	 * @since 7.0.0
	 *
	 * @param int|null $port The port to use with the new instance.
	 * @return static A new instance with the specified port.
	 */
	public function withPort( ?int $port ): UriInterface {
		$new       = clone $this;
		$new->port = $port;

		return $new;
	}

	/**
	 * Returns an instance with the specified path.
	 *
	 * @since 7.0.0
	 *
	 * @param string $path The path to use with the new instance.
	 * @return static A new instance with the specified path.
	 */
	public function withPath( string $path ): UriInterface {
		$new       = clone $this;
		$new->path = $path;

		return $new;
	}

	/**
	 * Returns an instance with the specified query string.
	 *
	 * @since 7.0.0
	 *
	 * @param string $query The query string to use with the new instance.
	 * @return static A new instance with the specified query string.
	 */
	public function withQuery( string $query ): UriInterface {
		$new        = clone $this;
		$new->query = $query;

		return $new;
	}

	/**
	 * Returns an instance with the specified URI fragment.
	 *
	 * @since 7.0.0
	 *
	 * @param string $fragment The fragment to use with the new instance.
	 * @return static A new instance with the specified fragment.
	 */
	public function withFragment( string $fragment ): UriInterface {
		$new           = clone $this;
		$new->fragment = $fragment;

		return $new;
	}

	/**
	 * Returns the string representation as a URI reference.
	 *
	 * @since 7.0.0
	 *
	 * @return string
	 */
	public function __toString(): string {
		$uri       = '';
		$authority = $this->getAuthority();

		if ( '' !== $this->scheme ) {
			$uri .= $this->scheme . ':';
		}

		if ( '' !== $authority ) {
			$uri .= '//' . $authority;
		}

		$path = $this->path;

		if ( '' !== $authority && ( '' === $path || '/' !== $path[0] ) ) {
			$path = '/' . $path;
		} elseif ( '' === $authority && str_starts_with( $path, '//' ) ) {
			$path = '/' . ltrim( $path, '/' );
		}

		$uri .= $path;

		if ( '' !== $this->query ) {
			$uri .= '?' . $this->query;
		}

		if ( '' !== $this->fragment ) {
			$uri .= '#' . $this->fragment;
		}

		return $uri;
	}

	/**
	 * Checks whether the current port is the standard port for the scheme.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if port is the standard port for the current scheme.
	 */
	private function is_standard_port(): bool {
		if ( null === $this->port ) {
			return false;
		}

		return isset( self::$default_ports[ $this->scheme ] )
			&& self::$default_ports[ $this->scheme ] === $this->port;
	}
}
