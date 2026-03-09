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
- `url()` with `javascript:`, `data:`, or any scheme not in `wp_allowed_protocols()`: token stripped
- `@import`, `@charset`, `@namespace`: rule stripped
- Unknown at-rules: stripped (safety-first)
- `bad-url-token`, `bad-string-token`: stripped
- HTML comment tokens (`<!--`, `-->`): stripped
- Null bytes: stripped in preprocessing

### Token type reference

Token type constants are defined as class constants on `WP_CSS_Token_Processor`:
`IDENT_TOKEN`, `FUNCTION_TOKEN`, `AT_KEYWORD_TOKEN`, `HASH_TOKEN`, `STRING_TOKEN`,
`BAD_STRING_TOKEN`, `URL_TOKEN`, `BAD_URL_TOKEN`, `DELIM_TOKEN`, `NUMBER_TOKEN`,
`PERCENTAGE_TOKEN`, `DIMENSION_TOKEN`, `WHITESPACE_TOKEN`, `CDO_TOKEN`, `CDC_TOKEN`,
`COLON_TOKEN`, `SEMICOLON_TOKEN`, `COMMA_TOKEN`, `OPEN_SQUARE_TOKEN`,
`CLOSE_SQUARE_TOKEN`, `OPEN_PAREN_TOKEN`, `CLOSE_PAREN_TOKEN`, `OPEN_CURLY_TOKEN`,
`CLOSE_CURLY_TOKEN`, `EOF_TOKEN`.

### validate() error codes

| Code                      | Condition                                              |
|---------------------------|--------------------------------------------------------|
| `css_injection`           | `</style` found anywhere in the input                 |
| `css_html_comment`        | CDO-token (`<!--`) or CDC-token (`-->`)               |
| `css_malformed_token`     | `bad-string-token` or `bad-url-token`                 |
| `css_unsafe_url`          | `url()` with `javascript:` or `data:` scheme, or a scheme not in `wp_allowed_protocols()` |
| `css_disallowed_at_rule`  | At-rule keyword not in the allowed list               |

If `validate()` returns `true`, calling `sanitize()` on the same input is guaranteed
to be a no-op (the input is returned unchanged).

### Known gaps (v1)

- Unicode range tokens (`U+`) are not supported.
- Surrogate pair edge cases beyond basic UTF-8 are not handled.
- CSS escape sequences (`\XX` or `\<char>`) in identifiers are not supported;
  a backslash is emitted as `DELIM_TOKEN`.
- `url("javascript:...")` with a quoted string argument is not flagged by the URL
  protocol check — it tokenizes as `FUNCTION_TOKEN` + `STRING_TOKEN`, not as
  `URL_TOKEN`. This is not a practical security concern (browsers do not execute
  `javascript:` URLs in CSS resource-fetch contexts) but means `validate()` does
  not reject quoted `javascript:` in `url()`.
- CSS block comments (`/* ... */`) are not tokenized as a unit and their content
  passes through `sanitize()` unchanged.

### Spec reference

CSS Syntax Level 3: https://www.w3.org/TR/css-syntax-3/
