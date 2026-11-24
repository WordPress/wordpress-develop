# PHPUnit Tests

PHPUnit is the official testing framework chosen by the core team to test PHP code.

For more information, please review the relevant Core Handbook pages:
- [PHP: PHPUnit](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [Writing PHP Tests](https://make.wordpress.org/core/handbook/testing/automated-testing/writing-phpunit-tests/)

## Run on a host that prohibits loopback listening

Set `WP_TESTS_SKIP_HTTP_LOOPBACK=1` in the PHPUnit process environment if the host prohibits listening on a local TCP port. When PHP cannot reserve a port, PHPUnit skips only the HTTP transport tests that need the local fixture server. If PHP reserves a port, the tests run normally and server startup errors still fail.

## Run the full matrix on a pull request

Add the `Full PHPUnit Matrix` label to a pull request in `WordPress/wordpress-develop` to run the same PHP and database combinations as the weekly scheduled run. The label-triggered workflow uses the same path filters as normal PHPUnit runs.

If you cannot apply labels, leave a PR comment asking a reviewer with triage access to add it. Fork PRs follow GitHub's usual workflow approval requirements.

Adding the label starts a separate run without another code push. An existing reduced-matrix run continues alongside it; its checks and results are preserved. New commits run the full matrix while the label remains. Remove and reapply it to request another run for the current revision, or remove it to return subsequent runs to the reduced matrix.

Unrelated labels skip only the label-triggered workflow's `full-matrix` job and do not replace or cancel PHPUnit checks.

Look for `PHPUnit Tests (full matrix)` in GitHub Actions and the PHPUnit checks on the PR. Each run tests the PR's merge commit and retains its own logs and results, so check the revision before relying on an older run.

## Find state that tests leak

A test or test class that changes shared state and does not restore it can make later tests pass or fail depending on the order they run in. `tests/phpunit/leaks/` holds an opt-in PHPUnit listener that snapshots state between tests and classes, and a script that reports what changed.

It snapshots database rows, schema and `AUTO_INCREMENT` counters, temporary tables, the object cache (including the keys on memcached servers), cached options that disagree with the database, globals, static properties and variables, constants, ini settings, environment variables, other process state such as the error handler, and files under `wp-content` and the temporary directory. A process-isolated test's child process is snapshotted too, if the test has `@preserveGlobalState disabled` as core's do: only the state that outlives it (database, files, memcached, stale memcached options). It needs PHPUnit 9 or earlier: PHPUnit 10 removed test listeners.

Run the tests with the leak configuration (`tests/phpunit/leaks/multisite.xml` for multisite), then compare the snapshots:

```
node ./tools/local-env/scripts/docker.js run --rm php ./vendor/bin/phpunit -c tests/phpunit/leaks/phpunit.xml --filter Tests_Option
node ./tools/local-env/scripts/docker.js run --rm php php tests/phpunit/leaks/diff.php tests/phpunit/build/leaks/snapshots.jsonl
```

The report lists what each test and class leaves behind, i.e. the state that differs before and after it. Cache entries it drops are left out: a miss reads the source again. It exits with 2 if it reports anything, 1 if the run did not finish or ran no test, else 0. Each run replaces the snapshots file. Snapshots take time and space, e.g. 421 MB for `Tests_Multisite_Site`: run one class or group per-test, not the whole suite.

To sweep the whole suite, use the `class` scope: it snapshots at class bounds only, by default only the state that outlives the PHP process (database, files, memcached, stale cached options). `diff.php` then lists each class that leaves that state unlike the install:

```
node ./tools/local-env/scripts/docker.js run --rm -e WP_TESTS_LEAKS_SCOPE=class php ./vendor/bin/phpunit -c tests/phpunit/leaks/multisite.xml
```

To find out why a class only fails after others, run it alone and in sequence, each with its own `WP_TESTS_LEAKS_OUT` and without `WP_TESTS_LEAKS_SCOPE`, then compare the state it starts with. Keys that differ between the two runs before any class ran, e.g. timestamps, are run noise and left out:

```
node ./tools/local-env/scripts/docker.js run --rm -e WP_TESTS_LEAKS_OUT=/var/www/tests/phpunit/build/leaks/alone.jsonl php ./vendor/bin/phpunit -c tests/phpunit/leaks/phpunit.xml --filter Tests_Class_Name
node ./tools/local-env/scripts/docker.js run --rm -e WP_TESTS_LEAKS_OUT=/var/www/tests/phpunit/build/leaks/sequence.jsonl php ./vendor/bin/phpunit -c tests/phpunit/leaks/phpunit.xml --filter 'Tests_Earlier_Class|Tests_Class_Name'
node ./tools/local-env/scripts/docker.js run --rm php php tests/phpunit/leaks/diff.php tests/phpunit/build/leaks/alone.jsonl tests/phpunit/build/leaks/sequence.jsonl Tests_Class_Name
```

`tests/phpunit/leaks/rules.php` sets what is snapshotted: per scope, a list of patterns over the snapshot keys (`area|key`, e.g. `static|WP_Theme_JSON_Resolver::*`; `!` negates, the last match wins), and per test file, rules adding or dropping patterns, each saying why. A file no pattern keeps, e.g. one rule `!*`, is not snapshotted. The listener checks the file before any test runs, and stops on an unknown area, a rule missing its reason, or a path matching no file.

The leak configurations pass the listener its settings: `rules`, the rules file from the repo root, and `scope`, `test` or `class`. Environment variables win over them when set:

* `WP_TESTS_LEAKS_RULES`: rules file.
* `WP_TESTS_LEAKS_SCOPE`: `test` or `class`.
* `WP_TESTS_LEAKS_AREAS`: comma-separated patterns replacing the scope's defaults in the rules file; its per-file rules still apply. Takes shorthands: `all`, `persistent`, `db`, `cache`, `globals`, `statics`, `runtime`, `files`, e.g. `statics,globals` or `cache,!stale`.
* `WP_TESTS_LEAKS_OUT`: snapshots file, default `tests/phpunit/build/leaks/snapshots.jsonl`.

To check the test classes a branch adds or changes:

```
classes=$(git diff --name-only --diff-filter=AM trunk...HEAD -- 'tests/phpunit/tests/*.php' | xargs grep -ho '^class [A-Za-z0-9_]*' | cut -d' ' -f2 | paste -sd'|' -)
node ./tools/local-env/scripts/docker.js run --rm php ./vendor/bin/phpunit -c tests/phpunit/leaks/phpunit.xml --filter "/^($classes)::/"
node ./tools/local-env/scripts/docker.js run --rm php php tests/phpunit/leaks/diff.php tests/phpunit/build/leaks/snapshots.jsonl
```

The `leak-probe` test suite leaks one kind of state per test, to check the listener sees each, and that the probe rules in `rules.php` drop and skip what they say. `check-probes.php` exits with 1 if the report misses one, or lists state it must not, e.g. state that dies with an isolated child. It reads the run's setup (multisite, PHP version, persistent object cache, scope) from the snapshots file:

```
node ./tools/local-env/scripts/docker.js run --rm php ./vendor/bin/phpunit -c tests/phpunit/leaks/phpunit.xml --testsuite leak-probe
node ./tools/local-env/scripts/docker.js run --rm php php tests/phpunit/leaks/check-probes.php tests/phpunit/build/leaks/snapshots.jsonl
```

The suite runs only when named, except on PHPUnit 5, which ignores `defaultTestSuite`: run `--testsuite default` and `--testsuite restapi-autosave` there, each with its own `WP_TESTS_LEAKS_OUT`, and diff each file. With a persistent object cache, `tear_down()` flushes it, so the cached option probes leave nothing.
