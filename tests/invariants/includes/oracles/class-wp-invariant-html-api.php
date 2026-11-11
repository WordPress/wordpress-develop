<?php
/**
 * Reads HTML as WordPress's HTML API reads it, as an oracle for invariants.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Asks WP_HTML_Tag_Processor where the syntax of HTML is.
 *
 * The tag processor follows the tokenizer of the HTML standard, so it finds the
 * tags, attributes and comments a browser finds, at their byte offsets, which
 * Dom\HTMLDocument does not give. It is WordPress's own code, so it is an
 * independent reading of what regular expressions and string functions make of
 * HTML, but not of the HTML API itself.
 *
 * It is only available once WordPress has loaded the HTML API. Its methods
 * return null before then.
 */
final class WP_Invariant_HTML_API {

	/**
	 * How many HTML strings boundaries() remembers.
	 */
	const CACHE = 16;

	/**
	 * Boundaries of the HTML strings boundaries() read last, by HTML.
	 *
	 * @var array[]
	 */
	private static $boundaries = array();

	/**
	 * Returns the markup in HTML, counted by kind.
	 *
	 * Text is left out, and so is what only looks like markup inside the contents
	 * of elements such as SCRIPT, TEXTAREA and TITLE, or inside comments. A tag
	 * which the end of the HTML cuts off is left out too, as a browser leaves it
	 * out.
	 *
	 * @param string $html HTML.
	 * @return int[]|null By kind: `<p` for a P start tag, `</p` for an end tag, `<!--` for any comment, and `<!DOCTYPE`.
	 */
	public static function markup( $html ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
			return null;
		}

		$counts    = array();
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_token() ) {
			switch ( $processor->get_token_type() ) {
				case '#tag':
					$kind = ( $processor->is_tag_closer() ? '</' : '<' ) . strtolower( $processor->get_tag() );
					break;

				case '#comment':
				case '#funky-comment':
					$kind = '<!--';
					break;

				case '#doctype':
					$kind = '<!DOCTYPE';
					break;

				default:
					continue 2;
			}

			$counts[ $kind ] = ( isset( $counts[ $kind ] ) ? $counts[ $kind ] : 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * Returns where the text nodes of HTML are.
	 *
	 * The contents of elements such as SCRIPT, TEXTAREA and TITLE are not text
	 * nodes here, and neither are attribute values or comments.
	 *
	 * @param string $html HTML.
	 * @return int[][]|null Each as `array( start, end )`, in bytes, with the end not included.
	 */
	public static function texts( $html ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
			return null;
		}

		$class     = 'WP_HTML_Tag_Processor';
		$texts     = array();
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_token() ) {
			if ( '#text' === $processor->get_token_type() ) {
				$start   = WP_Invariant::property( $processor, $class, 'token_starts_at' );
				$texts[] = array( $start, $start + WP_Invariant::property( $processor, $class, 'token_length' ) );
			}
		}

		return $texts;
	}

	/**
	 * Returns where the syntax of HTML starts: tags, tag names, attributes, attribute values, comments and DOCTYPE declarations.
	 *
	 * Only tags whose names are ASCII letters, digits and hyphens are included,
	 * and no comment which starts with `<?`, so that a string which is not HTML
	 * but has `<` in it, such as `Name <name@example.com>` or PHP code, has none.
	 *
	 * @param string $html HTML.
	 * @return string[]|null Kinds by byte offset: `tag`, `tag name`, `attribute`, `attribute value`, `comment` or `DOCTYPE`.
	 */
	public static function boundaries( $html ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor', false ) ) {
			return null;
		}
		if ( isset( self::$boundaries[ $html ] ) ) {
			return self::$boundaries[ $html ];
		}

		$class      = 'WP_HTML_Tag_Processor';
		$boundaries = array();
		$processor  = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_token() ) {
			$type  = $processor->get_token_type();
			$start = WP_Invariant::property( $processor, $class, 'token_starts_at' );

			if ( '#tag' === $type ) {
				$name = strtolower( $processor->get_tag() );
				if ( strspn( $name, 'abcdefghijklmnopqrstuvwxyz0123456789-' ) !== strlen( $name ) ) {
					continue;
				}

				$boundaries[ $start ] = 'tag';
				$boundaries[ WP_Invariant::property( $processor, $class, 'tag_name_starts_at' ) ] = 'tag name';
				foreach ( WP_Invariant::property( $processor, $class, 'attributes' ) as $attribute ) {
					$boundaries[ $attribute->start ] = 'attribute';
					if ( ! $attribute->is_true ) {
						$boundaries[ $attribute->value_starts_at ] = 'attribute value';
					}
				}
				foreach ( (array) WP_Invariant::property( $processor, $class, 'duplicate_attributes' ) as $spans ) {
					foreach ( $spans as $span ) {
						$boundaries[ $span->start ] = 'attribute';
					}
				}
			} elseif ( ( '#comment' === $type || '#funky-comment' === $type ) && '?' !== $html[ $start + 1 ] ) {
				$boundaries[ $start ] = 'comment';
			} elseif ( '#doctype' === $type ) {
				$boundaries[ $start ] = 'DOCTYPE';
			}
		}

		ksort( $boundaries );
		if ( count( self::$boundaries ) >= self::CACHE ) {
			unset( self::$boundaries[ array_key_first( self::$boundaries ) ] );
		}
		self::$boundaries[ $html ] = $boundaries;
		return $boundaries;
	}
}
