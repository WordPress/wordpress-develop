<?php
/**
 * I18N: WP_I18n_Translation_Controller class.
 *
 * @package WordPress
 * @subpackage WP_I18n_Translation_Controller
 * @since 6.5.0
 */

/**
 * Class WP_I18n_Translation_Controller.
 *
 * @since 6.5.0
 */
class WP_I18n_Translation_Controller {
	/**
	 * Current locale.
	 *
	 * @since 6.5.0
	 *
	 * @var string
	 */
	protected $current_locale = 'en_US';

	/**
	 * Map of loaded translations per locale and text domain.
	 *
	 * [ Locale => [ Textdomain => [ .., .. ] ] ]
	 *
	 * @since 6.5.0
	 *
	 * @var array<string, array<string, WP_I18n_Translation_File[]>>
	 */
	protected $loaded_translations = array();

	/**
	 * List of loaded translation files.
	 *
	 * [ Filename => [ Locale => [ Textdomain => WP_I18n_Translation_File ] ] ]
	 *
	 * @since 6.5.0
	 *
	 * @var array<string,array<string, array<string, WP_I18n_Translation_File|false>>>
	 */
	protected $loaded_files = array();

	/**
	 * Returns the WP_I18n_Translation_Controller singleton.
	 *
	 * @since 6.5.0
	 *
	 * @return WP_I18n_Translation_Controller
	 */
	public static function instance() {
		static $instance;

		if ( ! $instance ) {
			$instance = new WP_I18n_Translation_Controller();
		}

		return $instance;
	}

	/**
	 * Returns the current locale.
	 *
	 * @since 6.5.0
	 *
	 * @return string Locale.
	 */
	public function get_locale() {
		return $this->current_locale;
	}

	/**
	 * Sets the current locale.
	 *
	 * @since 6.5.0
	 *
	 * @param string $locale Locale.
	 */
	public function set_locale( string $locale ) {
		$this->current_locale = $locale;
	}

	/**
	 * Loads a translation file.
	 *
	 * @since 6.5.0
	 *
	 * @param string $translation_file Translation file.
	 * @param string $textdomain       Text domain.
	 * @param string $locale           Optional. Locale. Default current locale.
	 * @return bool True on success, false otherwise.
	 */
	public function load( string $translation_file, string $textdomain = 'default', string $locale = null ) {
		if ( null === $locale ) {
			$locale = $this->current_locale;
		}

		$translation_file = realpath( $translation_file );

		if ( false === $translation_file ) {
			return false;
		}

		if (
			isset( $this->loaded_files[ $translation_file ][ $locale ][ $textdomain ] ) &&
			false !== $this->loaded_files[ $translation_file ][ $locale ][ $textdomain ]
		) {
			return false === $this->loaded_files[ $translation_file ][ $locale ][ $textdomain ]->error();
		}

		if (
			isset( $this->loaded_files[ $translation_file ][ $locale ] ) &&
			array() !== $this->loaded_files[ $translation_file ][ $locale ]
		) {
			$moe = reset( $this->loaded_files[ $translation_file ][ $locale ] );
		} else {
			$moe = WP_I18n_Translation_File::create( $translation_file );
			if ( false === $moe || false !== $moe->error() ) {
				$moe = false;
			}
		}

		$this->loaded_files[ $translation_file ][ $locale ][ $textdomain ] = $moe;

		if ( ! $moe instanceof WP_I18n_Translation_File ) {
			return false;
		}

		if ( ! isset( $this->loaded_translations[ $locale ][ $textdomain ] ) ) {
			$this->loaded_translations[ $locale ][ $textdomain ] = array();
		}

		$this->loaded_translations[ $locale ][ $textdomain ][] = $moe;

		return true;
	}

	/**
	 * Unloads all translation files or a specific one for a given text domain.
	 *
	 * @since 6.5.0
	 *
	 * @param string                          $textdomain Optional. Text domain. Default 'default'.
	 * @param WP_I18n_Translation_File|string $mo         Translation file instance or file name.
	 * @param string                          $locale     Optional. Locale. Default all locales.
	 * @return bool True on success, false otherwise.
	 */
	public function unload( string $textdomain = 'default', $mo = null, string $locale = null ) {
		if ( ! $this->is_loaded( $textdomain, $locale ) ) {
			return false;
		}

		if ( null !== $mo ) {
			if ( is_string( $mo ) ) {
				$mo = realpath( $mo );
			}

			if ( null !== $locale ) {
				foreach ( $this->loaded_translations[ $locale ][ $textdomain ] as $i => $moe ) {
					if ( $mo === $moe || $mo === $moe->get_file() ) {
						unset( $this->loaded_translations[ $locale ][ $textdomain ][ $i ] );
						unset( $this->loaded_files[ $moe->get_file() ][ $locale ][ $textdomain ] );
						return true;
					}
				}

				return true;
			}

			foreach ( $this->loaded_translations as $l => $domains ) {
				foreach ( $domains[ $textdomain ] as $i => $moe ) {
					if ( $mo === $moe || $mo === $moe->get_file() ) {
						unset( $this->loaded_translations[ $l ][ $textdomain ][ $i ] );
						unset( $this->loaded_files[ $moe->get_file() ][ $l ][ $textdomain ] );
						return true;
					}
				}
			}

			return true;
		}

		if ( null !== $locale ) {
			foreach ( $this->loaded_translations[ $locale ][ $textdomain ] as $moe ) {
				unset( $this->loaded_files[ $moe->get_file() ][ $locale ][ $textdomain ] );
			}

			unset( $this->loaded_translations[ $locale ][ $textdomain ] );

			return true;
		}

		foreach ( $this->loaded_translations as $l => $domains ) {
			if ( ! isset( $domains[ $textdomain ] ) ) {
				continue;
			}

			foreach ( $domains[ $textdomain ] as $moe ) {
				unset( $this->loaded_files[ $moe->get_file() ][ $l ][ $textdomain ] );
			}

			unset( $this->loaded_translations[ $l ][ $textdomain ] );
		}

		return true;
	}

