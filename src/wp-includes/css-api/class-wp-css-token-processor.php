<?php
/**
 * CSS API: WP_CSS_Token_Processor class
 *
 * @package WordPress
 * @subpackage CSS-API
 * @since X.X.0
 */

/**
 * Core class used to tokenize a CSS string and sanitize or validate its content.
 *
 * ## What this class is
 *
 * A streaming, forward-only CSS tokenizer built on the CSS Syntax Level 3
 * specification token vocabulary. It is designed to process block-level custom
 * CSS (`attrs.style.css`) safely for users who lack the `unfiltered_html`
 * capability, replacing the incorrect use of `wp_kses()` (an HTML sanitizer)
 * on CSS content.
 *
 * ## What this class is NOT
 *
 * - Not a full CSS parser. It does not build a parse tree or understand
 *   the full CSS grammar beyond the token stream.
 * - Not a property/value validator. `color: turquoise-flamingo` will pass
 *   through. Authoring intent is not a security concern.
 * - Not a replacement for `safecss_filter_attr()`, which handles inline
 *   `style` attribute values (single declarations). This class handles
 *   multi-rule CSS blocks with nesting and at-rules.
 *
 * ## Spec reference
 *
 * CSS Syntax Level 3: https://www.w3.org/TR/css-syntax-3/
 * This implementation is spec-inspired but safety-first: gaps in coverage
 * cause stripping/rejection rather than silent pass-through.
 *
 * ## Known gaps (v1)
 *
 * - Unicode range tokens (`U+`) are not supported; treated as unknown and stripped.
 * - Surrogate pair edge cases beyond basic UTF-8 are not handled.
 * - CSS escape sequences (`\XX` hex or `\<char>`) in identifiers are not supported;
 *   a backslash is emitted as a DELIM_TOKEN rather than starting an escaped ident.
 * - `url("javascript:...")` with a quoted string argument tokenizes as
 *   FUNCTION_TOKEN + STRING_TOKEN (not URL_TOKEN), so the URL protocol check in
 *   sanitize() and validate() does not fire for quoted javascript: in url().
 * - CSS block comments (slash-star ... star-slash) are not tokenized as a unit; the
 *   comment delimiters and body are emitted as individual DELIM_TOKEN, WHITESPACE_TOKEN,
 *   and IDENT_TOKEN tokens. This means comment content passes through sanitize()
 *   unchanged. Stripping all comments was not implemented to avoid destroying
 *   intentional author comments in stored CSS.
 *
 * ## Usage
 *
 * ### Sanitize for storage (KSES pipeline):
 *
 *     $processor = new WP_CSS_Token_Processor( $css );
 *     $safe_css  = $processor->sanitize();
 *
 * ### Validate for REST API:
 *
 *     $processor = new WP_CSS_Token_Processor( $css );
 *     $result    = $processor->validate(); // true or WP_Error
 *
 * ### Low-level token inspection:
 *
 *     $processor = new WP_CSS_Token_Processor( $css );
 *     while ( $processor->next_token() ) {
 *         if ( WP_CSS_Token_Processor::URL_TOKEN === $processor->get_token_type() ) {
 *             // inspect or modify
 *         }
 *     }
 *     $output = $processor->get_updated_css();
 *
 * @since X.X.0
 */
class WP_CSS_Token_Processor {

	/**
	 * Represents a CSS ident-token.
	 *
	 * @since X.X.0
	 * @var string
	 */
	const IDENT_TOKEN = 'ident-token';

	/**
	 * Represents a CSS function-token (ident followed by `(`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const FUNCTION_TOKEN = 'function-token';

	/**
	 * Represents a CSS at-keyword-token (e.g. `@media`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const AT_KEYWORD_TOKEN = 'at-keyword-token';

	/**
	 * Represents a CSS hash-token (e.g. `#ff0000`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const HASH_TOKEN = 'hash-token';

	/**
	 * Represents a CSS string-token (single- or double-quoted string).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const STRING_TOKEN = 'string-token';

	/**
	 * Represents a CSS bad-string-token (e.g. an unterminated or newline-broken string).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const BAD_STRING_TOKEN = 'bad-string-token';

	/**
	 * Represents a CSS url-token (unquoted URL, e.g. `url(foo.png)`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const URL_TOKEN = 'url-token';

	/**
	 * Represents a CSS bad-url-token (malformed unquoted URL).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const BAD_URL_TOKEN = 'bad-url-token';

	/**
	 * Represents a CSS delim-token (a single unrecognised character).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const DELIM_TOKEN = 'delim-token';

	/**
	 * Represents a CSS number-token (e.g. `42`, `3.14`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const NUMBER_TOKEN = 'number-token';

	/**
	 * Represents a CSS percentage-token (e.g. `50%`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const PERCENTAGE_TOKEN = 'percentage-token';

	/**
	 * Represents a CSS dimension-token (e.g. `16px`, `1.5rem`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const DIMENSION_TOKEN = 'dimension-token';

	/**
	 * Represents a CSS whitespace-token (one or more whitespace characters).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const WHITESPACE_TOKEN = 'whitespace-token';

	/**
	 * Represents a CSS CDO-token (`<!--`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const CDO_TOKEN = 'CDO-token';

	/**
	 * Represents a CSS CDC-token (`-->`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const CDC_TOKEN = 'CDC-token';

	/**
	 * Represents a CSS colon-token (`:`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const COLON_TOKEN = 'colon-token';

	/**
	 * Represents a CSS semicolon-token (`;`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const SEMICOLON_TOKEN = 'semicolon-token';

	/**
	 * Represents a CSS comma-token (`,`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const COMMA_TOKEN = 'comma-token';

	/**
	 * Represents a CSS [-token (`[`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const OPEN_SQUARE_TOKEN = '[-token';

	/**
	 * Represents a CSS ]-token (`]`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const CLOSE_SQUARE_TOKEN = ']-token';

	/**
	 * Represents a CSS (-token (`(`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const OPEN_PAREN_TOKEN = '(-token';

	/**
	 * Represents a CSS )-token (`)`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const CLOSE_PAREN_TOKEN = ')-token';

	/**
	 * Represents a CSS {-token (`{`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const OPEN_CURLY_TOKEN = '{-token';

	/**
	 * Represents a CSS }-token (`}`).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const CLOSE_CURLY_TOKEN = '}-token';

	/**
	 * Represents a CSS EOF-token (end of input).
	 *
	 * @since X.X.0
	 * @var string
	 */
	const EOF_TOKEN = 'EOF-token';

