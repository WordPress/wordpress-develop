<?php
/**
 * CSS API: WP_CSS_Token_Serializer class
 *
 * @package WordPress
 * @subpackage CSS-API
 * @since 7.2.0
 */

/**
 * Writes tokens read by WP_CSS_Token_Processor as CSS text.
 *
 * This class is not a public API. Its methods are public so other core
 * classes can call them.
 *
 * The output parses to the same CSS as the tokens. Which characters are
 * escaped may change.
 *
 * @since 7.2.0
 * @access private
 *
 * @see WP_CSS_Token_Processor
 */
class WP_CSS_Token_Serializer {
	/**
	 * Token pairs that must be written with a comment between them.
	 *
	 * Keys are the first token and values list the second; a delim token is
	 * named by its value, every other token by its type.
	 *
	 * @see WP_CSS_Token_Serializer::serialize()
	 *
	 * @since 7.2.0
	 *
	 * @var array<string, string[]>
	 */
	private const MARKED_PAIRS = array(
		WP_CSS_Token_Processor::TOKEN_IDENT      => array(
			WP_CSS_Token_Processor::TOKEN_IDENT,
			WP_CSS_Token_Processor::TOKEN_FUNCTION,
			WP_CSS_Token_Processor::TOKEN_URL,
			WP_CSS_Token_Processor::TOKEN_BAD_URL,
			'-',
			WP_CSS_Token_Processor::TOKEN_NUMBER,
			WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
			WP_CSS_Token_Processor::TOKEN_DIMENSION,
			WP_CSS_Token_Processor::TOKEN_CDC,
			WP_CSS_Token_Processor::TOKEN_LEFT_PAREN,
		),
		WP_CSS_Token_Processor::TOKEN_AT_KEYWORD => self::IDENT_LIKE_FOLLOWERS,
		WP_CSS_Token_Processor::TOKEN_HASH       => self::IDENT_LIKE_FOLLOWERS,
		WP_CSS_Token_Processor::TOKEN_DIMENSION  => self::IDENT_LIKE_FOLLOWERS,
		'#'                                      => self::IDENT_LIKE_FOLLOWERS,
		'-'                                      => self::IDENT_LIKE_FOLLOWERS,
		WP_CSS_Token_Processor::TOKEN_NUMBER     => array(
			WP_CSS_Token_Processor::TOKEN_IDENT,
			WP_CSS_Token_Processor::TOKEN_FUNCTION,
			WP_CSS_Token_Processor::TOKEN_URL,
			WP_CSS_Token_Processor::TOKEN_BAD_URL,
			WP_CSS_Token_Processor::TOKEN_NUMBER,
			WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
			WP_CSS_Token_Processor::TOKEN_DIMENSION,
			WP_CSS_Token_Processor::TOKEN_CDC,
			'%',
		),
		'@'                                      => array(
			WP_CSS_Token_Processor::TOKEN_IDENT,
			WP_CSS_Token_Processor::TOKEN_FUNCTION,
			WP_CSS_Token_Processor::TOKEN_URL,
			WP_CSS_Token_Processor::TOKEN_BAD_URL,
			'-',
			WP_CSS_Token_Processor::TOKEN_CDC,
		),
		'.'                                      => self::NUMERIC_TOKENS,
		'+'                                      => self::NUMERIC_TOKENS,
		'/'                                      => array( '*' ),
	);

	/**
	 * Second tokens of the marked pairs shared by the at-keyword, hash,
	 * dimension, `#` and `-` rows.
	 *
	 * @since 7.2.0
	 *
	 * @var string[]
	 */
	private const IDENT_LIKE_FOLLOWERS = array(
		WP_CSS_Token_Processor::TOKEN_IDENT,
		WP_CSS_Token_Processor::TOKEN_FUNCTION,
		WP_CSS_Token_Processor::TOKEN_URL,
		WP_CSS_Token_Processor::TOKEN_BAD_URL,
		'-',
		WP_CSS_Token_Processor::TOKEN_NUMBER,
		WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
		WP_CSS_Token_Processor::TOKEN_DIMENSION,
		WP_CSS_Token_Processor::TOKEN_CDC,
	);

