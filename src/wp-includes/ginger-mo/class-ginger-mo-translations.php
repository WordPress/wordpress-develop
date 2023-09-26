<?php
/**
 * Class Ginger_MO_Translations.
 *
 * @package WordPress
 */

/**
 * Class Ginger_MO_Translations.
 *
 * @property-read array<string, string> $headers
 * @property-read array<string, string[]> $entries
 */
class Ginger_MO_Translations {
	/**
	 * Text domain.
	 *
	 * @var string
	 */
	protected $textdomain = 'default';

	/**
	 * Constructor.
	 *
	 * @param string $textdomain Text domain.
	 */
	public function __construct( string $textdomain = 'default' ) {
		$this->textdomain = $textdomain;
	}

	/**
	 * Magic getter for backward compatibility.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( string $name ) {
		if ( 'entries' === $name ) {
			$entries = Ginger_MO::instance()->get_entries( $this->textdomain );

			$result = array();

			foreach ( $entries as $original => $translations ) {
				$result[] = $this->make_entry( $original, $translations );
			}

			return $result;
		}

		if ( 'headers' === $name ) {
			return Ginger_MO::instance()->get_headers( $this->textdomain );
		}

		return null;
	}

	/**
	 * Magic setter.
	 *
	 * @param string $name Property name.
	 * @param mixed  $value Property value.
	 * @return void
	 */
	public function __set( string $name, $value ) {}

	/**
	 * Build a Translation_Entry from original string and translation strings.
	 *
	 * @see MO::make_entry()
	 *
	 * @param string $original    Original string to translate from MO file. Might contain
	 *                            0x04 as context separator or 0x00 as singular/plural separator.
	 * @param string $translations Translation strings from MO file.
	 * @return Translation_Entry Entry instance.
	 */
	private function make_entry( $original, $translations ): Translation_Entry {
		$entry = new Translation_Entry();

		// Look for context, separated by \4.
		$parts = explode( "\4", $original );
		if ( isset( $parts[1] ) ) {
			$original       = $parts[1];
			$entry->context = $parts[0];
		}

		// Look for plural original.
		$parts           = explode( "\0", $original );
		$entry->singular = $parts[0];
		if ( isset( $parts[1] ) ) {
			$entry->is_plural = true;
			$entry->plural    = $parts[1];
		}

		$entry->translations = explode( "\0", $translations );
		return $entry;
	}

	/**
	 * Translates a plural string.
	 *
	 * @param string|null $singular Singular string.
	 * @param string|null $plural   Plural string.
	 * @param int|float   $count    Count. Should be an integer, but some plugins pass floats.
	 * @param string|null $context  Context.
	 * @return string|null Translation if it exists, or the unchanged singular string.
	 */
	public function translate_plural( $singular, $plural, $count = 1, $context = '' ) {
		if ( null === $singular || null === $plural ) {
			return $singular;
		}

		$translation = Ginger_MO::instance()->translate_plural( array( $singular, $plural ), (int) $count, (string) $context, $this->textdomain );
		if ( false !== $translation ) {
			return $translation;
		}

		// Fall back to the original with English grammar rules.
		return ( 1 === $count ? $singular : $plural );
	}

	/**
	 * Translates a singular string.
	 *
	 * @param string|null $singular Singular string.
	 * @param string|null $context  Context.
	 * @return string|null Translation if it exists, or the unchanged singular string
	 */
	public function translate( $singular, $context = '' ) {
		if ( null === $singular ) {
			return $singular;
		}

		$translation = Ginger_MO::instance()->translate( $singular, (string) $context, $this->textdomain );
		if ( false !== $translation ) {
			return $translation;
		}

		// Fall back to the original.
		return $singular;
	}
}
