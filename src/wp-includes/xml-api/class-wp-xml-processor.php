<?php

/**
 * XML API: WP_XML_Processor class.
 *
 * This class incorporates an XML Processor with the following general characteristics:
 *
 *  - It ignores DTDs, merely parsing their syntax and ignoring their semantics.
 *  - It makes no network calls while parsing and loads no external documents.
 *  - The only entity expansion performed is for the five built-in entities.
 *  - It requires UTF-8 encoding of the input document, but passes through invalid byte sequences.
 *  - Rejects fatal errors in parsing, such as if the document is not well-formed.
 *  - Otherwise is a recoverable parser, taking liberties to let garbage remain garbage.
 *
 * @see https://www.w3.org/TR/xml/
 *
 * @todo Currently this only supports US-ASCII content. Needs an efficient streamable UTF-8 decoder
 *       for validation. See https://github.com/WordPress/wordpress-develop/pull/6883
 *
 * @package WordPress
 * @subpackage XML-API
 * @since {WP_VERSION}
 */
class WP_XML_Processor {
	/**
	 * Parsing should resume at this byte offset from the
	 * start of the document.
	 *
	 * @var int
	 */
	private $next_byte_at = 0;

	protected $parser_state = self::STATE_READY;

	protected $level_of_concern = self::CONCERNED_ABOUT_EVERYTHING;

	/**
	 * Input document to the parser.
	 *
	 * @var string
	 */
	private $xml = '';

	public static function create_full_parser( string $xml ) {

	}

	public static function create_fragment( string $xml ) {
		return new static( $xml );
	}

	private function __construct( string $xml ) {
		$this->xml = $xml;
	}

	/**
	 * Advances to the next syntactic token in the XML document.
	 *
	 * @since {WP_VERSION}
	 *
	 * @return bool Whether a well-formed token was found.
	 */
	public function next_token(): bool {
		try {
			return $this->base_class_next_token();
		} catch ( WP_XML_Malformed_Error $e ) {
			/*
			 * @todo Return `true` after preventing normative functions like `get_token_name()`
			 *       from returning values. `get_malformed_token_name()` family of functions
			 *       should take their place.
			 */
			return false;
		}
	}

	/**
	 * Advances to the next syntactic token in the XML document.
	 *
	 * @since {WP_VERSION}
	 *
	 * @throws WP_XML_Malformed_Error When encountering an error of sufficient concern.
	 *
	 * @return bool
	 */
	public function base_class_next_token(): bool {
		$xml          = $this->xml;
		$at           = $this->next_byte_at;
		$end          = strlen( $xml );
		$last_byte_at = $end - 1;

		while ( $at < $end ) {
			$was_at = $at;

			/*
			 * Transitions in the parsing model occur at the following:
			 *
			 *  - `<` starts tags, comments, CDATA, DTDs, PI nodes, and declarations
			 *  - `&` starts entity refs and character refs.
			 *  - `%` starts parameter-entity refs.
			 *
			 * @todo Probably only need to transition on `<`.
			 */
			$next_transition_after = strcspn( $xml, '<&%', $at, $end - $at );
			$at                    = $at + $next_transition_after;
			if ( $at >= $end ) {
				// @todo Return a text node?
			}

			if ( $at > $was_at ) {
				// @todo Create a text node.
			}

			// Officially move to where this token begins.
			$this->next_byte_at = $at;

			// Enter a comment.
			if ( 0 === substr_compare( $xml, '<!--', $at, 4 ) ) {
				$text_starts_at = $at + 4;
				$closer_at      = strpos( $xml, '-->', min( $end, $text_starts_at ) );

				// Maybe the comment ends in the next chunk.
				if ( false === $closer_at ) {
					$this->parser_state = self::STATE_INCOMPLETE;
					return false;
				}

				$this->parser_state = self::STATE_COMMENT;
				$this->next_byte_at = $closer_at + 3;

				if (
					$this->level_of_concern >= self::CONCERNED_ABOUT_CONTENT_ERRORS &&
					( $closer_at - $text_starts_at ) !== self::skip_chars( $xml, $text_starts_at, $closer_at )
				) {
					$this->malformed(
						self::CONCERNED_ABOUT_CONTENT_ERRORS,
						'Comment nodes must contain only allowable characters.'
					);
				}

				if (
					$this->level_of_concern >= self::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS &&
					strpos( $xml, '--', $text_starts_at ) < $closer_at
				) {
					/*
					 * In SGML, the double-hyphen delimits comments within markup
					 * declarations. In order to maintain compatability with SGML
					 * parsers, XML thus forbids their inclusion inside comments.
					 *
					 * Since WordPress is not concerned with SGML parsers, this
					 * parser considers this a benign syntax error, as it does not
					 * confuse the understanding of the structure of the input.
					 */
					$this->malformed(
						self::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS,
						'Comment nodes must not contain a double-hyphen.'
					);
				}

				return true;
			}

			// Enter a Processing Instruction Node.
			if ( 0 === substr_compare( $xml, '<?', $at, 2 ) ) {
				$text_starts_at = $at + 2;

				// Because "?" followed by ">" is special syntax in PHP, this sequence is escaped here.
				$closer_at = strpos( $xml, "\x3F\x3E", min( $end, $text_starts_at ) );

				if ( false === $closer_at ) {
					$this->parser_state = self::STATE_INCOMPLETE;
					return false;
				}

				$target_length = self::skip_whitespace( $xml, $text_starts_at, $closer_at );

				if ( $this->level_of_concern >= self::CONCERNED_ABOUT_UNRESOLVABLES && 0 === $target_length ) {
					$this->malformed(
						self::CONCERNED_ABOUT_UNRESOLVABLES,
						'Processing instruction nodes must contain a target indicating to which application the instruction is directed.'
					);
				}

				if ( $this->level_of_concern >= self::CONCERNED_ABOUT_CONTENT_ERRORS && self::skip_name( $xml, $text_starts_at ) !== $target_length ) {
					$this->malformed(
						self::CONCERNED_ABOUT_CONTENT_ERRORS,
						'Processing instruction target names must contain only allowable characters.'
					);
				}

				// This is an XML Declaration.
				if ( 3 === $target_length && 0 === substr_compare( $xml, $text_starts_at, 'xml', 3, false ) ) {
					$this->parser_state = self::STATE_XML_DECLARATION;

					// @todo Parse version and encoding and standalone declaration.
					return true;
				}

				if ( $this->level_of_concern >= self::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS ) {
					if ( 3 === $target_length && 0 === substr_compare( $xml, $text_starts_at, 'xml', 3, true ) ) {
						$this->malformed(
							self::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS,
							'Processing instruction target names matching "XML" (in a case-insensitive manner) are reserved names.'
						);
					}

					$character_data_length = $closer_at - ( $text_starts_at + $target_length );
					if ( self::skip_chars( $xml, $text_starts_at + $target_length, $closer_at ) !== $character_data_length ) {
						$this->malformed(
							self::CONCERNED_ABOUT_CONTENT_ERRORS,
							'Processing instruction nodes must contain only allowable characters.'
						);
					}
				}

				return true;
			}
		}

		// @todo Placeholder until other tokens are supported.
		return false;
	}

