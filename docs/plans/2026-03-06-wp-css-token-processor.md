# WP_CSS_Token_Processor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build `WP_CSS_Token_Processor` — a streaming CSS tokenizer with `sanitize()` and `validate()` consumers — that replaces `wp_kses()` for CSS block attributes, fixing compounding corruption of `&` and `>` in block custom CSS when saved by users without `unfiltered_html`.

**Architecture:** A single class (`WP_CSS_Token_Processor`) lives in a new `src/wp-includes/css-api/` directory, loaded via `wp-settings.php`. It provides low-level token navigation and two high-level instance methods: `sanitize()` (returns a safe CSS string) and `validate()` (returns `true|WP_Error`). The security policy — at-rule allowlisting, URL protocol filtering, injection guarding — is encoded in those two methods, not in the tokenizer itself.

**Tech Stack:** PHP 7.4+, PHPUnit, WordPress coding standards. No external dependencies. Follows `html-api/` conventions throughout.

**Design doc:** `docs/plans/2026-03-06-wp-css-token-processor-design.md`

**Run tests with:**
```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

---

## Task 1: Scaffold the css-api directory and register with wp-settings.php

**Files:**
- Create: `src/wp-includes/css-api/class-wp-css-token-processor.php`
- Create: `src/wp-includes/css-api/README.md`
- Modify: `src/wp-settings.php` (around line 278, after html-api requires)

**Step 1: Create the directory and stub class file**

```php
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

	// Token type constants.
	const IDENT_TOKEN       = 'ident-token';
	const FUNCTION_TOKEN    = 'function-token';
	const AT_KEYWORD_TOKEN  = 'at-keyword-token';
	const HASH_TOKEN        = 'hash-token';
	const STRING_TOKEN      = 'string-token';
	const BAD_STRING_TOKEN  = 'bad-string-token';
	const URL_TOKEN         = 'url-token';
	const BAD_URL_TOKEN     = 'bad-url-token';
	const DELIM_TOKEN       = 'delim-token';
	const NUMBER_TOKEN      = 'number-token';
	const PERCENTAGE_TOKEN  = 'percentage-token';
	const DIMENSION_TOKEN   = 'dimension-token';
	const WHITESPACE_TOKEN  = 'whitespace-token';
	const CDO_TOKEN         = 'CDO-token';
	const CDC_TOKEN         = 'CDC-token';
	const COLON_TOKEN       = 'colon-token';
	const SEMICOLON_TOKEN   = 'semicolon-token';
	const COMMA_TOKEN       = 'comma-token';
	const OPEN_SQUARE_TOKEN  = '[-token';
	const CLOSE_SQUARE_TOKEN = ']-token';
	const OPEN_PAREN_TOKEN   = '(-token';
	const CLOSE_PAREN_TOKEN  = ')-token';
	const OPEN_CURLY_TOKEN   = '{-token';
	const CLOSE_CURLY_TOKEN  = '}-token';
	const EOF_TOKEN          = 'EOF-token';

	/**
	 * The original CSS input string.
	 *
	 * @since X.X.0
	 * @var string
	 */
	private $css = '';

	/**
	 * Placeholder — full implementation in subsequent tasks.
	 *
	 * @since X.X.0
	 *
	 * @param string $css The CSS string to process.
	 */
	public function __construct( string $css ) {
		$this->css = $css;
	}
}
```

**Step 2: Create README.md**

```markdown
# CSS API

The CSS API provides tools for safely processing CSS strings in WordPress.

## WP_CSS_Token_Processor

A streaming, forward-only CSS tokenizer. Spec-inspired (CSS Syntax Level 3),
safety-first: unknown or unsupported constructs are stripped rather than
passed through silently.

### Primary use cases

**Sanitize block-level custom CSS for storage:**

    $processor = new WP_CSS_Token_Processor( $css );
    $safe_css  = $processor->sanitize();

**Validate CSS in REST API endpoints:**

    $processor = new WP_CSS_Token_Processor( $css );
    $result    = $processor->validate(); // true or WP_Error

### Security policy

- `</style` in input: `sanitize()` returns `''`; `validate()` returns `WP_Error( 'css_injection' )`
- `url()` with `javascript:` or `data:`: token stripped
- `@import`, `@charset`, `@namespace`: rule stripped
- Unknown at-rules: stripped (safety-first)
- `bad-url-token`, `bad-string-token`: stripped
- HTML comment tokens (`<!--`, `-->`): stripped
- Null bytes: stripped in preprocessing

### Known gaps (v1)

- Unicode range tokens (`U+`) are not supported.
- Surrogate pair edge cases beyond basic UTF-8 are not handled.

### Spec reference

CSS Syntax Level 3: https://www.w3.org/TR/css-syntax-3/
```

**Step 3: Register in wp-settings.php**

After line 278 (the last `html-api` require), add:

```php
require ABSPATH . WPINC . '/css-api/class-wp-css-token-processor.php';
```

**Step 4: Verify the class loads**

```bash
php -r "require 'src/wp-load.php'; echo class_exists('WP_CSS_Token_Processor') ? 'OK' : 'FAIL';"
```

Expected: `OK`

**Step 5: Commit**

```bash
git add src/wp-includes/css-api/ src/wp-settings.php
git commit -m "CSS API: Scaffold WP_CSS_Token_Processor class and css-api directory"
```

---

## Task 2: Implement the tokenizer core — `next_token()`, `get_token_type()`, `get_token_value()`

This is the heart of the class. Work token type by token type, test-first.

**Files:**
- Modify: `src/wp-includes/css-api/class-wp-css-token-processor.php`
- Create: `tests/phpunit/tests/css-api/WpCssTokenProcessorTest.php`

**Step 1: Create the test file scaffold**

```php
<?php
/**
 * Unit tests covering WP_CSS_Token_Processor tokenization.
 *
 * @package WordPress
 * @subpackage CSS-API
 *
 * @since X.X.0
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Token_Processor
 */
class Tests_CssApi_WpCssTokenProcessor extends WP_UnitTestCase {
}
```

**Step 2: Run the empty test class to confirm the group loads**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

Expected: `No tests executed!` (0 tests, no errors)

**Step 3: Add internal state properties to the class**

Add these private properties inside `WP_CSS_Token_Processor`, after the `$css` property:

```php
/**
 * Current byte offset in the CSS string.
 *
 * @since X.X.0
 * @var int
 */
private $at = 0;

/**
 * Byte offset where the current token starts.
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
 * Type of the current token.
 *
 * @since X.X.0
 * @var string|null
 */
private $token_type = null;

/**
 * Length of the CSS input string (cached).
 *
 * @since X.X.0
 * @var int
 */
private $length = 0;

/**
 * Current { } nesting depth.
 *
 * @since X.X.0
 * @var int
 */
