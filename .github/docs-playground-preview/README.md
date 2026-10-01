# Code Reference Playground preview

This directory pins the inputs for the WordPress Core Code Reference preview. The implementation lives in `.github/scripts/docs-playground-preview`; GitHub Actions only supplies exact source identity, caching, and publication authority.

The preview is informational. Pull request source is parsed as data in a job with no secrets and `contents: read` at most. Build jobs use GitHub's cache service: a trusted `trunk` build can warm the shared cache, while GitHub scopes a pull-request-created entry to its pull request ref so it cannot warm or replace the default-branch cache. Only default-branch publisher and cleanup code receives repository write permissions for release assets, comments, labels, cache deletion, or the stable `trunk` pointer.

## Local build

Use the exact Node, npm, PHP, and Composer versions in `dependencies.json`, then run:

```sh
npm --prefix .github/docs-playground-preview run build
```

The command infers the current Git repository and commit, resolves the moving Playground `beta` channel, builds or restores the invariant site base, parses eligible PHP under `src`, imports the complete Code Reference, packages the snapshot, and runs the same behavioral validation as CI. Local output and cache data are written below `.cache/docs-playground-preview`.

CI passes explicit `--source-repository`, `--source-sha`, run identity, cache, output, and metadata paths to this same command. `--resolve-only` writes the concrete beta and exact cache identity before the cache restore step. Local and CI snapshots are equivalent when their symbol counts, representative routes, search, runtime constants, and size boundary agree; their bytes need not agree.

Run the script tests with:

```sh
npm test --prefix .github/docs-playground-preview
```

Type check the scripts with:

```sh
npm run typecheck --prefix .github/docs-playground-preview
```

The `Code Reference Playground Preview Tests` workflow runs both commands whenever a push or a pull request touches `.github/scripts/docs-playground-preview`, this directory, or the workflow itself. Type checking runs even when the tests fail, so a single run reports both.

## Updating pins

All repositories in `dependencies.json` must remain on the allowed repositories enforced by `.github/scripts/docs-playground-preview/lib/config.mjs`, and every repository revision must be a full immutable commit hash. Tool versions are exact. GitHub Actions are pinned to full commit hashes in the workflow files.

To update a pinned repository:

1. Change its full commit in `dependencies.json`. The dependency's own lock files come from that immutable checkout; this repository does not copy or re-verify them.
2. Run the script tests and a complete local build.
3. Exercise cold and warm builds in the staging repository.

Keep duplicated tool identities synchronized:

- For `@wp-playground/cli`, `yarn`, `@wordpress/scripts`, or `@wordpress/i18n`, update both `dependencies.json` and the exact dependency in `package.json`, then regenerate `package-lock.json` with the manifest's exact Node and npm versions. A Playground CLI update also requires changing its fixed version check in `.github/scripts/docs-playground-preview/lib/config.mjs`.
- For Node, update `dependencies.json`, `.nvmrc`, `package.json`'s `engines.node`, `@types/node` in `package.json`'s `devDependencies`, and every `setup-node` input in the build, publish, and lifecycle workflows. Select a Node distribution containing the npm version recorded in `dependencies.json`; the build rejects a different npm version.
- For PHP, update `dependencies.json`, the fixed runtime checks in `.github/scripts/docs-playground-preview/lib/config.mjs` and `.github/scripts/docs-playground-preview/lib/publication.mjs`, and both `setup-php` inputs in the build workflow. PHP remains `8.4` unless the specification changes.
- For Composer, update `dependencies.json` and both `setup-php` Composer tool specifications in the build workflow.
- For a GitHub Action, update its full commit SHA and the adjacent release comment in every workflow use.

After any tool change, run the script tests, regenerate the lockfile when applicable, complete a local build, and exercise cold and warm staging builds.

A manifest or harness change automatically produces a different exact cache key. Increment `cacheSchemaVersion` when the stored base layout or meaning changes incompatibly, even if no dependency pin changed. Broad restore keys are prohibited.

## Repository activation

The workflows run in `WordPress/wordpress-develop`. Trusted staging work in `sirreal/wordpress-develop` runs only when the repository Actions variable `DOCS_PREVIEW_STAGING` is exactly `true`. That variable controls `trunk` builds, PR and `trunk` publishers, comments, labels, stale and expired lifecycle handling, cleanup, cache deletion, and every other trusted mutation.

There is one deliberate staging exception: the untrusted `pull_request` build job is enabled by the hard-coded `sirreal/wordpress-develop` allowlist without consulting `DOCS_PREVIEW_STAGING`. GitHub does not expose the base repository's Actions variables to a fork's `pull_request` workflow run. Requiring the variable there would make the required fork preview impossible to stage. With staging disabled, explicitly labeling a PR may therefore consume isolated read-only CI and create a one-day handoff artifact, but it cannot publish, comment, change labels, delete caches, or perform another trusted mutation. Arbitrary forks remain inert. This user-approved exception to section 5 is confined to the staging repository and the untrusted build job.

The staging repository needs a `docs-preview` label and the Actions variables used for the scenario being tested. Disable trusted staging by deleting `DOCS_PREVIEW_STAGING` or setting it to anything other than the lowercase string `true`.

## Validation enforcement

Behavioral validation is advisory unless the repository Actions variable `DOCS_PREVIEW_ENFORCE` is exactly `true`. Advisory failures emit workflow warnings and terminal PR state but cannot publish or replace a snapshot. Fatal build, handoff, identity, digest, size, and publication failures always fail.

