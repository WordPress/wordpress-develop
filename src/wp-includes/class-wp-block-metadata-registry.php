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
	 * Registers block metadata for a given source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $source   The source identifier for the metadata.
	 * @param array  $metadata The block metadata.
	 */
	public function register( $source, $metadata ) {
		$this->metadata[ $source ] = $metadata;
	}

	/**
	 * Retrieves block metadata for a given source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $source The source identifier for the metadata.
	 * @return array|null    The block metadata for the source, or null if not found.
	 */
	public function get_metadata( $source ) {
		return isset( $this->metadata[ $source ] ) ? $this->metadata[ $source ] : null;
	}

	/**
	 * Checks if metadata exists for a given source.
	 *
	 * @since 6.X.0
	 *
	 * @param string $source The source identifier for the metadata.
	 * @return bool          True if metadata exists for the source, false otherwise.
	 */
	public function has_metadata( $source ) {
		return isset( $this->metadata[ $source ] );
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
