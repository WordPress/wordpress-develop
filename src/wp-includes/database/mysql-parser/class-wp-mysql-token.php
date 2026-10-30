<?php

/**
 * MySQL token.
 *
 * This class represents a MySQL SQL token that is produced by WP_MySQL_Lexer,
 * and consumed by WP_MySQL_Parser during the parsing process.
 *
 * @access private
 */
class WP_MySQL_Token extends WP_Parser_Token {
	/**
	 * Whether the NO_BACKSLASH_ESCAPES SQL mode is enabled.
	 *
	 * @var bool
	 */
	private $sql_mode_no_backslash_escapes_enabled;

	/**
	 * Constructor.
	 *
	 * @param int    $id                                    Token type.
	 * @param int    $start                                 Byte offset in the input where the token begins.
	 * @param int    $length                                Byte length of the token in the input.
	 * @param string $input                                 Input bytes from which the token was parsed.
	 * @param bool   $sql_mode_no_backslash_escapes_enabled Whether the NO_BACKSLASH_ESCAPES SQL mode is enabled.
	 */
	public function __construct(
		int $id,
		int $start,
		int $length,
		string $input,
		bool $sql_mode_no_backslash_escapes_enabled
	) {
		$this->id     = $id;
		$this->start  = $start;
		$this->length = $length;
		$this->input  = $input;

		$this->sql_mode_no_backslash_escapes_enabled = $sql_mode_no_backslash_escapes_enabled;
	}

	/**
	 * Get the name of the token.
	 *
	 * This method is intended to be used only for testing and debugging purposes,
	 * when tokens need to be presented by their names in a human-readable form.
	 * It should not be used in production code, as it's not performance-optimized.
	 *
	 * @return string The token name.
	 */
	public function get_name(): string {
		$name = WP_MySQL_Lexer::get_token_name( $this->id );
		if ( null === $name ) {
			$name = 'UNKNOWN';
		}
		return $name;
	}

	/**
	 * Get the real unquoted value of the token.
	 *
	 * @return string The token value.
	 */
	public function get_value(): string {
		$value = $this->get_bytes();
		if (
			WP_MySQL_Lexer::SINGLE_QUOTED_TEXT === $this->id
			|| WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT === $this->id
			|| WP_MySQL_Lexer::BACK_TICK_QUOTED_ID === $this->id
		) {
			// Remove bounding quotes.
			$quote = $value[0];
			$value = substr( $value, 1, -1 );

			/*
			 * Quoted identifiers escape their bounding quote by doubling it, while
			 * backslashes remain literal. Strings in NO_BACKSLASH_ESCAPES mode use
			 * the same unquoting rule.
			 *
			 * See: https://dev.mysql.com/doc/refman/8.4/en/identifiers.html
			 */
			if (
				WP_MySQL_Lexer::BACK_TICK_QUOTED_ID === $this->id
				|| $this->sql_mode_no_backslash_escapes_enabled
			) {
				return str_replace( $quote . $quote, $quote, $value );
			}

			/**
			 * Unescape MySQL escape sequences.
			 *
			 * MySQL string literals use backslash as an escape character, and
			 * the string bounding quotes can also be escaped by being doubled.
			 *
			 * The escaping is done according to the following rules:
			 *
			 *   1. Some special character escape sequences are recognized.
			 *      For example, "\n" is a newline character, "\0" is ASCII NULL.
			 *   2. A specific treatment is applied to "\%" and "\_" sequences.
			 *      This is due to their special meaning for pattern matching.
			 *   3. Other backslash-prefixed characters resolve to their literal
			 *      values. For example, "\x" represents "x", "\\" represents "\".
			 *
			 * Despite looking similar, these rules are different from the C-style
			 * string escaping, so we cannot use "strip(c)slashes()" in this case.
			 *
			 * See: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html
			 */
			$backslash    = chr( 92 );
			$replacements = array(
				/*
				 * MySQL special character escape sequences.
				 */
				( $backslash . '0' ) => chr( 0 ),  // An ASCII NULL character (\0).
				( $backslash . "'" ) => chr( 39 ), // A single quote character (').
				( $backslash . '"' ) => chr( 34 ), // A double quote character (").
				( $backslash . 'b' ) => chr( 8 ),  // A backspace character.
				( $backslash . 'n' ) => chr( 10 ), // A newline (linefeed) character (\n).
				( $backslash . 'r' ) => chr( 13 ), // A carriage return character (\r).
				( $backslash . 't' ) => chr( 9 ),  // A tab character (\t).
				( $backslash . 'Z' ) => chr( 26 ), // An ASCII 26 (Control+Z) character.

				/*
				 * Normalize escaping of "%" and "_" characters.
				 *
				 * MySQL has unusual handling for "\%" and "\_" in all string literals.
				 * While other sequences follow the C-style escaping ("\?" is "?", etc.),
				 * "\%" resolves to "\%" and "\_" resolves to "\_" (unlike in C strings).
				 *
				 * This means that "\%" behaves like "\\%", and "\_" behaves like "\\_".
				 * To preserve this behavior, we need to add a second backslash here.
				 *
				 * From https://dev.mysql.com/doc/refman/8.4/en/string-literals.html:
				 *   > The \% and \_ sequences are used to search for literal instances
				 *   > of % and _ in pattern-matching contexts where they would otherwise
				 *   > be interpreted as wildcard characters. If you use \% or \_ outside
				 *   > of pattern-matching contexts, they evaluate to the strings \% and
				 *   > \_, not to % and _.
				 */
				( $backslash . '%' ) => $backslash . $backslash . '%',
				( $backslash . '_' ) => $backslash . $backslash . '_',

				/*
				 * Preserve a double backslash as-is, so that the trailing backslash
				 * is not consumed as the beginning of an escape sequence like "\n".
				 *
				 * Resolving "\\" to "\" will be handled in the next step, where all
				 * other backslash-prefixed characters resolve to their literal values.
				 */
				( $backslash . $backslash )
					=> $backslash . $backslash,

				/*
				 * The bounding quotes can also be escaped by being doubled.
				 */
				( $quote . $quote )  => $quote,
			);

			/*
			 * Apply the replacements.
			 *
			 * It is important to use "strtr()" and not "str_replace()", because
			 * "str_replace()" applies replacements one after another, modifying
			 * intermediate changes rather than just the original string:
			 *
			 *   - str_replace( [ 'a', 'b' ], [ 'b', 'c' ], 'ab' ); // 'cc' (bad)
			 *   - strtr( 'ab', [ 'a' => 'b', 'b' => 'c' ] );       // 'bc' (good)
			 */
			$value = strtr( $value, $replacements );

			/*
			 * A backslash with any other character represents the character itself.
			 * That is, \x evaluates to x, \\ evaluates to \, and \🙂 evaluates to 🙂.
			 *
			 * Use "s" (DOTALL) to match all characters including newlines. Do not use
			 * "u", so non-UTF-8 bytes are preserved (binary or single-byte charsets).
			 * Legacy multi-byte charsets like big5 or gbk, where a trail byte can be
			 * a backslash, are not supported.
			 */
			$preg_quoted_backslash = preg_quote( $backslash );
			$value                 = preg_replace( "/$preg_quoted_backslash(.)/s", '$1', $value );
		}
		return $value;
	}

	/**
	 * Get the token representation as a string.
	 *
	 * This method is intended to be used only for testing and debugging purposes,
	 * when tokens need to be presented in a human-readable form. It should not
	 * be used in production code, as it's not performance-optimized.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->get_value() . '<' . $this->id . ',' . $this->get_name() . '>';
	}
}