	/**
	 * Pauses the parser and indicates that the currently-matched token is malformed.
	 *
	 * @since {WP_VERSION}
	 *
	 * @see self::CONCERNED_ABOUT_EVERYTHING
	 * @see self::CONCERNED_ABOUT_CONTENT_ERRORS
	 * @see self::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS
	 * @see self::CONCERNED_ABOUT_UNRESOLVABLE_SYNTAX_ERRORS
	 * @see self::CONCERNED_ABOUT_UNRESOLVABLES
	 *
	 * @throws WP_XML_Malformed_Error This function is a helper to throw this error.
	 *
	 * @param int    $level_of_concern One of the levels-of-concern constants in this class.
	 * @param string $reason           Explanation for why the markup is malformed.
	 */
	protected function malformed( int $level_of_concern, string $reason ): void {
		$this->parser_state |= self::STATE_MALFORMED;
		throw new WP_XML_Malformed_Error( $level_of_concern, $reason );
	}

	/**
	 * Starts parsing an XML document.
	 *
	 * > document ::= prolog element Misc*
	 *
	 * @see https://www.w3.org/TR/xml/#NT-document
	 *
	 * @return bool
	 */
	private function step_initial(): bool {
		// Look for prolog
	}

	/*
	 * Parser helpers.
	 */

	/**
	 * Parses a sequence of XML `Char`.
	 *
	 * > [2] Char ::= #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
	 * >     // any Unicode character, excluding the surrogate blocks, FFFE, and FFFF.
	 *
	 * @see https://www.w3.org/TR/xml/#dt-character
	 *
	 * @param string   $xml
	 * @param int      $start_at
	 * @param int|null $end_at
	 *
	 * @return int
	 */
	public static function skip_chars( string $xml, int $start_at, int $end_at = null ): int {
		$at  = $start_at;
		$end = $end_at ?? strlen( $xml );

		while ( $at < $end ) {
			$code_point = ord( $xml[ $at ] );

			if (
				( 0x20 <= $code_point && $code_point <= 0x7F ) ||
				0x09 === $code_point || 0x0A === $code_point || 0x0D === $code_point
			) {
				++$at;
				continue;
			}

			break;
		}

		return $at - $start_at;
	}

