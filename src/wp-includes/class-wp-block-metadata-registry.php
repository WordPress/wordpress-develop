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
 * The WP_Block_Metadata_Registry allows plugins to register metadata for large
 * collections of blocks (e.g., 50-100+) using a single PHP file. This approach
 * reduces the need to read and decode multiple `block.json` files, enhancing
 * performance through opcode caching.
 *
 * @since 6.X.0
 */
class WP_Block_Metadata_Registry {

	/**
	 * Container for storing block metadata collections.
	 *
	 * Each entry maps a base path to its corresponding metadata and callback.
	 *
	 * @since 6.X.0
	 * @var array<string, array<string, mixed>>
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
	 * This method allows registering a collection of block metadata from a single
	 * manifest file, improving performance for large sets of blocks.
	 *
	 * @since 6.X.0
	 *
	 * @param string   $path                The absolute base path for the collection ( e.g., WP_PLUGIN_DIR . '/my-plugin/blocks/' ).
	 * @param string   $manifest            The absolute path to the manifest file containing the metadata collection.
	 * @param callable $identifier_callback Optional. Callback to determine the block identifier from a path.
	 *                                      The callback should accept a string (file or folder path) and return a string (block identifier).
	 *                                      This allows custom mapping between file paths and block names in the manifest.
	 *                                      If null, the default identifier callback is used, which extracts the parent
	 *                                      directory name. For example, when calling get_metadata() with a path like
	 *                                      'WP_PLUGIN_DIR/my-plugin/blocks/example/block.json', it would look for
	 *                                      a key named "example" in the manifest.
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
	 * Retrieves block metadata for a given block within a specific collection.
	 *
	 * This method uses the registered collections to efficiently lookup
	 * block metadata without reading individual `block.json` files.
	 *
	 * @since 6.X.0
	 *
	 * @param string $file_or_folder The path to the file or folder containing the block.
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
	 * This function is used when no custom identifier callback is provided during
	 * collection registration. It extracts the block identifier from the path:
	 * - For 'block.json' files, it uses the parent directory name.
	 * - For directories, it uses the directory name itself.
	 *
	 * For example:
	 * - Path: '/wp-content/plugins/my-plugin/blocks/example/block.json'
	 *   Identifier: 'example'
	 * - Path: '/wp-content/plugins/my-plugin/blocks/another-block'
	 *   Identifier: 'another-block'
	 *
	 * This default behavior matches the standard WordPress block structure.
	 * Custom callbacks can be provided for non-standard structures.
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
}
