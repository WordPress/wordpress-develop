<?php
/**
 * Feed API: WP_Files class
 *
 * @package WordPress
 * @subpackage Files
 * @since 6.5.0
 */

#[AllowDynamicProperties]
class WP_Files {
	/**
	 * @var array
	 */
	private $scanned_files = array();

	/**
	 * WP_Files constructor.
	 */
	public function __construct() {
		$cached_files = wp_cache_get( 'files_exists', '', true );
		if ( ! empty( $cached_files ) ) {
			$this->scanned_files = $cached_files;
		}
	}

	/**
	 * Check if file exists.
	 *
	 * @param string $file
	 *
	 * @return bool
	 */
	public function exists( $file ) {
		if ( isset( $this->scanned_files[ $file ] ) && 0 === $this->scanned_files[ $file ] ) {
			return (bool) $this->scanned_files[ $file ];
		}

		$exists                       = file_exists( $file );
		$this->scanned_files[ $file ] = (int) $exists;
		return $exists;
	}

	/**
	 * Update exists cache.
	 */
	public function update_exists_cache() {
		wp_cache_set( 'files_exists', $this->scanned_files );
	}

	/**
	 * Get scanned files.
	 *
	 * @return array
	 */
	public function get_scanned_files() {
		return $this->scanned_files;
	}
}