	/*
	 * Note: UNICODE_RANGE_TOKEN is intentionally absent. The tokenizer does not
	 * emit this token type; U+ sequences are treated as unknown tokens (DELIM_TOKEN).
	 * See the "Known gaps (v1)" section in the class docblock.
	 */

	/**
	 * At-rule keywords permitted in block custom CSS.
	 *
	 * At-rules not in this list are stripped by sanitize() and flagged
	 * by validate(). The check is case-insensitive. Vendor-prefixed variants
	 * (e.g. -webkit-keyframes) are included as explicit literal entries in
	 * this list.
	 *
	 * @since X.X.0
	 * @var string[]
	 */
	const ALLOWED_AT_RULES = array(
		'media',
		'supports',
		'keyframes',
		'-webkit-keyframes',
		'layer',
		'container',
		'font-face',
	);

	/**
	 * The original CSS input string (null bytes stripped).
	 *
	 * @since X.X.0
	 * @var string
	 */
	private $css = '';

	/**
	 * Current byte offset within the CSS string.
	 *
	 * @since X.X.0
	 * @var int
	 */
	private $at = 0;

	/**
	 * Byte offset where the current token starts, or null before the first token.
	 *
	 * @since X.X.0
	 * @var int|null
	 */
	private $token_start = null;

	/**
	 * Byte length of the current token.
	 *
	 * @since X.X.0
	 * @var int
	 */
	private $token_length = 0;

	/**
	 * Type of the current token, or null when no token has been consumed yet.
	 *
	 * @since X.X.0
	 * @var string|null
	 */
	private $token_type = null;

	/**
	 * Cached byte length of the CSS string.
	 *
	 * @since X.X.0
	 * @var int
	 */
	private $length = 0;

	/**
	 * Current `{ }` nesting depth.
	 *
	 * @since X.X.0
	 * @var int
	 */
	private $block_depth = 0;

	/**
	 * Pending token replacements to apply on get_updated_css().
	 *
	 * Each entry is an array with keys:
	 *   - 'start'       int    Byte offset of the token in the original CSS string.
	 *   - 'length'      int    Byte length of the token in the original CSS string.
	 *   - 'replacement' string Replacement text (empty string to remove the token).
	 *
	 * @since X.X.0
	 * @var array
	 */
	private $replacements = array();

	/**
	 * Log of tokens removed during the last sanitize() call.
	 *
	 * Each entry: [ 'token' => string, 'reason' => string ]
	 *
	 * Reset at the start of each sanitize() call.
	 *
	 * @since X.X.0
	 * @var array
	 */
	private $removed_tokens = array();

	/**
	 * Constructor.
	 *
	 * @since X.X.0
	 *
	 * @param string $css The CSS string to process.
	 */
	public function __construct( string $css ) {
		// Strip null bytes before any processing — these have no valid use in CSS
		// and are a common vector for bypassing text filters.
		$this->css    = str_replace( "\0", '', $css );
		$this->length = strlen( $this->css );
	}

	/**
	 * Advances the tokenizer to the next token.
	 *
	 * Returns true when a token was consumed, false at end-of-input.
	 *
	 * @since X.X.0
	 *
	 * @return bool True if a token was consumed, false at end of input.
	 */
	public function next_token(): bool {
		if ( $this->at >= $this->length ) {
			$this->token_type   = self::EOF_TOKEN;
			$this->token_start  = $this->at;
			$this->token_length = 0;
			return false;
		}

		$this->token_start = $this->at;
		$c                 = $this->css[ $this->at ];

		// Whitespace.
		if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c || "\f" === $c ) {
			$this->at++;
			while ( $this->at < $this->length ) {
				$nc = $this->css[ $this->at ];
				if ( ' ' !== $nc && "\t" !== $nc && "\n" !== $nc && "\r" !== $nc && "\f" !== $nc ) {
					break;
				}
				$this->at++;
			}
			$this->token_type   = self::WHITESPACE_TOKEN;
			$this->token_length = $this->at - $this->token_start;
			return true;
		}

