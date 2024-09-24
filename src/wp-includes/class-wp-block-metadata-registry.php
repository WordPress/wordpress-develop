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
	 * Caches the last matched collection path for performance optimization.
	 *
	 * @since 6.X.0
	 * @var string|null
	 */
	private static $last_matched_collection = null;

	/**
	 * Registers a block metadata collection.
	 *
	 * @since 6.X.0
	 *
	 * @param string   $path                The base path for the collection.
	 * @param string   $manifest            The path to the manifest file for the collection.
	 * @param callable $identifier_callback Optional. A callback function to determine the block identifier from a given path.
	 *                                      Default null, which uses the default identifier callback.
	 *                                      The callback should accept a single string parameter (the file or folder path)
	 *                                      and return a string (the block identifier).
	 *                                      The block identifier is used to uniquely identify a block within a collection,
	 *                                      and should be the keys used in the metadata array.
	 * @return bool                         True if the collection was registered successfully, false otherwise.
	 */
	public static function register_collection( $path, $manifest, $identifier_callback = null ) {
		$path = wp_normalize_path( rtrim( $path, '/' ) );

		// Check if the path is valid:
		if ( str_starts_with( $path, wp_normalize_path( ABSPATH . WPINC ) ) ) {
			// Core path is valid.
		} elseif ( str_starts_with( $path, wp_normalize_path( WP_PLUGIN_DIR ) ) ) {
			// For plugins, ensure the path is within a specific plugin directory and not the base plugin directory.
			$plugin_dir    = wp_normalize_path( WP_PLUGIN_DIR );
			$relative_path = substr( $path, strlen( $plugin_dir ) + 1 );
			$plugin_name   = strtok( $relative_path, '/' );

			if ( empty( $plugin_name ) || $plugin_name === $relative_path ) {
				// Invalid plugin path.
				return false;
			}
		} else {
			// Path is neither core nor a valid plugin path.
			return false;
		}

		self::$collections[ $path ] = array(
			'manifest' => $manifest,
			'metadata' => null,
			'identifier_callback' => $identifier_callback,
		);

		return true;
	}

	/**
	 * Retrieves block metadata for a given block name within a specific collection.
	 *
	 * @since 6.X.0
	 *
	 * @param string $file_or_folder The path to the file or folder containing the block metadata.
	 * @return array|null            The block metadata for the block, or null if not found.
	 */
	public static function get_metadata( $file_or_folder ) {
		$path = self::find_collection_path( $file_or_folder );
		if ( ! $path ) {
			return null;
		}

		$collection = &self::$collections[ $path ];

		if ( null === $collection['metadata'] ) {
			// Load the manifest file if not already loaded
			$collection['metadata'] = require $collection['manifest'];
		}

		// Use the identifier callback to get the block name, or the default callback if not set.
		$identifier_callback = self::$collections[ $path ]['identifier_callback'];
		if ( is_null( $identifier_callback ) ) {
			$block_name = self::default_identifier_callback( $file_or_folder );
		} else {
			$block_name = call_user_func( $identifier_callback, $file_or_folder );
		}

		return isset( $collection['metadata'][ $block_name ] ) ? $collection['metadata'][ $block_name ] : null;
	}

	/**
	 * Finds the collection path for a given file or folder.
	 *
	 * @since 6.X.0
	 *
	 * @param string $file_or_folder The path to the file or folder.
	 * @return string|null The collection path if found, or null if not found.
	 */
	private static function find_collection_path( $file_or_folder ) {
		if ( empty( $file_or_folder ) ) {
			return null;
		}

		// Check the last matched collection first, since block registration usually happens in batches per plugin or theme.
		$path = wp_normalize_path( rtrim( $file_or_folder, '/' ) );
		if ( self::$last_matched_collection && str_starts_with( $path, self::$last_matched_collection ) ) {
			return self::$last_matched_collection;
		}

		$collection_paths = array_keys( self::$collections );
		foreach ( $collection_paths as $collection_path ) {
			if ( str_starts_with( $path, $collection_path ) ) {
				self::$last_matched_collection = $collection_path;
				return $collection_path;
			}
		}
		return null;
	}

	/**
	 * Checks if metadata exists for a given block name in a specific collection.
	 *
	 * @since 6.X.0
	 *
	 * @param string $file_or_folder The path to the file or folder containing the block metadata.
	 * @return bool                  True if metadata exists for the block, false otherwise.
	 */
	public static function has_metadata( $file_or_folder ) {
		return null !== self::get_metadata( $file_or_folder );
	}

	/**
	 * Default callback function to determine the block identifier from a given path.
	 *
	 * This function is used when no custom identifier callback is provided during the
	 * registration of a block metadata collection. It determines the block identifier
	 * based on the provided path.
	 *
	 * If the path ends with 'block.json', the parent directory name is used as the block
	 * identifier. Otherwise, the name of the directory itself is used.
	 *
	 * @since 6.X.0
	 *
	 * @param string $path The file or folder path to determine the block identifier from.
	 * @return string The block identifier.
	 */
	public static function default_identifier_callback( $path ) {
		if ( substr( $path, -10 ) === 'block.json' ) {
			// If it's block.json, use the parent directory name.
			return basename( dirname( $path ) );
		} else {
			// Otherwise, assume it's a directory and use its name.
			return basename( $path );
		}
	}

	/**
	 * Private constructor to prevent instantiation.
	 */
	private function __construct() {
		// Prevent instantiation
	}
}