	/**
	 * Determines whether translations are loaded for a given text domain.
	 *
	 * @since 6.5.0
	 *
	 * @param string $textdomain Optional. Text domain. Default 'default'.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return bool True if there are any loaded translations, false otherwise.
	 */
	public function is_loaded( string $textdomain = 'default', string $locale = null ) {
		if ( null === $locale ) {
			$locale = $this->current_locale;
		}

		return isset( $this->loaded_translations[ $locale ][ $textdomain ] ) &&
			array() !== $this->loaded_translations[ $locale ][ $textdomain ];
	}

	/**
	 * Translates a singular string.
	 *
	 * @since 6.5.0
	 *
	 * @param string $text       Text to translate.
	 * @param string $context    Optional. Context for the string.
	 * @param string $textdomain Text domain.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return string|false Translation on success, false otherwise.
	 */
	public function translate( string $text, string $context = '', string $textdomain = 'default', string $locale = null ) {
		if ( '' !== $context ) {
			$context .= "\4";
		}

		$translation = $this->locate_translation( "{$context}{$text}", $textdomain, $locale );

		if ( false === $translation ) {
			return false;
		}

		return $translation['entries'][0];
	}

	/**
	 * Translates plurals.
	 *
	 * Checks both singular+plural combinations as well as just singulars,
	 * in case the translation file does not store the plural.
	 *
	 * @since 6.5.0
	 *
	 * @param array{0: string, 1: string} $plurals    Pair of singular and plural translations.
	 * @param int                         $number     Number of items.
	 * @param string                      $context    Optional. Context for the string.
	 * @param string                      $textdomain Text domain.
	 * @param string                      $locale     Optional. Locale. Default current locale.
	 * @return string|false Translation on success, false otherwise.
	 */
	public function translate_plural( array $plurals, int $number, string $context = '', string $textdomain = 'default', string $locale = null ) {
		if ( '' !== $context ) {
			$context .= "\4";
		}

		$text        = implode( "\0", $plurals );
		$translation = $this->locate_translation( "{$context}{$text}", $textdomain, $locale );

		if ( false === $translation ) {
			$text        = $plurals[0];
			$translation = $this->locate_translation( "{$context}{$text}", $textdomain, $locale );

			if ( false === $translation ) {
				return false;
			}
		}

		/* @var WP_I18n_Translation_File $source */
		$source = $translation['source'];
		$num    = $source->get_plural_form( $number );

		// See \Translations::translate_plural().
		return $translation['entries'][ $num ] ?? $translation['entries'][0];
	}

	/**
	 * Returns all existing headers for a given text domain.
	 *
	 * @since 6.5.0
	 *
	 * @param string $textdomain Text domain.
	 * @return array<string, string> Headers.
	 */
	public function get_headers( string $textdomain = 'default' ) {
		if ( array() === $this->loaded_translations ) {
			return array();
		}

		$headers = array();

		foreach ( $this->get_files( $textdomain ) as $moe ) {
			foreach ( $moe->headers() as $header => $value ) {
				$headers[ $this->normalize_header( $header ) ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Normalizes header names to be capitalized.
	 *
	 * @since 6.5.0
	 *
	 * @param string $header Header name.
	 * @return string Normalized header name.
	 */
	protected function normalize_header( string $header ) {
		$parts = explode( '-', $header );
		$parts = array_map( 'ucfirst', $parts );
		return implode( '-', $parts );
	}

	/**
	 * Returns all entries for a given text domain.
	 *
	 * @since 6.5.0
	 *
	 * @param string $textdomain Text domain.
	 * @return array<string, string> Entries.
	 */
	public function get_entries( string $textdomain = 'default' ) {
		if ( array() === $this->loaded_translations ) {
			return array();
		}

		$entries = array();

		foreach ( $this->get_files( $textdomain ) as $moe ) {
			$entries = array_merge( $entries, $moe->entries() );
		}

		return $entries;
	}

	/**
	 * Locates translation for a given string and text domain.
	 *
	 * @since 6.5.0
	 *
	 * @param string $singular   Singular translation.
	 * @param string $textdomain Text domain.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return array{source: WP_I18n_Translation_File, entries: string[]}|false Translations on success, false otherwise.
	 */
	protected function locate_translation( string $singular, string $textdomain = 'default', string $locale = null ) {
		if ( array() === $this->loaded_translations ) {
			return false;
		}

		// Find the translation in all loaded files for this text domain.
		foreach ( $this->get_files( $textdomain, $locale ) as $moe ) {
			$translation = $moe->translate( $singular );
			if ( false !== $translation ) {
				return array(
					'entries' => explode( "\0", $translation ),
					'source'  => $moe,
				);
			}
			if ( false !== $moe->error() ) {
				// Unload this file, something is wrong.
				$this->unload( $textdomain, $moe, $locale );
			}
		}

		// Nothing could be found.
		return false;
	}

	/**
	 * Returns all translation files for a given text domain.
	 *
	 * @since 6.5.0
	 *
	 * @param string $textdomain Text domain.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return WP_I18n_Translation_File[] List of translation files.
	 */
	protected function get_files( string $textdomain = 'default', string $locale = null ) {
		if ( null === $locale ) {
			$locale = $this->current_locale;
		}

		return $this->loaded_translations[ $locale ][ $textdomain ] ?? array();
	}
}
