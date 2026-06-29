# WP_HTML_Style_Attribute_Processor Specification

## Purpose

`WP_HTML_Style_Attribute_Processor` inspects and modifies decoded CSS text from an
HTML `style` attribute. Its input is the declaration-list contents of the
attribute, excluding HTML attribute syntax, entity decoding, and any surrounding
declaration-block braces.

The processor preserves the CSS declaration-list model. Duplicate declarations
remain distinct. Invalid CSS fragments are skipped or preserved according to CSS
parser semantics unless a requested mutation cannot be proven safe.

The processor's north star is:

1. preserve CSS structure;
2. preserve user intent;
3. preserve the semantic CSS value of any existing declaration it rewrites;
4. make minimal edits when doing so does not compromise the first three goals.

## Construction

Creation uses:

```php
WP_HTML_Style_Attribute_Processor::create( string $decoded_css_text ): static
```

The constructor is private. `create()` always returns a processor instance for a
string input.

Callers must not pass raw `WP_HTML_Tag_Processor::get_attribute( 'style' )`
results directly unless they have already checked that the result is a string.
Missing or boolean attributes are an HTML-layer concern, not accepted CSS text.

Parsing is lazy. Creating a processor does not scan the style text until cursor
movement, mutation validation, or serialization requires it.

## Declaration Model

The processor models each CSS declaration as:

```text
property-name: component-value-list !important?
```

The internal declaration data consists of:

- decoded property name;
- authored property-name source when available;
- value component list source range;
- `!important` flag;
- nullable source range for the parsed priority span;
- source ranges needed for safe mutation and verification.

`!important` is not part of the value, including for custom properties.

## Public API

Initial public methods:

```php
public static function create( string $decoded_css_text ): static;
public function next_declaration( ?string $property_name = null ): bool;
public function get_property_name(): ?string;
public function is_important(): ?bool;
public function set_value( string $value, ?bool $important = null ): bool;
public function set_important( bool $important ): bool;
public function remove_declaration(): bool;
public function append_declaration( string $property_name, string $value, bool $important = false ): bool;
public function get_updated_style(): string;
```

The first API does not expose `get_value()` or `get_raw_value()`. Raw CSS values
are difficult for callers to use safely, and a higher-level value API needs a
separate design.

Bookmarks, seek, and rewind are out of scope. Callers that need to rescan should
create a new processor from the updated style text.

## Cursor Semantics

`next_declaration()` advances a forward-only cursor over CSS declarations.

All current-declaration getters return `null` when the cursor is not positioned
on a valid declaration. This includes `is_important()`.

After `remove_declaration()` succeeds, the cursor becomes invalid until
`next_declaration()` advances it. A second removal on the same invalid cursor
returns `false`.

After `set_value()` or `set_important()` succeeds without removing the
declaration, the cursor remains on the same logical declaration.

`set_value( '' )` removes the current declaration. On success it has the same
cursor semantics as `remove_declaration()`.

`append_declaration()` does not move the cursor. If the cursor was exhausted, it
remains invalid until a later `next_declaration()` advances to the appended
declaration.

`next_declaration()` flushes pending safe edits before advancing because
advancement must reflect the updated declaration list.

Current-position getters should reflect successful pending edits without forcing
a full reparse where practical, following HTML API style.

## Property Names

All public property-name arguments are decoded/plaintext CSS property names, not
CSS-escaped identifier source.

Ordinary properties match ASCII case-insensitively. Custom properties match
exactly because casing is semantic.

`get_property_name()` exposes CSS semantics:

- ordinary property names are returned lowercase;
- custom property names preserve exact decoded casing.

Edits preserve authored property-name spelling when the declaration already
exists. Appended ordinary properties serialize lowercase because there is no
authored spelling to preserve. Appended custom properties preserve exact casing.

Appended property names must be valid plaintext CSS property names. The processor
does not escape arbitrary strings into identifiers. Custom properties follow the
same validity rule with the required `--` prefix.

## Value Validation

Mutation inputs accept CSS declaration values, not declaration-list text.

The supplied value must fit safely in both of these declaration slots, depending
on the property being changed:

```css
foo: <value>;
--foo: <value>;
```

Validation checks structure and CSS syntax safety, not property-specific browser
support. The processor should not maintain a browser-style matrix of supported
properties and value grammars.

Values must be complete and self-contained. Reject inputs that:

- are empty after CSS whitespace trimming, except `set_value( '' )`, which
  removes the current declaration;
