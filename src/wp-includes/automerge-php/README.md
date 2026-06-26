# Native PHP Port: automerge

This repository is the native PHP porting workspace for automerge.

- Upstream: https://github.com/automerge/automerge
- Reference: Automerge document/change format
- Initial focus: Binary/change decoding, map/list/text operations, and deterministic materialization in native PHP.

No process bridge, FFI, WASM runtime, sidecar service, or Node/Rust/C helper is allowed in the PHP implementation path.

## Upstream Benchmark

The pinned upstream runners now complete with the required Rust target and
wasm-bindgen version available on `PATH`. The active correctness denominator is
680 tests: 312 JavaScript Mocha tests plus 368 Rust `automerge` tests. The
registered manifest contains 688 entries, including 1 JavaScript pending test
and 7 Rust ignored tests.

## Commands

```bash
composer validate --no-interaction --no-check-publish
composer dump-autoload
composer test
```

Update `PORTING_STATUS.json` after every meaningful test run.

## Current Scope

This checkpoint implements a small native PHP document model for the first
mapped Automerge upstream slice:

- root map set/read/delete and scalar overwrite behavior
- nested object and simple list materialization
- text insertion, deletion, JSON serialization, Unicode materialization, and
  deterministic concurrent insertion ordering
- native in-process change capture and replay for initial document values
- `applyLocalEdit()` / `encodeUpdate()` / `merge()` for different Gutenberg
  paragraph blocks edited concurrently
- same-paragraph concurrent insertion through serialized Gutenberg post content
- one-sided start/end block insertion through the save-time adapter path
- one-sided deletion of an unchanged Gutenberg block
- native heads, metadata-since-heads, empty changes, and save/load payload
  round trips
- legacy changes API basics: empty diffs, getChanges, applyChanges, and
  reconstruction from native change records
- legacy list element and text object updates replayed through native change
  records
- empty-document sync protocol message generation and no-data reply suppression
- equal-head sync state tracking for documents that already share the same
  changes
- one-sided sync transfer from a data-bearing peer to an empty peer
- prior-sync-state convergence when one peer adds later commits
- concurrent-head tracking for independent diverged sync messages
- legacy `Automerge.from()` initialization coercion for maps, lists, strings,
  scalars, and explicit actor IDs
- legacy sequential immutability, no-conflict overwrites, no-op changes, and
  ignored existing-value updates
- legacy list element existing-value no-ops plus root map assignment,
  deletion, clone, and object-replacement behavior
- legacy root scalar type changes and empty string keys
- native DateTime values in maps and lists through change replay
- empty-change history entries and merged-head dependency metadata
- rejection of unsupported PHP assignment values before history changes
- replay of list assignment and root-key text splices through getChanges/applyChanges
- nested map assignment, object literals, and arbitrary-depth nested updates
- nested map replacement and primitive/map type changes
- nested map property deletion and root map-reference deletion
- sync-state acknowledgement checks with `hasOurChanges()`
- `patches.ts` map-update patch application on documents
- `patches.ts` list update, insertion, and deletion patch application
- `patches.ts` text splice and deletion patch application
- `patches.ts` map/list/text patch application against plain PHP arrays
- `patches.ts` plain-array increment, mark no-op, and deep document map update
- legacy list insertion, deletion, index assignment, and bulk-style updates
- legacy list assignment boundary checks for append versus out-of-range writes
- legacy nested object/list updates, list replacement, list element type
  changes, and arbitrary-depth list insertion
- legacy patch callback emission for root string and list assignment
- native Counter values for nested-map deletion and same-counter concurrent
  increment merges
- root-level same-field conflict reporting with deterministic winner
  materialization, including different-type and nested-map assignment conflicts
- causal root assignment merges that clear prior same-field conflicts
- legacy save/load actor reassignment, nested data, at-sign keys, root conflicts,
  and basic history snapshots
- legacy history change-message metadata for root/list updates
- native last-local-change decoding and grouped root assignment changes
- same-value root writes that intentionally resolve existing conflicts
- Automerge document patch application for native Counter increment patches
- Automerge document patch application for text mark and unmark metadata
- native document detection and plain PHP materialization helpers
- native head-based views over the local change log and editable view clones
- native text mark, unmark, marks, and marksAt API behavior
- repeated root writes for scalar, null, date, counter, and byte-array values
- basic object-id classification for root documents, arrays, and scalar values
- topological history traversal over native merged change logs
- native stats wrapper for change and operation counts
- sync acknowledgement state stops follow-up messages after convergence
- deterministic conflict metadata independent of merge order
- path-based text splice and updateText helpers preserve caller path arrays
- simple cursor and cursor-position helpers preserve caller path arrays
- native list/text indexOf helpers for materialized Automerge values
- backend materialization view for native PHP documents
- list convenience operations for splice, push, unshift, shift, and insertAt
- clone boundaries do not carry patch callbacks between port instances
- patch callback before/after metadata exposes matching document heads
- patch callback before/after metadata covers list deletion materialization
- nested property deletion emits callback metadata with native delete paths
- native head-to-head diff emits map/list/text insertion patches and validates heads
- reverse head-to-head diff reconstructs deleted string list values
- path-scoped diff supports nested map replacement and shallow recursion
- changeAt-style historical-head scalar updates preserve unrelated visible content
- text head-to-head diffs emit put/splice patch pairs in both directions
- native incremental save batches match saveSince from the last saved heads
- inspectChange exposes decoded change metadata and text operation shape
- toJS materializes each immutable document at its own heads
- ImmutableString/RawString value objects preserve scalar string identity
- sync state carries read-only metadata and can ignore incoming changes
- text splice can target string values nested inside arrays
- updateText diffs and merges multi-codepoint grapheme clusters as text units
- sync messages keep sending unacknowledged local changes after new edits
- failed change transactions roll back clone-local mutations
- server-side post updates can merge with editor edits on separate blocks

Binary change decoding, upstream-compatible save/load bytes, views, object IDs,
full list CRDT semantics, and Automerge's complete change format are not
implemented yet. Those are the next correctness targets; no bridge or sidecar
is used.
