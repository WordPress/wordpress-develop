# Contrarian Upstream Test Audit: Automerge

Generated: 2026-05-23T00:23:00Z

## Verdict

`corrected-runtime-denominator`: the old May 21 static estimate of 724 upstream
tests is superseded by current runtime upstream runner evidence.

Current Automerge PHP status is not relying on the old fallback denominator. It
reports:

- Active upstream parity: `680/680`
- Registered manifest parity: `688/688`
- Optional upstream-pending coverage: `1/1`
- Optional upstream-ignored coverage: `7/7`
- WordPress scenarios: `10/10`

I do not find missing rows against the current runtime-derived manifest. The
old 724 estimate should not be used as the active denominator.

## Upstream Checkout

- Upstream submodule: `upstream/automerge`
- Pinned commit: `44cd91582bd3ed9af05ef1a7843bb1074ad11112`
- JavaScript package: `upstream/automerge/javascript/package.json`
- Rust workspace: `upstream/automerge/rust/Cargo.toml`

## Runner Evidence

Canonical commands:

```bash
cd upstream/automerge/javascript && npm test
cd upstream/automerge/rust && cargo test -p automerge
```

The current runtime evidence is recorded in:

- `UPSTREAM_BENCHMARK.json`
- `UPSTREAM_BENCHMARK.md`
- `UPSTREAM_TEST_MANIFEST.json`
- `artifacts/upstream-benchmark/javascript-npm-test-current.txt`
- `artifacts/upstream-benchmark/rust-cargo-test-current.txt`

I reran both canonical test commands during this audit. Results matched the
stored benchmark evidence:

| Surface | Active Passing | Pending/Ignored | Registered | Result |
| --- | ---: | ---: | ---: | --- |
| JavaScript mocha suite | 312 | 1 pending | 313 | passed |
| Rust `automerge` crate | 368 | 7 ignored | 375 | passed |
| Combined runtime manifest | 680 | 8 | 688 | passed |

`UPSTREAM_TEST_MANIFEST.json` agrees with those counts:

- `activeTotal: 680`
- `registeredTotal: 688`
- `portedPassing: 680`
- `portedPendingPassing: 1`
- `portedIgnoredPassing: 7`
- `todoUnported: 0`

## What Changed Since The May 21 Audit

The May 21 audit used static grep-style counting because JavaScript and Rust
upstream runners were blocked in that environment. That estimate counted 724
entries. The current repo now has installed JS dependencies and a working Rust
toolchain, and `UPSTREAM.md` explicitly says the static estimate is superseded.

The current denominator is based on actual upstream runner output, not a static
scan. Runtime active tests are the correct progress denominator. Pending and
ignored upstream rows remain registered separately and are also mapped by
optional PHP parity tests.

## Optional Rows

The one JavaScript pending row is mapped:

- `upstream/automerge/javascript/test/sync_test.ts:672` `should sync three nodes`

The seven Rust ignored doctests are mapped:

- `storage::document::Document::parse`
- `storage::parse`
- `storage::parse` line 56
- `storage::parse` line 69
- `storage::parse::Input::split`
- `storage::parse::Split::remaining`
- `storage::parse::range_of`

## Recommendation

Keep the current status model:

- `totalKnownUpstreamTests: 680`
- `mappedUpstreamTests: 680`
- `registeredTotalUpstreamTests: 688`
- `registeredMappedUpstreamTests: 688`

Do not restore the stale 724 denominator unless a future upstream runner
execution reports additional active or registered rows. If that happens, the
manifest should identify the exact new upstream row IDs before changing the
coordinator progress report.