private $block_depth = 0;
```

Also update `__construct()` to strip null bytes and cache length:

```php
public function __construct( string $css ) {
	// Strip null bytes before any processing — these have no valid use in CSS
	// and are a common vector for bypassing text filters.
	$this->css    = str_replace( "\0", '', $css );
	$this->length = strlen( $this->css );
}
```

**Step 4: Write failing tests for whitespace and EOF tokens**

```php
/**
 * @covers ::next_token
 * @covers ::get_token_type
 */
public function test_eof_on_empty_input() {
	$p = new WP_CSS_Token_Processor( '' );
	$this->assertFalse( $p->next_token() );
}

public function test_whitespace_token() {
	$p = new WP_CSS_Token_Processor( '   ' );
	$this->assertTrue( $p->next_token() );
	$this->assertSame( WP_CSS_Token_Processor::WHITESPACE_TOKEN, $p->get_token_type() );
	$this->assertSame( '   ', $p->get_token_value() );
	$this->assertFalse( $p->next_token() );
}
```

**Step 5: Run — expect FAIL**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

Expected: FAIL — methods `next_token`, `get_token_type`, `get_token_value` do not exist.

**Step 6: Implement `next_token()`, `get_token_type()`, `get_token_value()` with whitespace support**

```php
/**
 * Advances the processor to the next token.
 *
 * Returns true if a token was found, false at end of input.
 *
 * @since X.X.0
 *
 * @return bool Whether a token was found.
 */
public function next_token(): bool {
	if ( $this->at >= $this->length ) {
		$this->token_type = self::EOF_TOKEN;
		return false;
	}

	$this->token_start = $this->at;
	$c = $this->css[ $this->at ];

	// Whitespace: space, tab, newline, carriage return, form feed.
	if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c || "\f" === $c ) {
		$this->at += strspn( $this->css, " \t\n\r\f", $this->at );
		$this->token_length = $this->at - $this->token_start;
		$this->token_type   = self::WHITESPACE_TOKEN;
		return true;
	}

	// More token types added in subsequent steps.
	// Unknown character: advance one byte to avoid infinite loop.
	++$this->at;
	$this->token_length = 1;
	$this->token_type   = self::DELIM_TOKEN;
	return true;
}

/**
 * Returns the type of the current token.
 *
 * @since X.X.0
 *
 * @return string|null Token type constant, or null if next_token() has not been called.
 */
public function get_token_type(): ?string {
	return $this->token_type;
}

/**
 * Returns the raw value of the current token as it appears in the input.
 *
 * @since X.X.0
 *
 * @return string|null Raw token value, or null if next_token() has not been called.
 */
public function get_token_value(): ?string {
	if ( null === $this->token_start ) {
		return null;
	}
	return substr( $this->css, $this->token_start, $this->token_length );
}
```

**Step 7: Run — expect PASS for whitespace and EOF tests**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

Expected: PASS (2 tests)

**Step 8: Add tests and implement the following token types one at a time, committing after each group passes:**

For each group below: write the test, run to see it fail, implement the token, run to see it pass.

**Group A — Single-character punctuation tokens:**

Tests:
```php
public function test_colon_token() {
	$p = new WP_CSS_Token_Processor( ':' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::COLON_TOKEN, $p->get_token_type() );
}

public function test_semicolon_token() {
	$p = new WP_CSS_Token_Processor( ';' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::SEMICOLON_TOKEN, $p->get_token_type() );
}

public function test_comma_token() {
	$p = new WP_CSS_Token_Processor( ',' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::COMMA_TOKEN, $p->get_token_type() );
}

public function test_open_curly_token() {
	$p = new WP_CSS_Token_Processor( '{' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::OPEN_CURLY_TOKEN, $p->get_token_type() );
}

public function test_close_curly_token() {
	$p = new WP_CSS_Token_Processor( '}' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::CLOSE_CURLY_TOKEN, $p->get_token_type() );
}

public function test_open_paren_token() {
	$p = new WP_CSS_Token_Processor( '(' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::OPEN_PAREN_TOKEN, $p->get_token_type() );
}

public function test_close_paren_token() {
	$p = new WP_CSS_Token_Processor( ')' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::CLOSE_PAREN_TOKEN, $p->get_token_type() );
}

public function test_open_square_token() {
	$p = new WP_CSS_Token_Processor( '[' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::OPEN_SQUARE_TOKEN, $p->get_token_type() );
}

