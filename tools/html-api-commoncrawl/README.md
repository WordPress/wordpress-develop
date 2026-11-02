# HTML API CommonCrawl analysis

Compares how the HTML API parses real-world documents with and without a pull request. It fetches a random sample of HTML documents from the latest [CommonCrawl](https://commoncrawl.org/) crawl, then replays the same documents through both checkouts to measure:

- how long the Tag Processor and the HTML Processor take to parse each document, and how long `wp_kses( $html, 'post' )` takes on it, for all documents and for small, medium, and large ones;
- which documents the HTML Processor does not support, and why it stopped on them, with a few example documents for each reason.

The [`HTML API CommonCrawl Analysis`](../../.github/workflows/html-api-commoncrawl.yml) workflow runs this for a pull request when:
- the `html-api-commoncrawl` label is added to the pull request, if it changes the HTML API or `kses.php`. The label is removed straight away, so add it again to run another analysis.
- or it is run manually from the Actions tab with a pull request number.

The report is left as a comment on the pull request. Each new analysis replaces the previous comment.

## Files

- `cc-analyzer.phar`: [cc-analyzer](#rebuilding-cc-analyzerphar), which fetches documents from CommonCrawl into a local batch, and replays a batch through an analysis script. It streams whole WARC files and keeps their HTML responses. That takes only a handful of requests: CommonCrawl blocks runners which send many, such as one per record. It honors CommonCrawl's rate limiting, and if a download still fails, it lists the failure and the analysis runs on the documents it has.
- `bootstrap.php`: loads the HTML API, and what `wp_kses()` needs, from the `src/` directory in `HTML_API_BENCH_WP_SRC`, without loading WordPress. It presents a UTF-8 site at example.com. When a change adds a file to the HTML API, add it to the list of files here.
- `benchmark.php`: an analysis script which times the Tag Processor, the HTML Processor, and `wp_kses( $html, 'post' )` on each document and writes `benchmark.csv`.
- `analysis.php`: an analysis script which records whether the HTML Processor supports each document and writes `analysis.csv`.
- `report.php`: compares the results from two checkouts and prints the Markdown report.

## Running locally

This needs PHP 8.5, as used by the workflow, with the `curl`, `pdo_sqlite`, and `zlib` extensions. Each analysis script runs in a separate process for each checkout, because PHP cannot load two versions of the HTML API into one process.

```sh
# Fetch 1,000 documents, or as many as can be fetched in 5 minutes. The workflow uses --limit all instead, to keep every
# HTML document in the WARC files it downloads.
php -d memory_limit=1G tools/html-api-commoncrawl/cc-analyzer.phar --workspace /tmp/cc batch fetch corpus \
	--resource-types html --limit 1000 --limit-warcs 1 --max-jobs 1 --http-retries 6 --duration 5m --progress text

# Time each document three times with each checkout, and record support once.
for checkout in base merge; do
	for round in 1 2 3; do
		HTML_API_BENCH_WP_SRC="/path/to/$checkout/src" CC_ANALYZER_OUTPUT_DIR="/tmp/results/$checkout/round-$round" \
			php -d memory_limit=1G tools/html-api-commoncrawl/cc-analyzer.phar --workspace /tmp/cc \
			batch run corpus tools/html-api-commoncrawl/benchmark.php
	done

	HTML_API_BENCH_WP_SRC="/path/to/$checkout/src" CC_ANALYZER_OUTPUT_DIR="/tmp/results/$checkout/analysis" \
		php -d memory_limit=1G tools/html-api-commoncrawl/cc-analyzer.phar --workspace /tmp/cc \
		batch run corpus tools/html-api-commoncrawl/analysis.php
done

php tools/html-api-commoncrawl/report.php /tmp/results/base /tmp/results/merge > report.md
```

A batch can be replayed as many times as needed without fetching it again.

## Settings

The workflow reads these environment variables. When it is run manually, each one can be set from an input of the same name.

| Variable | Default | Meaning |
| --- | --- | --- |
| `HTML_API_BENCH_WARCS` | `1` | How many WARC files to download. Every HTML document in them is analyzed; each file holds around 20,000. |
| `HTML_API_BENCH_FETCH_DURATION` | `30m` | Longest time to spend fetching. Fetching stops at whichever limit is reached first. |
| `HTML_API_BENCH_ROUNDS` | `3` | How many times to time each document with each checkout. Each document's time is its median. |
| `HTML_API_BENCH_SMALL_MAX_BYTES` | `32768` | Documents smaller than this are small. |
| `HTML_API_BENCH_LARGE_MIN_BYTES` | `262144` | Documents at least this large are large. Everything between is medium. |

## Rebuilding `cc-analyzer.phar`

`cc-analyzer.phar` was built from cc-analyzer commit `57bfbca` (branch `index-sampled-fetch`) with `tools/build-phar.php`. Before building, the default HTTP identity was removed so that requests carry only a generic `User-Agent` and no `From` header:

- `src/Http/CurlStreamClient.php`: `DEFAULT_USER_AGENT` set to `'cc-analyzer/0.1 PHP-cURL'` and `DEFAULT_CONTACT_HEADER` set to `''`.
- `src/Cli/Application.php`: the `--user-agent` and `--contact-email` defaults set to the same values, and the help text to match.

To rebuild, make the same changes in a copy of cc-analyzer and run:

```sh
php -d phar.readonly=0 tools/build-phar.php --output /path/to/wordpress-develop/tools/html-api-commoncrawl/cc-analyzer.phar
```