		// Single-character punctuation.
		if ( ':' === $c ) {
			$this->at++;
			$this->token_type   = self::COLON_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( ';' === $c ) {
			$this->at++;
			$this->token_type   = self::SEMICOLON_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( ',' === $c ) {
			$this->at++;
			$this->token_type   = self::COMMA_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( '{' === $c ) {
			$this->at++;
			$this->block_depth++;
			$this->token_type   = self::OPEN_CURLY_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( '}' === $c ) {
			$this->at++;
			if ( $this->block_depth > 0 ) {
				$this->block_depth--;
			}
			$this->token_type   = self::CLOSE_CURLY_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( '(' === $c ) {
			$this->at++;
			$this->token_type   = self::OPEN_PAREN_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( ')' === $c ) {
			$this->at++;
			$this->token_type   = self::CLOSE_PAREN_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( '[' === $c ) {
			$this->at++;
			$this->token_type   = self::OPEN_SQUARE_TOKEN;
			$this->token_length = 1;
			return true;
		}

		if ( ']' === $c ) {
			$this->at++;
			$this->token_type   = self::CLOSE_SQUARE_TOKEN;
			$this->token_length = 1;
			return true;
		}

		// CDO-token `<!--` — must be checked before `<` delim.
		if ( '<' === $c ) {
			if ( $this->at + 3 < $this->length && '<!--' === substr( $this->css, $this->at, 4 ) ) {
				$this->at          += 4;
				$this->token_type   = self::CDO_TOKEN;
				$this->token_length = 4;
				return true;
			}
			// Falls through to DELIM_TOKEN.
		}

		// CDC-token `-->` — must be checked before general `-` ident-start.
		if ( '-' === $c ) {
			if ( $this->at + 2 < $this->length && '-->' === substr( $this->css, $this->at, 3 ) ) {
				$this->at          += 3;
				$this->token_type   = self::CDC_TOKEN;
				$this->token_length = 3;
				return true;
			}
			// Check for ident-start after `-` (custom property `--` or `-` + ident-start char).
			if ( $this->is_ident_start( $this->at ) ) {
				$this->consume_ident_chars();
				$ident_value = substr( $this->css, $this->token_start, $this->at - $this->token_start );
				// url( special handling.
				if ( $this->at < $this->length && '(' === $this->css[ $this->at ] && 'url' === strtolower( $ident_value ) ) {
					return $this->consume_url_or_function();
				}
				if ( $this->at < $this->length && '(' === $this->css[ $this->at ] ) {
					$this->at++;
					$this->token_type   = self::FUNCTION_TOKEN;
					$this->token_length = $this->at - $this->token_start;
					return true;
				}
				$this->token_type   = self::IDENT_TOKEN;
				$this->token_length = $this->at - $this->token_start;
				return true;
			}
			// Falls through to DELIM_TOKEN.
		}

		// at-keyword-token.
		if ( '@' === $c ) {
			if ( $this->at + 1 < $this->length && $this->is_ident_start( $this->at + 1 ) ) {
				$this->at++; // consume `@`.
				$this->consume_ident_chars();
				$this->token_type   = self::AT_KEYWORD_TOKEN;
				$this->token_length = $this->at - $this->token_start;
				return true;
			}
			// Falls through to DELIM_TOKEN.
		}

		// hash-token.
		if ( '#' === $c ) {
			if ( $this->at + 1 < $this->length ) {
				$nc = $this->css[ $this->at + 1 ];
				if ( $this->is_ident_char( $nc ) ) {
					$this->at++; // consume `#`.
					$this->consume_ident_chars();
					$this->token_type   = self::HASH_TOKEN;
					$this->token_length = $this->at - $this->token_start;
					return true;
				}
			}
			// Falls through to DELIM_TOKEN.
		}

		// Numeric tokens — number-token, dimension-token, percentage-token.
		if ( $this->is_number_start( $this->at ) ) {
			$this->consume_number();
			if ( $this->at < $this->length && '%' === $this->css[ $this->at ] ) {
				$this->at++;
				$this->token_type   = self::PERCENTAGE_TOKEN;
				$this->token_length = $this->at - $this->token_start;
				return true;
			}
			if ( $this->at < $this->length && $this->is_ident_start( $this->at ) ) {
				$this->consume_ident_chars();
				$this->token_type   = self::DIMENSION_TOKEN;
				$this->token_length = $this->at - $this->token_start;
				return true;
			}
			$this->token_type   = self::NUMBER_TOKEN;
			$this->token_length = $this->at - $this->token_start;
			return true;
		}

		// String tokens.
		if ( '"' === $c || "'" === $c ) {
			$quote = $c;
			$this->at++;
			$bad = false;
			while ( $this->at < $this->length ) {
				$sc = $this->css[ $this->at ];
				if ( $sc === $quote ) {
					$this->at++;
					break;
				}
				if ( '\\' === $sc ) {
					// Skip the next character (escape sequence).
					$this->at += 2;
					continue;
				}
				if ( "\n" === $sc || "\r" === $sc || "\f" === $sc ) {
					$bad = true;
					break;
				}
				$this->at++;
			}
			$this->token_type   = $bad ? self::BAD_STRING_TOKEN : self::STRING_TOKEN;
			$this->token_length = $this->at - $this->token_start;
			return true;
		}

		// Ident-like tokens (ident-token, function-token).
		if ( $this->is_ident_start( $this->at ) ) {
			$this->consume_ident_chars();
			$ident_value = substr( $this->css, $this->token_start, $this->at - $this->token_start );
			// url( special handling.
			if ( $this->at < $this->length && '(' === $this->css[ $this->at ] && 'url' === strtolower( $ident_value ) ) {
				return $this->consume_url_or_function();
			}
			if ( $this->at < $this->length && '(' === $this->css[ $this->at ] ) {
				$this->at++;
				$this->token_type   = self::FUNCTION_TOKEN;
				$this->token_length = $this->at - $this->token_start;
				return true;
			}
			$this->token_type   = self::IDENT_TOKEN;
			$this->token_length = $this->at - $this->token_start;
			return true;
		}

		// Fallback: DELIM_TOKEN — consume one byte.
		$this->at++;
		$this->token_type   = self::DELIM_TOKEN;
		$this->token_length = 1;
		return true;
	}

	/**
	 * Returns the type of the current token.
	 *
	 * Returns null before the first call to next_token().
	 *
	 * @since X.X.0
	 *
	 * @return string|null Token type constant, or null if no token has been consumed.
	 */
	public function get_token_type(): ?string {
		return $this->token_type;
	}

	/**
	 * Returns the raw CSS text of the current token.
	 *
	 * Returns null before the first call to next_token().
	 *
	 * @since X.X.0
	 *
	 * @return string|null Raw token text, or null if no token has been consumed.
	 */
	public function get_token_value(): ?string {
		if ( null === $this->token_start ) {
			return null;
		}
		return substr( $this->css, $this->token_start, $this->token_length );
	}

	/**
	 * Returns the current `{ }` nesting depth.
	 *
	 * The depth is incremented when an OPEN_CURLY_TOKEN is consumed and
	 * decremented (never below 0) when a CLOSE_CURLY_TOKEN is consumed.
	 *
	 * @since X.X.0
	 *
	 * @return int Current block nesting depth.
	 */
	public function get_block_depth(): int {
		return $this->block_depth;
	}

	/**
	 * Removes the current token from the CSS output.
	 *
	 * Records a removal that will be applied when get_updated_css() is called.
	 * Has no effect and returns false if next_token() has not been called yet,
	 * or if next_token() has exhausted the input (returned false).
	 *
	 * @since X.X.0
	 *
	 * @return bool Whether the removal was recorded.
	 */
	public function remove_token(): bool {
		if ( null === $this->token_start || self::EOF_TOKEN === $this->token_type ) {
			return false;
		}
		$this->replacements[] = array(
			'start'       => $this->token_start,
			'length'      => $this->token_length,
			'replacement' => '',
		);
		return true;
	}

	/**
	 * Replaces the current token's raw text in the CSS output.
	 *
	 * Records a replacement that will be applied when get_updated_css() is called.
	 * Has no effect and returns false if next_token() has not been called yet,
	 * or if next_token() has exhausted the input (returned false).
	 *
	 * Note: The replacement text is used verbatim — no escaping or validation
	 * is applied. Callers are responsible for providing safe replacement values.
	 *
	 * @since X.X.0
	 *
	 * @param string $value Replacement text.
	 * @return bool Whether the replacement was recorded.
	 */
	public function set_token_value( string $value ): bool {
		if ( null === $this->token_start || self::EOF_TOKEN === $this->token_type ) {
			return false;
		}
		$this->replacements[] = array(
			'start'       => $this->token_start,
			'length'      => $this->token_length,
			'replacement' => $value,
		);
		return true;
	}

	/**
	 * Returns the CSS string with all recorded modifications applied.
	 *
	 * Modifications recorded via remove_token() and set_token_value() are applied
	 * to the original input string in reverse byte order, so that earlier byte
	 * offsets remain valid as later replacements are made first.
	 *
	 * If no modifications have been recorded, returns the original CSS string
	 * (after null-byte stripping applied in the constructor).
	 *
	 * @since X.X.0
	 *
	 * @return string The modified CSS string.
	 */
	public function get_updated_css(): string {
		if ( empty( $this->replacements ) ) {
			return $this->css;
		}

		// Deduplicate by start offset — keep the last-recorded replacement for
		// any given position (last-write-wins semantics).
		$keyed = array();
		foreach ( $this->replacements as $replacement ) {
			$keyed[ $replacement['start'] ] = $replacement;
		}
		$sorted = array_values( $keyed );

		// Sort replacements by start offset descending so we apply from end to
		// start, keeping earlier byte offsets valid as we make changes.
		usort(
			$sorted,
			static function ( $a, $b ) {
				return $b['start'] - $a['start'];
			}
		);

		$output = $this->css;
		foreach ( $sorted as $replacement ) {
			$output = substr_replace(
				$output,
				$replacement['replacement'],
				$replacement['start'],
				$replacement['length']
			);
		}
		return $output;
	}

	/**
	 * Returns the list of tokens removed during the last sanitize() call.
	 *
	 * Each entry contains:
	 *   - 'token'  string  The raw token text that was removed.
	 *   - 'reason' string  A short code describing why it was removed.
	 *
	 * Returns an empty array if sanitize() has not been called, or if
	 * the last sanitize() call removed nothing.
	 *
	 * @since X.X.0
	 *
	 * @return array Array of removal log entries.
	 */
	public function get_removed_tokens(): array {
		return $this->removed_tokens;
	}

	/**
	 * Validates the CSS string against all safety checks without modifying it.
	 *
	 * Returns `true` when the CSS passes every check. When a violation is found,
	 * returns a `WP_Error` on the **first** violation encountered — subsequent
	 * tokens are not inspected.
	 *
	 * **Guarantee:** If `validate()` returns `true`, then calling `sanitize()` on
	 * the same input will return that input unchanged (i.e. `sanitize()` is a
	 * no-op). This makes `validate()` suitable for REST API schema validation where
	 * you want to reject bad input rather than silently strip it.
	 *
	 * **Null bytes:** The constructor strips null bytes before any processing, so a
	 * `css_null_byte` violation can never be triggered on a normally-constructed
	 * `WP_CSS_Token_Processor` instance. No `css_null_byte` check is implemented;
	 * callers that need to detect raw null bytes in the original input must check
	 * the string before constructing the processor.
	 *
	 * **Known gap:** `url("javascript:...")` with a quoted string argument is not
	 * flagged as unsafe — it tokenizes as FUNCTION_TOKEN + STRING_TOKEN, not as
	 * URL_TOKEN, so the URL protocol check does not fire. See the sanitize()
	 * docblock for the full explanation. This is not a practical security concern
	 * but means validate() does not reject quoted javascript: in url().
	 *
	 * **Error codes:**
	 *
	 * | Code                      | Condition                                              |
	 * |---------------------------|--------------------------------------------------------|
	 * | `css_injection`           | `</style` found anywhere in the input (case-insensitive) |
	 * | `css_html_comment`        | CDO-token (`<!--`) or CDC-token (`-->`)                |
	 * | `css_malformed_token`     | `BAD_STRING_TOKEN` or `BAD_URL_TOKEN`                  |
	 * | `css_unsafe_url`          | `URL_TOKEN` with `javascript:` or `data:` scheme, or a scheme not in `wp_allowed_protocols()` |
	 * | `css_disallowed_at_rule`  | AT_KEYWORD_TOKEN whose keyword is not in `ALLOWED_AT_RULES` |
	 *
	 * Example usage:
	 *
	 *     $processor = new WP_CSS_Token_Processor( $css );
	 *     $result    = $processor->validate();
	 *     if ( is_wp_error( $result ) ) {
	 *         // handle $result->get_error_code() ...
	 *     }
	 *
	 * @since X.X.0
	 *
	 * @return true|WP_Error True if the CSS passes all checks; WP_Error on the first violation.
	 */
	public function validate() {
		// Injection guard — if </style appears anywhere, reject immediately.
		// This check is case-insensitive and runs before tokenization because
		// the CSS tokenizer handles raw text differently than an HTML parser:
		// even inside a string token the sequence would close a <style> element.
		if ( false !== stripos( $this->css, '</style' ) ) {
			return new WP_Error(
				'css_injection',
				__( 'CSS contains a style element closing tag.' )
			);
		}

		$this->reset();

		$allowed_protocols = wp_allowed_protocols();

		while ( $this->next_token() ) {
			$type  = $this->get_token_type();
			$value = $this->get_token_value();

			// HTML comment tokens have no valid use in CSS.
			if ( self::CDO_TOKEN === $type || self::CDC_TOKEN === $type ) {
				return new WP_Error(
					'css_html_comment',
					__( 'CSS contains an HTML comment token.' )
				);
			}

			// Malformed tokens — bad-string and bad-url have no recoverable content.
			if ( self::BAD_STRING_TOKEN === $type || self::BAD_URL_TOKEN === $type ) {
				return new WP_Error(
					'css_malformed_token',
					__( 'CSS contains a malformed token.' )
				);
			}

			// URL protocol filtering.
			if ( self::URL_TOKEN === $type ) {
				// URL_TOKEN always contains an unquoted URL (quoted URLs become FUNCTION_TOKEN
				// in consume_url_or_function()). The optional quote groups are included
				// defensively, in case the token value is ever constructed differently.
				$url = (string) preg_replace( '/^url\(\s*["\']?|["\']?\s*\)$/i', '', $value );
				$url    = trim( $url );
				$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

				if ( 'javascript' === $scheme || 'data' === $scheme ) {
					return new WP_Error(
						'css_unsafe_url',
						__( 'CSS contains a URL with an unsafe scheme.' )
					);
				}

				if ( '' !== $scheme && ! in_array( $scheme, $allowed_protocols, true ) ) {
					return new WP_Error(
						'css_unsafe_url',
						__( 'CSS contains a URL with a disallowed scheme.' )
					);
				}
			}

			// At-rule allowlist enforcement.
			if ( self::AT_KEYWORD_TOKEN === $type ) {
				// Strip '@' and normalise to lowercase for comparison.
				$keyword = strtolower( ltrim( $value, '@' ) );

				if ( ! in_array( $keyword, self::ALLOWED_AT_RULES, true ) ) {
					return new WP_Error(
						'css_disallowed_at_rule',
						__( 'CSS contains a disallowed at-rule.' )
					);
				}
			}
		}

		return true;
	}

	/**
	 * Sanitizes the CSS string, stripping unsafe tokens and rules.
	 *
	 * Security policy applied:
	 *
	 * - Returns '' immediately if `</style` is found anywhere in the input.
	 *   This injection guard runs before tokenization and takes priority over
	 *   all other checks. The CSS Syntax specification treats CSS as rawtext
	 *   inside a <style> element, so `</style` appearing in any context
	 *   (including inside a string or comment) could close the element.
	 *
	 * - Strips null bytes (handled in the constructor).
	 *
	 * - Strips CDO (`<!--`) and CDC (`-->`) tokens. HTML comments have no
	 *   valid use in CSS and suggest an attempt to embed CSS inside HTML.
	 *
	 * - Strips `bad-string-token` and `bad-url-token`. These represent
	 *   malformed CSS constructs that should not be preserved.
	 *
	 * - Strips `url()` tokens where the URL has a `javascript:` or `data:`
	 *   scheme — these are always unsafe in a CSS context. Other URL tokens
	 *   whose scheme is not in wp_allowed_protocols() have their URL value
	 *   replaced with '' (preserving the `url()` wrapper) to avoid breaking
	 *   the surrounding declaration structure while removing the unsafe URL.
	 *
	 * Known gap: `url("javascript:...")` with a quoted string argument tokenizes
	 * as FUNCTION_TOKEN `url(` followed by a STRING_TOKEN, not as a URL_TOKEN.
	 * The string is preserved as-is. This is not a practical security concern
	 * because browsers do not execute javascript: in CSS resource-fetch contexts,
	 * but it means sanitize() does not strip quoted javascript: in url().
	 *
	 * - Strips entire at-rules whose keyword is not in ALLOWED_AT_RULES,
	 *   including their following block or semicolon terminator. Unknown
	 *   at-rules are stripped rather than passed through (safety-first).
	 *   See ALLOWED_AT_RULES for the permitted list.
	 *
	 * Strip granularity: a bad token removes that token; a bad at-rule removes
	 * the entire rule. The rest of the CSS is preserved.
	 *
	 * Idempotency guarantee: sanitize( sanitize( $css ) ) === sanitize( $css ).
	 *
	 * @since X.X.0
	 *
	 * @return string The sanitized CSS string.
	 */
	public function sanitize(): string {
		// Injection guard — if </style appears anywhere, refuse the whole value.
		// This check is case-insensitive to match the HTML parser's behavior.
		// We do not continue tokenizing because the injection could be embedded
		// inside a string token or other context that the tokenizer handles
		// differently than an HTML parser would.
		if ( false !== stripos( $this->css, '</style' ) ) {
			return '';
		}

		$this->removed_tokens = array();
		$this->reset();

		$allowed_protocols = wp_allowed_protocols();

		while ( $this->next_token() ) {
			$type  = $this->get_token_type();
			$value = $this->get_token_value();

			// Strip HTML comment tokens — these have no valid use in CSS.
			if ( self::CDO_TOKEN === $type || self::CDC_TOKEN === $type ) {
				$this->removed_tokens[] = array(
					'token'  => $value,
					'reason' => 'html_comment',
				);
				$this->remove_token();
				continue;
			}

			// Strip malformed tokens — bad-string and bad-url have no recoverable content.
			if ( self::BAD_STRING_TOKEN === $type || self::BAD_URL_TOKEN === $type ) {
				$this->removed_tokens[] = array(
					'token'  => $value,
					'reason' => self::BAD_STRING_TOKEN === $type ? 'bad_string' : 'bad_url',
				);
				$this->remove_token();
				continue;
			}

			// URL protocol filtering.
			if ( self::URL_TOKEN === $type ) {
				// URL_TOKEN always contains an unquoted URL (quoted URLs become FUNCTION_TOKEN
				// in consume_url_or_function()). The optional quote groups are included
				// defensively, in case the token value is ever constructed differently.
				$url = (string) preg_replace( '/^url\(\s*["\']?|["\']?\s*\)$/i', '', $value );
				$url    = trim( $url );
				$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

				if ( 'javascript' === $scheme || 'data' === $scheme ) {
					// Always strip javascript: and data: entirely — no legitimate use in CSS.
					$this->removed_tokens[] = array(
						'token'  => $value,
						'reason' => 'unsafe_url_protocol',
					);
					$this->remove_token();
					continue;
				}

				if ( '' !== $scheme && ! in_array( $scheme, $allowed_protocols, true ) ) {
					// Disallowed scheme — replace the URL value with '' to preserve
					// the surrounding declaration structure (e.g. background: url();).
					$this->removed_tokens[] = array(
						'token'  => $value,
						'reason' => 'disallowed_url_protocol',
					);
					$this->set_token_value( 'url()' );
					continue;
				}
			}

			// At-rule allowlist enforcement.
			if ( self::AT_KEYWORD_TOKEN === $type ) {
				// Strip '@' and normalise to lowercase for comparison.
				$keyword = strtolower( ltrim( $value, '@' ) );

				if ( ! in_array( $keyword, self::ALLOWED_AT_RULES, true ) ) {
					$this->removed_tokens[] = array(
						'token'  => $value,
						'reason' => 'disallowed_at_rule',
					);
					$this->remove_token();
					// Consume and remove the rule's block or statement terminator.
					$this->consume_and_remove_rule_block();
				}
			}
		}

		return $this->get_updated_css();
	}

	/**
	 * Resets the processor cursor to the beginning of the input.
	 *
	 * Called at the start of sanitize() and validate() to allow those
	 * methods to be called on a freshly constructed instance without
	 * requiring the caller to have iterated the tokenizer first.
	 *
	 * Clears all recorded replacements and resets the block depth counter.
	 * Does NOT clear $removed_tokens — that log is reset at the start of
	 * sanitize() so callers can read it after sanitize() returns.
	 *
	 * @since X.X.0
	 */
	private function reset(): void {
		$this->at           = 0;
		$this->token_start  = null;
		$this->token_length = 0;
		$this->token_type   = null;
		$this->block_depth  = 0;
		$this->replacements = array();
	}

	/**
	 * Consumes and removes the block or semicolon tail of a disallowed at-rule.
	 *
	 * Called immediately after calling remove_token() on a disallowed at-keyword.
	 * Advances the cursor and calls remove_token() on every token until the at-rule
	 * ends — either at a top-level ';' (statement at-rules like @import) or after
	 * the closing '}' of a balanced block at-rule like @media.
	 *
	 * @since X.X.0
	 */
	private function consume_and_remove_rule_block(): void {
		// Use a local depth counter rather than $this->block_depth because we want to
		// track nesting relative to the at-rule being consumed, not the global document
		// depth. $this->block_depth is updated by next_token() for all tokens consumed
		// here and will be correct on return — the two counters remain consistent.
		$depth = 0;
		while ( $this->next_token() ) {
			$type = $this->get_token_type();
			$this->remove_token();

			if ( self::OPEN_CURLY_TOKEN === $type ) {
				++$depth;
			} elseif ( self::CLOSE_CURLY_TOKEN === $type ) {
				--$depth;
				if ( $depth <= 0 ) {
					break;
				}
			} elseif ( self::SEMICOLON_TOKEN === $type && 0 === $depth ) {
				break;
			}
		}
	}

	/**
	 * Determines whether the byte at the given offset is an ident-start character.
	 *
	 * An ident-start character is one of: a–z, A–Z, `_`, any byte with value
	 * greater than 127 (non-ASCII), or `-` followed by another ident-start char
	 * or another `-` (for custom properties `--`).
	 *
	 * @since X.X.0
	 *
	 * @param int $offset Byte offset into the CSS string.
	 * @return bool True if the byte at $offset begins an identifier.
	 */
	private function is_ident_start( int $offset ): bool {
		if ( $offset >= $this->length ) {
			return false;
		}
		$c = $this->css[ $offset ];
		$o = ord( $c );

		// a–z, A–Z, underscore, or non-ASCII.
		if ( ( $o >= 65 && $o <= 90 ) || ( $o >= 97 && $o <= 122 ) || 95 === $o || $o > 127 ) {
			return true;
		}

		// `-` can start an ident if followed by another `-` (custom property) or
		// another ident-start character.
		if ( '-' === $c ) {
			if ( $offset + 1 >= $this->length ) {
				return false;
			}
			$nc = $this->css[ $offset + 1 ];
			$no = ord( $nc );
			if ( '-' === $nc ) {
				return true; // `--` custom property.
			}
			if ( ( $no >= 65 && $no <= 90 ) || ( $no >= 97 && $no <= 122 ) || 95 === $no || $no > 127 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines whether a single character is a valid ident body character.
	 *
	 * Ident body characters are: a–z, A–Z, 0–9, `-`, `_`, or non-ASCII bytes.
	 *
	 * @since X.X.0
	 *
	 * @param string $c A single byte/character.
	 * @return bool True if $c is a valid ident body character.
	 */
	private function is_ident_char( string $c ): bool {
		$o = ord( $c );
		return ( $o >= 97 && $o <= 122 )  // a–z
			|| ( $o >= 65 && $o <= 90 )   // A–Z
			|| ( $o >= 48 && $o <= 57 )   // 0–9
			|| 45 === $o                  // `-`
			|| 95 === $o                  // `_`
			|| $o > 127;                  // non-ASCII
	}

	/**
	 * Advances $this->at past all ident body characters starting at $this->at.
	 *
	 * @since X.X.0
	 *
	 * @return void
	 */
	private function consume_ident_chars(): void {
		// Note: CSS escape sequences (\XX hex or \<char>) in identifiers are not
		// supported in v1. A backslash is emitted as DELIM_TOKEN. See "Known gaps"
		// in the class docblock.
		while ( $this->at < $this->length && $this->is_ident_char( $this->css[ $this->at ] ) ) {
			$this->at++;
		}
	}

	/**
	 * Determines whether the byte sequence starting at $offset looks like the
	 * start of a CSS number.
	 *
	 * A number start is: a digit, or `+`/`-` followed by a digit or `.` followed
	 * by a digit, or `.` followed by a digit.
	 *
	 * @since X.X.0
	 *
	 * @param int $offset Byte offset into the CSS string.
	 * @return bool True if a number starts at $offset.
	 */
	private function is_number_start( int $offset ): bool {
		if ( $offset >= $this->length ) {
			return false;
		}
		$c = $this->css[ $offset ];
		$o = ord( $c );

		// Digit.
		if ( $o >= 48 && $o <= 57 ) {
			return true;
		}

		// `+` or `-` followed by digit or `.digit`.
		if ( '+' === $c || '-' === $c ) {
			if ( $offset + 1 < $this->length ) {
				$n1 = ord( $this->css[ $offset + 1 ] );
				if ( $n1 >= 48 && $n1 <= 57 ) {
					return true;
				}
				if ( '.' === $this->css[ $offset + 1 ] && $offset + 2 < $this->length ) {
					$n2 = ord( $this->css[ $offset + 2 ] );
					if ( $n2 >= 48 && $n2 <= 57 ) {
						return true;
					}
				}
			}
			return false;
		}

		// `.digit`.
		if ( '.' === $c ) {
			if ( $offset + 1 < $this->length ) {
				$n1 = ord( $this->css[ $offset + 1 ] );
				if ( $n1 >= 48 && $n1 <= 57 ) {
					return true;
				}
			}
			return false;
		}

		return false;
	}

	/**
	 * Advances $this->at past a complete CSS number (integer or decimal, with optional sign).
	 *
	 * @since X.X.0
	 *
	 * @return void
	 */
	private function consume_number(): void {
		// Optional sign.
		if ( $this->at < $this->length && ( '+' === $this->css[ $this->at ] || '-' === $this->css[ $this->at ] ) ) {
			$this->at++;
		}
		// Integer part.
		while ( $this->at < $this->length ) {
			$o = ord( $this->css[ $this->at ] );
			if ( $o >= 48 && $o <= 57 ) {
				$this->at++;
			} else {
				break;
			}
		}
		// Optional decimal part.
		if ( $this->at < $this->length && '.' === $this->css[ $this->at ] ) {
			$this->at++;
			while ( $this->at < $this->length ) {
				$o = ord( $this->css[ $this->at ] );
				if ( $o >= 48 && $o <= 57 ) {
					$this->at++;
				} else {
					break;
				}
			}
		}
		// Optional exponent (e/E followed by optional sign and digits).
		if ( $this->at < $this->length && ( 'e' === $this->css[ $this->at ] || 'E' === $this->css[ $this->at ] ) ) {
			$next_offset = $this->at + 1;
			if ( $next_offset < $this->length && ( '+' === $this->css[ $next_offset ] || '-' === $this->css[ $next_offset ] ) ) {
				$next_offset++;
			}
			if ( $next_offset < $this->length ) {
				$o = ord( $this->css[ $next_offset ] );
				if ( $o >= 48 && $o <= 57 ) {
					$this->at = $next_offset;
					while ( $this->at < $this->length ) {
						$o = ord( $this->css[ $this->at ] );
						if ( $o >= 48 && $o <= 57 ) {
							$this->at++;
						} else {
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * Consumes a `url(…)` sequence after the ident `url` has been consumed and `(`
	 * is the current character.
	 *
	 * If the first non-whitespace character inside the parentheses is `"` or `'`,
	 * this falls through to a FUNCTION_TOKEN so the caller can later encounter a
	 * STRING_TOKEN inside it. Otherwise the unquoted URL body is consumed, emitting
	 * URL_TOKEN on success or BAD_URL_TOKEN on failure.
	 *
	 * @since X.X.0
	 *
	 * @return bool Always true (a token was consumed).
	 */
	private function consume_url_or_function(): bool {
		// Consume the `(`.
		$this->at++;

		// Peek past optional whitespace.
		$peek = $this->at;
		while ( $peek < $this->length ) {
			$pc = $this->css[ $peek ];
			if ( ' ' !== $pc && "\t" !== $pc && "\n" !== $pc && "\r" !== $pc && "\f" !== $pc ) {
				break;
			}
			$peek++;
		}

		// If the next non-whitespace char is a quote, emit FUNCTION_TOKEN.
		if ( $peek < $this->length && ( '"' === $this->css[ $peek ] || "'" === $this->css[ $peek ] ) ) {
			$this->token_type = self::FUNCTION_TOKEN;
			// Use $this->at (not a peek offset) so the FUNCTION_TOKEN spans only 'url(' —
			// whitespace between 'url(' and the quote is not part of this token; it will be
			// emitted as a separate WHITESPACE_TOKEN by the next next_token() call.
			$this->token_length = $this->at - $this->token_start;
			return true;
		}

		// Consume optional leading whitespace inside the url().
		$this->at = $peek;

		// Consume unquoted URL characters.
		$bad = false;
		while ( $this->at < $this->length ) {
			$uc = $this->css[ $this->at ];
			if ( ')' === $uc ) {
				$this->at++;
				break;
			}
			if ( ' ' === $uc || "\t" === $uc || "\n" === $uc || "\r" === $uc || "\f" === $uc ) {
				// Whitespace mid-URL: consume trailing whitespace then expect `)`.
				while ( $this->at < $this->length ) {
					$wc = $this->css[ $this->at ];
					if ( ' ' !== $wc && "\t" !== $wc && "\n" !== $wc && "\r" !== $wc && "\f" !== $wc ) {
						break;
					}
					$this->at++;
				}
				if ( $this->at < $this->length && ')' === $this->css[ $this->at ] ) {
					$this->at++;
				} else {
					$bad = true;
					// Note: Escape sequences in bad-URL recovery are not handled in v1
					// (e.g. \) would incorrectly end the bad-URL token). BAD_URL_TOKEN is
					// always stripped by sanitize(), so the impact is contained.
					// Consume until `)` or EOF.
					while ( $this->at < $this->length && ')' !== $this->css[ $this->at ] ) {
						$this->at++;
					}
					if ( $this->at < $this->length ) {
						$this->at++;
					}
				}
				break;
			}
			if ( '"' === $uc || "'" === $uc || '(' === $uc ) {
				$bad = true;
				// Consume until `)` or EOF.
				while ( $this->at < $this->length && ')' !== $this->css[ $this->at ] ) {
					$this->at++;
				}
				if ( $this->at < $this->length ) {
					$this->at++;
				}
				break;
			}
			$this->at++;
		}

		$this->token_type   = $bad ? self::BAD_URL_TOKEN : self::URL_TOKEN;
		$this->token_length = $this->at - $this->token_start;
		return true;
	}
}
