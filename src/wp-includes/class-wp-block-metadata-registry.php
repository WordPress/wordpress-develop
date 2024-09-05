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
	 * Holds the instance of this class.
	 *
	 * @since 6.X.0
	 * @var WP_Block_Metadata_Registry
	 */
	private static $instance;

	/**
	 * Container for storing block metadata.
	 *
	 * @since 6.X.0
	 * @var array
	 */
	private $metadata = array();

	/**
	 * Registers block metadata for a given source and namespace.
	 *
	 * @since 6.X.0
	 *
	 * @param string $namespace The namespace for the metadata (e.g., 'core', 'mythirdpartyplugin').
	 * @param string $source	The source identifier for the metadata within the namespace.
	 * @param array  $metadata	The block metadata.
	 */
	public function register( $namespace, $source, $metadata ) {
		if ( ! isset( $this->metadata[ $namespace ] ) ) {
			$this->metadata[ $namespace ] = array();
		}
		$this->metadata[ $namespace ][ $source ] = $metadata;
	}

	/**
	 * Retrieves block metadata for a given namespace and source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $namespace The namespace for the metadata.
	 * @param string $source	The source identifier for the metadata within the namespace.
	 * @return array|null The block metadata for the source, or null if not found.
	 */
	public function get_metadata( $namespace, $source ) {
		return isset( $this->metadata[ $namespace ][ $source ] ) ? $this->metadata[ $namespace ][ $source ] : null;
	}

	/**
	 * Retrieves the instance of this class.
	 *
	 * @since 6.X.0
	 *
	 * @return WP_Block_Metadata_Registry The instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