	/**
	 * Second tokens of the marked pairs in the `.` and `+` rows.
	 *
	 * @since 7.2.0
	 *
	 * @var string[]
	 */
	private const NUMERIC_TOKENS = array(
		WP_CSS_Token_Processor::TOKEN_NUMBER,
		WP_CSS_Token_Processor::TOKEN_PERCENTAGE,
		WP_CSS_Token_Processor::TOKEN_DIMENSION,
	);

	/**
	 * Text of the token types that have no value.
	 *
	 * @since 7.2.0
	 *
	 * @var array<string, string>
	 */
	private const FIXED_TEXT = array(
		WP_CSS_Token_Processor::TOKEN_COLON         => ':',
		WP_CSS_Token_Processor::TOKEN_SEMICOLON     => ';',
		WP_CSS_Token_Processor::TOKEN_COMMA         => ',',
		WP_CSS_Token_Processor::TOKEN_LEFT_PAREN    => '(',
		WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN   => ')',
		WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET  => '[',
		WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET => ']',
		WP_CSS_Token_Processor::TOKEN_LEFT_BRACE    => '{',
		WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE   => '}',
		WP_CSS_Token_Processor::TOKEN_CDO           => '<!--',
		WP_CSS_Token_Processor::TOKEN_CDC           => '-->',
		WP_CSS_Token_Processor::TOKEN_BAD_STRING    => "\"\n",
		WP_CSS_Token_Processor::TOKEN_BAD_URL       => 'url(()',
	);

	/**
	 * Closing token type of each token type that opens a block.
	 *
	 * @since 7.2.0
	 *
	 * @var array<string, string>
	 */
	private const BLOCK_CLOSERS = array(
		WP_CSS_Token_Processor::TOKEN_FUNCTION     => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
		WP_CSS_Token_Processor::TOKEN_LEFT_PAREN   => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
		WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET,
		WP_CSS_Token_Processor::TOKEN_LEFT_BRACE   => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
	);

