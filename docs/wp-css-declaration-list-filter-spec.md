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

The declarations helper uses three further private helpers:
`_safecss_filter_attr_value_is_allowed()`, `_safecss_filter_attr_url_is_allowed()`
and `_safecss_filter_attr_is_trivia()`.
All are `@access private`, `@internal`, `@since 7.2.0`.

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
`WP_HTML_Style_Attribute_Processor::get_value_tokens()`, the `WP_CSS_Token`
objects covering the value, and runs the checks on their decoded values. It
writes each accepted declaration with
`WP_HTML_Style_Attribute_Processor::serialize_declaration()` and has no
serializer of its own.

## Rejection Unit

One declaration. A rejected declaration is dropped whole. No declaration is
rewritten to make it acceptable, and no declaration is rejected because of a
neighbour.

## Policy

One rule applies to every declaration, for every property including custom
ones:

0. **The end of the input.** CSS closes a block (a function, `(`, `[` or
   `{`) and a comment, string or url token that the input ends inside. The
   processor exposes the declaration, the rules below check it like any
   other, and the output writes it closed: `width: calc(1px` gives
   `width: calc(1px);` and `font-family: "abc` gives `font-family: "abc";`. A
   `;` inside an open block is part of the value, so
   `width: calc(1px; color: red` is one declaration, as a browser reads it.

An empty `safe_style_css` array disables filtering. `safecss_filter_attr()`
returns the input with C0 control characters and `\0` sequences removed,
which is what the legacy function returns for an empty list, and does not
call the declarations helper. A filter result that is not an array is
replaced with the default list, on both paths.

1. **Property name.** The decoded, lowercased name is in the `safe_style_css`
   list, whose entries are lowercased once before matching; or the list
   contains `--*` and the name matches `^--[a-zA-Z0-9_-]+$`. Custom property
   names are matched case-sensitively. List entries that are not strings are
   ignored, so a list with no string entry allows no property, as in the
   legacy function, which compared entries strictly.
2. **Functions.** Every function in the value, at any nesting depth, is in the
   function allowlist: the legacy function's list, the math functions `abs`,
   `sign`, `round`, `mod`, `rem`, `pow`, `sqrt`, `hypot`, `log`, `exp`, `sin`,
   `cos`, `tan`, `asin`, `acos`, `atan`, `atan2`, the six gradient functions,
   the color functions `rgb`, `rgba`, `hsl`, `hsla`, `hwb`, `lab`, `lch`,
   `oklab`, `oklch`, `color`, `color-mix`, `light-dark`, the filter
   functions `blur`, `brightness`, `contrast`, `drop-shadow`, `grayscale`,
   `hue-rotate`, `invert`, `opacity`, `saturate`, `sepia`, and the easing
   functions `cubic-bezier`, `steps`, `linear`. Names are decoded and
   matched case-insensitively. There is no per-property list: a gradient or a
   color function is allowed on every property.

   A function is added to the allowlist when it passes two tests. Security:
   its result depends only on its arguments; it loads no resource and reads
   no document, layout, scroll, input, author-defined code or condition.
   Stability: Blink, Gecko and WebKit ship it without a prefix or a flag, as
   MDN's browser-compat-data records. This is the CSS Working Group's rule
   for shipping a pre-CR feature unprefixed, counted in engines rather than
   browsers. Spec maturity is not a test: `min()`, `max()` and `clamp()` are
   in a Working Draft and have shipped in every engine since 2020.
3. **URLs.** Every URL in the value, at any depth, whether a `url()` token or
   `url("...")` with a string argument, is non-empty after trimming and is
   returned unchanged by `wp_kses_bad_protocol()` with `wp_allowed_protocols()`.
   `url(` with anything other than one string argument, closed by `)` or by
   the end of the input, is invalid CSS and the declaration is dropped. There is no per-property list of URL-bearing
   properties. `src()` is not in the function allowlist: it exists so `var()`
   can supply a URL, which the filter cannot check.
