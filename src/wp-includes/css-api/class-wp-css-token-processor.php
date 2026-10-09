<?php
/**
 * CSS API: WP_CSS_Token_Processor class
 *
 * @package WordPress
 * @subpackage CSS-API
 * @since 7.2.0
 */

/**
 * Tokenizes CSS according to the CSS Syntax Level 3 specification.
 *
 * This class follows the algorithm in https://www.w3.org/TR/css-syntax-3/ and
 * exposes a pull-based API so callers can stream over large stylesheets without
 * allocating every token up front. Each call to next_token() advances the cursor
 * and fills in metadata (type, value, raw slice, byte offsets) that you can read
 * through the getter methods.
 *
 * ## Design choices
 *
 * ### On-the-fly normalization
 *
 * The CSS Spec requires the following normalization step:
 *
 * > Replace any U+000D CARRIAGE RETURN (CR) code points, U+000C FORM FEED (FF)
 * > code points, or pairs of U+000D CARRIAGE RETURN (CR) followed by U+000A LINE
 * > FEED (LF) in input by a single U+000A LINE FEED (LF) code point.
 * > Replace any U+0000 NULL or surrogate code points in input with U+FFFD REPLACEMENT
 * > CHARACTER (�).
 *
 * This processor delays normalization as much as possible. That keeps the raw byte
 * positions intact for accurate rewrites while still letting consumers ask for a
 * normalized token when they need one.
 * For example, url(a<NUL>b) is a URL token whose value is a�b, where <NUL>
 * means one zero byte in the source. That byte stays in the raw token, so later
 * edits still use byte offsets from the original input.
 *
 * ### No EOF token
 *
 * The EOF token is a CSS parsing concept, not CSS tokenization concept. Therefore,
 * this processor does not produce it.
 *
 * ### UTF-8 handling
 *
 * Only UTF-8 strings are supported. Invalid sequences are replaced with U+FFFD (�)
 * using the maximal subpart approach described in
 * https://www.unicode.org/versions/Unicode9.0.0/ch03.pdf, section 3.9 Best Practices
 * for Using U+FFFD.
 *
 * ## Usage
 *
 * Basic iteration:
 *
 *     $css = 'width: 10px;';
 *     $processor = WP_CSS_Token_Processor::create( $css );
 *     while ( $processor->next_token() ) {
 *         echo $processor->get_normalized_token();
 *     }
 *     // Outputs:
 *     // width: 10px;
 *
 * Gathering diagnostics with byte offsets:
 *
 *     $css = "color: red;\ncolor: re\nd;";
 *     $processor = WP_CSS_Token_Processor::create( $css );
 *     $bad_strings = array();
 *     while ( $processor->next_token() ) {
 *         if ( WP_CSS_Token_Processor::TOKEN_BAD_STRING === $processor->get_token_type() ) {
 *             $bad_strings[] = array(
 *                 'start'  => $processor->get_token_start(),
 *                 'length' => $processor->get_token_length(),
 *                 'value'  => $processor->get_unnormalized_token(),
 *             );
 *         }
 *     }
 *
 * @since 7.2.0
 *
 * @see https://www.w3.org/TR/css-syntax-3/#tokenization
 */
class WP_CSS_Token_Processor {
	/**
	 * Token type constants matching the CSS Syntax Level 3 specification.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#tokenization
	 */
	public const TOKEN_WHITESPACE = 'whitespace-token';
	public const TOKEN_COMMENT    = 'comment';
	public const TOKEN_STRING     = 'string-token';

	/**
	 * BAD-STRING tokens occur when a string contains an unescaped newline.
	 *
	 * Valid strings: "hello", 'world', "line1\Aline2" (escaped newline)
	 * Invalid (produces bad-string): "hello
	 *                                 world"  (literal newline breaks the string)
	 *
	 * The processor stops at the newline and produces a bad-string token for error recovery.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#typedef-bad-string-token
	 */
	public const TOKEN_BAD_STRING        = 'bad-string-token';
	public const TOKEN_HASH              = 'hash-token';
	public const HASH_TOKEN_ID           = 'id';
	public const HASH_TOKEN_UNRESTRICTED = 'unrestricted';
	public const TOKEN_DELIM             = 'delim-token';
	public const TOKEN_NUMBER            = 'number-token';
	public const TOKEN_PERCENTAGE        = 'percentage-token';
	public const TOKEN_DIMENSION         = 'dimension-token';
	public const TOKEN_AT_KEYWORD        = 'at-keyword-token';
	public const TOKEN_COLON             = 'colon-token';
	public const TOKEN_SEMICOLON         = 'semicolon-token';
	public const TOKEN_COMMA             = 'comma-token';
	public const TOKEN_LEFT_PAREN        = '(-token';
	public const TOKEN_RIGHT_PAREN       = ')-token';
	public const TOKEN_LEFT_BRACKET      = '[-token';
	public const TOKEN_RIGHT_BRACKET     = ']-token';
	public const TOKEN_LEFT_BRACE        = '{-token';
	public const TOKEN_RIGHT_BRACE       = '}-token';
	public const TOKEN_FUNCTION          = 'function-token';

	/**
	 * URL tokens represent unquoted URLs in url() notation.
	 *
	 * For example, `url(image.jpg)` is a URL token.
	 *
	 * Quoted URLs like `url( "https://example.com" )` are handled as a function
	 * token, _not_ a URL token.
	 *
	 * Bad URL tokens are created when invalid characters are encountered in
	 * a URL token.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#typedef-url-token
	 */
	public const TOKEN_URL = 'url-token';

	/**
	 * BAD-URL tokens occur when a URL contains invalid characters.
	 *
	 * Invalid characters: quotes ("), apostrophes ('), parentheses (()
	 * Example invalid: url(image(.jpg) or url(image".jpg)
	 *
	 * When detected, the processor consumes everything up to ) or EOF.
	 * This prevents the bad URL from breaking subsequent tokens.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#typedef-bad-url-token
	 */
	public const TOKEN_BAD_URL = 'bad-url-token';

	/**
	 * Identifier tokens, such as `color`, `margin-top`, `red`,
	 * `inherit`, `--my-var`, `\x-escaped`, `über` (Unicode), etc.
	 *
	 * There are restrictions on the codepoints that start or are contained in
	 * an identifier, and identifiers may contain escape sequences.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#typedef-ident-token
	 */
	public const TOKEN_IDENT = 'ident-token';

	/**
	 * CDC (Comment Delimiter Close) token: -->
	 *
	 * Legacy token from when CSS was embedded in HTML <style> tags
	 * and needed to be hidden from old browsers using HTML comments:
	 *
	 *   <style>
	 *   <!--
	 *   body { color: red; }
	 *   -->
	 *   </style>
	 *
	 * Modern CSS no longer needs these, but they're preserved for compatibility.
	 * In stylesheets, they're typically treated like whitespace.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#typedef-CDC-token
	 */
	public const TOKEN_CDC = 'CDC-token';

	/**
	 * CDO (Comment Delimiter Open) token: <!--
	 *
	 * Legacy token from when CSS was embedded in HTML <style> tags.
	 * See TOKEN_CDC for full explanation of HTML comment compatibility.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#typedef-CDO-token
	 */
	public const TOKEN_CDO = 'CDO-token';

	/**
	 * @var string
	 */
	private $css;

	/**
	 * @var int
	 */
	private $length = 0;

	/**
	 * @var int
	 */
	private $at = 0;

	/**
	 * The type of the current token. One of the self::TOKEN_* constants.
	 *
	 * @var string|null
	 * @phpstan-var self::TOKEN_*|null
	 */
	private $token_type = null;

	/**
	 * The type flag for the current token, if any.
	 *
	 * Hash tokens carry an "id" or "unrestricted" flag. Per CSS Syntax Level 3,
	 * <number-token> and <dimension-token> have a type flag indicating whether
	 * the number was written as an integer or a number (with decimal point or
	 * exponent). <percentage-token> does not have a type flag.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-number
	 *
	 * @var string|null
	 * @phpstan-var 'id'|'unrestricted'|'integer'|'number'|null
	 */
	private $token_type_flag = null;