	/**
	 * Serializes tokens as CSS text that parses to the same CSS.
	 *
	 * This follows CSS Syntax Level 3 §9 Serialization:
	 *
	 * > The only requirement for serialization is that it must "round-trip" with parsing,
	 * > that is, parsing the stylesheet must produce the same data structures as
	 * > parsing, serializing, and parsing again,
	 * > except for consecutive <whitespace-token>s,
	 * > which may be collapsed into a single token.
	 *
	 * Comment tokens are dropped. Each run of whitespace, including a run
	 * broken by comments, is written as one space; a run is never removed.
	 *
	 * Tokens are written from their decoded values. Identifiers, function
	 * names, at-keywords, hash names, dimension units, strings and url values
	 * are escaped again, so `\66 oo` becomes `foo` and `"a&b"` becomes `"a\26 b"`.
	 *
	 * > A <delim-token> containing U+005C REVERSE SOLIDUS (\)
	 * > must be serialized as U+005C REVERSE SOLIDUS
	 * > followed by a newline.
	 * > (The tokenizer only ever emits such a token followed by a <whitespace-token>
	 * > that starts with a newline.)
	 *
	 * > A <hash-token> with the "unrestricted" type flag may not need
	 * > as much escaping as the same token with the "id" type flag.
	 *
	 * A hash with the "id" flag is escaped as an identifier, so `#\31 a` stays
	 * `#\31 a`. A hash with the "unrestricted" flag may start with a digit or
	 * `-`, so `#1a` stays `#1a`.
	 *
	 * > The unit of a <dimension-token> may need escaping
	 * > to disambiguate with scientific notation.
	 *
	 * A unit that starts with `e`, an optional `-`, and a digit has the `e`
	 * escaped, so the unit `e3` is written `1\65 3`, not `1e3`.
	 *
	 * > For any consecutive pair of tokens,
	 * > if the first token shows up in the row headings of the following table,
	 * > and the second token shows up in the column headings,
	 * > and there’s a ✗ in the cell denoted by the intersection of the chosen row and column,
	 * > the pair of tokens must be serialized with a comment between them.
	 * >
	 * > Single characters in the row and column headings represent a <delim-token> with that value,
	 * > except for "(",
	 * > which represents a (-token.
	 *
	 *     ┌────────────┬───────┬──────────┬─────┬─────────┬───┬────────┬────────────┬───────────┬─────┬───┬───┬───┐
	 *     │            │ ident │ function │ url │ bad url │ - │ number │ percentage │ dimension │ CDC │ ( │ * │ % │
	 *     ├────────────┼───────┼──────────┼─────┼─────────┼───┼────────┼────────────┼───────────┼─────┼───┼───┼───┤
	 *     │ ident      │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │   ✗    │     ✗      │     ✗     │  ✗  │ ✗ │   │   │
	 *     │ at-keyword │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │   ✗    │     ✗      │     ✗     │  ✗  │   │   │   │
	 *     │ hash       │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │   ✗    │     ✗      │     ✗     │  ✗  │   │   │   │
	 *     │ dimension  │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │   ✗    │     ✗      │     ✗     │  ✗  │   │   │   │
	 *     │ #          │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │   ✗    │     ✗      │     ✗     │  ✗  │   │   │   │
	 *     │ -          │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │   ✗    │     ✗      │     ✗     │  ✗  │   │   │   │
	 *     │ number     │   ✗   │    ✗     │  ✗  │    ✗    │   │   ✗    │     ✗      │     ✗     │  ✗  │   │   │ ✗ │
	 *     │ @          │   ✗   │    ✗     │  ✗  │    ✗    │ ✗ │        │            │           │  ✗  │   │   │   │
	 *     │ .          │       │          │     │         │   │   ✗    │     ✗      │     ✗     │     │   │   │   │
	 *     │ +          │       │          │     │         │   │   ✗    │     ✗      │     ✗     │     │   │   │   │
	 *     │ /          │       │          │     │         │   │        │            │           │     │   │ ✗ │   │
	 *     └────────────┴───────┴──────────┴─────┴─────────┴───┴────────┴────────────┴───────────┴─────┴───┴───┴───┘
	 *
	 * Comments are not preserved, so an empty CSS comment is written between
	 * the tokens of a marked pair when no whitespace is between them.
	 *
	 * The table compares token types in pairs, so it misses two sequences that
	 * the tokenizer reads as a CDO or CDC token: `<`, `!` and a token that
	 * starts with `--`, which spell `<!--`, and an ident `--` followed by `>`,
	 * which spell `-->`. An empty CSS comment is written before the last token
	 * of each.
	 *
	 * Bad-string and bad-url tokens have no value. A bad string is written as
	 * a quote followed by a newline, where the tokenizer ends one; a bad url is
	 * written as `url(()`. Both re-tokenize as the same bad token. If a later
	 * step replaces the newline after a bad string with a space, the quote
	 * starts a string that includes the text after it.
	 *
	 * Strings and urls cut off by the end of the input are written closed.
	 * Blocks still open at the end are closed in reverse order, so `a(b[c` is
	 * written `a(b[c])`.
	 *
	 * Example:
	 *
	 *     $processor = WP_CSS_Token_Processor::create( 'url( a&b ) calc(1px  +  2px' );
	 *     $tokens    = array();
	 *     while ( $processor->next_token() ) {
	 *         $tokens[] = $processor->get_token();
	 *     }
	 *     WP_CSS_Token_Serializer::serialize( $tokens ); // 'url(a\26 b) calc(1px + 2px)'
	 *
	 * @link https://www.w3.org/TR/css-syntax-3/#serialization
	 *
	 * @since 7.2.0
	 *
	 * @param iterable<WP_CSS_Token> $tokens Tokens read by WP_CSS_Token_Processor::get_token().
	 * @return string CSS text.
	 */
	public static function serialize( iterable $tokens ): string {
		$output  = '';
		$closers = array();

		// Pair key of the last token written, or the whitespace type after a space.
		$last = null;

		// Text of the last two tokens written, or a space after whitespace.
		$last_text        = '';
		$before_last_text = '';

		foreach ( $tokens as $token ) {
			$type = $token->get_type();

			if ( WP_CSS_Token_Processor::TOKEN_COMMENT === $type ) {
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_WHITESPACE === $type ) {
				if ( WP_CSS_Token_Processor::TOKEN_WHITESPACE !== $last ) {
					$output          .= ' ';
					$last             = WP_CSS_Token_Processor::TOKEN_WHITESPACE;
					$before_last_text = $last_text;
					$last_text        = ' ';
				}
				continue;
			}

			$key  = WP_CSS_Token_Processor::TOKEN_DELIM === $type ? $token->get_value() : $type;
			$text = self::serialize_token( $token );

			if (
				( null !== $last && isset( self::MARKED_PAIRS[ $last ] ) && in_array( $key, self::MARKED_PAIRS[ $last ], true ) ) ||
				// The table misses `<`, `!`, `--x`, which reads as `<!--`, and `--`, `>`, which reads as `-->`.
				( '<' === $before_last_text && '!' === $last_text && 0 === strncmp( $text, '--', 2 ) ) ||
				( '--' === $last_text && '>' === $text )
			) {
				$output .= '/**/';
			}

			$output          .= $text;
			$last             = $key;
			$before_last_text = $last_text;
			$last_text        = $text;

			if ( isset( self::BLOCK_CLOSERS[ $type ] ) ) {
				$closers[] = self::BLOCK_CLOSERS[ $type ];
			} elseif ( end( $closers ) === $type ) {
				array_pop( $closers );
			}
		}

		while ( array() !== $closers ) {
			$output .= self::FIXED_TEXT[ array_pop( $closers ) ];
		}

		return $output;
	}

