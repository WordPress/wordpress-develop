<?php
/**
 * Block Metadata Registry
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.X.0
 */

/**
 * Class used for managing block metadata collections.
 *
 * @since 6.X.0
 */
class WP_Block_Metadata_Registry {

	/**
	 * Container for storing block metadata collections.
	 *
	 * @since 6.X.0
	 * @var array
	 */
	private static $collections = array();

	/**
	 * Registers a block metadata collection.
	 *
	 * @since 6.X.0
	 *
	 * @param string $path     The base path for the collection.
	 * @param string $manifest The path to the manifest file for the collection.
	 */
	public static function register_collection( $path, $manifest ) {
		$path = rtrim( $path, '/' );
		self::$collections[ $path ] = array(
			'manifest' => $manifest,
			'metadata' => null,
		);
	}

	/**
	 * Retrieves block metadata for a given block name within a specific collection.
	 *
	 * @since 6.X.0
	 *
	 * @param string $path       The base path of the collection.
	 * @param string $block_name The block name to look for.
	 * @return array|null        The block metadata for the block, or null if not found.
	 */
	public static function get_metadata( $path, $block_name ) {
		$path = rtrim( $path, '/' );
		if ( ! isset( self::$collections[ $path ] ) ) {
			return null;
		}

		$collection = &self::$collections[ $path ];

		if ( null === $collection['metadata'] ) {
			// Load the manifest file if not already loaded
			$collection['metadata'] = require $collection['manifest'];
		}

		return isset( $collection['metadata'][ $block_name ] ) ? $collection['metadata'][ $block_name ] : null;
	}

	/**
	 * Checks if metadata exists for a given block name in a specific collection.
	 *
	 * @since 6.X.0
	 *
	 * @param string $path       The base path of the collection.
	 * @param string $block_name The block name to check for.
	 * @return bool              True if metadata exists for the block, false otherwise.
	 */
	public static function has_metadata( $path, $block_name ) {
		return null !== self::get_metadata( $path, $block_name );
	}

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
		// Prevent instantiation
	}
}
