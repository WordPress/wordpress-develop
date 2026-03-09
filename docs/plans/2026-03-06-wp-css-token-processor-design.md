# Design: WP_CSS_Token_Processor

**Date:** 2026-03-06
**Status:** Approved
**Related:** https://github.com/WordPress/wordpress-develop/pull/11104, https://core.trac.wordpress.org/ticket/64771

---

## Background

When a user without `unfiltered_html` (e.g. Author role, or site admins on some multisite configurations) saves a post containing block-level custom CSS (`attrs.style.css`) with `&` or `>` characters, the `filter_block_content()` pipeline corrupts the CSS through a three-step mangling chain:

1. `parse_blocks()` / `json_decode()` — `\u0026` becomes `&`
2. `filter_block_kses_value()` / `wp_kses()` — `&` becomes `&amp;`, `>` becomes `&gt;` (KSES treats CSS as HTML)
3. `serialize_block_attributes()` / `json_encode()` — `&amp;` becomes `\u0026amp;`

Each subsequent save compounds the corruption. The root cause is that `wp_kses()` is an HTML sanitizer being applied to CSS — the wrong tool for the job. This class is the right tool.

---

## Scope

### In scope (this session)

- `WP_CSS_Token_Processor` class — streaming CSS tokenizer
- `sanitize()` instance method — strips unsafe tokens/rules, returns safe CSS string
- `validate()` instance method — returns `true|WP_Error`
- `get_updated_css()` instance method — reconstruct CSS after manual token modifications
- `get_removed_tokens()` instance method — inspection after `sanitize()`
- Low-level navigation and modification methods
- Full inline PHPDoc
- `README.md` in `src/wp-includes/css-api/`
- Full test suite

### Out of scope (follow-on sessions)

- Integration with `filter_block_kses_value()` in `blocks.php`
- `WP_CSS_Processor` — rule/declaration-aware layer (v2)
- Replacing `process_blocks_custom_css()` in `WP_Theme_JSON`
- CSS selector query engine (TODO in `class-wp-block.php:385`)
- Customizer CSS and Global Styles CSS pipeline adoption

---

## Architecture

### Directory structure

```
src/wp-includes/
└── css-api/
    ├── class-wp-css-token-processor.php
    └── README.md

tests/phpunit/tests/
└── css-api/
    ├── WpCssTokenProcessorTest.php
    ├── WpCssTokenSanitizeTest.php
    └── WpCssTokenValidateTest.php
```

### Component map

```
WP_CSS_Token_Processor              — tokenizes a CSS string into a typed token stream
        |
        | sanitize(): string         — strips unsafe tokens/rules, returns safe CSS
        | validate(): true|WP_Error  — returns true, or WP_Error with reason code
        | get_updated_css(): string  — reconstruct after manual token modifications
```

The integration point (`filter_block_kses_value()` dispatching to `sanitize()` for `['style','css']` paths) is a follow-on PR and is not part of this session.

---

## `WP_CSS_Token_Processor`

### Design principles

- **Spec-inspired, safety-first** — follows the CSS Syntax Level 3 token vocabulary and structure, but prioritises correctness on security-relevant tokens over completeness. Gaps cause rejection/stripping rather than silent pass-through.
- **Forward-only streaming** — like `WP_HTML_Tag_Processor`, the processor advances a cursor through the input. No backtracking except via bookmarks (v2).
- **Non-destructive modification** — operates on the original string buffer and applies edits on output via `get_updated_css()`.
- **Instance-based API** — consistent with `WP_HTML_Tag_Processor`. Create an instance, call methods, retrieve output.

### Token types

#### Security-critical (must be correct)