GitHub does not expose base-repository Actions variables to an untrusted fork `pull_request` job. Such a job therefore records a behavioral failure in its handoff without enforcing it locally. The trusted publisher reads `DOCS_PREVIEW_ENFORCE` from the base repository, updates the sticky comment without publishing the invalid candidate, and then fails its workflow when enforcement is enabled. Same-repository and `trunk` builds can read the variable directly and fail their build workflow as well.

To enable enforcement:

1. Open repository **Settings**.
2. Open **Secrets and variables → Actions → Variables**.
3. Create or update `DOCS_PREVIEW_ENFORCE` with the value `true`.

## Pull request lifecycle

The label name is exactly `docs-preview`, and only pull requests targeting `trunk` are eligible.

- Adding the label requests a complete build of the exact pull request head repository and SHA.
- A healthy public snapshot for the same SHA is reused. **Re-run all jobs** on the original build bypasses reuse.
- While the label remains, a synchronized head cancels the obsolete build and requests the newest SHA.
- The latest terminal publisher removes the label after success, validation failure, or build failure.
- A later unlabeled commit retains the latest successful link, marks it stale, and asks a maintainer to add the label again.
- Closing or merging deletes only that pull request's assets and `refs/pull/<number>/merge` docs caches, then marks the historical comment expired.

The sticky comment is separate from the general Core Playground comment and is identified by `<!-- code-reference-docs-preview -->`.

## Publication and cleanup

Snapshots and metadata are assets on the `code-reference-playground-preview` prerelease.

- Pull request snapshots use `code-reference-pr-<number>-<sha>-<run>-<attempt>.zip` with a same-stem JSON metadata asset. Only the latest healthy pair for an open pull request is retained.
- Trunk snapshots use `code-reference-trunk-<sha>-<run>-<attempt>.zip` with a same-stem JSON metadata asset.
- The stable trunk Blueprint is `code-reference-trunk.json` on the dedicated `docs-preview-code-reference` Git branch. The repository README points Playground at that stable raw URL.

Trunk publication uploads and publicly validates the immutable snapshot and metadata, creates and publicly validates an immutable Blueprint commit, then atomically moves the dedicated branch ref and re-reads its exact SHA. Only after that identity is proven are older trunk assets deleted. A failed or ambiguous ref mutation retains every snapshot the ref could identify.

PR cleanup matches only its exact PR prefix. Trunk cleanup matches only the trunk prefix. Neither path deletes assets owned by the other or changes another pull request.

## Recovery

Publication failure never requires deleting the current working asset first.

- For a PR, fix the cause and add `docs-preview` again, or use **Re-run all jobs** on the original build for a forced same-SHA rebuild.
- For `trunk`, re-run the newest trunk build or push the next commit. The next successful publisher moves the stable ref and removes orphaned trunk assets.
- If the publisher cannot determine whether the stable ref moved, it retains both old and candidate assets. Inspect the ref and the two metadata assets before removing anything manually.
- Do not delete the `docs-preview-code-reference` branch or the snapshot it names during recovery. A successful newest trunk run repairs the pointer safely.

## Bespoke validators

Pinned standard tools own Git, ZIP, SQLite, JSON, PHP, and process semantics. This implementation does not inspect archive grammar, database bytes, canonical serialization, descriptors, or process trees. The retained project-specific checks are limited to boundaries or product behavior that standard tools cannot understand on their own:

- **Dependency manifest and cache identity:** checks the repository allowlist, immutable commit shape, exact tool versions, concrete beta identity, and every base input. This protects the shared default-branch cache boundary.
- **Publisher handoff identity:** binds schema, deployment repository, event, PR when applicable, exact source SHA, run ID and attempt, runtime identity, filename, size, and digest before any publication. This protects the untrusted-to-trusted publication boundary.
- **Candidate file size and digest:** uses ordinary file stat and SHA-256 tools without opening the snapshot. This enforces the publisher identity and 100 MiB boundaries.
- **Parser product inspection:** uses the standard JSON parser, then enforces eligible Core source paths, supported hook types, nonempty records, per-type catastrophic-under-count floors, and the trusted importer root. These are required section 14 product and “parse, never execute” checks; they do not validate the parser's internal format beyond the fields the importer consumes.
- **Latest-wins authorization:** re-reads current PR or `trunk` state and the newest eligible workflow attempt immediately before publication mutations. This prevents an obsolete run from moving or deleting the live preview.
- **Public snapshot delivery:** fetches the immutable snapshot through the Playground CORS proxy and checks response status, CORS headers, byte size, and SHA-256. Publication is not complete until the public product has the validated identity.
- **Public metadata identity:** downloads the just-published JSON metadata, applies the same source/runtime/snapshot identity checks, and compares its parsed value with the candidate. This prevents a wrong public metadata asset from becoming the retained pointer identity.
- **Public Blueprint and stable ref identity:** parses the small Blueprint with the standard JSON parser, checks its semantic identity through the public proxy at an immutable commit, atomically moves the dedicated Git ref, and verifies that ref's SHA. This protects transactional availability without reimplementing Git semantics.
- **Behavioral snapshot validation:** boots the final snapshot through Playground and checks the required routes, search, provenance, and runtime policy. This is the section 14 product validator; it deliberately ignores intermediate archive and database representations.

No archive grammar, database format, canonical byte serialization, descriptor transaction, or process-tree validator is part of this pipeline.

## Staging evidence

Before upstream rollout, record links to the `sirreal/wordpress-develop` workflow runs and comments that demonstrate every scenario in the specification's staging acceptance gate. Evidence may be accumulated across runs. Keep the run URLs and observed SHA, cache state, comment state, public URL, route/search results, runtime policy, failure-preservation result, cleanup result, and measured public snapshot size in the eventual pull request description.