	/**
	 * Serializes one token other than whitespace or a comment.
	 *
	 * @since 7.2.0
	 *
	 * @param WP_CSS_Token $token Token.
	 * @return string CSS text.
	 */
	private static function serialize_token( WP_CSS_Token $token ): string {
		$value = $token->get_value();

		switch ( $token->get_type() ) {
			case WP_CSS_Token_Processor::TOKEN_IDENT:
				return self::escape_ident( $value );

			case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				return self::escape_ident( $value ) . '(';

			case WP_CSS_Token_Processor::TOKEN_AT_KEYWORD:
				return '@' . self::escape_ident( $value );

			case WP_CSS_Token_Processor::TOKEN_HASH:
				return WP_CSS_Token_Processor::HASH_TOKEN_ID === $token->get_flag()
					? '#' . self::escape_ident( $value )
					: '#' . self::escape_name( $value );

			case WP_CSS_Token_Processor::TOKEN_STRING:
				return self::escape_string( $value );

			case WP_CSS_Token_Processor::TOKEN_URL:
				return self::escape_url( $value );

			case WP_CSS_Token_Processor::TOKEN_NUMBER:
				return $value;

			case WP_CSS_Token_Processor::TOKEN_PERCENTAGE:
				return $value . '%';

			case WP_CSS_Token_Processor::TOKEN_DIMENSION:
				$unit = self::escape_ident( $token->get_unit() );
				// A unit that starts with `e`, an optional `-`, and a digit would read as an exponent.
				if ( 1 === preg_match( '/^[eE]-?[0-9]/', $unit ) ) {
					$unit = sprintf( '\\%X ', ord( $unit[0] ) ) . substr( $unit, 1 );
				}
				return $value . $unit;

			case WP_CSS_Token_Processor::TOKEN_DELIM:
				return '\\' === $value ? "\\\n" : $value;

			default:
				return self::FIXED_TEXT[ $token->get_type() ];
		}
	}

	/**
	 * Escapes a decoded value as identifier text.
	 *
	 * Characters not valid in CSS identifiers are hex-escaped, as are the
	 * characters escape_string() escapes for HTML and CSS.
	 *
	 * An empty value returns an empty string. Callers that require an ident
	 * token must reject an empty value.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#escaping
	 * @see https://www.w3.org/TR/css-syntax-3/#would-start-an-identifier
	 *
	 * @since 7.2.0
	 * @access private
	 *
	 * @param string $value Decoded value.
	 * @return string Identifier text.
	 */
	public static function escape_ident( string $value ): string {
		// A lone `-` cannot start an identifier.
		if ( '-' === $value ) {
			return '\\2D ';
		}

		// Neither can a digit or a `-` followed by a digit, so the digit is escaped.
		$digit_at = ( isset( $value[0] ) && '-' === $value[0] ) ? 1 : 0;
		if ( isset( $value[ $digit_at ] ) && $value[ $digit_at ] >= '0' && $value[ $digit_at ] <= '9' ) {
			return substr( $value, 0, $digit_at ) . sprintf( '\\%X ', ord( $value[ $digit_at ] ) ) . self::escape_name( substr( $value, $digit_at + 1 ) );
		}

		return self::escape_name( $value );
	}

