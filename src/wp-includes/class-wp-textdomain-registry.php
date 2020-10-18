<?php
/**
 * Locale API: WP_Textdomain_Registry class
 *
 * @package WordPress
 * @subpackage i18n
 * @since 5.6.0
 */

/**
 * Core class used for registering textdomains
 *
 * @since 5.6.0
 */
class WP_Textdomain_Registry {
	/**
	 * List of domains and their language directory paths.
	 *
	 * @since 5.6.0
	 *
	 * @var array
	 */
	protected $domains = array();

	/**
	 * Holds a cached list of available .mo files to improve performance.
	 *
	 * @since 5.6.0
	 *
	 * @var array
	 */
	protected $cached_mo_files;

	/**
	 * Returns the MO file path for a specific domain.
	 *
	 * @since 5.6.0
	 *
	 * @param string $domain Text domain.
	 * @return string|false|null MO file path or false if there is none available.
	 *                           Null if none have been fetched yet.
	 */
	public function get( $domain ) {
		return isset( $this->domains[ $domain ] ) ? $this->domains[ $domain ] : null;
	}

	/**
	 * Sets the MO file path for a specific domain.
	 *
	 * @since 5.6.0
	 *
	 * @param string $domain Text domain.
	 * @param string $path Language directory path.
	 */
	public function set( $domain, $path ) {
		$this->domains[ $domain ] = $path;
	}

	/**
	 * Resets the registry state.
	 *
	 * @since 5.6.0
	 */
	public function reset() {
		$this->cached_mo_files = null;
		$this->domains = array();
	}

	/**
	 * Gets the path to a translation file in the languages directory for the current locale.
	 *
	 * @since 5.6.0
	 *
	 * @param string $domain Text domain.
	 */
	public function get_translation_from_lang_dir( $domain ) {
		if ( null === $this->cached_mo_files ) {
			$this->cached_mo_files = array();

			$this->set_cached_mo_files();
		}

		$locale = is_admin() ? get_user_locale() : get_locale();
		$mofile = "{$domain}-{$locale}.mo";

		$path = WP_LANG_DIR . '/plugins/' . $mofile;
		if ( in_array( $path, $this->cached_mo_files, true ) ) {
			$this->set( $domain, WP_LANG_DIR . '/plugins/' );

			return;
		}

		$path = WP_LANG_DIR . '/themes/' . $mofile;
		if ( in_array( $path, $this->cached_mo_files, true ) ) {
			$this->set( $domain, WP_LANG_DIR . '/themes/' );

			return;
		}

		$this->set( $domain, false );
	}

	/**
	 * Reads and caches all available MO files from the plugins and themes language directories.
	 *
	 * @since 5.6.0
	 */
	protected function set_cached_mo_files() {
		$locations = array(
			WP_LANG_DIR . '/plugins',
			WP_LANG_DIR . '/themes',
		);

		foreach ( $locations as $location ) {
			$mo_files = glob( $location . '/*.mo' );

			if ( $mo_files ) {
				$this->cached_mo_files = array_merge( $this->cached_mo_files, $mo_files );
			}
		}
	}
}