	/**
	 * The byte offset at which the current token starts.
	 *
	 * Example:
	 *
	 * background-image: url(https://example.com/image.jpg);
	 *                   ^ token_starts_at
	 *
	 * @var int|null
	 */
	private $token_starts_at = null;

	/**
	 * The byte length of the current token.
	 *
	 * Example:
	 *
	 * background-image: url(https://example.com/image.jpg);
	 *                   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
	 *                             token_length
	 *
	 * @var int|null
	 */
	private $token_length = null;

	/**
	 * The byte offset at which the value of the current token starts.
	 *
	 * It is used for STRING and URL tokens. For example:
	 *
	 * background-image: url(https://example.com/image.jpg);
	 *                       ^ token_value_starts_at
	 *
	 * @var int|null
	 */
	private $token_value_starts_at = null;

	/**
	 * The byte offset at which the value of the current token starts.
	 *
	 * It is relevant for STRING and URL tokens. For example:
	 *
	 * background-image: url(https://example.com/image.jpg);
	 *                       ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
	 *                             token_value_length
	 *
	 * @var int|null
	 */
	private $token_value_length = null;

	/**
	 * A cache for the decoded and normalized token value.
	 *
	 * - `false` indicates that the value has not been computed.
	 * - `null` is used for token types without an associated value: whitespace,
	 *   bad-url, comment, punctuation, etc.
	 * - `string` is used for token types with an associated value: ident, string,
	 *   function, url, etc.
	 *
	 * @var string|null|false
	 */
	private $token_value = false;

	/**
	 * The unit of the current token, e.g. "px", "em", "deg", etc.
	 *
	 * @var string|null
	 */
	private $token_unit = null;

	/**
	 * Whether the current token ended at its closing delimiter.
	 *
	 * Comment, string, url and bad-url tokens end at `*\/`, the matching quote
	 * or `)`. When the input ends first, the token is complete but this flag
	 * is false. Every other token type is always terminated.
	 *
	 * @var bool
	 */
	private $token_is_terminated = true;

	/**
	 * Constructor for the CSS processor.
	 *
	 * Do not instantiate directly. Use WP_CSS_Token_Processor::create() instead.
	 *
	 * @since 7.2.0
	 *
	 * @param string $css         CSS source to tokenize.
	 */
	private function __construct( string $css ) {
		$this->css    = $css;
		$this->length = strlen( $css );
	}

	/**
	 * Creates a CSS processor for the given CSS string.
	 *
	 * Use this method to create a CSS processor instance.
	 *
	 * ## Current Support
	 *
	 * - The only supported document encoding is `UTF-8`, which is the default value.
	 *
	 * @since 7.2.0
	 *
	 * @param string $css      CSS source to tokenize.
	 * @param string $encoding Text encoding of the document; must be default of 'UTF-8'.
	 * @return static|null The created processor if successful, otherwise null.
	 */
	public static function create( string $css, string $encoding = 'UTF-8' ) {
		if ( 'UTF-8' !== $encoding ) {
			return null;
		}

		return new static( $css );
	}

