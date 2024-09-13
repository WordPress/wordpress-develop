<?php
/**
 * Block Metadata Registry
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.X.0
 */

/**
 * Class used for managing block metadata from various sources.
 *
 * @since 6.X.0
 */
class WP_Block_Metadata_Registry {

	/**
	 * Container for storing block metadata.
	 *
	 * @since 6.X.0
	 * @var array
	 */
	private static $metadata = array();

	/**
	 * Registers block metadata for a given source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $source   The source identifier for the metadata.
	 * @param array  $metadata The block metadata.
	 */
	public static function register( $source, $metadata ) {
		self::$metadata[ $source ] = $metadata;
	}

	/**
	 * Retrieves block metadata for a given source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $source The source identifier for the metadata.
	 * @return array|null    The block metadata for the source, or null if not found.
	 */
	public static function get_metadata( $source ) {
		return isset( self::$metadata[ $source ] ) ? self::$metadata[ $source ] : null;
	}

	/**
	 * Checks if metadata exists for a given source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $source The source identifier for the metadata.
	 * @return bool          True if metadata exists for the source, false otherwise.
	 */
	public static function has_metadata( $source ) {
		return isset( self::$metadata[ $source ] );
	}

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
		// Prevent instantiation
	}
}
