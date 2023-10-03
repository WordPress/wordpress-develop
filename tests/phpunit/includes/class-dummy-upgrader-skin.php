<?php

/**
 * Dummy skin for the WordPress Upgrader classes during tests.
 *
 * @see WP_Upgrader
 */
class Dummy_Upgrader_Skin extends WP_Upgrader_Skin {
	/**
	 * @return void
	 */
	public function header() {}

	/**
	 * @return void
	 */
	public function footer() {}

	/**
	 * @return bool
	 */
	public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) {
		return true;
	}

	/**
	 * @param string $feedback Message data.
	 * @param mixed  ...$args  Optional text replacements.
	 *
	 * @return void
	 */
	public function feedback( $feedback, ...$args ) {}
}