	/**
	 * Moves to the next token in the CSS stream.
	 *
	 * Implements the main tokenization loop, consuming the next token from the input stream.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-token
	 *
	 * @since 7.2.0
	 *
	 * @return bool Whether a token was found.
	 */
	public function next_token(): bool {
		$this->after_token();

		// Bale out once we reach the end.
		if ( $this->at >= $this->length ) {
			return false;
		}

		/*
		 * CSS comments. They are not preserved as tokens in the specification, but we
		 * still track them.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#consume-comment
		 */
		if (
			$this->at + 1 < $this->length &&
			'/' === $this->css[ $this->at ] &&
			'*' === $this->css[ $this->at + 1 ]
		) {
			$this->token_type            = self::TOKEN_COMMENT;
			$this->token_starts_at       = $this->at;
			$this->token_value_starts_at = $this->at;

			$end = strpos( $this->css, '*/', $this->at + 2 );
			if ( false === $end ) {
				// The comment runs to the end of the input.
				$this->token_is_terminated = false;
				$this->at                  = $this->length;
			} else {
				$this->at = $end + 2;
			}
			$this->token_length       = $this->at - $this->token_starts_at;
			$this->token_value_length = $this->token_length - 4;
			return true;
		}

		/*
		 * Whitespace tokens.
		 *
		 * We consider U+000A LINE FEED, U+0009 CHARACTER TABULATION, and U+0020 SPACE bytes covered by the spec.
		 * In addition, we also capture U+000D CARRIAGE RETURN and U+000C FORM FEED that are normally converted to
		 * U+000A LINE FEED during the preprocessing phase.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#newline
		 * @see https://www.w3.org/TR/css-syntax-3/#whitespace
		 */
		$whitespace_length = strspn( $this->css, "\t\n\f\r ", $this->at );
		if ( $whitespace_length > 0 ) {
			$this->token_type      = self::TOKEN_WHITESPACE;
			$this->token_length    = $whitespace_length;
			$this->token_starts_at = $this->at;
			$this->at             += $whitespace_length;
			return true;
		}

		/*
		 * String tokens with either " or ' as delimiters.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#consume-string-token
		 */
		if ( '"' === $this->css[ $this->at ] || "'" === $this->css[ $this->at ] ) {
			return $this->consume_string();
		}

		$char                  = $this->css[ $this->at ];
		$this->token_starts_at = $this->at;

		/*
		 * U+0023 NUMBER SIGN (#)
		 *
		 * A hash token is created when # is followed by an ident code point or valid escape.
		 * This is commonly used for hex colors (#fff) or ID selectors (#header).
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#consume-token
		 */
		if ( '#' === $char ) {
			if ( $this->at + 1 < $this->length ) {
				if (
					$this->consume_ident_codepoint( $this->at + 1 ) > 0 ||
					// The next two input code points are a valid escape.
					$this->is_valid_escape( $this->at + 1 )
				) {
					// Create a <hash-token>.
					++$this->at;

					// > If the next 3 input code points would start an ident sequence,
					// > set the <hash-token>'s type flag to "id".
					$this->token_type_flag = $this->check_if_3_code_points_start_an_ident_sequence( $this->at )
						? self::HASH_TOKEN_ID
						: self::HASH_TOKEN_UNRESTRICTED;

					// Consume an ident sequence, and set the <hash-token>'s value to the returned string.
					$this->consume_ident_sequence();
					$this->token_type   = self::TOKEN_HASH;
					$this->token_length = $this->at - $this->token_starts_at;
					return true;
				}
			}
			// Otherwise, return a <delim-token> with its value set to the current input code point.
			++$this->at;
			$this->token_type   = self::TOKEN_DELIM;
			$this->token_length = 1;
			return true;
		}

		/*
		 * Simple single-byte tokens
		 *
		 * These characters form their own tokens when encountered.
		 * Note: ( tokens here are not function tokens - those are handled
		 * in consume_ident_like() when ( follows an identifier.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#tokenization
		 */
		$simple = array(
			'(' => self::TOKEN_LEFT_PAREN,
			')' => self::TOKEN_RIGHT_PAREN,
			',' => self::TOKEN_COMMA,
			':' => self::TOKEN_COLON,
			';' => self::TOKEN_SEMICOLON,
			'[' => self::TOKEN_LEFT_BRACKET,
			']' => self::TOKEN_RIGHT_BRACKET,
			'{' => self::TOKEN_LEFT_BRACE,
			'}' => self::TOKEN_RIGHT_BRACE,
		);
		if ( isset( $simple[ $char ] ) ) {
			++$this->at;
			$this->token_type   = $simple[ $char ];
			$this->token_length = 1;
			return true;
		}

		/*
		 * U+0040 COMMERCIAL AT (@)
		 *
		 * An at-keyword is @ followed by an identifier, used for at-rules like
		 * @media, @import, @keyframes, etc.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#consume-token
		 */
		if ( '@' === $char ) {
			++$this->at;
			// If the next 3 input code points after the @ would start an ident sequence,
			// consume an ident sequence, create an <at-keyword-token> with its value set to the returned value,
			// and return it.
			if ( $this->check_if_3_code_points_start_an_ident_sequence( $this->at ) ) {
				$this->consume_ident_sequence();
				$this->token_type   = self::TOKEN_AT_KEYWORD;
				$this->token_length = $this->at - $this->token_starts_at;
				return true;
			} else {
				// Otherwise, return a <delim-token> with its value set to the current input code point.
				$this->token_type   = self::TOKEN_DELIM;
				$this->token_length = 1;
				return true;
			}
		}

		/*
		 * Numbers start with digits, the plus sign, minus sign, and decimal point.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#starts-with-a-number
		 */
		if ( $this->would_next_3_code_points_start_a_number() ) {
			return $this->consume_numeric();
		}

		/*
		 * U+002D HYPHEN-MINUS (-)
		 */
		if ( '-' === $char ) {
			// This case is covered above:
			// > If the input stream starts with a number.

			/*
			 * If followed by another hyphen and >, this is a CDC token (-->)
			 *
			 * Comment Delimiter Close - legacy HTML comment syntax in CSS.
			 *
			 * @see https://www.w3.org/TR/css-syntax-3/#CDC-token-diagram
			 */
			if (
				$this->at + 2 < $this->length &&
				'-' === $this->css[ $this->at + 1 ] &&
				'>' === $this->css[ $this->at + 2 ]
			) {
				// Consume them and return a <CDC-token>.
				$this->at          += 3;
				$this->token_type   = self::TOKEN_CDC;
				$this->token_length = 3;
				return true;
			}

			// Otherwise, if the input stream starts with an ident sequence,
			// reconsume the current input code point, consume an ident-like
			// token, and return it.
			if ( $this->check_if_3_code_points_start_an_ident_sequence( $this->at ) ) {
				return $this->consume_ident_like();
			}

			// Otherwise, return a <delim-token> with its value set to the current input code point.
			++$this->at;
			$this->token_type   = self::TOKEN_DELIM;
			$this->token_length = 1;
			return true;
		}

		/*
		 * U+003C LESS-THAN SIGN (<)
		 * If followed by !--, this is a CDO token (<!--)
		 *
		 * Comment Delimiter Open - legacy HTML comment syntax in CSS.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#CDO-token-diagram
		 */
		if ( '<' === $char && $this->at + 3 < $this->length &&
			'!' === $this->css[ $this->at + 1 ] &&
			'-' === $this->css[ $this->at + 2 ] &&
			'-' === $this->css[ $this->at + 3 ] ) {
			// Consume them and return a <CDO-token>.
			$this->at          += 4;
			$this->token_type   = self::TOKEN_CDO;
			$this->token_length = 4;
			return true;
		}

		/*
		 * Ident-start code point
		 *
		 * If the input stream starts with an ident sequence, reconsume the current
		 * input code point, consume an ident-like token, and return it.
		 *
		 * Could be an identifier, function, or url() token.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#consume-ident-like-token
		 */
		if ( $this->check_if_3_code_points_start_an_ident_sequence( $this->at ) ) {
			return $this->consume_ident_like();
		}

		/*
		 * Delim token (delimiter)
		 *
		 * Any code point that doesn't match above rules becomes a delim token.
		 * Handle multi-byte UTF-8 characters properly.
		 *
		 * @see https://www.w3.org/TR/css-syntax-3/#delim-token-diagram
		 */
		if ( ord( $char ) >= 0x80 ) {
			$new_at         = $this->at;
			$invalid_length = 0;
			if ( 1 !== _wp_scan_utf8( $this->css, $new_at, $invalid_length, null, 1 ) ) {
				/**
				 * Trouble ahead!
				 * Bytes at $at are not a valid UTF-8 sequence.
				 *
				 * We'll move forward by $invalid_length bytes and continue processing.
				 * Later on, during the string decoding, we'll replace the invalid bytes with U+FFFD
				 * via maximal subpart”replacement.
				 */
				$matched_bytes = $invalid_length;
			} else {
				$matched_bytes = $new_at - $this->at;
			}

			$this->at          += $matched_bytes;
			$this->token_type   = self::TOKEN_DELIM;
			$this->token_length = $matched_bytes;
			return true;
		}

		// Single ASCII delim.
		++$this->at;
		$this->token_type   = self::TOKEN_DELIM;
		$this->token_length = 1;
		return true;
	}

	/**
	 * Gets the current token type.
	 *
	 * @since 7.2.0
	 *
	 * @return string|null
	 * @phpstan-return self::TOKEN_*|null
	 */
	public function get_token_type(): ?string {
		return $this->token_type;
	}

	/**
	 * Gets the current token's type flag, if it has one.
	 *
	 * Some token types have an additional flag:
	 * - Hash tokens have an "id" or "unrestricted" flag.
	 * - Number and dimension tokens have an "integer" flag when the number was
	 *   written without a decimal point or exponent, and a "number" flag otherwise.
	 * - Percentage tokens do not have a type flag.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-number
	 *
	 * @since 7.2.0
	 *
	 * @return string|null
	 * @phpstan-return 'id'|'unrestricted'|'integer'|'number'|null
	 */
	public function get_token_type_flag(): ?string {
		return $this->token_type_flag;
	}

	/**
	 * Gets the normalized token text from the CSS source.
	 *
	 * Returns the token with CSS normalization and escape decoding applied:
	 * - CSS escapes decoded (e.g., \6c → l, \2f → /, \A → newline)
	 * - \r\n, \r, \f → \n
	 * - \x00 → U+FFFD (�)
	 *
	 * This is different from get_token_value() which returns the semantic value
	 * (e.g., for strings: content without quotes; for numbers: numeric value).
	 *
	 * @since 7.2.0
	 *
	 * @return string|null
	 */
	public function get_normalized_token(): ?string {
		if ( null === $this->token_starts_at || null === $this->token_length ) {
			return null;
		}

		return $this->decode_range(
			$this->token_starts_at,
			$this->token_length,
			self::TOKEN_STRING === $this->token_type
		);
	}

	/**
	 * Gets the raw, unnormalized token text from the CSS source.
	 *
	 * Returns the exact bytes from the source without any normalization.
	 * This preserves original line endings (\r\n, \r, \f) and null bytes.
	 *
	 * @since 7.2.0
	 *
	 * @return string|null
	 */
	public function get_unnormalized_token(): ?string {
		if ( null === $this->token_starts_at || null === $this->token_length ) {
			return null;
		}
		return substr( $this->css, $this->token_starts_at, $this->token_length );
	}