| Constant | Examples | Notes |
|---|---|---|
| `WP_CSS_Token_Processor::URL_TOKEN` | `url(foo.png)` | Protocol-filtered against `wp_allowed_protocols()` |
| `WP_CSS_Token_Processor::BAD_URL_TOKEN` | `url(foo bar)` | Malformed URL — stripped |
| `WP_CSS_Token_Processor::STRING_TOKEN` | `"hello"`, `'world'` | Quoted strings |
| `WP_CSS_Token_Processor::BAD_STRING_TOKEN` | Unterminated string | Stripped |
| `WP_CSS_Token_Processor::AT_KEYWORD_TOKEN` | `@media`, `@import` | At-rule allowlist enforced in `sanitize()` |
| `WP_CSS_Token_Processor::OPEN_CURLY_TOKEN` | `{` | Block depth tracking |
| `WP_CSS_Token_Processor::CLOSE_CURLY_TOKEN` | `}` | Block depth tracking |

#### Structurally important

| Constant | Examples |
|---|---|
| `WP_CSS_Token_Processor::IDENT_TOKEN` | `color`, `red`, `sans-serif` |
| `WP_CSS_Token_Processor::FUNCTION_TOKEN` | `calc(`, `var(`, `rgb(` |
| `WP_CSS_Token_Processor::DELIM_TOKEN` | `&`, `>`, `+`, `~`, `*` |
| `WP_CSS_Token_Processor::DIMENSION_TOKEN` | `16px`, `1.5rem`, `100vh` |
| `WP_CSS_Token_Processor::PERCENTAGE_TOKEN` | `50%` |
| `WP_CSS_Token_Processor::NUMBER_TOKEN` | `42`, `1.5` |
| `WP_CSS_Token_Processor::HASH_TOKEN` | `#ff0000`, `#my-id` |
| `WP_CSS_Token_Processor::WHITESPACE_TOKEN` | Preserved in output |
| `WP_CSS_Token_Processor::SEMICOLON_TOKEN` | `;` |
| `WP_CSS_Token_Processor::COLON_TOKEN` | `:` |
| `WP_CSS_Token_Processor::COMMA_TOKEN` | `,` |

#### Stripped unconditionally

| Constant | Reason |
|---|---|
| `WP_CSS_Token_Processor::CDO_TOKEN` | `<!--` — HTML comments have no place in CSS |
| `WP_CSS_Token_Processor::CDC_TOKEN` | `-->` — HTML comments have no place in CSS |
| Null bytes | Stripped in preprocessing, before tokenization |
| `</style` sequence | Injection guard — `sanitize()` returns `''`, `validate()` returns `WP_Error` |

#### Out of scope for v1 (documented gaps — treated as unknown, stripped)

- Unicode range tokens (`U+`)
- Surrogate pair edge cases beyond basic UTF-8

### API surface

#### Construction

```php
$processor = new WP_CSS_Token_Processor( string $css );
```

#### Low-level navigation

```php
$processor->next_token(): bool       // Advance cursor. Returns false at EOF.
$processor->get_token_type(): string // Token type constant for current token.
$processor->get_token_value(): string // Raw value of current token.
$processor->get_block_depth(): int   // Current { } nesting depth.
```

#### Low-level modification

```php
$processor->set_token_value( string $value ): bool // Replace current token's value.
$processor->remove_token(): bool                    // Remove current token from output.
```

#### High-level consumers (primary public API)

```php
$processor->sanitize(): string          // Strip unsafe tokens/rules. Returns safe CSS string.
$processor->validate(): true|WP_Error   // true if safe, WP_Error with code if not.
$processor->get_updated_css(): string   // Reconstruct CSS after manual token modifications.
$processor->get_removed_tokens(): array // Log of what was stripped and why, after sanitize().
```

---

## Security Policy

### `sanitize()` — token-level rules

Applied during tokenization, before structural analysis:

| Condition | Action |
|---|---|
| `</style` anywhere in input | Return `''` immediately — do not continue |
| Null bytes | Strip in preprocessing |
| `bad-url-token`, `bad-string-token` | Strip token |
| `CDO-token`, `CDC-token` | Strip token |
| `url-token` with `javascript:` or `data:` | Strip token entirely |
| `url-token` with other disallowed protocol | Replace URL value with `''`, preserve `url()` wrapper |

### `sanitize()` — rule-level rules

Applied during structural traversal, after tokenization:

**At-rule allowlist:**

```
Allowed:  @media, @supports, @keyframes, @layer, @container, @font-face
Blocked:  @import, @charset, @namespace
Unknown:  stripped (safety-first — gaps reject, not pass-through)
```

