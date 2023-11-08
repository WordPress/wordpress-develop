<?php
/**
 * Translation proxy class.
 *
 * @package WordPress
 * @subpackage L10n
 * @since 6.5.0
 */

/**
 * Class WP_Translation_Proxy.
 *
 * @since 6.5.0
 */
final class WP_Translation_Proxy extends WP_String_Proxy {

	private $text;
	private $context;
	private $domain;

	/**
	 * Instantiate a WP_Translation_Proxy object.
	 *
	 * @since 6.5.0
	 *
	 * @param string $single  Text to translate.
	 * @param string $domain  Optional. Text domain to use for the translation. Default "default".
	 * @param string $context Optional. Context information for the translators. Default null.
	 */
	public function __construct( $single, $domain = 'default', $context = null ) {
		$this->text    = $single;
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
		return wp_translation_cache()->translate( $this->cache_id, $this->text, $this->domain, $this->context );
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