	/**
	 * Gets the current token value as a normalized and decoded string. This is
	 * a slight divergence from the CSS Syntax Level 3 spec, where all the numberic
	 * values are parsed as numbers. This processor is only concerned with their
	 * textual representation.
	 *
	 * Returns the semantic value of the token per CSS Syntax Level 3 spec:
	 *
	 * - For delimiters: the single code point
	 * - For numbers/percentages: the string representation of the number
	 * - For dimensions: the string representation of the number (use get_token_unit() for the unit)
	 * - For identifiers/functions/hash/at-keywords: the decoded identifier string
	 * - For strings/URLs: the decoded string value
	 * - For other tokens: null
	 *
	 * @since 7.2.0
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#token-value
	 * @return string|null
	 */
	public function get_token_value(): ?string {
		if ( false === $this->token_value ) {
			if ( null === $this->token_starts_at || null === $this->token_length ) {
				return null;
			}

			switch ( $this->token_type ) {
				case self::TOKEN_HASH:
					// Hash value starts after the # character.
					$this->token_value = $this->decode_range( $this->token_starts_at + 1, $this->token_length - 1 );
					break;

				case self::TOKEN_AT_KEYWORD:
					// At-keyword value starts after the @ character.
					$this->token_value = $this->decode_range( $this->token_starts_at + 1, $this->token_length - 1 );
					break;

				case self::TOKEN_FUNCTION:
					// Function name is everything except the final (.
					$this->token_value = $this->decode_range( $this->token_starts_at, $this->token_length - 1 );
					break;

				case self::TOKEN_IDENT:
					// Identifier is the entire token.
					$this->token_value = $this->decode_range( $this->token_starts_at, $this->token_length );
					break;

				case self::TOKEN_BAD_STRING:
					$this->token_value = null;
					break;

				case self::TOKEN_STRING:
					if ( null !== $this->token_value_starts_at && null !== $this->token_value_length ) {
						$this->token_value = $this->decode_range(
							$this->token_value_starts_at,
							$this->token_value_length,
							true
						);
					} else {
						$this->token_value = null;
					}
					break;

				case self::TOKEN_URL:
					// Decode and cache the URL value.
					if ( null !== $this->token_value_starts_at && null !== $this->token_value_length ) {
						$this->token_value = $this->decode_range(
							$this->token_value_starts_at,
							$this->token_value_length
						);
					} else {
						$this->token_value = null;
					}
					break;

				case self::TOKEN_DELIM:
					// Delim value is the single code point.
					$this->token_value = $this->decode_range( $this->token_starts_at, $this->token_length );
					break;

				case self::TOKEN_NUMBER:
					// Return the string representation of the number (not parsed to float).
					$this->token_value = substr( $this->css, $this->token_starts_at, $this->token_length );
					break;

				case self::TOKEN_PERCENTAGE:
					// Return the string representation of the number (without the %).
					$this->token_value = substr( $this->css, $this->token_starts_at, $this->token_length - 1 );
					break;

				case self::TOKEN_DIMENSION:
					// Return the string representation of the number (without the unit).
					$this->token_value = substr( $this->get_normalized_token(), 0, -strlen( $this->token_unit ) );
					break;

				default:
					$this->token_value = null;
					break;
			}
		}

		return $this->token_value;
	}

	/**
	 * Gets the token start at.
	 *
	 * @since 7.2.0
	 *
	 * @return int|null
	 */
	public function get_token_start(): ?int {
		return $this->token_starts_at;
	}

	/**
	 * Gets the token length.
	 *
	 * @since 7.2.0
	 *
	 * @return int|null
	 */
	public function get_token_length(): ?int {
		return $this->token_length;
	}

	/**
	 * Gets the unit for dimension tokens.
	 *
	 * @since 7.2.0
	 *
	 * @return string|null
	 */
	public function get_token_unit(): ?string {
		return $this->token_unit;
	}

	/**
	 * Reports whether the current token ended at its closing delimiter.
	 *
	 * Comment, string, url and bad-url tokens have a closing delimiter: `*\/`,
	 * the matching quote, or `)`. When the input ends before the delimiter,
	 * the tokenizer still returns the token, and this method returns false.
	 * It returns true for every other token type, which have no closing
	 * delimiter, and false before the first call to next_token().
	 *
	 * Example:
	 *
	 *     $processor = WP_CSS_Token_Processor::create( '"abc' );
	 *     $processor->next_token();
	 *     $processor->get_token_type();      // 'string-token'
	 *     $processor->is_token_terminated(); // false
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-comment
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-string-token
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-url-token
	 *
	 * @since 7.2.0
	 *
	 * @return bool Whether the current token ended at its closing delimiter.
	 */
	public function is_token_terminated(): bool {
		return null !== $this->token_type && $this->token_is_terminated;
	}

	/**
	 * Serializes a plain PHP string as CSS identifier text.
	 *
	 * Characters not valid in CSS identifiers are hex-escaped. This uses
	 * the same safety escaping as {@see WP_CSS_Token_Processor::serialize_string()} for HTML
	 * and CSS-sensitive characters, plus escaping of whitespace and other
	 * characters not permitted in idents.
	 *
	 * An empty string serializes to an empty string. Callers that require an
	 * ident token must reject an empty value.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#escaping
	 * @see https://www.w3.org/TR/css-syntax-3/#would-start-an-identifier
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded string value to encode as a CSS identifier.
	 * @return string Serialized CSS identifier text.
	 */
	public static function serialize_ident( string $value ): string {
		$value  = wp_scrub_utf8( $value );
		$result = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $value[ $i ] );

			// NULL → U+FFFD REPLACEMENT CHARACTER.
			if ( 0x00 === $byte ) {
				$result .= "\u{FFFD}";
				continue;
			}

			// Non-ASCII bytes (≥ 0x80): valid in idents, pass through.
			if ( $byte >= 0x80 ) {
				$result .= $value[ $i ];
				continue;
			}

			// ASCII letters and underscore: always valid in idents.
			if (
				( $byte >= 0x41 && $byte <= 0x5A ) || // A-Z
				( $byte >= 0x61 && $byte <= 0x7A ) || // a-z
				0x5F === $byte                         // _
			) {
				$result .= $value[ $i ];
				continue;
			}

			// Hyphen: valid in idents, but check for hyphen-digit at start.
			if ( 0x2D === $byte ) {
				// A hyphen standing alone must be escaped to form an ident token.
				if ( 0 === $i && 1 === $length ) {
					$result .= '\\2D ';
					continue;
				}

				// Hyphen at position 0 followed by a digit at position 1: escape the digit.
				if ( 0 === $i && $i + 1 < $length && ord( $value[ $i + 1 ] ) >= 0x30 && ord( $value[ $i + 1 ] ) <= 0x39 ) {
					$result .= '-';
					++$i;
					$result .= sprintf( '\\%X ', ord( $value[ $i ] ) );
					continue;
				}
				$result .= '-';
				continue;
			}

			// Digits: valid except at position 0.
			if ( $byte >= 0x30 && $byte <= 0x39 ) {
				if ( 0 === $i ) {
					$result .= sprintf( '\\%X ', $byte );
				} else {
					$result .= $value[ $i ];
				}
				continue;
			}

