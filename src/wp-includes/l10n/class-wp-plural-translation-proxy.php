<?php
/**
 * Plural translation proxy class.
 *
 * @package WordPress
 * @subpackage L10n
 * @since 6.5.0
 */

/**
 * Class WP_Plural_Translation_Proxy.
 *
 * @since 6.5.0
 */
final class WP_Plural_Translation_Proxy extends WP_String_Proxy {

	private $single;

	private $plural;

	private $number;

	private $context;

	private $domain;

	/**
	 * Instantiate a WP_Translation_Proxy object.
	 *
	 * @since 6.5.0
	 *
	 * @param string $single The text to be used if the number is singular.
	 * @param string $plural The text to be used if the number is plural.
	 * @param int    $number The number to compare against to use either the singular or plural form.
	 * @param string $domain Optional. Text domain. Unique identifier for retrieving translated strings.
	 *                       Default 'default'.
	 */
	public function __construct( $single, $plural, $number, $context = null, $domain = 'default' ) {
		$this->single  = $single;
		$this->plural  = $plural;
		$this->number   = $number;
		$this->context = $context;
		$this->domain  = $domain;

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
		return wp_translation_cache()->translate_plural(
			$this->cache_id,
			$this->single,
			$this->plural,
			$this->number,
			$this->domain,
			$this->context
		);
	}

	/**
	 * When the proxy object leaves memory, clear the cache entry.
	 *
	 * @since 6.5.0
	 */
	public function __destruct() {
		wp_translation_cache()->clear_translation( $this->cache_id );
	}
}
