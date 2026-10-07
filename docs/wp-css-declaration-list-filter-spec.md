# CSS Declaration List Filter Specification

Status: implemented on `css-api/safecss-filter-attr` as the default path of
`safecss_filter_attr()` in `wp-includes/kses.php`. Each section states a
decision. The "Decisions" section records the questions that were open during
the prototype and how each was settled.

## Purpose

The declaration list filter replaces the parsing and checking inside
`safecss_filter_attr()`. It takes the decoded CSS text of a `style` attribute,
parses it as a declaration list, drops the declarations a policy rejects, and
returns a declaration list built from the accepted ones.

The legacy function splits on `;`, matches property names as text, and tests
values with regular expressions. The filter parses with
`WP_HTML_Style_Attribute_Processor`, so the unit it accepts or rejects is the
same declaration a browser sees.

## Home

There is no new public function or class. `safecss_filter_attr()` keeps its
signature and docblock. It builds the allowed property list once, applies
`safe_style_css` once, and dispatches to one of two private helpers in
`kses.php`:

- `_safecss_filter_attr_declarations( $css, $allowed_attr )`: this filter.
- `_safecss_filter_attr_legacy( $css, $allowed_attr )`: the pre-7.2.0 body,
  unchanged except that it receives the list.

The declarations helper uses five further private helpers:
`_safecss_filter_attr_value_is_allowed()`, `_safecss_filter_attr_url_is_allowed()`,
`_safecss_filter_attr_string_is_unterminated()`, `_safecss_filter_attr_is_trivia()`
and `_safecss_filter_attr_serialize_value()`. All are `@access private`,
`@internal`, `@since 7.2.0`.

## Scope

In scope: a declaration list, as in a `style` attribute or the body of one
rule. Out of scope: whole stylesheets, at-rules, selectors, and `@font-face`
descriptors. Those need their own parsing and their own policy.

The filter does not decode HTML entities and does not encode its output. CSS
source text goes in and CSS source text comes out. Entity handling belongs to
the HTML layer, as it does for the legacy function's KSES caller.

The filter does not call `wp_kses_no_null()`. The legacy function did, which
deleted NUL bytes and every `\0`-prefixed text run before parsing. The
processor handles NUL bytes as CSS does (U+FFFD), and deleting `\0` text would
corrupt valid escapes such as `\00e9`. A `\0` escape therefore decodes to
U+FFFD, as it does in a browser.

## Parsing Model

The filter sees only what the processor exposes as a declaration. Text the
processor skips is never checked and never emitted. This includes items with no
colon, at-rules, and declarations whose value contains a bad string, a bad URL,
an unmatched closing token, a top-level `!`, or a `{}` block mixed with other
tokens in a non-custom property. The processor's specification, "Invalid
Fragments", defines this and the splitting rule for a stray `}`.

Consequence: the filter never has to judge malformed CSS. A declaration either
parses as CSS or is not there.

## Value Access

The filter reads each value through
`WP_HTML_Style_Attribute_Processor::get_value_tokens()`, the token view the
processor exposes: the slice of its own token list covering the value, each
token as `{type, value, start, length, end}` with offsets into the style text.
The filter uses the decoded `value` for the checks and for re-escaping strings,
URLs, identifiers and function names, and copies other tokens from the source
bytes at `start` and `length`. No second tokenization takes place.

## Rejection Unit

One declaration. A rejected declaration is dropped whole. No declaration is
rewritten to make it acceptable, and no declaration is rejected because of a
neighbour.

## Policy

Checks run only when the `safe_style_css` list is non-empty. An empty list
means no checks, as in the legacy function; the output is still re-serialized.

1. **Property name.** The decoded, case-folded name is in the `safe_style_css`
   list; or the list contains `--*` and the name matches
   `^--[a-zA-Z0-9_-]+$`. Custom property names are matched case-sensitively.
2. **Functions.** Every function in the value, at any nesting depth, is in the
   function allowlist: the legacy function's list, the six gradient functions,
   and the color functions `rgb`, `rgba`, `hsl`, `hsla`, `hwb`, `lab`, `lch`,
   `oklab`, `oklch`, `color`, `color-mix`, `light-dark`. Names are decoded and
   matched case-insensitively. There is no per-property list: a gradient or a
   color function is allowed on every property.
3. **URLs.** Every URL in the value, at any depth, whether a `url()` token or
   `url("...")` with a string argument, is non-empty after trimming and is
   returned unchanged by `wp_kses_bad_protocol()` with `wp_allowed_protocols()`.
   `url()` with anything other than one string argument is invalid CSS and the
   declaration is dropped. `src()` is treated like `url()`. There is no
   per-property list of URL-bearing properties.