	/**
	 * Parses an XML `Name`.
	 *
	 * > [4]  NameStartChar ::= ":" | [A-Z] | "_" | [a-z] | [#xC0-#xD6] | [#xD8-#xF6] |
	 * >                        [#xF8-#x2FF] | [#x370-#x37D] | [#x37F-#x1FFF] |
	 * >                        [#x200C-#x200D] | [#x2070-#x218F] | [#x2C00-#x2FEF] |
	 * >                        [#x3001-#xD7FF] | [#xF900-#xFDCF] | [#xFDF0-#xFFFD] |
	 * >                        [#x10000-#xEFFFF]
	 * > [4a] NameChar      ::= NameStartChar | "-" | "." | [0-9] | #xB7 |
	 * >                        [#x0300-#x036F] | [#x203F-#x2040]
	 * > [5]  Name          ::= NameStartChar (NameChar)*
	 *
	 * @see https://www.w3.org/TR/xml/#NT-Name
	 *
	 * @since {WP_VERSION}
	 *
	 * @param int $at Byte offset into input document to begin parsing name.
	 * @return int|null Byte length of parsed name, if found, otherwise `null`.
	 */
	public static function skip_name( string $xml, int $at ): int {
		$starting_chars = self::skip_name_start_chars( $xml, $at );
		if ( 0 === $starting_chars ) {
			return 0;
		}

		return $starting_chars + self::skip_name_chars( $xml, $at + $starting_chars );
	}

	public static function skip_name_chars( string $xml, int $at ): int {
		$end    = strlen( $xml );
		$was_at = $at;

		while ( $at < $end ) {
			$at += strspn( $xml, ':ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz-0123456789.', $at, $end - $at );
			/*
			 * @todo Parse multi-byte UTF-8 characters with `utf8_parse_next()`.
			 *
			 * Example:
			 *
			 *     $code_point = utf_parse_next( $xml, $at + $stride, $consumed_bytes );
			 *     if ( null === $code_point ) {
			 *         throw new ValueError( 'Invalid UTF-8 cannot appear in an XML Name' );
			 *     }
			 *
			 *     if ( 0xB7 === $code_point || ( 0xC0 <= $code_point && $code_point <= D6 ) || ... ) {
			 *         $at += $consumed_bytes;
			 *         continue;
			 *     }
			 */

			break;
		}

		return $at - $was_at;
	}

	public static function skip_name_start_chars( string $xml, int $at ): int {
		$end    = strlen( $xml );
		$was_at = $at;

		while ( $at < $end ) {
			$at += strspn( $xml, ':ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz', $at, $end - $at );
			/*
			 * @todo Parse multi-byte UTF-8 characters with `utf8_parse_next()`.
			 *
			 * Example:
			 *
			 *     $code_point = utf_parse_next( $xml, $at + $stride, $consumed_bytes );
			 *     if ( null === $code_point ) {
			 *         throw new ValueError( 'Invalid UTF-8 cannot appear in an XML Name' );
			 *     }
			 *
			 *     if ( ( 0xC0 <= $code_point && $code_point <= D6 ) || ... ) {
			 *         $at += $consumed_bytes;
			 *         continue;
			 *     }
			 */

			break;
		}

		return $at - $was_at;
	}

	public static function skip_whitespace( string $xml, int $at, int $end = null ): int {
		return strspn( $xml, "\x20\x09\x0D\x0A", $at, $end );
	}

	/*
	 * Constants
	 */

	/*
	 * Parser states.
	 */

	const STATE_READY = 0;

	const STATE_TEXT_NODE = 1;

	const STATE_START_TAG = self::STATE_TEXT_NODE << 1;

	const STATE_END_TAG = self::STATE_START_TAG << 1;

	const STATE_EMPTY_TAG = self::STATE_END_TAG << 1;

	const STATE_COMMENT = self::STATE_EMPTY_TAG << 1;

	const STATE_CDATA_SECTION = self::STATE_COMMENT << 1;

	const STATE_DOCTYPE = self::STATE_CDATA_SECTION << 1;

	const STATE_PI_NODE = self::STATE_DOCTYPE << 1;

	const STATE_XML_DECLARATION = self::STATE_PI_NODE << 1;

	const STATE_COMPLETE = self::STATE_XML_DECLARATION << 1;

	const STATE_INCOMPLETE = self::STATE_COMPLETE << 1;

	const STATE_MALFORMED = self::STATE_INCOMPLETE << 1;

	/*
	 * Levels of concern.
	 */


	const CONCERNED_ABOUT_UNRESOLVABLES = 0;

	const CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS = self::CONCERNED_ABOUT_UNRESOLVABLES + 1;

	const CONCERNED_ABOUT_CONTENT_ERRORS = self::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS + 1;

	const CONCERNED_ABOUT_EVERYTHING = self::CONCERNED_ABOUT_CONTENT_ERRORS + 1;

}