	/**
	 * Escapes a decoded value as a quoted string.
	 *
	 * Many characters that are problematic in HTML or may be complicated for
	 * rudimentary CSS or HTML processors to handle are encoded using Unicode
	 * escape sequences.
	 *
	 * The string reads back as the same value, except that NULL becomes U+FFFD.
	 * Line endings are not normalized: CR and FF are escaped as `\D ` and `\C `.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#escaping
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded value.
	 * @return string Quoted string text.
	 */
	private static function escape_string( string $value ): string {
		$value   = wp_scrub_utf8( $value );
		$escaped = strtr(
			$value,
			array(
				// Escape existing backslashes to prevent unintentional escapes in result.
				'\\' => '\\5C ',

				// CSS cannot represent NULL; preprocessing replaces it with U+FFFD.
				"\0" => "\u{FFFD}",

				// Newlines must be escaped in CSS strings.
				"\n" => '\\A ',
				"\r" => '\\D ',
				"\f" => '\\C ',

				// Arbitrary characters for Unicode escaping:

				// HTML syntax may be problematic.
				'<'  => '\\3C ',
				'>'  => '\\3E ',
				'&'  => '\\26 ',

				// CSS syntax may be problematic.
				','  => '\\2C ',
				';'  => '\\3B ',
				'{'  => '\\7B ',
				'}'  => '\\7D ',
				'"'  => '\\22 ',
				"'"  => '\\27 ',
			)
		);
		return "\"{$escaped}\"";
	}

	/**
	 * Escapes every code point that cannot appear in an identifier.
	 *
	 * Letters, digits, `-`, `_` and code points U+0080 and above are written
	 * as they are; every other code point is hex-escaped. Unlike
	 * escape_ident(), a leading digit or `-` is not escaped, as the name of
	 * a hash token with the "unrestricted" flag requires.
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded name.
	 * @return string Escaped name.
	 */
	private static function escape_name( string $value ): string {
		$value  = wp_scrub_utf8( $value );
		$result = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $value[ $i ] );

			if ( 0x00 === $byte ) {
				$result .= "\u{FFFD}";
				continue;
			}

			if (
				$byte >= 0x80 ||
				( $byte >= 0x30 && $byte <= 0x39 ) || // 0-9
				( $byte >= 0x41 && $byte <= 0x5A ) || // A-Z
				( $byte >= 0x61 && $byte <= 0x7A ) || // a-z
				0x5F === $byte || // _
				0x2D === $byte // -
			) {
				$result .= $value[ $i ];
				continue;
			}

			$result .= sprintf( '\\%X ', $byte );
		}

		return $result;
	}

	/**
	 * Escapes a decoded value as an unquoted url.
	 *
	 * The code points an unquoted url cannot contain are hex-escaped: quotes,
	 * parentheses, `\`, whitespace and non-printable code points. So are the
	 * code points escape_string() escapes for HTML and CSS: `< > & , ; { }`.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-url-token
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded value.
	 * @return string Url text, including `url(` and `)`.
	 */
	private static function escape_url( string $value ): string {
		$value  = wp_scrub_utf8( $value );
		$result = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $value[ $i ] );

			if ( 0x00 === $byte ) {
				$result .= "\u{FFFD}";
				continue;
			}

			if (
				$byte <= 0x20 ||
				0x7F === $byte ||
				false !== strpos( '"\'()\\<>&,;{}', $value[ $i ] )
			) {
				$result .= sprintf( '\\%X ', $byte );
				continue;
			}

			$result .= $value[ $i ];
		}

		return "url({$result})";
	}
}