public function test_close_square_token() {
	$p = new WP_CSS_Token_Processor( ']' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::CLOSE_SQUARE_TOKEN, $p->get_token_type() );
}
```

Implementation — add to `next_token()` before the "unknown character" fallback:
```php
if ( ':' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::COLON_TOKEN; return true; }
if ( ';' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::SEMICOLON_TOKEN; return true; }
if ( ',' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::COMMA_TOKEN; return true; }
if ( '{' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::OPEN_CURLY_TOKEN; ++$this->block_depth; return true; }
if ( '}' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::CLOSE_CURLY_TOKEN; if ( $this->block_depth > 0 ) { --$this->block_depth; } return true; }
if ( '(' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::OPEN_PAREN_TOKEN; return true; }
if ( ')' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::CLOSE_PAREN_TOKEN; return true; }
if ( '[' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::OPEN_SQUARE_TOKEN; return true; }
if ( ']' === $c ) { ++$this->at; $this->token_length = 1; $this->token_type = self::CLOSE_SQUARE_TOKEN; return true; }
```

**Group B — `ident-token` (identifiers: property names, keywords, selector parts)**

CSS ident starts with `[a-zA-Z_-]` or `\` escape or non-ASCII. For v1, handle ASCII identifiers:

Tests:
```php
public function test_ident_token_simple() {
	$p = new WP_CSS_Token_Processor( 'color' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
	$this->assertSame( 'color', $p->get_token_value() );
}

public function test_ident_token_with_hyphen() {
	$p = new WP_CSS_Token_Processor( 'background-color' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
	$this->assertSame( 'background-color', $p->get_token_value() );
}

public function test_ident_token_custom_property() {
	$p = new WP_CSS_Token_Processor( '--my-var' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::IDENT_TOKEN, $p->get_token_type() );
	$this->assertSame( '--my-var', $p->get_token_value() );
}
```

Implementation helper + token detection:
```php
/**
 * Whether the character at a given offset can start an identifier.
 *
 * An identifier start character is a-z, A-Z, underscore, hyphen (when followed
 * by another identifier char or another hyphen), or any non-ASCII character.
 *
 * @since X.X.0
 *
 * @param int $offset Byte offset in the CSS string.
 * @return bool
 */
private function is_ident_start( int $offset ): bool {
	if ( $offset >= $this->length ) {
		return false;
	}
	$c = $this->css[ $offset ];
	// Non-ASCII — treat as identifier character.
	if ( ord( $c ) > 127 ) {
		return true;
	}
	if ( ctype_alpha( $c ) || '_' === $c ) {
		return true;
	}
	// Hyphen is valid if followed by another ident-start or another hyphen.
	if ( '-' === $c ) {
		$next = $offset + 1 < $this->length ? $this->css[ $offset + 1 ] : '';
		return '-' === $next || ctype_alpha( $next ) || '_' === $next || ( '' !== $next && ord( $next ) > 127 );
	}
	return false;
}

/**
 * Consumes identifier characters from the current offset.
 *
 * Identifier characters are: a-z, A-Z, 0-9, hyphen, underscore, non-ASCII.
 *
 * @since X.X.0
 */
private function consume_ident_chars(): void {
	while ( $this->at < $this->length ) {
		$c = $this->css[ $this->at ];
		if ( ctype_alnum( $c ) || '-' === $c || '_' === $c || ord( $c ) > 127 ) {
			++$this->at;
		} else {
			break;
		}
	}
}
```

Add to `next_token()` before the fallback:
```php
if ( $this->is_ident_start( $this->at ) ) {
	$this->consume_ident_chars();
	// If immediately followed by '(', it's a function token.
	if ( $this->at < $this->length && '(' === $this->css[ $this->at ] ) {
		++$this->at;
		$this->token_length = $this->at - $this->token_start;
		$this->token_type   = self::FUNCTION_TOKEN;
		return true;
	}
	$this->token_length = $this->at - $this->token_start;
	$this->token_type   = self::IDENT_TOKEN;
	return true;
}
```

**Group C — `at-keyword-token`**

Tests:
```php
public function test_at_keyword_token_media() {
	$p = new WP_CSS_Token_Processor( '@media' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::AT_KEYWORD_TOKEN, $p->get_token_type() );
	$this->assertSame( '@media', $p->get_token_value() );
}

public function test_at_keyword_token_import() {
	$p = new WP_CSS_Token_Processor( '@import' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::AT_KEYWORD_TOKEN, $p->get_token_type() );
	$this->assertSame( '@import', $p->get_token_value() );
}
```

Implementation — add to `next_token()`:
```php
if ( '@' === $c && $this->is_ident_start( $this->at + 1 ) ) {
	++$this->at; // consume '@'
	$this->consume_ident_chars();
	$this->token_length = $this->at - $this->token_start;
	$this->token_type   = self::AT_KEYWORD_TOKEN;
	return true;
}
```

**Group D — `hash-token`**

Tests:
```php
public function test_hash_token_color() {
	$p = new WP_CSS_Token_Processor( '#ff0000' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::HASH_TOKEN, $p->get_token_type() );
	$this->assertSame( '#ff0000', $p->get_token_value() );
}
```

Implementation:
```php
if ( '#' === $c ) {
	++$this->at; // consume '#'
	$this->consume_ident_chars();
	$this->token_length = $this->at - $this->token_start;
	$this->token_type   = self::HASH_TOKEN;
	return true;
}
```

**Group E — numeric tokens: `number-token`, `dimension-token`, `percentage-token`**

Tests:
```php
public function test_number_token_integer() {
	$p = new WP_CSS_Token_Processor( '42' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::NUMBER_TOKEN, $p->get_token_type() );
	$this->assertSame( '42', $p->get_token_value() );
}

public function test_dimension_token() {
	$p = new WP_CSS_Token_Processor( '16px' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::DIMENSION_TOKEN, $p->get_token_type() );
	$this->assertSame( '16px', $p->get_token_value() );
}

public function test_percentage_token() {
	$p = new WP_CSS_Token_Processor( '50%' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::PERCENTAGE_TOKEN, $p->get_token_type() );
	$this->assertSame( '50%', $p->get_token_value() );
}

public function test_dimension_token_rem() {
	$p = new WP_CSS_Token_Processor( '1.5rem' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::DIMENSION_TOKEN, $p->get_token_type() );
	$this->assertSame( '1.5rem', $p->get_token_value() );
}
```

Implementation helper:
```php
/**
 * Whether the character at a given offset starts a number.
 *
 * @since X.X.0
 *
 * @param int $offset Byte offset.
 * @return bool
 */
private function is_number_start( int $offset ): bool {
	if ( $offset >= $this->length ) {
		return false;
	}
	$c = $this->css[ $offset ];
	if ( ctype_digit( $c ) ) {
		return true;
	}
	// +/- followed by digit or decimal point.
	if ( ( '+' === $c || '-' === $c ) && $offset + 1 < $this->length ) {
		$next = $this->css[ $offset + 1 ];
		return ctype_digit( $next ) || ( '.' === $next && $offset + 2 < $this->length && ctype_digit( $this->css[ $offset + 2 ] ) );
	}
	// Decimal point followed by digit.
	if ( '.' === $c && $offset + 1 < $this->length ) {
		return ctype_digit( $this->css[ $offset + 1 ] );
	}
	return false;
}

/**
 * Consumes numeric characters (digits and at most one decimal point).
 *
 * @since X.X.0
 */
private function consume_number(): void {
	// Optional sign.
	if ( $this->at < $this->length && ( '+' === $this->css[ $this->at ] || '-' === $this->css[ $this->at ] ) ) {
		++$this->at;
	}
	// Integer part.
	while ( $this->at < $this->length && ctype_digit( $this->css[ $this->at ] ) ) {
		++$this->at;
	}
	// Optional decimal part.
	if ( $this->at < $this->length && '.' === $this->css[ $this->at ] && $this->at + 1 < $this->length && ctype_digit( $this->css[ $this->at + 1 ] ) ) {
		$this->at += 2; // consume '.' and first decimal digit
		while ( $this->at < $this->length && ctype_digit( $this->css[ $this->at ] ) ) {
			++$this->at;
		}
	}
}
```

Add to `next_token()`:
```php
if ( $this->is_number_start( $this->at ) ) {
	$this->consume_number();
	if ( $this->at < $this->length && '%' === $this->css[ $this->at ] ) {
		++$this->at;
		$this->token_length = $this->at - $this->token_start;
		$this->token_type   = self::PERCENTAGE_TOKEN;
		return true;
	}
	if ( $this->is_ident_start( $this->at ) ) {
		$this->consume_ident_chars();
		$this->token_length = $this->at - $this->token_start;
		$this->token_type   = self::DIMENSION_TOKEN;
		return true;
	}
	$this->token_length = $this->at - $this->token_start;
	$this->token_type   = self::NUMBER_TOKEN;
	return true;
}
```

**Group F — `string-token` and `bad-string-token`**

Tests:
```php
public function test_string_token_double_quoted() {
	$p = new WP_CSS_Token_Processor( '"hello world"' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::STRING_TOKEN, $p->get_token_type() );
	$this->assertSame( '"hello world"', $p->get_token_value() );
}

public function test_string_token_single_quoted() {
	$p = new WP_CSS_Token_Processor( "'hello'" );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::STRING_TOKEN, $p->get_token_type() );
}

public function test_bad_string_token_unterminated() {
	// A newline inside a string (without escape) terminates it as bad-string.
	$p = new WP_CSS_Token_Processor( "\"hello\nworld\"" );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::BAD_STRING_TOKEN, $p->get_token_type() );
}
```

Implementation — add to `next_token()`:
```php
if ( '"' === $c || "'" === $c ) {
	$quote = $c;
	++$this->at;
	$is_bad = false;
	while ( $this->at < $this->length ) {
		$sc = $this->css[ $this->at ];
		if ( $sc === $quote ) {
			++$this->at; // consume closing quote
			break;
		}
		if ( "\n" === $sc || "\r" === $sc || "\f" === $sc ) {
			// Unescaped newline in a string — bad-string-token.
			$is_bad = true;
			break;
		}
		if ( '\\' === $sc ) {
			// Escape sequence — consume both backslash and next char.
			$this->at += 2;
			continue;
		}
		++$this->at;
	}
	$this->token_length = $this->at - $this->token_start;
	$this->token_type   = $is_bad ? self::BAD_STRING_TOKEN : self::STRING_TOKEN;
	return true;
}
```

**Group G — `url-token` and `bad-url-token`**

Note: `url(` is consumed as a `function-token` by the ident logic. But the CSS spec handles `url()` specially — no quotes around the URL value. We need to detect `url(` and consume it as a `url-token`.

Adjust the function-token detection in the ident branch:
```php
// After consuming ident chars, check for function:
if ( $this->at < $this->length && '(' === $this->css[ $this->at ] ) {
	$ident_value = strtolower( substr( $this->css, $this->token_start, $this->at - $this->token_start ) );
	++$this->at; // consume '('

	if ( 'url' === $ident_value ) {
		// Consume optional whitespace.
		while ( $this->at < $this->length && in_array( $this->css[ $this->at ], array( ' ', "\t", "\n", "\r", "\f" ), true ) ) {
			++$this->at;
		}
		// If next char is a quote, fall through to function-token — url("...") handled as function.
		if ( $this->at < $this->length && ( '"' === $this->css[ $this->at ] || "'" === $this->css[ $this->at ] ) ) {
			$this->token_length = $this->at - $this->token_start;
			$this->token_type   = self::FUNCTION_TOKEN;
			return true;
		}
		// Consume unquoted URL value.
		$is_bad = false;
		while ( $this->at < $this->length ) {
			$uc = $this->css[ $this->at ];
			if ( ')' === $uc ) {
				++$this->at;
				break;
			}
			if ( ' ' === $uc || "\t" === $uc || "\n" === $uc || "\r" === $uc || "\f" === $uc ) {
				// Whitespace inside unquoted URL — skip, then expect ')'.
				while ( $this->at < $this->length && in_array( $this->css[ $this->at ], array( ' ', "\t", "\n", "\r", "\f" ), true ) ) {
					++$this->at;
				}
				if ( $this->at < $this->length && ')' === $this->css[ $this->at ] ) {
					++$this->at;
				} else {
					$is_bad = true;
				}
				break;
			}
			if ( '"' === $uc || "'" === $uc || '(' === $uc ) {
				// Invalid characters in unquoted URL.
				$is_bad = true;
				break;
			}
			++$this->at;
		}
		$this->token_length = $this->at - $this->token_start;
		$this->token_type   = $is_bad ? self::BAD_URL_TOKEN : self::URL_TOKEN;
		return true;
	}

	$this->token_length = $this->at - $this->token_start;
	$this->token_type   = self::FUNCTION_TOKEN;
	return true;
}
```

Tests:
```php
public function test_url_token_unquoted() {
	$p = new WP_CSS_Token_Processor( 'url(foo.png)' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::URL_TOKEN, $p->get_token_type() );
	$this->assertSame( 'url(foo.png)', $p->get_token_value() );
}

public function test_url_token_with_quotes_is_function() {
	// url("foo.png") is a function-token wrapping a string-token per the CSS spec.
	$p = new WP_CSS_Token_Processor( 'url("foo.png")' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::FUNCTION_TOKEN, $p->get_token_type() );
}

public function test_bad_url_token() {
	$p = new WP_CSS_Token_Processor( "url(foo bar)" );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::BAD_URL_TOKEN, $p->get_token_type() );
}
```

**Group H — `CDO-token` and `CDC-token`**

Tests:
```php
public function test_cdo_token() {
	$p = new WP_CSS_Token_Processor( '<!--' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::CDO_TOKEN, $p->get_token_type() );
}

public function test_cdc_token() {
	$p = new WP_CSS_Token_Processor( '-->' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::CDC_TOKEN, $p->get_token_type() );
}
```

Implementation — add to `next_token()` before other checks:
```php
// CDO: <!--
if ( '<' === $c && $this->at + 3 < $this->length && '!--' === substr( $this->css, $this->at + 1, 3 ) ) {
	$this->at += 4;
	$this->token_length = 4;
	$this->token_type   = self::CDO_TOKEN;
	return true;
}
// CDC: -->
if ( '-' === $c && $this->at + 2 < $this->length && '->' === substr( $this->css, $this->at + 1, 2 ) ) {
	$this->at += 3;
	$this->token_length = 3;
	$this->token_type   = self::CDC_TOKEN;
	return true;
}
```

**Group I — `delim-token` (everything else: `&`, `>`, `+`, `~`, `*`, `!`, `.`, `/`, `<`, `^`, `|` etc.)**

The existing fallback already handles this — single character consumed as `DELIM_TOKEN`. Add a test:
```php
public function test_delim_token_ampersand() {
	$p = new WP_CSS_Token_Processor( '&' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
	$this->assertSame( '&', $p->get_token_value() );
}

public function test_delim_token_child_combinator() {
	$p = new WP_CSS_Token_Processor( '>' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::DELIM_TOKEN, $p->get_token_type() );
	$this->assertSame( '>', $p->get_token_value() );
}
```

**Step 9: Run all tokenizer tests**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

Expected: All pass.

**Step 10: Add `get_block_depth()` and its test**

```php
/**
 * Returns the current block nesting depth.
 *
 * Block depth increases on `{` and decreases on `}`, never below 0.
 *
 * @since X.X.0
 *
 * @return int Current nesting depth.
 */
public function get_block_depth(): int {
	return $this->block_depth;
}
```

Test:
```php
public function test_block_depth_tracking() {
	$p = new WP_CSS_Token_Processor( '.a { .b { color: red; } }' );
	$this->assertSame( 0, $p->get_block_depth() );
	while ( $p->next_token() ) {
		if ( WP_CSS_Token_Processor::OPEN_CURLY_TOKEN === $p->get_token_type() ) {
			break;
		}
	}
	$this->assertSame( 1, $p->get_block_depth() );
}
```

**Step 11: Run all tests and commit**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

Expected: All pass.

```bash
git add src/wp-includes/css-api/class-wp-css-token-processor.php tests/phpunit/tests/css-api/WpCssTokenProcessorTest.php
git commit -m "CSS API: Implement WP_CSS_Token_Processor tokenizer core with tests"
```

---

## Task 3: Implement `get_updated_css()`, `remove_token()`, `set_token_value()`

**Files:**
- Modify: `src/wp-includes/css-api/class-wp-css-token-processor.php`
- Modify: `tests/phpunit/tests/css-api/WpCssTokenProcessorTest.php`

**Step 1: Write failing tests**

```php
public function test_get_updated_css_unchanged_when_no_modifications() {
	$css = 'color: red;';
	$p   = new WP_CSS_Token_Processor( $css );
	while ( $p->next_token() ) {}
	$this->assertSame( $css, $p->get_updated_css() );
}

public function test_remove_token_removes_it_from_output() {
	$p = new WP_CSS_Token_Processor( 'color: red;' );
	while ( $p->next_token() ) {
		if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'red' === $p->get_token_value() ) {
			$p->remove_token();
		}
	}
	$this->assertSame( 'color: ;', $p->get_updated_css() );
}

public function test_set_token_value_replaces_value_in_output() {
	$p = new WP_CSS_Token_Processor( 'color: red;' );
	while ( $p->next_token() ) {
		if ( WP_CSS_Token_Processor::IDENT_TOKEN === $p->get_token_type() && 'red' === $p->get_token_value() ) {
			$p->set_token_value( 'blue' );
		}
	}
	$this->assertSame( 'color: blue;', $p->get_updated_css() );
}
```

**Step 2: Run — expect FAIL**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

**Step 3: Add a replacements log property and implement the three methods**

Add property:
```php
/**
 * Pending replacements to apply on get_updated_css().
 *
 * Each entry: [ 'start' => int, 'length' => int, 'replacement' => string ]
 *
 * @since X.X.0
 * @var array
 */
private $replacements = array();
```

Implement methods:
```php
/**
 * Removes the current token from the CSS output.
 *
 * Has no effect if next_token() has not been called.
 *
 * @since X.X.0
 *
 * @return bool Whether the removal was recorded.
 */
public function remove_token(): bool {
	if ( null === $this->token_start ) {
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
 * Replaces the current token's value in the CSS output.
 *
 * For most token types this replaces the entire raw token text.
 * Has no effect if next_token() has not been called.
 *
 * @since X.X.0
 *
 * @param string $value Replacement text.
 * @return bool Whether the replacement was recorded.
 */
public function set_token_value( string $value ): bool {
	if ( null === $this->token_start ) {
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
 * Modifications are applied in reverse byte order so that earlier
 * offsets remain valid as later replacements are made.
 *
 * @since X.X.0
 *
 * @return string The modified CSS string.
 */
public function get_updated_css(): string {
	if ( empty( $this->replacements ) ) {
		return $this->css;
	}

	// Sort by start offset descending so we apply from end to start,
	// keeping earlier offsets valid.
	$sorted = $this->replacements;
	usort( $sorted, static function ( $a, $b ) {
		return $b['start'] - $a['start'];
	} );

	$output = $this->css;
	foreach ( $sorted as $replacement ) {
		$output = substr_replace( $output, $replacement['replacement'], $replacement['start'], $replacement['length'] );
	}
	return $output;
}
```

**Step 4: Run — expect PASS**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

**Step 5: Commit**

```bash
git add src/wp-includes/css-api/class-wp-css-token-processor.php tests/phpunit/tests/css-api/WpCssTokenProcessorTest.php
git commit -m "CSS API: Add get_updated_css(), remove_token(), set_token_value() with tests"
```

---

## Task 4: Implement `sanitize()`

**Files:**
- Modify: `src/wp-includes/css-api/class-wp-css-token-processor.php`
- Create: `tests/phpunit/tests/css-api/WpCssTokenSanitizeTest.php`

**Step 1: Create test file scaffold**

```php
<?php
/**
 * Unit tests covering WP_CSS_Token_Processor::sanitize().
 *
 * @package WordPress
 * @subpackage CSS-API
 *
 * @since X.X.0
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Token_Processor
 */
class Tests_CssApi_WpCssTokenSanitize extends WP_UnitTestCase {

	/**
	 * Helper: run sanitize() on a CSS string and return the result.
	 *
	 * @param string $css
	 * @return string
	 */
	private function sanitize( string $css ): string {
		return ( new WP_CSS_Token_Processor( $css ) )->sanitize();
	}
}
```

**Step 2: Write all failing tests**

```php
// --- Injection guard ---

public function test_style_close_tag_returns_empty_string() {
	$this->assertSame( '', $this->sanitize( 'color: red; </style> .evil {}' ) );
}

public function test_partial_style_close_tag_returns_empty_string() {
	$this->assertSame( '', $this->sanitize( 'color: red; </style' ) );
}

// --- Null bytes ---

public function test_null_bytes_are_stripped() {
	$this->assertSame( 'color: red;', $this->sanitize( "color\0: red;" ) );
}

// --- CSS nesting selectors survive (the PR #11104 regression cases) ---

public function test_css_nesting_ampersand_survives() {
	$css = 'color: blue; & p { color: red; }';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_child_combinator_survives() {
	$css = '& > p { margin: 0; }';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_adjacent_sibling_combinator_survives() {
	$css = '& + span { color: green; }';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

// --- CDO/CDC stripped ---

public function test_cdo_token_stripped() {
	$this->assertSame( 'color: red;', $this->sanitize( '<!--color: red;' ) );
}

public function test_cdc_token_stripped() {
	$this->assertSame( 'color: red;', $this->sanitize( '-->color: red;' ) );
}

// --- bad-string-token stripped ---

public function test_bad_string_token_stripped() {
	// A string containing an unescaped newline is a bad-string-token.
	$this->assertSame( 'content: ;', $this->sanitize( "content: \"bad\nstring\";" ) );
}

// --- bad-url-token stripped ---

public function test_bad_url_token_stripped() {
	$this->assertSame( 'background-image: ;', $this->sanitize( 'background-image: url(bad url);' ) );
}

// --- URL protocol filtering ---

public function test_url_with_javascript_protocol_stripped() {
	$this->assertSame( 'background: ;', $this->sanitize( 'background: url(javascript:alert(1));' ) );
}

public function test_url_with_data_protocol_stripped() {
	$this->assertSame( 'background: ;', $this->sanitize( 'background: url(data:image/png;base64,abc);' ) );
}

public function test_url_with_https_survives() {
	$css = 'background: url(https://example.com/image.png);';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_url_with_relative_path_survives() {
	$css = 'background: url(image.png);';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

// --- At-rule allowlist ---

public function test_allowed_at_rule_media_survives() {
	$css = '@media (max-width: 768px) { color: red; }';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_allowed_at_rule_supports_survives() {
	$css = '@supports (display: grid) { color: red; }';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_blocked_at_rule_import_stripped() {
	$result = $this->sanitize( "@import url('https://evil.com/style.css'); color: red;" );
	$this->assertStringNotContainsString( '@import', $result );
	$this->assertStringContainsString( 'color: red;', $result );
}

public function test_blocked_at_rule_charset_stripped() {
	$result = $this->sanitize( '@charset "UTF-8"; color: red;' );
	$this->assertStringNotContainsString( '@charset', $result );
}

public function test_unknown_at_rule_stripped() {
	$result = $this->sanitize( '@unknown-future-rule { color: red; } .a { color: blue; }' );
	$this->assertStringNotContainsString( '@unknown-future-rule', $result );
	$this->assertStringContainsString( 'color: blue;', $result );
}

// --- Idempotency ---

/**
 * @dataProvider data_idempotency_fixtures
 */
public function test_sanitize_is_idempotent( string $css ) {
	$once  = $this->sanitize( $css );
	$twice = $this->sanitize( $once );
	$this->assertSame( $once, $twice, 'sanitize() must be idempotent' );
}

public function data_idempotency_fixtures(): array {
	return array(
		'simple declaration'        => array( 'color: red;' ),
		'nesting ampersand'         => array( 'color: blue; & p { color: red; }' ),
		'child combinator'          => array( '& > p { margin: 0; }' ),
		'media query'               => array( '@media (max-width: 768px) { color: red; }' ),
		'custom property'           => array( '--my-color: #ff0000;' ),
		'multiple declarations'     => array( 'color: red; font-size: 16px; margin: 0;' ),
		'var() usage'               => array( 'color: var(--my-color);' ),
		'already sanitized import'  => array( 'color: blue;' ),
	);
}

// --- get_removed_tokens() ---

public function test_get_removed_tokens_empty_when_nothing_stripped() {
	$p = new WP_CSS_Token_Processor( 'color: red;' );
	$p->sanitize();
	$this->assertEmpty( $p->get_removed_tokens() );
}

public function test_get_removed_tokens_populated_after_strip() {
	$p = new WP_CSS_Token_Processor( 'background: url(javascript:alert(1));' );
	$p->sanitize();
	$removed = $p->get_removed_tokens();
	$this->assertNotEmpty( $removed );
	$this->assertArrayHasKey( 'token', $removed[0] );
	$this->assertArrayHasKey( 'reason', $removed[0] );
}
```

**Step 3: Run — expect FAIL**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

**Step 4: Implement `sanitize()` and `get_removed_tokens()`**

Add property:
```php
/**
 * Log of tokens removed during sanitize().
 *
 * Each entry: [ 'token' => string, 'reason' => string ]
 *
 * @since X.X.0
 * @var array
 */
private $removed_tokens = array();
```

Add constants for the allowed at-rule list:
```php
/**
 * At-rule keywords that are allowed in block custom CSS.
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
```

Implement the methods:
```php
/**
 * Returns the list of tokens removed during the last sanitize() call.
 *
 * Each entry contains:
 *   - 'token'  string  The raw token text that was removed.
 *   - 'reason' string  A short description of why it was removed.
 *
 * @since X.X.0
 *
 * @return array Array of removal log entries.
 */
public function get_removed_tokens(): array {
	return $this->removed_tokens;
}

/**
 * Sanitizes the CSS string, stripping unsafe tokens and rules.
 *
 * Applies the following security policy:
 * - Returns '' immediately if `</style` is detected (injection guard).
 * - Strips null bytes (handled in __construct).
 * - Strips bad-string-token and bad-url-token.
 * - Strips CDO (<!--) and CDC (-->) tokens.
 * - Strips url() tokens with javascript: or data: protocols.
 * - Strips url() tokens with any protocol not in wp_allowed_protocols().
 * - Strips @import, @charset, @namespace, and unknown at-rules (with their blocks).
 * - Strip granularity: bad token → remove token; bad at-rule → remove entire rule.
 *
 * Idempotency guarantee: sanitize( sanitize( $css ) ) === sanitize( $css ).
 *
 * @since X.X.0
 *
 * @return string The sanitized CSS string.
 */
public function sanitize(): string {
	// Injection guard — if </style appears anywhere, refuse the whole value.
	// This mirrors the check in WP_REST_Global_Styles_Controller::validate_custom_css().
	if ( false !== stripos( $this->css, '</style' ) ) {
		return '';
	}

	$this->removed_tokens = array();
	$this->replacements   = array();
	$this->reset();

	$allowed_protocols = wp_allowed_protocols();

	while ( $this->next_token() ) {
		$type  = $this->get_token_type();
		$value = $this->get_token_value();

		// Strip HTML comment tokens — these have no valid use in CSS.
		if ( self::CDO_TOKEN === $type || self::CDC_TOKEN === $type ) {
			$this->removed_tokens[] = array( 'token' => $value, 'reason' => 'html_comment' );
			$this->remove_token();
			continue;
		}

		// Strip malformed tokens.
		if ( self::BAD_STRING_TOKEN === $type ) {
			$this->removed_tokens[] = array( 'token' => $value, 'reason' => 'bad_string' );
			$this->remove_token();
			continue;
		}
		if ( self::BAD_URL_TOKEN === $type ) {
			$this->removed_tokens[] = array( 'token' => $value, 'reason' => 'bad_url' );
			$this->remove_token();
			continue;
		}

		// URL protocol filtering.
		if ( self::URL_TOKEN === $type ) {
			// Extract the URL from url(...).
			$url = preg_replace( '/^url\(\s*["\']?|["\']?\s*\)$/i', '', $value );
			$url = trim( $url );

			$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
			if ( 'javascript' === $scheme || 'data' === $scheme ) {
				// Always strip javascript: and data: — high risk, no legitimate use in block CSS.
				$this->removed_tokens[] = array( 'token' => $value, 'reason' => 'unsafe_url_protocol' );
				$this->remove_token();
				continue;
			}
			if ( '' !== $scheme && ! in_array( $scheme, $allowed_protocols, true ) ) {
				$this->removed_tokens[] = array( 'token' => $value, 'reason' => 'disallowed_url_protocol' );
				$this->remove_token();
				continue;
			}
		}

		// At-rule allowlist enforcement.
		if ( self::AT_KEYWORD_TOKEN === $type ) {
			// The token value includes '@', e.g. '@media' — strip the '@' for comparison.
			$keyword = strtolower( ltrim( $value, '@' ) );

			if ( ! in_array( $keyword, self::ALLOWED_AT_RULES, true ) ) {
				// Strip the at-rule keyword and its entire following block (if any).
				$this->removed_tokens[] = array( 'token' => $value, 'reason' => 'disallowed_at_rule' );
				$this->remove_token();
				// Consume and remove the rule's block or up to the next semicolon.
				$this->consume_and_remove_rule_block();
			}
		}
	}

	return $this->get_updated_css();
}

/**
 * Consumes and removes the block or semicolon-terminated tail of an at-rule.
 *
 * Called immediately after removing an at-rule keyword token. Advances
 * the cursor and removes tokens until the at-rule ends — either at a
 * top-level ';' (for statement at-rules like @import) or after a
 * balanced '{ ... }' block (for block at-rules like @media).
 *
 * @since X.X.0
 */
private function consume_and_remove_rule_block(): void {
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
		} elseif ( self::EOF_TOKEN === $type ) {
			break;
		}
	}
}

/**
 * Resets the processor cursor to the beginning of the input.
 *
 * Called at the start of sanitize() and validate() to allow the
 * same instance to be used cleanly for one operation.
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
```

**Step 5: Run — expect PASS**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

**Step 6: Commit**

```bash
git add src/wp-includes/css-api/class-wp-css-token-processor.php tests/phpunit/tests/css-api/WpCssTokenSanitizeTest.php
git commit -m "CSS API: Implement sanitize() with security policy and tests"
```

---

## Task 5: Implement `validate()`

**Files:**
- Modify: `src/wp-includes/css-api/class-wp-css-token-processor.php`
- Create: `tests/phpunit/tests/css-api/WpCssTokenValidateTest.php`

**Step 1: Create test file and write failing tests**

```php
<?php
/**
 * Unit tests covering WP_CSS_Token_Processor::validate().
 *
 * @package WordPress
 * @subpackage CSS-API
 *
 * @since X.X.0
 *
 * @group css-api
 *
 * @coversDefaultClass WP_CSS_Token_Processor
 */
class Tests_CssApi_WpCssTokenValidate extends WP_UnitTestCase {

	private function validate( string $css ) {
		return ( new WP_CSS_Token_Processor( $css ) )->validate();
	}

	// --- Returns true for safe CSS ---

	public function test_valid_simple_css_returns_true() {
		$this->assertTrue( $this->validate( 'color: red;' ) );
	}

	public function test_valid_nested_css_returns_true() {
		$this->assertTrue( $this->validate( 'color: blue; & p { color: red; }' ) );
	}

	public function test_valid_media_query_returns_true() {
		$this->assertTrue( $this->validate( '@media (max-width: 768px) { color: red; }' ) );
	}

	// --- Returns WP_Error for each blocked condition ---

	public function test_style_close_tag_returns_wp_error() {
		$result = $this->validate( 'color: red; </style>' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_injection', $result->get_error_code() );
	}

	public function test_bad_string_token_returns_wp_error() {
		$result = $this->validate( "content: \"bad\nstring\";" );
		$this->assertWPError( $result );
		$this->assertSame( 'css_malformed_token', $result->get_error_code() );
	}

	public function test_bad_url_token_returns_wp_error() {
		$result = $this->validate( 'background: url(bad url);' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_malformed_token', $result->get_error_code() );
	}

	public function test_javascript_url_returns_wp_error() {
		$result = $this->validate( 'background: url(javascript:alert(1));' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_unsafe_url', $result->get_error_code() );
	}

	public function test_data_url_returns_wp_error() {
		$result = $this->validate( 'background: url(data:image/png;base64,abc);' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_unsafe_url', $result->get_error_code() );
	}

	public function test_blocked_at_rule_returns_wp_error() {
		$result = $this->validate( "@import url('https://evil.com/style.css');" );
		$this->assertWPError( $result );
		$this->assertSame( 'css_disallowed_at_rule', $result->get_error_code() );
	}

	public function test_unknown_at_rule_returns_wp_error() {
		$result = $this->validate( '@unknown-rule { color: red; }' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_disallowed_at_rule', $result->get_error_code() );
	}

	public function test_cdo_token_returns_wp_error() {
		$result = $this->validate( '<!-- color: red;' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_html_comment', $result->get_error_code() );
	}

	public function test_cdc_token_returns_wp_error() {
		$result = $this->validate( '--> color: red;' );
		$this->assertWPError( $result );
		$this->assertSame( 'css_html_comment', $result->get_error_code() );
	}

	// --- validate() passing guarantees sanitize() is a no-op ---

	/**
	 * @dataProvider data_valid_css_fixtures
	 */
	public function test_validate_passing_means_sanitize_is_noop( string $css ) {
		$p = new WP_CSS_Token_Processor( $css );
		$validation = $p->validate();
		if ( true !== $validation ) {
			$this->markTestSkipped( 'CSS is not valid — skipping no-op check.' );
		}
		$sanitized = ( new WP_CSS_Token_Processor( $css ) )->sanitize();
		$this->assertSame( $css, $sanitized, 'If validate() returns true, sanitize() must be a no-op.' );
	}

	public function data_valid_css_fixtures(): array {
		return array(
			array( 'color: red;' ),
			array( 'color: blue; & p { color: red; }' ),
			array( '& > p { margin: 0; }' ),
			array( '@media (max-width: 768px) { color: red; }' ),
			array( '--my-color: #ff0000;' ),
			array( 'color: var(--my-color);' ),
			array( 'background: url(https://example.com/image.png);' ),
		);
	}
}
```

**Step 2: Run — expect FAIL**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

**Step 3: Implement `validate()`**

```php
/**
 * Validates that the CSS string is safe to store and output.
 *
 * Returns true if the CSS would survive sanitize() unchanged.
 * Returns a WP_Error if any unsafe construct is detected.
 *
 * Unlike sanitize(), this method does not modify the CSS. It returns
 * on the first violation found.
 *
 * Error codes:
 * - 'css_injection'        — </style sequence detected
 * - 'css_malformed_token'  — bad-string-token or bad-url-token
 * - 'css_unsafe_url'       — url() with javascript: or data: protocol
 * - 'css_disallowed_at_rule' — @import, @charset, @namespace, or unknown at-rule
 * - 'css_html_comment'     — CDO (<!--) or CDC (-->) token
 *
 * @since X.X.0
 *
 * @return true|WP_Error True if the CSS is valid, WP_Error otherwise.
 */
public function validate() {
	// Injection guard.
	if ( false !== stripos( $this->css, '</style' ) ) {
		return new WP_Error( 'css_injection', __( 'CSS must not contain a </style closing tag.' ) );
	}

	$this->reset();
	$allowed_protocols = wp_allowed_protocols();

	while ( $this->next_token() ) {
		$type  = $this->get_token_type();
		$value = $this->get_token_value();

		if ( self::CDO_TOKEN === $type || self::CDC_TOKEN === $type ) {
			return new WP_Error( 'css_html_comment', __( 'CSS must not contain HTML comment tokens.' ) );
		}

		if ( self::BAD_STRING_TOKEN === $type || self::BAD_URL_TOKEN === $type ) {
			return new WP_Error( 'css_malformed_token', __( 'CSS contains a malformed string or URL token.' ) );
		}

		if ( self::URL_TOKEN === $type ) {
			$url    = preg_replace( '/^url\(\s*["\']?|["\']?\s*\)$/i', '', $value );
			$url    = trim( $url );
			$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

			if ( 'javascript' === $scheme || 'data' === $scheme ) {
				return new WP_Error( 'css_unsafe_url', __( 'CSS contains a URL with an unsafe protocol.' ) );
			}
			if ( '' !== $scheme && ! in_array( $scheme, $allowed_protocols, true ) ) {
				return new WP_Error( 'css_unsafe_url', __( 'CSS contains a URL with a disallowed protocol.' ) );
			}
		}

		if ( self::AT_KEYWORD_TOKEN === $type ) {
			$keyword = strtolower( ltrim( $value, '@' ) );
			if ( ! in_array( $keyword, self::ALLOWED_AT_RULES, true ) ) {
				return new WP_Error( 'css_disallowed_at_rule', __( 'CSS contains a disallowed at-rule.' ) );
			}
		}
	}

	return true;
}
```

**Step 4: Run — expect PASS**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

**Step 5: Commit**

```bash
git add src/wp-includes/css-api/class-wp-css-token-processor.php tests/phpunit/tests/css-api/WpCssTokenValidateTest.php
git commit -m "CSS API: Implement validate() with WP_Error codes and tests"
```

---

## Task 6: Final review, edge case tests, and documentation pass

**Files:**
- Modify: `src/wp-includes/css-api/class-wp-css-token-processor.php` (docblock polish)
- Modify: `tests/phpunit/tests/css-api/WpCssTokenProcessorTest.php` (edge cases)
- Modify: `tests/phpunit/tests/css-api/WpCssTokenSanitizeTest.php` (edge cases)

**Step 1: Add edge case tests for known tricky inputs**

Add to `WpCssTokenProcessorTest.php`:
```php
public function test_empty_input_returns_no_tokens() {
	$p = new WP_CSS_Token_Processor( '' );
	$this->assertFalse( $p->next_token() );
}

public function test_whitespace_only_input() {
	$p = new WP_CSS_Token_Processor( '   ' );
	$this->assertTrue( $p->next_token() );
	$this->assertSame( WP_CSS_Token_Processor::WHITESPACE_TOKEN, $p->get_token_type() );
	$this->assertFalse( $p->next_token() );
}

public function test_function_token_calc() {
	$p = new WP_CSS_Token_Processor( 'calc(' );
	$p->next_token();
	$this->assertSame( WP_CSS_Token_Processor::FUNCTION_TOKEN, $p->get_token_type() );
	$this->assertSame( 'calc(', $p->get_token_value() );
}

public function test_sequence_of_tokens_in_declaration() {
	$p      = new WP_CSS_Token_Processor( 'color: red;' );
	$tokens = array();
	while ( $p->next_token() ) {
		$tokens[] = $p->get_token_type();
	}
	$this->assertContains( WP_CSS_Token_Processor::IDENT_TOKEN, $tokens );
	$this->assertContains( WP_CSS_Token_Processor::COLON_TOKEN, $tokens );
	$this->assertContains( WP_CSS_Token_Processor::SEMICOLON_TOKEN, $tokens );
}
```

Add to `WpCssTokenSanitizeTest.php`:
```php
public function test_empty_input_returns_empty_string() {
	$this->assertSame( '', $this->sanitize( '' ) );
}

public function test_custom_properties_survive() {
	$css = '--my-color: #ff0000;';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_var_function_survives() {
	$css = 'color: var(--my-color);';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_keyframes_survives() {
	$css = '@keyframes slide { from { opacity: 0; } to { opacity: 1; } }';
	$this->assertSame( $css, $this->sanitize( $css ) );
}

public function test_multiple_blocked_at_rules_all_stripped() {
	$result = $this->sanitize( "@import 'evil.css'; @charset 'UTF-8'; color: red;" );
	$this->assertStringNotContainsString( '@import', $result );
	$this->assertStringNotContainsString( '@charset', $result );
	$this->assertStringContainsString( 'color: red;', $result );
}

// The specific compounding corruption scenario from PR #11104.
public function test_pr_11104_regression_repeated_saves_do_not_corrupt() {
	$original = 'color: blue; & p { color: red; }';
	$after_save_1 = $this->sanitize( $original );
	$after_save_2 = $this->sanitize( $after_save_1 );
	$after_save_3 = $this->sanitize( $after_save_2 );
	$this->assertSame( $original, $after_save_1 );
	$this->assertSame( $original, $after_save_2 );
	$this->assertSame( $original, $after_save_3 );
}
```

**Step 2: Run full test suite**

```bash
php vendor/phpunit/phpunit/phpunit --group css-api
```

Expected: All pass.

**Step 3: Review class docblock**

Open `src/wp-includes/css-api/class-wp-css-token-processor.php`. Verify:
- Class docblock explains purpose, non-goals, spec reference, usage examples, known gaps
- Every public method has `@since`, `@param`, `@return`
- Every security decision in `sanitize()` and `validate()` has a comment explaining *why*

**Step 4: Final commit**

```bash
git add src/wp-includes/css-api/class-wp-css-token-processor.php tests/phpunit/tests/css-api/
git commit -m "CSS API: Add edge case tests and final documentation pass for WP_CSS_Token_Processor"
```

---

## Completion checklist

- [ ] `src/wp-includes/css-api/class-wp-css-token-processor.php` exists and loads via `wp-settings.php`
- [ ] `src/wp-includes/css-api/README.md` exists
- [ ] All token types from the design doc are implemented and tested
- [ ] `sanitize()` passes all fixture tests including PR #11104 regression cases
- [ ] `sanitize()` is idempotent (tested over fixture set)
- [ ] `validate()` returns `true` for safe CSS and `WP_Error` with correct codes for each violation
- [ ] `validate()` passing guarantees `sanitize()` is a no-op (tested)
- [ ] `get_removed_tokens()` is populated correctly after `sanitize()`
- [ ] All public methods have full PHPDoc
- [ ] All tests use `@group css-api`
- [ ] `php vendor/phpunit/phpunit/phpunit --group css-api` passes with no failures