Strip granularity: declaration fails → drop declaration; rule fails → drop rule; rest of CSS preserved.

### `validate()` rules

Returns `WP_Error` if any of the following are present:

| Condition | Error code |
|---|---|
| `</style` sequence | `css_injection` |
| `bad-url-token` or `bad-string-token` | `css_malformed_token` |
| Disallowed `url()` protocol | `css_unsafe_url` |
| Blocked or unknown at-rule | `css_disallowed_at_rule` |
| Null bytes | `css_null_byte` |
| `CDO-token` / `CDC-token` | `css_html_comment` |

`validate()` passing is a guarantee that `sanitize()` is a no-op on the same input.

### What the security policy explicitly does NOT do

- Does not validate property names or values — authoring intent, not a security concern
- Does not restrict CSS nesting depth
- Does not filter `var()` or custom properties — cannot execute code
- Does not block `expression()` — IE-era only, not worth the complexity

### Idempotency guarantee

`sanitize()` must be idempotent:

```
sanitize( sanitize( $css ) ) === sanitize( $css )
```

This is a hard requirement enforced by the test suite. It directly addresses the compounding corruption bug in PR #11104.

---

## Documentation

### Inline PHPDoc

- Every public method: `@since`, `@param`, `@return`, usage example
- Class docblock: purpose, what it is not, spec reference, usage examples, known gaps
- Security decisions commented with *why*, not just *what*

### README.md

Located at `src/wp-includes/css-api/README.md`. Covers:

- Purpose and scope
- Quick usage examples for `sanitize()` and `validate()`
- Token type reference
- Security policy summary
- Known gaps and future work

---

## Testing

### Test files

```
tests/phpunit/tests/css-api/
├── WpCssTokenProcessorTest.php    — tokenizer unit tests
├── WpCssTokenSanitizeTest.php     — sanitize() tests
└── WpCssTokenValidateTest.php     — validate() tests
```

### Test categories

#### Tokenizer unit tests (`WpCssTokenProcessorTest.php`)

- Each token type in isolation: correct `get_token_type()` and `get_token_value()`
- Token sequences: declaration, qualified rule, nested rule
- Block depth tracking via `get_block_depth()`
- Edge cases: empty input, whitespace-only, single character
- Manual modification: `set_token_value()`, `remove_token()`, `get_updated_css()`

#### Sanitize tests (`WpCssTokenSanitizeTest.php`)

- CSS nesting selectors (`&`, `& > p`, `& + span`) survive unchanged
- Child combinator (`>`) survives unchanged
- Valid at-rules (`@media`, `@supports`, `@keyframes`) survive unchanged
- Blocked at-rule (`@import`) is stripped entirely
- Unknown at-rule is stripped
- `url()` with allowed protocol survives
- `url()` with `javascript:` is stripped entirely
- `url()` with `data:` is stripped entirely
- `bad-url-token` is stripped
- `bad-string-token` is stripped
- `</style` input returns `''`
- Null bytes are stripped
- `CDO` / `CDC` tokens are stripped
- `get_removed_tokens()` is populated after stripping
- `get_removed_tokens()` is empty when nothing is stripped
- **Idempotency**: `sanitize(sanitize($css)) === sanitize($css)` over a broad fixture set
- **Regression fixtures from PR #11104**:
  - `color: blue; & p { color: red; }` survives unchanged
  - `& > p { margin: 0; }` survives unchanged
  - Repeated saves do not compound corruption

#### Validate tests (`WpCssTokenValidateTest.php`)

- Valid CSS returns `true`
- Each blocked condition returns `WP_Error` with the correct error code
- `validate()` passing guarantees `sanitize()` is a no-op (tested over fixture set)

---

## Open questions (deferred)

- Should `get_removed_tokens()` be structured (array of `['token' => ..., 'reason' => ...]`) or flat? TBD during implementation.
- Should the at-rule allowlist be filterable via a WordPress filter hook (like `safe_style_css`)? Likely yes, deferred to implementation.
- Exact `@since` version tag — placeholder `X.X.0` during development.