- contain top-level declaration separators such as semicolons;
- contain a top-level `!important`, because priority is a separate argument;
- contain bad strings or bad URLs;
- contain unmatched delimiters or constructs that are valid only because CSS
  closes them at EOF;
- cannot be proven to occupy only the declaration value slot.

Nested semicolons and `!important` text inside balanced blocks, functions,
strings, or URLs are allowed when the tokenizer and parser say they are part of
the value.

## Importance

`set_value( $value, null )` preserves the existing important flag.
`set_value( $value, true )` sets it.
`set_value( $value, false )` clears it.

`append_declaration()` receives importance as a separate boolean and rejects a
top-level `!important` in `$value`.

`set_important()` changes only declaration priority.

When clearing importance, the processor may remove only the parsed priority span
when that is safe. If minimal removal is unsafe, it may normalize the current
declaration as long as it preserves CSS structure and the existing value's
semantics. If that cannot be proven, it returns `false`.

When setting importance, the processor adds canonical ` !important` at the
parsed value end or normalizes the declaration if necessary. It must verify that
the updated declaration reparses as important.

## Mutation Atomicity And Verification

Invalid or inapplicable mutations return `false` without `_doing_it_wrong()`.

Failed mutations are atomic: style text, cursor state, and previous successful
edits are preserved.

A mutation returning `true` guarantees that `get_updated_style()` includes it.

Every successful mutation must preserve the expected declaration-list structure.
`set_value()` must not let an input value merge, split, hide, or otherwise change
declarations beyond the intended current declaration.

The public contract describes guarantees, not the exact validation mechanism.
Synthetic declarations, parser comparisons, token-level checks, and reparsing are
implementation details.

Multiple mutations to the same logical declaration collapse to the final
intended state. Mutations to different declarations in one pass are allowed when
each mutation can be independently verified against the updated declaration
list.

## Formatting And Normalization

The processor preserves surrounding text, comments, ignored invalid fragments,
and authored spelling when cheap and safe.

Minimal edits are a lower priority than safety, correctness, preserved CSS
structure, and semantic preservation. Any normalization required to satisfy
those higher priorities is allowed, but normalization that rewrites an existing
declaration value must be strict: it must serialize from parsed token/component
data with equivalent CSS semantics. If equivalence cannot be proven, the
mutation returns `false`.

`set_value()` trims supplied values according to CSS whitespace.

When possible, `set_value()` replaces the current declaration's value and
priority portion rather than normalizing the whole declaration. Correctness wins
over whitespace preservation.

## Invalid Fragments

Declaration discovery follows CSS semantics. If CSS would ignore text as a
declaration in a style attribute declaration-list context, the processor does
not expose it as a declaration.

Ignored invalid fragments are preserved where possible. Mutations touching a
style containing invalid chunks must verify that the declaration-list structure
remains safe. If preservation and a requested mutation are incompatible, the
mutation returns `false` or normalizes only the range necessary to preserve
semantics and structure.

## Append And EOF Repair

`append_declaration()` may succeed even when trailing text is malformed, but only
when the processor can preserve existing CSS semantics.

When trailing declaration text is valid only because CSS closes an unclosed
function or block at EOF, appending must materialize the missing closing
delimiters before inserting the new declaration.

Example:

```css
color: var(--x
```

Appending `background: white` should produce a structurally safe equivalent such
as:

```css
color: var(--x); background: white;
```

EOF repair is part of a successful append and appears in `get_updated_style()`.
Repair should insert only the specific missing delimiters discovered from parser
or tokenizer state, plus the boundary needed before the appended declaration. If
the processor cannot determine a precise repair, append returns `false`.

EOF repair is essential for append. `set_value()` replacement values must be
self-contained and do not receive EOF repair.

## Test Requirements

Tests should cover:

- private construction and `create()` behavior;
- decoded string input boundary;
- declaration traversal, duplicate preservation, and property-name matching;
- getter `null` behavior off-cursor;
- absence of public raw value getter in the first API;
- `set_value()` priority preservation, setting, clearing, and empty-value
  removal;
- `set_important()` setting, clearing, cursor behavior, and safe normalization;
- rejection of unsafe values and property names with atomic failure;
- append behavior, duplicate appends, exhausted cursor behavior, and empty-value
  rejection;
- EOF repair for append and failure when repair cannot be precise;
- invalid fragments, comments, adjacent declarations, and removal ranges;
- preservation of authored spelling where required;
- integration with `WP_HTML_Tag_Processor::set_attribute()` after callers supply
  decoded string input.
