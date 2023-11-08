<?php
/**
 * Translation cache class.
 *
 * @package WordPress
 * @subpackage L10n
 * @since 6.5.0
 */

/**
 * Class WP_Translation_Cache.
 *
 * @since 6.5.0
 */
class WP_Translation_Cache {

	const MAX_LOCALES = 5;

	/**
	 * The translation cache.
	 *
	 * Keyed by locale then cache ID.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	private $cache = array();

	/**
	 * The original locale for the request.
	 *
	 * This locale will never be pruned from the cache.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	private $original_locale;

	/**
	 * The current locale. Determines the current cache key.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	private $current_locale;

	/**
	 * WP_Translation_Cache constructor.
	 *
	 * @since 6.5.0
	 */
	public function __construct() {
		$locale                = determine_locale();
		$this->original_locale = $locale;
		$this->current_locale  = $locale;
	}

	/**
	 * Initialize the translation cache.
	 *
	 * @since 6.5.0
	 */
	public function init() {
		add_action( 'change_locale', array( $this, 'on_change_locale' ) );
	}

	/**
	 * When the locale is changed, update the current cache key.
	 *
	 * @since 6.5.0
	 *
	 * @param string $locale
	 */
	public function on_change_locale( $locale ) {
		$this->current_locale = $locale;
		$this->prune_cache();
	}

	/**
	 * Lazily translate the given text.
	 *
	 * @since 6.5.0
	 *
	 * @param int    $cache_id The cache ID identifying this translation.
	 * @param string $text     The untranslated text.
	 * @param string $domain   The text domain.
	 * @param string $context  Optional. Context information for the translators. Default null.
	 * @return string The translated text.
	 */
	public function translate( $cache_id, $text, $domain, $context = null ) {
		if ( ! isset( $this->cache[ $this->current_locale ][ $cache_id ] ) ) {
			$translation                                       = null === $context ? translate( $text, $domain ) : translate_with_gettext_context( $text, $context, $domain );
			$this->cache[ $this->current_locale ][ $cache_id ] = $translation;
		}

		return $this->cache[ $this->current_locale ][ $cache_id ];
	}

	/**
	 * Lazily translate the given text.
	 *
	 * @since 6.5.0
	 *
	 * @param int    $cache_id The cache ID identifying this translation.
	 * @param string $single   The text to be used if the number is singular.
	 * @param string $plural   The text to be used if the number is plural.
	 * @param int    $number   The number to compare against to use either the singular or plural form.
	 * @param string $domain   Optional. Text domain. Unique identifier for retrieving translated strings.
	 *                         Default 'default'.
	 * @param string $context  Optional. Context information for the translators. Default null.
	 * @return string The translated text.
	 */
	public function translate_plural( $cache_id, $single, $plural, $number, $domain, $context = null ) {
		if ( ! isset( $this->cache[ $this->current_locale ][ $cache_id ] ) ) {
			$translations = get_translations_for_domain( $domain );
			$translation  = $translations->translate_plural( $single, $plural, $number, $context );

			if ( null === $context ) {
				/**
				 * Filters the singular or plural form of a string.
				 *
				 * @since 2.2.0
				 *
				 * @param string $translation Translated text.
				 * @param string $single      The text to be used if the number is singular.
				 * @param string $plural      The text to be used if the number is plural.
				 * @param int    $number      The number to compare against to use either the singular or plural form.
				 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
				 */
				$translation = apply_filters( 'ngettext', $translation, $single, $plural, $number, $domain );

				/**
				 * Filters the singular or plural form of a string for a domain.
				 *
				 * The dynamic portion of the hook name, `$domain`, refers to the text domain.
				 *
				 * @since 5.5.0
				 *
				 * @param string $translation Translated text.
				 * @param string $single      The text to be used if the number is singular.
				 * @param string $plural      The text to be used if the number is plural.
				 * @param int    $number      The number to compare against to use either the singular or plural form.
				 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
				 */
				$translation = apply_filters( "ngettext_{$domain}", $translation, $single, $plural, $number, $domain );
			} else {
				/**
				 * Filters the singular or plural form of a string with gettext context.
				 *
				 * @since 2.8.0
				 *
				 * @param string $translation Translated text.
				 * @param string $single      The text to be used if the number is singular.
				 * @param string $plural      The text to be used if the number is plural.
				 * @param int    $number      The number to compare against to use either the singular or plural form.
				 * @param string $context     Context information for the translators.
				 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
				 */
				$translation = apply_filters( 'ngettext_with_context', $translation, $single, $plural, $number, $context, $domain );

				/**
				 * Filters the singular or plural form of a string with gettext context for a domain.
				 *
				 * The dynamic portion of the hook name, `$domain`, refers to the text domain.
				 *
				 * @since 5.5.0
				 *
				 * @param string $translation Translated text.
				 * @param string $single      The text to be used if the number is singular.
				 * @param string $plural      The text to be used if the number is plural.
				 * @param int    $number      The number to compare against to use either the singular or plural form.
				 * @param string $context     Context information for the translators.
				 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
				 */
				$translation = apply_filters( "ngettext_with_context_{$domain}", $translation, $single, $plural, $number, $context, $domain );
			}

			$this->cache[ $this->current_locale ][ $cache_id ] = $translation;
		}

		return $this->cache[ $this->current_locale ][ $cache_id ];
	}


	/**
	 * Lazily translate the given text with context.
	 *
	 * @since 6.5.0
	 *
	 * @param int    $cache_id The cache ID identifying this translation.
	 * @param string $text     The untranslated text.
	 * @param string $context  The translation context.
	 * @param string $domain   The text domain.
	 *
	 * @return string
	 */
	public function translate_with_context( $cache_id, $text, $context, $domain ) {
		if ( ! isset( $this->cache[ $this->current_locale ][ $cache_id ] ) ) {
			$this->cache[ $this->current_locale ][ $cache_id ] = translate_with_gettext_context( $text, $context, $domain );
		}

		return $this->cache[ $this->current_locale ][ $cache_id ];
	}

	/**
	 * Remove the entry from the cache.
	 *
	 * @since 6.5.0
	 *
	 * @param int $cache_id
	 */
	public function clear_translation( $cache_id ) {
		foreach ( $this->cache as $locale => $translations ) {
			unset( $this->cache[ $locale ][ $cache_id ] );
		}
	}

	/**
	 * Completely clear the translation cache.
	 *
	 * @since 6.5.0
	 */
	public function clear() {
		$this->cache = array();
	}

	/**
	 * Prune the translation cache.
	 *
	 * @since 6.5.0
	 */
	private function prune_cache() {
		$i = 0;

		foreach ( array_reverse( $this->cache ) as $locale => $items ) {
			++$i;

			if ( $locale === $this->original_locale ) {
				continue;
			}

			if ( $i > self::MAX_LOCALES ) {
				unset( $this->cache[ $locale ] );
			}
		}
	}
}
