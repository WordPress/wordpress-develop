# Plugin compatibility tests

Checks that plugins from the WordPress.org directory can be activated against a version of WordPress without fataling. The Plugin Compatibility Tests workflow runs these scripts against the most popular plugins. The same scripts run locally, which is how to test far more plugins than CI should.

| Script | What it does |
| --- | --- |
| `test-plugins.sh` | Tests a list of plugins against one version of WordPress. This is what each workflow shard runs. |
| `list-plugins.sh` | Prints plugin slugs from the directory: the most popular, all of them, or those updated since a date. |
| `compare-results.sh` | Compares a baseline run with a candidate run and reports the plugins that regressed. |
| `test-all-plugins.sh` | Runs all of the above for a large list, in parallel, resumable. |
| `fixtures/` | Plugins built to break in one specific way each, with the result each must get. |

All of them need Docker. The list scripts also need curl and jq.

## Comparing against a baseline

Across the whole directory, thousands of plugins fail on a current PHP whatever version of WordPress they run on. A list of failures from one run is mostly noise. What matters before a release is the plugins that **pass on the current release and fail on the next one**.

So a large run tests every plugin twice, and compares:

```
plugins.txt ──┬── test-plugins.sh --wp-version=latest  ──► latest.tsv  ──┐
              │                                                          ├── compare-results.sh ──► report.md
              └── test-plugins.sh --wp-version=7.2-RC1 ──► 7.2-RC1.tsv ──┘
```

The report lists plugins that regressed (passed on the baseline, failed on the candidate) and plugins the candidate fixed. Plugins that fail on both are counted but not listed. A plugin whose version changed between the two runs is flagged, since the plugin changed too.

## Testing the whole directory

```
# The 5,000 most popular plugins, 8 at a time, against 7.2-RC1 compared with the current release.
tools/plugin-compatibility/test-all-plugins.sh --count=5000 --wp-version=7.2-RC1 --jobs=8

# Every plugin updated in the last two years.
tools/plugin-compatibility/test-all-plugins.sh --updated-since=2024-11-01 --wp-version=7.2-RC1 --jobs=8

# Everything.
tools/plugin-compatibility/test-all-plugins.sh --all --wp-version=7.2-RC1 --jobs=8
```

Everything is written to `plugin-compatibility-results/`: the plugin list, a results file and log per copy, the download cache, and `report.md`. If the run is interrupted, run the same command again to carry on where it stopped. Pass an exact version rather than `nightly` for a run that may be resumed on another day, because nightly moves.

Rough sizes, at the roughly 7 seconds per plugin measured on the most popular plugins. Smaller plugins are likely quicker.

| List | Plugins | One version, 8 jobs | Baseline and candidate, 8 jobs |
| --- | --- | --- | --- |
| `--count=1000` | 1,000 | 15 minutes | 30 minutes |
| `--updated-since` two years ago | about 42,000 | 10 hours | 20 hours |
| `--all` | about 74,000 | 18 hours | 36 hours |

Each job runs its own database and PHP containers, so memory is usually what limits `--jobs`.

## Testing a few plugins

```
tools/plugin-compatibility/test-plugins.sh --wp-version=nightly woocommerce classic-editor
```

Run `test-plugins.sh --help` for every option, including `--results` and `--cache-dir` for long runs and `--no-docker` to use a PHP, WP-CLI and database you already have.
