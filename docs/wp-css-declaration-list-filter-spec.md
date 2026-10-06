# CSS Declaration List Filter Specification

Status: draft on `css-api/safecss-filter-attr`. Function and file names are
provisional. Each section states a decision; sections marked "Open" state a
question still to decide.

## Purpose

The declaration list filter replaces the parsing and checking inside
`safecss_filter_attr()`. It takes the decoded CSS text of a `style` attribute,
parses it as a declaration list, drops the declarations a policy rejects, and
returns a declaration list built from the accepted ones.

The legacy function splits on `;`, matches property names as text, and tests
values with regular expressions. The filter parses with
`WP_HTML_Style_Attribute_Processor`, so the unit it accepts or rejects is the
same declaration a browser sees.

## Scope

In scope: a declaration list, as in a `style` attribute or the body of one
rule. Out of scope: whole stylesheets, at-rules, selectors, and `@font-face`
descriptors. Those need their own parsing and their own policy.

The filter does not decode HTML entities and does not encode its output. CSS
source text goes in and CSS source text comes out. Entity handling belongs to
the HTML layer, as it does for the legacy function's KSES caller.

## Parsing Model

The filter sees only what the processor exposes as a declaration. Text the
processor skips is never checked and never emitted. This includes items with no
colon, at-rules, and declarations whose value contains a bad string, a bad URL,
an unmatched closing token, a top-level `!`, or a `{}` block mixed with other
tokens in a non-custom property. The processor's specification, "Invalid
Fragments", defines this and the splitting rule for a stray `}`.

Consequence: the filter never has to judge malformed CSS. A declaration either
parses as CSS or is not there.

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
   color function is allowed on every property. (Open: the exact list.)
3. **URLs.** Every URL in the value, at any depth, whether a `url()` token or
   `url("...")` with a string argument, is non-empty after trimming and is
   returned unchanged by `wp_kses_bad_protocol()` with `wp_allowed_protocols()`.
   There is no per-property list of URL-bearing properties.
4. **Escapes and comments.** Checks run on decoded values, so an escaped
   function name or scheme is checked as what it denotes. Comments are never
   emitted.

The policy checks what a value contains, not whether it is valid for its
property. Browser grammar is out of scope.

## Output

The output is built from the accepted declarations, not cut from the input.
Each declaration is `property:value;` with `!important` preserved as
` !important` before the `;`. Property names are the decoded names. Values are
serialized from their tokens: strings and URLs re-escaped from their decoded
values, identifiers and function names re-escaped, whitespace and comment runs
reduced to one space, blocks left open at EOF closed.

Properties of the output:

- It re-parses to the accepted declarations and nothing else.
- Filtering it again changes nothing.
- It ends with `;` when non-empty. Callers that tested for `;` to detect
  multiple declarations must change.

## Hooks

`safe_style_css` is honoured as today. `safecss_filter_attr_allow_css` is not
provided: it exposed a regex test string that has no equivalent here. The code
marks where it fired.

## Value Access (Open)

The processor's first API had no value getter. The filter needs the value's
tokens for the checks and for the serializer. Two routes were prototyped on
this branch and agree on every test vector:

- a source-text getter, with the filter tokenizing the value again;
- a token view, with the processor handing over the tokens it already has.

Decision pending: which route, and in what shape. If the token view is chosen,
the processor specification's "absence of public raw value getter" test
requirement changes.

## Differences From The Legacy Function

Intended:

- Property names match case-insensitively.
- Color functions, gradients and `url()` are allowed on every property.
- Functions may nest to any depth.
- A function left open at EOF is accepted and closed in the output.
- A function not on the allowlist is rejected wherever it appears, including
  inside a gradient.
- A declaration CSS would drop is dropped, even when the legacy function kept
  its text.

Formatting only: no space after the colon, double-quoted strings, URLs as
`url("...")`, trailing `;`.

## Open Decisions

- Bare parenthesised blocks in a value (`width: (1px)`) parse and pass the
  policy. Browsers reject them as values for every standard property. Reject
  them, or leave grammar to the browser?
- A string that runs to EOF inside `url("...` parses and passes. Reject it?
- Transition: the legacy function chooses this implementation by default, with
  a filter to opt back in for a period. Name and duration undecided.
- Shared property list: the prototype copies the default list from
  `kses.php`. The final version has one list.

## Test Requirements

Tests should cover:

- every legacy test vector, with each difference in output classified as
  formatting, intended, or a defect;
- idempotence over every vector;
- each policy rule in isolation: name case, custom name grammar, nested
  disallowed function, escaped function name, escaped scheme, empty URL,
  `url()` token and `url("...")` function forms;
- empty `safe_style_css`;
- `!important` preservation and placement;
- comments, escapes and whitespace in output;
- declarations the processor drops are absent from the output.