4. **Structure.** In a non-custom property, the value has no bare `( )` block
   and no `{ }` block at its top level. No standard property grammar accepts
   either. A `[ ]` block is kept: `grid-template-columns: [a] 1fr` is valid.
   A `( )` block nested in a function, as in `calc(1px + (2px * 3))` or a
   `var()` fallback, is governed by that function's grammar and is kept.
   Custom properties keep every block.
5. **Delimiters.** The value has no `&` delimiter token at any depth, in every
   property including custom ones. The output contains no `&`: the serializer
   escapes it in every other token, and no standard property value uses a
   bare `&`, so a value that has one is rejected. The same applies to a
   control character delimiter, U+0001 to U+001F other than whitespace or
   U+007F, which the serializer writes raw. `<`, `>`, `=`, `<!--` and
   `-->` are kept; encoding the output for HTML is the caller's task.
6. **Escapes and comments.** Checks run on decoded values, so an escaped
   function name or scheme is checked as what it denotes. Comments are
   dropped; see "Output".

Beyond rules 4 and 5 the policy checks what a value contains, not whether it
is valid for its property. Browser grammar is out of scope.

## Output

The output is built from the accepted declarations, not cut from the input:
it joins the `serialize_declaration()` text of each one. Each declaration is
`property: value;`, with `!important` preserved as ` !important` before the
`;`. Property names are the decoded names, escaped as identifiers. Values are
written from their tokens by `WP_CSS_Token_Serializer::serialize()`, which
follows CSS Syntax Level 3 §9:

- Comments are dropped. Each whitespace run, including one broken by comments,
  becomes one space.
- An empty comment is written between two tokens that would read as other
  tokens when written together, as the §9 table lists, so `#a\62/**/c`
  serializes as `#ab/**/c`. Between other pairs a dropped comment leaves
  nothing: `a/**/,b` serializes as `a,b`.
- Identifiers, function names, at-keywords, hash names and dimension units are
  re-escaped from their decoded values. A hash keeps its type flag: `#\31 a`
  stays `#\31 a` and `#1a` stays `#1a`.
- Strings are written double-quoted. url tokens are written unquoted;
  `url("...")` is a function with a string argument and keeps its quotes.
