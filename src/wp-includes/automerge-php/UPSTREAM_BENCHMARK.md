# Upstream Benchmark: Automerge

Generated: 2026-05-23T00:09:08Z

Upstream commit: `44cd91582bd3ed9af05ef1a7843bb1074ad11112`

## Result

Status: `passed-with-pending-ignored`

Count method: `runtime-runner-counts`

The canonical upstream runners now complete in this environment. Active
progress uses passing/runnable tests as the denominator, while the manifest also
registers upstream pending/ignored entries:

| Surface | Active | Pending/Ignored | Registered |
| --- | ---: | ---: | ---: |
| JavaScript mocha suite | 312 | 1 | 313 |
| Rust automerge crate | 368 | 7 | 375 |
| Combined | 680 | 8 | 688 |

`UPSTREAM_TEST_MANIFEST.json` contains 688 entries, of which
680 are active. Current PHP ported-passing entries:
680 active, 1 optional upstream-pending,
and 7 optional upstream-ignored.

## Runner Attempts

| Command | CWD | Status | Exit | Output |
| --- | --- | --- | ---: | --- |
| `cd upstream/automerge/javascript && npm run build` | `.` | `passed` | 0 | `artifacts/upstream-benchmark/javascript-npm-build-current.txt` |
| `cd upstream/automerge/javascript && npm test` | `.` | `passed-with-pending` | 0 | `artifacts/upstream-benchmark/javascript-npm-test-current.txt` |
| `cd upstream/automerge/rust && cargo test -p automerge` | `.` | `passed-with-ignored` | 0 | `artifacts/upstream-benchmark/rust-cargo-test-current.txt` |

## Runtime Artifacts

- `artifacts/upstream-benchmark/javascript-npm-build-current.txt`
- `artifacts/upstream-benchmark/javascript-npm-test-current.txt`
- `artifacts/upstream-benchmark/rust-cargo-test-current.txt`

## Notes

The benchmark derives counts from the current raw upstream artifacts: JavaScript npm test reported 312 passing, 0 failing, and 1 pending; Rust cargo test -p automerge reported 368 passing, 0 failing, and 7 ignored. Active progress uses the 680 active-test denominator, while the manifest registers 688 total units including pending/ignored entries.

Skipped JavaScript declarations excluded from the registered runtime total:
