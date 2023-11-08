<?php
/**
 * HTML escaping proxy class.
 *
 * @package WordPress
 * @subpackage L10n
 * @since 6.5.0
 */

/**
 * Class WP_Escape_HTML_Proxy.
 *
 * @since 6.5.0
 */
final class WP_Escape_HTML_Proxy extends WP_String_Proxy {

	private $value;

	/**
	 * Instantiate a WP_Translation_Proxy object.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $value Value to be escaped. Needs to be castable to a
	 *                     string.
	 */
	public function __construct( $value ) {
		$this->value = $value;

		parent::__construct();
	}

	/**
	 * Lazily evaluate the result the first time it is being requested.
	 *
	 * @since 6.5.0
	 *
	 * @return string
	 */
	protected function result() {
		return esc_html( (string) $this->value );
	}
}
