# Upstream Reference

- URL: https://github.com/automerge/automerge
- Reference scope: Automerge document/change format
- Local checkout: `upstream/automerge` submodule

## Canonical Upstream Test Commands

The JavaScript package declares this canonical test script in
`upstream/automerge/javascript/package.json`:

```bash
cd upstream/automerge/javascript && npm test
```

The Rust core crate test command for the Automerge implementation is:

```bash
cd upstream/automerge/rust && cargo test -p automerge
```

The first PHP checkpoint maps behavior from
`upstream/automerge/javascript/test/basic_test.ts` and
`upstream/automerge/javascript/test/text_test.ts`. See
`tests/upstream-test-map.md` for row-level mapping.

## Benchmark Denominator

The full upstream benchmark checkpoint is recorded in:

- `UPSTREAM_BENCHMARK.json`
- `UPSTREAM_BENCHMARK.md`
- `UPSTREAM_TEST_MANIFEST.json`

The older static audit denominator from commit `7f6a950` estimated 724 upstream
entries while the upstream runners were blocked. That estimate is now
superseded by current runtime benchmark artifacts:

- JavaScript: `npm test` reports 312 passing and 1 pending entry.
- Rust: `cargo test -p automerge` reports 368 passing and 7 ignored entries.

The active correctness denominator is therefore 680 runtime-passing upstream
tests. The registered manifest total is 688 entries when the upstream pending
and ignored rows are included. `PORTING_STATUS.json` reports active progress
against 680 and registered manifest coverage against 688.
