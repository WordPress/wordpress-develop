# CSS tokenizer test corpus

This directory contains a third-party test corpus used for testing the WordPress CSS API tokenizer.

`css-test-cases.json` derives from the npm package
[`@rmenke/css-tokenizer-tests`](https://www.npmjs.com/package/@rmenke/css-tokenizer-tests)
by Romain Menke, MIT licensed (see `LICENSE`). The package source is on GitHub at
[romainmenke/css-tokenizer-tests](https://github.com/romainmenke/css-tokenizer-tests).

The 185 cases match version 1.3.0 of the package, whose test content is identical to
version 1.2.0. Version 1.4.0 adds 102 cases that are not in this file.

## Transform

The upstream corpus maps a case name to `{ css, tokens }`. Each upstream token has
`type`, `raw`, `startIndex`, `endIndex` and `structured`. This file keeps the case names,
`css`, `type` and `raw` unchanged and transforms the rest:

- `startIndex` and `endIndex` are byte offsets into the UTF-8 source. Upstream uses
  JavaScript string indexes (UTF-16 code units).
- `value` is `structured.value` flattened onto the token. Numeric values are the
  number's source text as a string (`"10e2"`, not `1000`); for dimension and
  percentage tokens the unit or `%` is removed. Tokens without structured data have
  `value: null`.
- `unit` is `structured.unit`, present on dimension tokens only.
- `numberType` is `structured.type` on number and dimension tokens. The upstream
  `structured.type` of hash tokens (`id` or `unrestricted`) and `signCharacter` are
  not kept.
- `normalized` is WordPress's expectation for `WP_CSS_Token_Processor::get_normalized_token()`:
  the token text after input preprocessing and escape decoding. Upstream has no such
  field.

Three cases carry WordPress's token expectations where the upstream package contradicts
CSS Syntax Level 3: `tests/ident/0007` (`-§`), `tests/ident/0008` (`-×`) and
`tests/fuzz/b69ece36-057f-4450-9423-a1661787bce6`. In each, a non-ASCII code point or
U+0000 (which preprocessing replaces with U+FFFD) is an ident code point, so the
hyphen or identifier before it continues as one ident token. Upstream splits them into
delim tokens.

## Updating

There is no conversion script. To update, take `testCorpus` from the package's
`index.cjs`, apply the transform above, set `normalized` for each token per the
specification, and update the version noted in this README.
