<?php
/**
 * Upgrader API: Plugin_Dependency_Installer_Skin class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 5.9.0
 */

/**
 * Plugin Dependency Installer Skin for WordPress Plugin Installer.
 *
 * @since 2.8.0
 * @since 4.6.0 Moved to its own file from wp-admin/includes/class-wp-upgrader-skins.php.
 *
 * @see WP_Upgrader_Skin
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class WP_Plugin_Dependency_Installer_Skin
 */
class WP_Plugin_Dependency_Installer_Skin extends Plugin_Installer_Skin {
	/**
	 * Header
	 *
	 * @return void
	 */
	public function header() {
	}

	/**
	 * Footer
	 *
	 * @return void
	 */
	public function footer() {
	}

	/**
	 * Error
	 *
	 * @param array $errors Array of errors.
	 *
	 * @return void
	 */
	public function error( $errors ) {
	}

	/**
	 * Feedback
	 *
	 * @param string $string Feedback string.
	 * @param array  ...$args Array of args.
	 *
	 * @return void
	 */
	public function feedback( $string, ...$args ) {
	}
}