			// Everything else: hex-escape.
			$result .= sprintf( '\\%X ', $byte );
		}

		return $result;
	}

	/**
	 * Creates a quoted CSS string from a plain PHP string value.
	 *
	 * Example:
	 *     $value = 'CSS & a "<style>" tag\'s strings';
	 *     $css_string = WP_CSS_Token_Processor::serialize_string( $value );
	 *     echo "<style>*::before { content: {$css_string}; }</style>";
	 *
	 * CSS strings are quoted, and many characters that are problematic in HTML
	 * or may be complicated for rudimentary CSS or HTML processors to handle
	 * are encoded using Unicode escape sequences.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#escaping
	 *
	 * @since 7.2.0
	 *
	 * @param string $value Decoded string value to encode as a quoted CSS string.
	 * @return string Serialized quoted CSS string text.
	 */
	public static function serialize_string( string $value ): string {
		$value   = wp_scrub_utf8( $value );
		$escaped = strtr(
			$value,
			array(
				// Escape existing backslashes to prevent unintentional escapes in result.
				'\\'   => '\\5C ',

				// Pre-processing replaces NULLs and some newlines. Replace and escape as necessary.
				"\0"   => "\u{FFFD}",

				// Normalize and replace newlines. https://www.w3.org/TR/css-syntax-3/#input-preprocessing
				"\r\n" => '\\A ',
				"\r"   => '\\A ',
				"\f"   => '\\A ',

				// Newlines must be escaped in CSS strings.
				"\n"   => '\\A ',

				// Arbitrary characters for Unicode escaping:

				// HTML syntax may be problematic.
				'<'    => '\\3C ',
				'>'    => '\\3E ',
				'&'    => '\\26 ',

				// CSS syntax may be problematic.
				','    => '\\2C ',
				';'    => '\\3B ',
				'{'    => '\\7B ',
				'}'    => '\\7D ',
				'"'    => '\\22 ',
				"'"    => '\\27 ',
			)
		);
		return "\"{$escaped}\"";
	}

	/**
	 * Clears token state between tokens.
	 */
	private function after_token(): void {
		$this->token_type            = null;
		$this->token_type_flag       = null;
		$this->token_starts_at       = null;
		$this->token_length          = null;
		$this->token_value           = false;
		$this->token_unit            = null;
		$this->token_is_terminated   = true;
		$this->token_value_starts_at = null;
		$this->token_value_length    = null;
	}

	/**
	 * Consumes a string token.
	 *
	 * Strings are quoted with either " or ' and can contain escape sequences.
	 * Newlines inside strings (without escaping) make the string invalid.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-string-token
	 *
	 * @return bool
	 */
	private function consume_string(): bool {
		// Initially create a <string-token> with its value set to the empty string.
		$this->token_starts_at = $this->at;
		$ending_char           = $this->css[ $this->at ];

		// Skip past the opening quote.
		++$this->at;
		$value_starts_at = $this->at;

		// Characters that need special handling: the ending quote, newlines, backslashes.
		$special_chars = "'" === $ending_char ? "'\n\f\r\\" : "\"\n\f\r\\";

		while ( $this->at < $this->length ) {
			// Consume normal characters until we hit a special character.
			$normal_len = strcspn( $this->css, $special_chars, $this->at );
			if ( $normal_len > 0 ) {
				$this->at += $normal_len;
			}

			if ( $this->at >= $this->length ) {
				break; // EOF.
			}

			$char = $this->css[ $this->at ];
			switch ( $char ) {
				case $ending_char:
					// Ending quote.
					// Return the <string-token>.
					++$this->at;
					$this->token_type            = self::TOKEN_STRING;
					$this->token_length          = $this->at - $this->token_starts_at;
					$this->token_value_starts_at = $value_starts_at;
					$this->token_value_length    = $this->at - $value_starts_at - 1;
					return true;

				case "\n":
				case "\f":
				case "\r":
					/*
					 * Newline.
					 *
					 * This is a parse error. Reconsume the current input code point,
					 * create a <bad-string-token>, and return it.
					 *
					 * Unescaped newlines are not allowed in strings. To include a newline,
					 * it must be escaped as \A or the string must end and a new one begin.
					 *
					 * @see https://www.w3.org/TR/css-syntax-3/#consume-string-token
					 */
					$this->token_type            = self::TOKEN_BAD_STRING;
					$this->token_length          = $this->at - $this->token_starts_at;
					$this->token_value_starts_at = $value_starts_at;
					$this->token_value_length    = $this->at - $value_starts_at;
					return true;

				case '\\':
					// U+005C REVERSE SOLIDUS (\)
					// If the next input code point is EOF, do nothing.
					++$this->at;
					if ( $this->at >= $this->length ) {
						// Backslash-EOF: do nothing, just consume the backslash.
						continue 2;
					}

					// Otherwise, if the next input code point is a newline, consume it.
					$next = $this->css[ $this->at ];
					if ( "\n" === $next || "\f" === $next ) {
						++$this->at;
						continue 2;
					} elseif ( "\r" === $next ) {
						++$this->at;
						// Handle \r\n as a single newline.
						if ( $this->at < $this->length && "\n" === $this->css[ $this->at ] ) {
							++$this->at;
						}
						continue 2;
					}

					// Otherwise, (the stream starts with a valid escape) consume an escaped
					// code point (just to advance position, don't store the result).
					$this->decode_escape_at( $this->at, $matched_bytes );
					$this->at += $matched_bytes;
					continue 2;

				default:
					_doing_it_wrong( __METHOD__, 'Unexpected character in string: ' . $char, '1.0.0' );
					break;
			}
		}

		// EOF
		// This is a parse error. Return the <string-token>.
		$this->token_type            = self::TOKEN_STRING;
		$this->token_is_terminated   = false;
		$this->token_length          = $this->at - $this->token_starts_at;
		$this->token_value_starts_at = $value_starts_at;
		$this->token_value_length    = $this->at - $value_starts_at;
		return true;
	}

	/**
	 * Consumes a numeric token (number, percentage, dimension).
	 *
	 * Numbers can be integers or decimals, with optional sign and exponent.
	 * They can be followed by % (percentage) or an identifier (dimension).
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-numeric-token
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-number
	 *
	 * @return bool
	 */
	private function consume_numeric(): bool {
		// Consume a number and let number be the result.
		// The type flag defaults to "integer".
		$number_type = 'integer';

		// If the next input code point is U+002B PLUS SIGN (+) or U+002D HYPHEN-MINUS (-),
		// consume it and append it to repr.
		if ( '+' === $this->css[ $this->at ] || '-' === $this->css[ $this->at ] ) {
			++$this->at;
		}

		// While the next input code point is a digit, consume it and append it to repr.
		$digits = strspn( $this->css, '0123456789', $this->at );
		if ( $digits > 0 ) {
			$this->at += $digits;
		}

		// If the next 2 input code points are U+002E FULL STOP (.) followed by a digit, then.
		if (
			$this->at + 1 < $this->length &&
			'.' === $this->css[ $this->at ] &&
			$this->css[ $this->at + 1 ] >= '0' &&
			$this->css[ $this->at + 1 ] <= '9'
		) {
			// Consume them.
			++$this->at;
			// Set type to "number".
			$number_type = 'number';
			// While the next input code point is a digit, consume it and append it to repr.
			$digits = strspn( $this->css, '0123456789', $this->at );
			if ( $digits > 0 ) {
				$this->at += $digits;
			}
		}

		// If the next 2 or 3 input code points are U+0045 LATIN CAPITAL LETTER E (E)
		// or U+0065 LATIN SMALL LETTER E (e), optionally followed by U+002D HYPHEN-MINUS (-)
		// or U+002B PLUS SIGN (+), followed by a digit, then.
		if ( $this->at < $this->length ) {
			$e = $this->css[ $this->at ];
			if ( 'e' === $e || 'E' === $e ) {
				$save_pos = $this->at;
				++$this->at;
				$has_exp = false;

				if ( $this->at < $this->length ) {
					$next = $this->css[ $this->at ];
					if ( ( '+' === $next || '-' === $next ) && $this->at + 1 < $this->length &&
						$this->css[ $this->at + 1 ] >= '0' && $this->css[ $this->at + 1 ] <= '9' ) {
						// Consume them.
						++$this->at;
						$has_exp = true;
					} elseif ( $next >= '0' && $next <= '9' ) {
						$has_exp = true;
					}
				}

				if ( $has_exp ) {
					// Set type to "number".
					$number_type = 'number';
					// While the next input code point is a digit, consume it and append it to repr.
					$digits = strspn( $this->css, '0123456789', $this->at );
					if ( $digits > 0 ) {
						$this->at += $digits;
					}
				} else {
					$this->at = $save_pos;
				}
			}
		}

		/**
		 * This is the end of spec section 4.3.12. Consume a number.
		 * We still have some work to do as specified in section 4.3.3. Consume a numeric token:
		 * https://www.w3.org/TR/css-syntax-3/#consume-numeric-token
		 */

		// If the next 3 input code points would start an ident sequence, then.
		if ( $this->check_if_3_code_points_start_an_ident_sequence( $this->at ) ) {
			// Create a <dimension-token> with the same value and type flag as number,
			// and a unit set initially to the empty string.
			// Consume an ident sequence. Set the <dimension-token>'s unit to the returned value.
			$unit_starts_at = $this->at;
			$this->consume_ident_sequence();
			$this->token_unit      = $this->decode_range( $unit_starts_at, $this->at - $unit_starts_at );
			$this->token_type      = self::TOKEN_DIMENSION;
			$this->token_type_flag = $number_type;
			$this->token_length    = $this->at - $this->token_starts_at;
			return true;
		}

		// Otherwise, if the next input code point is U+0025 PERCENTAGE SIGN (%), consume it.
		// Create a <percentage-token> with the same value as number, and return it.
		// Note: percentage tokens do not have a type flag per spec.
		if ( $this->at < $this->length && '%' === $this->css[ $this->at ] ) {
			++$this->at;
			$this->token_type   = self::TOKEN_PERCENTAGE;
			$this->token_length = $this->at - $this->token_starts_at;
			return true;
		}

		// Otherwise, create a <number-token> with the same value and type flag as number, and return it.
		$this->token_type      = self::TOKEN_NUMBER;
		$this->token_type_flag = $number_type;
		$this->token_length    = $this->at - $this->token_starts_at;
		return true;
	}

	/**
	 * Consumes an ident-like token (function, url, ident).
	 *
	 * After consuming an identifier, checks if it's followed by '(' to determine
	 * if it's a function or url() token, otherwise it's a plain identifier.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-ident-like-token
	 *
	 * @return bool
	 */
	private function consume_ident_like(): bool {
		// Consume an ident sequence, and let string be the result.
		$ident_start = $this->at;
		$decoded     = $this->consume_ident_sequence();
		$string      = $decoded ?? $this->decode_range( $ident_start, $this->at - $ident_start );

		// If string's value is an ASCII case-insensitive match for "url",
		// and the next input code point is U+0028 LEFT PARENTHESIS (().
		if ( 0 === strcasecmp( $string, 'url' ) && $this->at < $this->length && '(' === $this->css[ $this->at ] ) {
			// Consume it.
			++$this->at;

			// While the next two input code points are whitespace, consume the next input code point.
			$ws_len = strspn( $this->css, "\t\n\f\r ", $this->at );

			// If the next one or two input code points are U+0022 QUOTATION MARK ("),
			// U+0027 APOSTROPHE ('), or whitespace followed by U+0022 QUOTATION MARK (")
			// or U+0027 APOSTROPHE (').
			if ( $this->at + $ws_len < $this->length ) {
				$next = $this->css[ $this->at + $ws_len ];
				if ( '"' === $next || "'" === $next ) {
					// then create a <function-token> with its value set to string and return it.
					if ( null !== $decoded ) {
						$this->token_value = $decoded;
					}
					$this->token_type   = self::TOKEN_FUNCTION;
					$this->token_length = $this->at - $this->token_starts_at;
					return true;
				}
			}

			// Otherwise, consume a url token, and return it.
			$this->at += $ws_len;
			return $this->consume_url();
		}

		// Otherwise, if the next input code point is U+0028 LEFT PARENTHESIS (().
		if ( $this->at < $this->length && '(' === $this->css[ $this->at ] ) {
			// Consume it.
			++$this->at;
			// Create a <function-token> with its value set to string and return it.
			if ( null !== $decoded ) {
				$this->token_value = $decoded;
			}
			$this->token_type   = self::TOKEN_FUNCTION;
			$this->token_length = $this->at - $this->token_starts_at;
			return true;
		}

		// Otherwise, create an <ident-token> with its value set to string and return it.
		if ( null !== $decoded ) {
			$this->token_value = $decoded;
		}
		$this->token_type   = self::TOKEN_IDENT;
		$this->token_length = $this->at - $this->token_starts_at;
		return true;
	}

	/**
	 * Consumes a url token.
	 *
	 * URL tokens can contain unquoted URLs with escape sequences but not quotes,
	 * parentheses, or certain control characters. Invalid characters create a
	 * bad-url token.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-url-token
	 *
	 * @return bool
	 */
	private function consume_url(): bool {
		// Initially create a <url-token> with its value set to the empty string.
		// Consume as much whitespace as possible.
		$this->at += strspn( $this->css, "\t\n\f\r ", $this->at );

		$value_starts_at = $this->at;

		// Repeatedly consume the next input code point from the stream.
		while ( $this->at < $this->length ) {
			// U+0029 RIGHT PARENTHESIS ())
			// Return the <url-token>.
			if ( ')' === $this->css[ $this->at ] ) {
				++$this->at;
				$this->token_type            = self::TOKEN_URL;
				$this->token_length          = $this->at - $this->token_starts_at;
				$this->token_value_starts_at = $value_starts_at;
				$this->token_value_length    = $this->at - $value_starts_at - 1;
				return true;
			}

			// whitespace
			// Consume as much whitespace as possible. If the next input code point is
			// U+0029 RIGHT PARENTHESIS ()) or EOF, consume it and return the <url-token>
			// (if EOF was encountered, this is a parse error); otherwise, consume the
			// remnants of a bad url, create a <bad-url-token>, and return it.
			$ws_len = strspn( $this->css, "\t\n\f\r ", $this->at );
			if ( $ws_len > 0 ) {
				$value_ends_at = $this->at;
				$this->at     += $ws_len;
				// Accept either ) or EOF after whitespace.
				if ( $this->at >= $this->length ) {
					// EOF is a parse error, but we return the <url-token> anyway.
					$this->token_type            = self::TOKEN_URL;
					$this->token_is_terminated   = false;
					$this->token_length          = $this->at - $this->token_starts_at;
					$this->token_value_starts_at = $value_starts_at;
					$this->token_value_length    = $value_ends_at - $value_starts_at;
					return true;
				}

				if ( ')' === $this->css[ $this->at ] ) {
					// Skip the closing parenthesis and return the <url-token>.
					++$this->at;
					$this->token_type            = self::TOKEN_URL;
					$this->token_length          = $this->at - $this->token_starts_at;
					$this->token_value_starts_at = $value_starts_at;
					$this->token_value_length    = $value_ends_at - $value_starts_at;
					return true;
				}

				return $this->consume_remnants_of_bad_url();
			}

			// These codepoints trigger a parse error.
			$byte = ord( $this->css[ $this->at ] );
			if (
				'"' === $this->css[ $this->at ] ||
				"'" === $this->css[ $this->at ] ||
				'(' === $this->css[ $this->at ] ||

				// NUL becomes U+FFFD during CSS preprocessing, so it is valid here.
				// Keep its source byte for offsets; decode_range() replaces it when
				// the caller reads the value. Other non-printable controls stay invalid.
				( $byte >= 0x01 && $byte <= 0x08 ) ||

				// Line Tabulation.
				0x0B === $byte ||

				// Control characters.
				( $byte >= 0x000E && $byte <= 0x001F ) ||

				// Delete.
				0x7F === $byte
			) {
				// Consume the remnants of a bad url,
				// create a <bad-url-token>, and return it.
				return $this->consume_remnants_of_bad_url();
			}

			// U+005C REVERSE SOLIDUS (\)
			// If the stream starts with a valid escape, consume an escaped code point.
			if ( '\\' === $this->css[ $this->at ] ) {
				if ( $this->is_valid_escape( $this->at ) ) {
					++$this->at;
					$this->decode_escape_at( $this->at, $matched_bytes );
					$this->at += $matched_bytes;
					continue;
				}
				// Otherwise, this is a parse error. Consume the remnants of a bad url,
				// create a <bad-url-token>, and return it.
				return $this->consume_remnants_of_bad_url();
			}

			$at             = $this->at;
			$invalid_length = 0;
			if ( 1 !== _wp_scan_utf8( $this->css, $at, $invalid_length, null, 1 ) ) {
				/**
				 * Trouble ahead!
				 * Bytes at $at are not a valid UTF-8 sequence.
				 *
				 * We'll move forward by $invalid_length bytes and continue processing.
				 * Later on, during the string decoding, we'll replace the invalid bytes with U+FFFD
				 * via maximal subpart”replacement.
				 */
				$this->at += $invalid_length;
			} else {
				$this->at = $at;
			}
		}

		// EOF
		// This is a parse error. Return the <url-token>.
		$this->token_type            = self::TOKEN_URL;
		$this->token_is_terminated   = false;
		$this->token_length          = $this->at - $this->token_starts_at;
		$this->token_value_starts_at = $value_starts_at;
		$this->token_value_length    = $this->at - $value_starts_at;
		return true;
	}

	/**
	 * Finishes a bad url token by consuming remnants.
	 *
	 * When an invalid character is encountered in a URL, we must consume
	 * the remainder of the URL up to the closing ) or EOF.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-remnants-of-bad-url
	 *
	 * @return bool
	 */
	private function consume_remnants_of_bad_url(): bool {
		// Set to true when a `)` ends the token.
		$this->token_is_terminated = false;

		while ( $this->at < $this->length ) {
			$this->at += strcspn( $this->css, ')\\', $this->at );

			if ( $this->at >= $this->length ) {
				break;
			}

			if ( '\\' === $this->css[ $this->at ] ) {
				++$this->at;
				if ( $this->is_valid_escape( $this->at - 1 ) ) {
					$this->decode_escape_at( $this->at, $matched_bytes );
					$this->at += $matched_bytes;
					continue;
				}
			} elseif ( ')' === $this->css[ $this->at ] ) {
				++$this->at;
				$this->token_is_terminated = true;
				break;
			}
		}

		$this->token_type   = self::TOKEN_BAD_URL;
		$this->token_length = $this->at - $this->token_starts_at;
		return true;
	}

	/**
	 * Consumes an identifier sequence.
	 *
	 * Identifiers can contain letters, digits, hyphens, underscores, non-ASCII
	 * characters, and escape sequences. Null bytes are replaced with U+FFFD.
	 *
	 * Returns the decoded identifier string if escapes were encountered,
	 * or null if no decoding was needed (can use raw substring).
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-name
	 */
	private function consume_ident_sequence() {
		while ( $this->at < $this->length ) {
			$codepoint_bytes = $this->consume_ident_codepoint( $this->at );
			if ( $codepoint_bytes > 0 ) {
				$this->at += $codepoint_bytes;
				continue;
			}

			if ( $this->is_valid_escape( $this->at ) ) {
				++$this->at;

				$this->decode_escape_at( $this->at, $matched_bytes );
				$this->at += $matched_bytes;
				continue;
			}

			break;
		}
	}

	/**
	 * Ident-start code point
	 *     A letter, a non-ASCII code point, or U+005F LOW LINE (_).
	 *
	 * Ident code point
	 *     An ident-start code point, a digit, or U+002D HYPHEN-MINUS (-).
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#ident-start-code-point
	 * @return int The number of bytes consumed.
	 */
	private function consume_ident_codepoint( $at ): int {
		// ident code points.
		if ( ( $this->css[ $at ] >= '0' && $this->css[ $at ] <= '9' ) ||
			'-' === $this->css[ $at ] ) {
			return 1;
		}

		return $this->consume_ident_start_codepoint( $at );
	}


	/**
	 * Ident-start code point
	 *     A letter, a non-ASCII code point, or U+005F LOW LINE (_).
	 *
	 * Ident code point
	 *     An ident-start code point, a digit, or U+002D HYPHEN-MINUS (-).
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#ident-start-code-point
	 * @return int The number of bytes consumed.
	 */
	private function consume_ident_start_codepoint( $at ): int {
		if ( $at >= $this->length ) {
			return 0;
		}

		// ASCII codepoints.
		if ( ( $this->css[ $at ] >= 'A' && $this->css[ $at ] <= 'Z' ) ||
			( $this->css[ $at ] >= 'a' && $this->css[ $at ] <= 'z' ) ||
			'_' === $this->css[ $at ] ) {
			return 1;
		}

		// Special case for null bytes – they are replaced with U+FFFD during preprocessing.
		if ( "\x00" === $this->css[ $at ] ) {
			return 1;
		}

		$new_at         = $at;
		$invalid_length = 0;
		if ( 1 !== _wp_scan_utf8( $this->css, $new_at, $invalid_length, null, 1 ) ) {
			/**
			 * Trouble ahead!
			 * Bytes at $at are not a valid UTF-8 sequence.
			 *
			 * We'll move forward by $invalid_length bytes and continue processing.
			 * Later on, during the string decoding, we'll replace the invalid bytes with U+FFFD
			 * via maximal subpart”replacement.
			 */
			return $invalid_length;
		}

		$codepoint_byte_length = $new_at - $at;
		$codepoint             = self::utf8_ord( substr( $this->css, $at, $codepoint_byte_length ) );
		if ( null !== $codepoint && $codepoint >= 0x80 ) {
			return $codepoint_byte_length;
		}
		return 0;
	}

	/**
	 * Decodes and normalizes ident-like or string CSS values from a byte range.
	 *
	 * @param int  $start          Start byte offset.
	 * @param int  $length         Length of the substring to decode.
	 * @param bool $string_escapes Optional, default false. When true, apply additional escape
	 *                             rules that apply only to string tokens.
	 * @return string Decoded and normalized string.
	 */
	private function decode_range( int $start, int $length, bool $string_escapes = false ): string {
		// Fast path: check if any processing is needed.
		$slice         = wp_scrub_utf8( substr( $this->css, $start, $length ) );
		$special_chars = "\\\r\f\x00";
		if ( false === strpbrk( $slice, $special_chars ) ) {
			// No special chars - return raw substring (almost zero allocations).
			return $slice;
		}

		// Slow path: build decoded string (one allocation).
		$decoded = '';
		$at      = $start;
		$end     = $start + $length;

		while ( $at < $end ) {
			// Find next special character within the token boundary.
			$normal_len = strcspn( $this->css, $special_chars, $at, $end - $at );
			if ( $normal_len > 0 ) {
				$decoded .= wp_scrub_utf8( substr( $this->css, $at, $normal_len ) );
				$at      += $normal_len;
			}

			if ( $at >= $end ) {
				break;
			}

			$char = $this->css[ $at ];

			// Handle escapes (if enabled).
			if ( '\\' === $char ) {
				if ( $string_escapes ) {
					if ( $at + 1 >= $end ) {
						// Backslash-EOF: consume the backslash and stop.
						++$at;
						continue;
					}

					$next = $this->css[ $at + 1 ];
					if ( "\n" === $next || "\f" === $next ) {
						// Backslash followed by LF or FF is a string line continuation.
						$at += 2;
						continue;
					}

					if ( "\r" === $next ) {
						// Backslash followed by CR or CRLF is a string line continuation.
						$at += 2;
						if ( $at < $end && "\n" === $this->css[ $at ] ) {
							++$at;
						}
						continue;
					}
				}

				if ( $this->is_valid_escape( $at ) ) {
					++$at;
					$decoded .= $this->decode_escape_at( $at, $bytes_consumed );
					$at      += $bytes_consumed;
					continue;
				}
				// Invalid escape - consume the backslash and keep going.
				$decoded .= '\\';
				++$at;
				continue;
			}

			// CSS normalization: \r\n, \r, and \f all become \n.
			if ( "\r" === $char ) {
				$decoded .= "\n";
				++$at;
				// Handle \r\n as single newline.
				if ( $at < $end && "\n" === $this->css[ $at ] ) {
					++$at;
				}
				continue;
			}

			if ( "\f" === $char ) {
				$decoded .= "\n";
				++$at;
				continue;
			}

			// Null bytes become U+FFFD.
			if ( "\x00" === $char ) {
				$decoded .= "\u{FFFD}";
				++$at;
				continue;
			}
		}

		return $decoded;
	}

	/**
	 * Decodes an escape sequence starting at the given offset without
	 * modifying $this->at.
	 *
	 * Escape sequences are backslash followed by 1-6 hex digits (with optional
	 * trailing whitespace) or any other character. Invalid code points are
	 * replaced with U+FFFD.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#consume-escaped-code-point
	 *
	 * @param int $offset Byte offset (should point to the character after the backslash).
	 * @param int &$bytes_consumed Output parameter: number of bytes consumed.
	 * @return string The decoded character(s).
	 */
	private function decode_escape_at( int $offset, &$bytes_consumed ): string {
		// This method assumes the U+005C REVERSE SOLIDUS (\) has already been consumed
		// and the next input code point has already been verified to be part of a valid
		// escape sequence.
		$at = $offset;

		// EOF.
		if ( $at >= $this->length ) {
			// This is a parse error. Return U+FFFD REPLACEMENT CHARACTER (�).
			$bytes_consumed = 0;
			return "\u{FFFD}";
		}

		// Hex digits (CSS spec allows at most 6).
		$hex_len = strspn( $this->css, '0123456789ABCDEFabcdef', $at, 6 );
		if ( $hex_len > 0 ) {
			$hex = substr( $this->css, $at, $hex_len );
			$at += $hex_len;

			// If the next input code point is whitespace, consume it as well.
			if ( $at < $this->length ) {
				$next = $this->css[ $at ];
				if ( "\t" === $next || "\n" === $next || "\f" === $next || ' ' === $next ) {
					++$at;
				} elseif ( "\r" === $next ) {
					++$at;
					// Handle \r\n as a single whitespace – the preprocessing phase would replace \r\n with \n.
					if ( $at < $this->length && "\n" === $this->css[ $at ] ) {
						++$at;
					}
				}
			}

			$bytes_consumed = $at - $offset;
			$code_point     = hexdec( $hex );
			if (
				$code_point <= 0 ||
				( $code_point >= 0xD800 && $code_point <= 0xDFFF ) ||
				$code_point > 0x10FFFF
			) {
				return "\u{FFFD}";
			}

			// Convert the hex digits to a UTF-8 string.
			return WP_HTML_Decoder::code_point_to_utf8_bytes( $code_point );
		}

		// Anything else.
		// Return the current input code point.
		// Null bytes are replaced with U+FFFD during preprocessing.
		if ( "\x00" === $this->css[ $at ] ) {
			$bytes_consumed = 1;
			return "\u{FFFD}";
		}

		$new_at         = $at;
		$invalid_length = 0;
		if ( 1 !== _wp_scan_utf8( $this->css, $new_at, $invalid_length, null, 1 ) ) {
			// Consume the invalid subpart and return U+FFFD.
			$bytes_consumed = $invalid_length;
			return "\u{FFFD}";
		}

		$bytes_consumed = $new_at - $at;
		return substr( $this->css, $at, $bytes_consumed );
	}

	/**
	 * Checks if current position starts a valid escape sequence.
	 *
	 * A valid escape is a backslash not followed by a newline or EOF.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#starts-with-a-valid-escape
	 *
	 * @param int $offset Byte offset.
	 * @return bool
	 */
	private function is_valid_escape( int $offset ): bool {
		// If the first code point is not U+005C REVERSE SOLIDUS (\), return false.
		if ( $offset >= $this->length || '\\' !== $this->css[ $offset ] ) {
			return false;
		}
		// Otherwise, if the second code point is a newline, return false.
		if ( $offset + 1 >= $this->length ) {
			// Second code point is EOF - this is a valid escape per spec (weird!)
			// Are we sure we're interpreting the spec correctly?
			return true;
		}

		// Otherwise, if the second code point is not a newline, return true.
		return (
			"\n" !== $this->css[ $offset + 1 ] &&

			// Form feed is normalized to newline during preprocessing.
			"\f" !== $this->css[ $offset + 1 ] &&

			// Carriage return is normalized to newline during preprocessing.
			"\r" !== $this->css[ $offset + 1 ]

			// We don't need to check for \r\n separately here. The \r check alone covers
			// that scenario.
		);
	}

	/**
	 * Checks if the next 3 code points would start a number.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#starts-with-a-number
	 *
	 * @return bool
	 */
	private function would_next_3_code_points_start_a_number(): bool {
		if ( $this->at >= $this->length ) {
			return false;
		}

		// Look at the first code point.

		// U+002B PLUS SIGN (+) or U+002D HYPHEN-MINUS (-).
		if ( '+' === $this->css[ $this->at ] || '-' === $this->css[ $this->at ] ) {
			if ( $this->at + 1 >= $this->length ) {
				return false;
			}
			// If the second code point is a digit, return true.
			if ( $this->css[ $this->at + 1 ] >= '0' && $this->css[ $this->at + 1 ] <= '9' ) {
				return true;
			}
			// Otherwise, the second code point must be a full stop (.) and the third code point must be a digit.
			if ( '.' === $this->css[ $this->at + 1 ] && $this->at + 2 < $this->length ) {
				return $this->css[ $this->at + 2 ] >= '0' && $this->css[ $this->at + 2 ] <= '9';
			}

			// Otherwise, return false.
			return false;
		}

		// U+002E FULL STOP (.).
		if ( '.' === $this->css[ $this->at ] ) {
			if ( $this->at + 1 >= $this->length ) {
				return false;
			}
			return $this->css[ $this->at + 1 ] >= '0' && $this->css[ $this->at + 1 ] <= '9';
		}

		// Digit.
		if ( $this->css[ $this->at ] >= '0' && $this->css[ $this->at ] <= '9' ) {
			return true;
		}

		// Anything else – return false.
		return false;
	}

	/**
	 * Checks if three code points would start an identifier sequence.
	 *
	 * This implements the CSS spec's "Check if three code points would start an ident sequence"
	 * algorithm, which checks the code point at $offset and the following two code points.
	 *
	 * NOTE: "Three code points" means three Unicode code points, not three bytes.
	 * Multi-byte UTF-8 sequences count as single code points.
	 *
	 * @see https://www.w3.org/TR/css-syntax-3/#would-start-an-identifier
	 *
	 * @param int $offset Byte offset of the first code point to check.
	 * @return bool
	 */
	private function check_if_3_code_points_start_an_ident_sequence( int $offset ): bool {
		if ( $offset >= $this->length ) {
			return false;
		}

		if ( '-' === $this->css[ $offset ] ) {
			// If the second code point is a U+002D HYPHEN-MINUS (-), return true.
			// e.g. --custom-property.
			if ( $offset + 1 < $this->length && '-' === $this->css[ $offset + 1 ] ) {
				return true;
			}
			// Otherwise, check if the second code point is an ident-START code point or valid escape.
			// Note: After a hyphen, only ident-START code points are valid, NOT digits or hyphens.
			++$offset;
		}

		return $this->consume_ident_start_codepoint( $offset ) > 0 || $this->is_valid_escape( $offset );
	}

	/**
	 * Convert a UTF-8 byte sequence to its Unicode codepoint.
	 *
	 * @param  string $character  UTF-8 encoded byte sequence representing a single Unicode character.
	 *
	 * @return int Unicode codepoint.
	 */
	private static function utf8_ord( string $character ): int {
		// Convert the byte sequence to its binary representation.
		$bytes = unpack( 'C*', $character );

		// Initialize the codepoint.
		$codepoint = 0;

		// Calculate the codepoint based on the number of bytes.
		if ( 1 === count( $bytes ) ) {
			$codepoint = $bytes[1];
		} elseif ( 2 === count( $bytes ) ) {
			$codepoint = ( ( $bytes[1] & 0x1F ) << 6 ) | ( $bytes[2] & 0x3F );
		} elseif ( 3 === count( $bytes ) ) {
			$codepoint = ( ( $bytes[1] & 0x0F ) << 12 ) | ( ( $bytes[2] & 0x3F ) << 6 ) | ( $bytes[3] & 0x3F );
		} elseif ( 4 === count( $bytes ) ) {
			$codepoint = ( ( $bytes[1] & 0x07 ) << 18 ) | ( ( $bytes[2] & 0x3F ) << 12 ) | ( ( $bytes[3] & 0x3F ) << 6 ) | ( $bytes[4] & 0x3F );
		}

		return $codepoint;
	}
}
