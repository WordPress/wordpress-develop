#!/usr/bin/env bash
#
# Temporary: reports whether CommonCrawl serves this runner, to tell whether a 403 for WARC records comes from a block on
# this address after too many requests, or from a refusal of byte-range requests for records from this network.
#
# Requests one known WARC record by range, the same file without a range (stopped once its size is known), and a range of
# the URL index, with the same User-Agent as cc-analyzer. Prints each status, the CDN's headers, and the start of any
# response which is not a success.

set -u

data='https://data.commoncrawl.org'
record="$data/crawl-data/CC-MAIN-2026-43/segments/1791170054432.19/warc/CC-MAIN-20261010041422-20261010071422-00381.warc.gz"
index="$data/cc-index/collections/CC-MAIN-2026-43/indexes/cluster.idx"

probe() {
	local label=$1
	shift
	local body
	body=$( mktemp )

	curl -sS -o "$body" -D "$body.headers" -A 'cc-analyzer/0.1 PHP-cURL' --max-time 5 --max-filesize 65536 \
		-w "${label}: HTTP %{http_code}, %{size_download} bytes\n" "$@" || true
	grep -iE '^(server|x-cache|x-amz-cf-pop|retry-after|content-type):' "$body.headers" | sed 's/^/  /'
	if ! grep -qE '^HTTP/[0-9.]+ 2' "$body.headers"; then
		head -c 600 "$body" | sed 's/^/  | /'
		echo
	fi

	rm -f "$body" "$body.headers"
}

probe 'WARC record by range' -r 5766330-5777984 "$record"
probe 'WARC file without a range' "$record"
probe 'URL index by range' -r 0-4095 "$index"
