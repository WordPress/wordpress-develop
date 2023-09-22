<?php
/**
 * PHPCS cross-version compatibility helper class.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

namespace WordPressCS\WordPress;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\File;

/**
 * PHPCSHelper
 *
 * PHPCS cross-version compatibility helper class.
 *
 * Deals with files which cannot be aliased 1-on-1 as the original
 * class was split up into several classes.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.13.0
 */
class PHPCSHelper {

	/**
	 * Get the PHPCS version number.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public static function get_version() {
		return Config::VERSION;
	}

	/**
	 * Pass config data to PHPCS.
	 *
	 * PHPCS cross-version compatibility helper.
	 *
	 * @since 0.13.0
	 *
	 * @param string      $key   The name of the config value.
	 * @param string|null $value The value to set. If null, the config entry
	 *                           is deleted, reverting it to the default value.
	 * @param boolean     $temp  Set this config data temporarily for this script run.
	 *                           This will not write the config data to the config file.
	 */
	public static function set_config_data( $key, $value, $temp = false ) {
		Config::setConfigData( $key, $value, $temp );
	}

	/**
	 * Get the value of a single PHPCS config key.
	 *
	 * @since 0.13.0
	 *
	 * @param string $key The name of the config value.
	 *
	 * @return string|null
	 */
	public static function get_config_data( $key ) {
		return Config::getConfigData( $key );
	}

	/**
	 * Get the tab width as passed to PHPCS from the command-line or the ruleset.
	 *
	 * @since 0.13.0
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being processed.
	 *
	 * @return int Tab width. Defaults to 4.
	 */
	public static function get_tab_width( File $phpcsFile ) {
		$tab_width = 4;

		if ( isset( $phpcsFile->config->tabWidth ) && $phpcsFile->config->tabWidth > 0 ) {
			$tab_width = $phpcsFile->config->tabWidth;
		}

		return $tab_width;
	}

	/**
	 * Check whether the `--ignore-annotations` option has been used.
	 *
	 * @since 0.13.0
	 *
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile Optional. The current file being processed.
	 *
	 * @return bool True if annotations should be ignored, false otherwise.
	 */
	public static function ignore_annotations( File $phpcsFile = null ) {
		if ( isset( $phpcsFile, $phpcsFile->config->annotations ) ) {
			return ! $phpcsFile->config->annotations;
		} else {
			$annotations = Config::getConfigData( 'annotations' );
			if ( isset( $annotations ) ) {
				return ! $annotations;
			}
		}
	}

}