- A `\` delimiter, which only a backslash before a newline produces, keeps its
  newline so it does not escape the byte that follows.

- Blocks still open at the end of the input, and strings and url tokens cut
  off by it, are written closed, so the output never absorbs the `;` that
  follows.

Strings and url values hex-escape `\`, `<`, `>`, `&`, `,`, `;`, `{`, `}`,
quotes and control characters; url values also escape parentheses and
whitespace. A URL with a query string therefore serializes as
`url(a?x=1\26 y=2)`, which decodes to the same URL.

Properties of the output:

- It re-parses to the accepted declarations and nothing else.
- Filtering it again changes nothing.
- It ends with `;` when non-empty. Callers that tested for `;` to detect
  multiple declarations, or that appended their own `;`, must accept both
  forms while the legacy path exists. See "Callers".

## Callers

Core-owned callers adapted on this branch. Each accepts the legacy form (no
trailing `;`) and the new form (trailing `;`):

- `WP_Style_Engine_CSS_Declarations::filter_declaration()` strips one trailing
  `;` from the filtered declaration, restores the spacer after the colon when
  prettifying, and then appends ` !important` when the result has no `;`.
  `get_declarations_string()` appends `;` as before. The engine's output bytes
  are unchanged except for the filter's value serialization (double-quoted
  strings in `url()`).
- `get_block_wrapper_attributes()` strips one trailing `;` from the merged
  `style` value, as it strips `;` from its inputs.
- `wp_get_layout_style()` filtered a bare value such as `800px`, which the
  legacy function passed through and this filter drops as an item with no
  colon. It now filters `max-width:` plus the value and keeps the value of the
  result.
- `WP_Theme_JSON::is_safe_css_declaration()` keeps the legacy behavior: it
  adds a `wp_kses_force_legacy_css_parser` callback that returns `true` for
  its own call and removes it afterward.

Files under `wp-includes/blocks/` are synced from the Gutenberg repository and
are not changed here. `post-featured-image.php` appends `;` after each
filtered declaration, which now gives `;;`: an empty declaration, which
browsers ignore. The other block callers put the result in a `style`
attribute, where a trailing `;` changes nothing. A Gutenberg change can drop
the appended `;`.

## Hooks

`safe_style_css` is honoured as today, applied once before dispatch, so both
paths see the same list. An empty array returns the input before dispatch,
and a result that is not an array is replaced with the default list (see
"Policy").

`wp_kses_force_legacy_css_parser` is new in 7.2.0. It receives `false`;
returning `true` selects the legacy helper. It matches the form of
`wp_kses_force_legacy_parser` and exists for a transition period so a site
can restore the previous output while it adapts.

`safecss_filter_attr_allow_css` is deprecated in 7.2.0 and applied by the
legacy helper only. It exposed a regex test string that has no equivalent
here. When a callback is attached, the declarations helper calls
`_deprecated_hook()` once per call, naming no replacement, and does not
apply the hook. A site that needs the hook can
select the legacy helper through `wp_kses_force_legacy_css_parser`.

## Differences From The Legacy Function

Intended:

- Property names match case-insensitively.
- Color functions, gradients and `url()` are allowed on every property.
- Functions may nest to any depth.
- A function not on the allowlist is rejected wherever it appears, including
  inside a gradient, `calc()` or `minmax()`. The legacy function removed
  `calc()` and `minmax()` with everything inside them, so it kept
  `minmax(fit-content(200px), 1fr)`; the parser rejects it until
  `fit-content()` is added.
- The filter, math and easing functions are allowed. The legacy function
  rejected the filter functions, except `hue-rotate()`, which passed because
  its pattern for `rotate(` matched inside the name.
- A declaration CSS would drop is dropped, even when the legacy function kept
  its text. This includes an item with no colon, which the legacy function
  passed through unchecked.
- Escapes are accepted, decoded for the checks, and re-escaped in the output.
- A `\0` escape decodes to U+FFFD instead of being deleted from the text.
- A `=` delimiter is kept. The legacy function rejected it. Both reject a
  bare `&`.
- A control character delimiter rejects its declaration. The legacy function
  deleted U+0000 to U+001F and kept the rest of the declaration, and kept
  U+007F.
- A comment, string or url token cut off by the end of the input is closed,
  and its declaration is checked like any other. The legacy function
  rejected an item containing `/*` and an unclosed `url(`, and kept an
  unclosed string as it was.
- A block left open at the end of the input is closed, and its declaration is
  checked like any other. The legacy function rejected a value with an
  unclosed `(`, which its bad-character test matched, and kept an unclosed
  `[` or `{` as it was.
- A lone `{ }` block in a standard property is rejected, as the legacy
  function rejected `}`.
- An allowed-list entry with capitals matches, since entries and names are
  lowercased before matching.
- A `safe_style_css` result that is not an array is replaced with the default
  list, on both paths. The legacy function returned the input for `null`,
  `false`, `''` and `0`, and threw a `TypeError` for other values when the
  input had a declaration.
- `safecss_filter_attr_allow_css` is deprecated and not applied. Most
  plugins and themes in the WordPress.org directories that attach a callback
  use it only to allow color, filter, math, easing or transform functions,
  which the parser accepts by default. The remaining uses are `data:` URLs
  and functions this policy excludes, such as `attr()`.

Formatting only: one space after the colon, double-quoted strings and unquoted
url tokens with hex-escaped punctuation, comments dropped, trailing `;`.

Unchanged: an empty `safe_style_css` array returns the input.

On the 156 legacy test vectors: 20 identical, 115 formatting only, 2
narrowings (`expression()` inside a gradient; an unmatched `)`), 19 widenings,
0 defects, idempotent on all 156.

## Decisions

Open during the prototype, now settled:

- **Open blocks.** Closed, as CSS Syntax Level 3 closes a simple block or a
  function at the end of the input ("consume a simple block", "consume a
  function"), and checked like any other declaration. An earlier version rejected them, citing the legacy
  function's rejection of an unbalanced parenthesis; the legacy function
  kept an unclosed `[` or `{`, and a closed block gives the output the
  meaning a browser reads from the input. This applies the same rule as
  tokens cut off by the end of the input, so a url function with a string,
  `url("a.png"`, and a url token, `url(a.png`, are both kept.
- **Bare `( )` and `{ }` blocks.** Rejected at the top level of a non-custom
  property's value; kept when nested in a function and in custom properties.
  Rejecting at any depth would drop `calc(3em + (10px * 2))`, which the legacy
  function accepts and which is valid CSS. The processor already drops a
  `{ }` block mixed with other tokens in a non-custom property; this rule
  covers a value that is only a `{ }` block.
- **Tokens cut off by the end of the input.** Closed, as CSS Syntax Level 3
  closes them: a string or url token at the end of the input is returned
  with a parse error (§4.3.5, §4.3.6), and a comment ends there. The
  declaration is kept if the policy accepts it, and the output writes the
  token closed. An earlier version dropped the last accepted declaration
  instead. Browsers keep every such declaration, and the legacy function
  kept `color: red; /*` and `font-family: "abc`, which that rule dropped.
- **Transition.** The filter is named `wp_kses_force_legacy_css_parser`,
  after `wp_kses_force_legacy_parser`, defaults to `false`, and receives no
  other argument. Duration is not fixed; the filter docblock says it exists
  for a transition period.
- **Shared property list.** `safecss_filter_attr()` builds the list once and
  passes it to both helpers. The prototype's copy is deleted.
- **Value access.** The token view of `WP_CSS_Token` objects. The
  prototype's `get_value_source()` is not kept.
- **Serialization.** `serialize_declaration()` for each accepted declaration.
  Token lists are not a public unit for writing CSS, and the filter's own
  value serializer, which turned every comment run into a space, is deleted:
  in Chrome `calc(1px/**/+/**/2px)` is invalid, and the space made it
  `calc(1px + 2px)`, which is valid.
- **`wp_kses_no_null()`.** Not applied on the new path. See "Scope".

## Tests

`tests/phpunit/tests/kses/safecssFilterAttr.php` covers each policy rule in
isolation, blocks left open at the end of the input in standard and custom
properties, comments,
strings and url tokens cut off by the end of the input, the structural rule in
standard, nested and custom positions,
`!important`, comments, one case per marked cell of the §9 table and a sample
of unmarked pairs, whitespace, escapes, the empty `safe_style_css` array, a
`safe_style_css` result that is not an array, list entries that are not strings,
the two filters, and idempotence over a provider of the inputs that exercise
the serializer.

In `tests/phpunit/tests/kses.php`, `data_safecss_filter_attr` and
`data_kses_style_attr_with_url` serve both paths. The new output is compared
with `assertEqualStyleAttribute()`, so a row whose output changes only in form
keeps its expectation. A row whose output changes otherwise also lists the
pre-7.2.0 output, which the `_legacy` tests assert through `wp_kses_force_legacy_css_parser`.
`tools/Tests_Safecss_Filter_Differential.php` compares the two paths over both
providers and classifies each difference.

Tests outside kses whose expectations changed only in form now compare style
values by their declarations:
`tests/phpunit/tests/block-supports/wpRenderBackgroundSupport.php` with
`assertEqualHTML()`, `tests/phpunit/tests/blocks/supportedStyles.php` with
`assertEqualStyleAttribute()` and `assertEqualHTML()`, and
`tests/phpunit/tests/style-engine/styleEngine.php` with
`assertEqualStyleAttribute()` for declaration lists without a selector.
One Style Engine test asserted legacy policy: that `safecss_filter_attr_allow_css`
fires and that `url()` is dropped on `line-height`. It now asserts the new
output.
