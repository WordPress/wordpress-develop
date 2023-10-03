<?php
/**
 * Main functionality.
 *
 * @package WordPress
 */

/**
 * Class Ginger_MO.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Ginger_MO {
	/**
	 * Current locale.
	 *
	 * @var string
	 */
	protected $current_locale = 'en_US';

	/**
	 * Map of loaded translations per locale and text domain.
	 *
	 * [ Locale => [ Textdomain => [ .., .. ] ] ]
	 *
	 * @var array<string, array<string, Ginger_MO_Translation_File[]>>
	 */
	protected $loaded_translations = array();

	/**
	 * List of loaded translation files.
	 *
	 * [ Filename => [ Locale => [ Textdomain => Ginger_MO_Translation_File ] ] ]
	 *
	 * @var array<string,array<string, array<string, Ginger_MO_Translation_File|false>>>
	 */
	protected $loaded_files = array();

	/**
	 * Returns the Ginger_MO singleton.
	 *
	 * @return Ginger_MO
	 */
	public static function instance(): Ginger_MO {
		static $instance;

		if ( ! $instance ) {
			$instance = new Ginger_MO();
		}

		return $instance;
	}

	/**
	 * Returns the current locale.
	 *
	 * @return string Locale.
	 */
	public function get_locale(): string {
		return $this->current_locale;
	}

	/**
	 * Sets the current locale.
	 *
	 * @param string $locale Locale.
	 * @return void
	 */
	public function set_locale( string $locale ) {
		$this->current_locale = $locale;
	}

	/**
	 * Loads a translation file.
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @param string $translation_file Translation file.
	 * @param string $textdomain       Text domain.
	 * @param string $locale           Optional. Locale. Default current locale.
	 * @return bool True on success, false otherwise.
	 */
	public function load( string $translation_file, string $textdomain = 'default', string $locale = null ): bool {
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
			$moe = Ginger_MO_Translation_File::create( $translation_file );
			if ( false === $moe || false !== $moe->error() ) {
				$moe = false;
			}
		}

		$this->loaded_files[ $translation_file ][ $locale ][ $textdomain ] = $moe;

		if ( ! $moe instanceof Ginger_MO_Translation_File ) {
			return false;
		}

		if ( ! isset( $this->loaded_translations[ $locale ][ $textdomain ] ) ) {
			$this->loaded_translations[ $locale ][ $textdomain ] = array();
		}

		// Ensure that last-loaded translation takes precedence.
		array_unshift( $this->loaded_translations[ $locale ][ $textdomain ], $moe );

		return true;
	}

	/**
	 * Unload all translation files or a specific one for a given text domain.
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @param string                            $textdomain Text domain.
	 * @param Ginger_MO_Translation_File|string $mo         Translation file instance or file name.
	 * @param string                            $locale     Optional. Locale. Default all locales.
	 * @return bool True on success, false otherwise.
	 */
	public function unload( string $textdomain = 'default', $mo = null, string $locale = null ): bool {
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
	 * @param string $textdomain Text domain.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return bool True if there are any loaded translations, false otherwise.
	 */
	public function is_loaded( string $textdomain = 'default', string $locale = null ): bool {
		if ( null === $locale ) {
			$locale = $this->current_locale;
		}

		return isset( $this->loaded_translations[ $locale ][ $textdomain ] ) &&
			array() !== $this->loaded_translations[ $locale ][ $textdomain ];
	}

	/**
	 * Translates a singular string.
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
	 * @todo Revisit this.
	 *
	 * @param array{0: string, 1: string} $plurals    Pair of singular and plural translation.
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

		/* @var Ginger_MO_Translation_File $source */
		$source = $translation['source'];
		$num    = $source->get_plural_form( $number );

		// TODO: Use nplurals from Plural-Forms header?
		// See \Translations::translate_plural() in core.

		return $translation['entries'][ $num ] ?? $translation['entries'][0];
	}

	/**
	 * Returns all existing headers for a given text domain.
	 *
	 * @param string $textdomain Text domain.
	 * @return array<string, string> Headers.
	 */
	public function get_headers( string $textdomain = 'default' ): array {
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
	 * @param string $header Header name.
	 * @return string Normalized header name.
	 */
	protected function normalize_header( string $header ): string {
		$parts = explode( '-', $header );
		$parts = array_map( 'ucfirst', $parts );
		return implode( '-', $parts );
	}

	/**
	 * Returns all entries for a given text domain.
	 *
	 * @param string $textdomain Text domain.
	 * @return array<string, string> Entries.
	 */
	public function get_entries( string $textdomain = 'default' ): array {
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
	 * @param string $singular   Singular translation.
	 * @param string $textdomain Text domain.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return array{source: Ginger_MO_Translation_File, entries: string[]}|false Translations on success, false otherwise.
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
	 * @param string $textdomain Text domain.
	 * @param string $locale     Optional. Locale. Default current locale.
	 * @return Ginger_MO_Translation_File[] List of translation files.
	 */
	protected function get_files( string $textdomain = 'default', string $locale = null ): array {
		if ( null === $locale ) {
			$locale = $this->current_locale;
		}

		return $this->loaded_translations[ $locale ][ $textdomain ] ?? array();
	}
}
