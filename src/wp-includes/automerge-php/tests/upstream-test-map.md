# Upstream Test Map

Reference checkout: `upstream/automerge`.

Canonical upstream commands discovered for this checkpoint:

```bash
cd upstream/automerge/javascript && npm test
cd upstream/automerge/rust && cargo test -p automerge
```

The first mapped PHP slice targets user-visible materialization behavior from
the JavaScript test suite before binary change decoding is ported. The PHP API
is not pretending to be the JavaScript proxy API; each row maps the upstream
behavior into native PHP method calls.

| PHP test | Upstream source |
| --- | --- |
| init clone and free creates independent PHP document values | `javascript/test/basic_test.ts:12` `should init clone and free` |
| root map set/read materializes PHP array in insertion order | `javascript/test/basic_test.ts:44` `handle basic set and read on root object` |
| root map delete keeps only the live property across repeated changes | `javascript/test/basic_test.ts:66` `should be able to insert and delete a large number of properties` |
| root map overwrites retain the last scalar value | `javascript/test/basic_test.ts:183` `handle overwrites to values` |
| object values materialize through root map set | `javascript/test/basic_test.ts:200` `handle set with object value` |
| list creation materializes an empty PHP list | `javascript/test/basic_test.ts:210` `handle simple list creation` |
| simple list values can be read and replaced | `javascript/test/basic_test.ts:216` `handle simple lists` |
| text insertion exposes length, index access, and string materialization | `javascript/test/text_test.ts:17` `should support insertion` |
| text deletion removes the visible character at the splice range | `javascript/test/text_test.ts:25` `should support deletion` |
| text zero-length splice after deletion is a no-op | `javascript/test/text_test.ts:36` `should support implicit and explicit deletion` |
| concurrent text insertion deterministically preserves both branches | `javascript/test/text_test.ts:48` `should handle concurrent insertion` |
| text and scalar root operations can happen in the same document state | `javascript/test/text_test.ts:60` `should handle text and other ops in the same change` |
| document JSON encoding serializes text as a plain string | `javascript/test/text_test.ts:70` `should serialize to JSON as a simple string` |
| text can be modified after assignment to a document root key | `javascript/test/text_test.ts:77` `should allow modification after an object is assigned to a document` |
| unicode text values survive root materialization | `javascript/test/text_test.ts:115` `should support unicode when creating text` |
| from initializes text values with string length and index access | `javascript/test/text_test.ts:95` `should initialize text in Automerge.from()` |
| from encodes the initial root as one replayable native change | `javascript/test/text_test.ts:105` `should encode the initial value as a change` |
| updateText merges non-overlapping replacements from concurrent actors | `javascript/test/text_test.ts:132` `should calculate a diff when updating text` |
| change metadata since heads matches decoded native changes | `javascript/test/basic_test.ts:300` `get change metadata` |
| emptyChange advances document heads with a new hash | `javascript/test/basic_test.ts:396` `should generate a hash` |
| hasHeads returns true for heads present in the document history | `javascript/test/basic_test.ts:685` `should return true if the document in question has all the heads` |
| hasHeads returns false for heads absent from another document | `javascript/test/basic_test.ts:692` `should return false if the document does not have the heads` |
| native save/load round trip hydrates a materialized document | `javascript/test/basic_test.ts:578` `can load a doc without checking the heads` |

Next mapping targets are full change encoding/applyChanges beyond initial root
snapshots, head/view history, nested object IDs, list splices, and Rust binary
save/load fixtures.
