<?php
/**
 * Image CDN API: WP_Image_CDN_Registry class.
 *
 * @package WordPress
 * @subpackage Media
 * @since 7.3.0
 */

/**
 * Core class used to map image mime types to the CDNs that serve them.
 *
 * @since 7.3.0
 */
final class WP_Image_CDN_Registry {

	/**
	 * Registered CDNs, keyed by the mime type they serve.
	 *
	 * Each mime type maps to a single CDN.
	 *
	 * @since 7.3.0
	 * @var array<string, array{handle: string, base_url: string}>
	 */
	private array $mime_types = array();

	/**
	 * Container for the main instance of the class.
	 *
	 * @since 7.3.0
	 * @var WP_Image_CDN_Registry|null
	 */
	private static ?WP_Image_CDN_Registry $instance = null;

	/**
	 * Registers a CDN for one or more image mime types.
	 *
	 * Registering a handle again replaces all of its previous mappings. When a mime type is
	 * already served by another CDN, the last one registered is used.
	 *
	 * @since 7.3.0
	 *
	 * @param string                $handle     Unique handle for the CDN.
	 * @param array<string, string> $mime_types Map of image mime types to the base URL that replaces
	 *                                          the uploads base URL for files of that type.
	 * @return bool True if at least one mime type was registered, false otherwise.
	 */
	public function register( string $handle, array $mime_types ): bool {
		if ( '' === $handle ) {
			_doing_it_wrong( __METHOD__, __( 'Image CDN handle must not be empty.' ), '7.3.0' );
			return false;
		}

		$this->unregister( $handle );

		$registered = false;
		foreach ( $mime_types as $mime_type => $base_url ) {
			if ( ! is_string( $mime_type ) || ! str_starts_with( $mime_type, 'image/' ) ) {
				_doing_it_wrong(
					__METHOD__,
					/* translators: %s: Mime type. */
					sprintf( __( '"%s" is not an image mime type.' ), esc_html( (string) $mime_type ) ),
					'7.3.0'
				);
				continue;
			}

			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			$host   = wp_parse_url( $base_url, PHP_URL_HOST );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! is_string( $host ) || '' === $host ) {
				_doing_it_wrong(
					__METHOD__,
					/* translators: %s: Mime type. */
					sprintf( __( 'The image CDN base URL for "%s" must be an absolute http or https URL.' ), esc_html( $mime_type ) ),
					'7.3.0'
				);
				continue;
			}

			$this->mime_types[ $mime_type ] = array(
				'handle'   => $handle,
				'base_url' => trailingslashit( $base_url ),
			);
			$registered                     = true;
		}

		return $registered;
	}

	/**
	 * Removes every mime type mapping for a CDN.
	 *
	 * @since 7.3.0
	 *
	 * @param string $handle The CDN handle.
	 * @return bool True if any mapping was removed, false otherwise.
	 */
	public function unregister( string $handle ): bool {
		$removed = false;
		foreach ( $this->mime_types as $mime_type => $cdn ) {
			if ( $cdn['handle'] === $handle ) {
				unset( $this->mime_types[ $mime_type ] );
				$removed = true;
			}
		}

		return $removed;
	}

	/**
	 * Removes the CDN mapping for a mime type.
	 *
	 * @since 7.3.0
	 *
	 * @param string $mime_type The image mime type.
	 * @return bool True if the mapping was removed, false if none was registered.
	 */
	public function unregister_mime_type( string $mime_type ): bool {
		if ( ! isset( $this->mime_types[ $mime_type ] ) ) {
			return false;
		}

		unset( $this->mime_types[ $mime_type ] );
		return true;
	}

	/**
	 * Gets the CDN registered for a mime type.
	 *
	 * @since 7.3.0
	 *
	 * @param string $mime_type The image mime type.
	 * @return array{handle: string, base_url: string}|null The CDN handle and base URL, or null if none is registered.
	 */
	public function get_for_mime_type( string $mime_type ): ?array {
		return $this->mime_types[ $mime_type ] ?? null;
	}

	/**
	 * Checks whether any CDN is registered.
	 *
	 * @since 7.3.0
	 *
	 * @return bool True if at least one mime type has a CDN.
	 */
	public function has_registrations(): bool {
		return array() !== $this->mime_types;
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_Image_CDN_Registry The main instance.
	 */
	public static function get_instance(): WP_Image_CDN_Registry {
		self::$instance ??= new self();

		return self::$instance;
	}
}