4. **Structure.** In a non-custom property, the value has no bare `( )` block
   at its top level and no string that runs to the end of the input. No
   standard property grammar accepts either. A `( )` block nested in a
   function, as in `calc(1px + (2px * 3))` or a `var()` fallback, is governed
   by that function's grammar and is kept. Custom properties keep both.
5. **Escapes and comments.** Checks run on decoded values, so an escaped
   function name or scheme is checked as what it denotes. Comments are never
   emitted.

Beyond rule 4 the policy checks what a value contains, not whether it is valid
for its property. Browser grammar is out of scope.

## Output

The output is built from the accepted declarations, not cut from the input.
Each declaration is `property:value;` with `!important` preserved as
` !important` before the `;`. Property names are the decoded names. Values are
serialized from their tokens: strings and URLs re-escaped from their decoded
values with `WP_CSS_Token_Processor::serialize_string()`, identifiers and
function names re-escaped with `serialize_ident()`, whitespace and comment runs
reduced to one space, blocks left open at EOF closed. A `\` delimiter, which
only a backslash before a newline produces, keeps its newline so it does not
escape the space that follows.

`serialize_string()` hex-escapes `\`, `<`, `>`, `&`, `,`, `;`, `{`, `}` and
both quotes. A URL with a query string therefore serializes as
`url("a?x=1\26 y=2")`, which decodes to the same URL.

Properties of the output:

- It re-parses to the accepted declarations and nothing else.
- Filtering it again changes nothing.
- It ends with `;` when non-empty. Callers that tested for `;` to detect
  multiple declarations must change.

## Hooks

`safe_style_css` is honoured as today, applied once before dispatch, so both
paths see the same list.

`safecss_filter_attr_use_legacy` is new in 7.2.0. It receives `false` and the
input CSS; returning `true` selects the legacy helper. It exists for a
transition period so a site can restore the previous output while it adapts.

`safecss_filter_attr_allow_css` is applied by the legacy helper only. It
exposed a regex test string that has no equivalent here. A comment in the
declarations helper marks where it fired.

## Differences From The Legacy Function

Intended:

- Property names match case-insensitively.
- Color functions, gradients and `url()` are allowed on every property.
- Functions may nest to any depth.
- A function left open at EOF is accepted and closed in the output.
- A function not on the allowlist is rejected wherever it appears, including
  inside a gradient.
- A declaration CSS would drop is dropped, even when the legacy function kept
  its text. This includes an item with no colon, which the legacy function
  passed through unchecked.
- Escapes are accepted, decoded for the checks, and re-escaped in the output.
- A `\0` escape decodes to U+FFFD instead of being deleted from the text.

Formatting only: no space after the colon, double-quoted strings with
hex-escaped punctuation, URLs as `url("...")`, trailing `;`.

On the 156 legacy test vectors: 22 identical, 115 formatting only, 2
narrowings (`expression()` inside a gradient; an unmatched `)`), 17 widenings,
0 defects, idempotent on all 156.

## Decisions

Open during the prototype, now settled:

- **Bare `( )` blocks.** Rejected at the top level of a non-custom property's
  value; kept when nested in a function and in custom properties. Rejecting at
  any depth would drop `calc(3em + (10px * 2))`, which the legacy function
  accepts and which is valid CSS.
- **Strings that run to EOF.** Rejected in non-custom properties at any depth,
  including inside `url("...`. Kept in custom properties. Detected from the
  token view: the token's `end` equals the input length and its source bytes
  do not end with an unescaped copy of the opening quote. A tokenizer flag for
  this condition would be the cleaner source; the token processor does not
  expose one yet.
- **Transition.** The filter is named `safecss_filter_attr_use_legacy`,
  defaults to `false`, and receives the input CSS. Duration is not fixed; the
  filter docblock says it exists for a transition period.
- **Shared property list.** `safecss_filter_attr()` builds the list once and
  passes it to both helpers. The prototype's copy is deleted.
- **Value access.** The token view, as bare arrays, as PR 2 ships it. The
  prototype's `get_value_source()` is not kept.
- **`wp_kses_no_null()`.** Not applied on the new path. See "Scope".

## Tests

`tests/phpunit/tests/kses/safecssFilterAttr.php` covers each policy rule in
isolation, the structural rule in standard, nested and custom positions, the
unterminated-string helper, `!important`, comments, whitespace, escapes, the
empty `safe_style_css` list, the two filters, and idempotence over a provider
of the inputs that exercise the serializer.

In `tests/phpunit/tests/kses.php`, `data_safecss_filter_attr` and
`data_kses_style_attr_with_url` serve both paths. The new output is compared
with `assertEqualStyleAttribute()`, so a row whose output changes only in form
keeps its expectation. A row whose output changes otherwise also lists the
pre-7.2.0 output, which the `_legacy` tests assert through `safecss_filter_attr_use_legacy`.
`tools/Tests_Safecss_Filter_Differential.php` compares the two paths over both
providers and classifies each difference.
