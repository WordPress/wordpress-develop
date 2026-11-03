#!/usr/bin/env bash
##
# Prints plugin slugs from the WordPress.org directory, one per line: the most popular first, or with `--all`
# or `--updated-since`, the most recently updated first.
#
# This is where both the Plugin Compatibility Tests workflow and `test-all-plugins.sh` get their list from, so
# that a run on a developer's machine tests the same plugins, in the same order, as a workflow run would.
#
#     tools/plugin-compatibility/list-plugins.sh --count=1000 > plugins.txt
#     tools/plugin-compatibility/list-plugins.sh --all > plugins.txt
#     tools/plugin-compatibility/list-plugins.sh --updated-since=2024-11-01 > plugins.txt
#
# Requires curl and jq.
##

set -uo pipefail

COUNT=""
UPDATED_SINCE=""
# The API caps `per_page` at 250 and returns 250 without complaint for anything larger.
PAGE_SIZE=250

usage() {
	cat <<'USAGE_EOF'
Usage: list-plugins.sh --count=<n> | --all | --updated-since=<date> [--count=<n>]

Prints WordPress.org plugin slugs, one per line.

Options:
  --count=<n>               How many plugins to list.
  --all                     List every plugin in the directory, roughly 78,000 of them, most recently
                            updated first.
  --updated-since=<date>    List the plugins updated on or after <date>, written YYYY-MM-DD, most recently
                            updated first. Combine with --count to cap the list. A plugin nobody has updated
                            in years is likely to fail on a current PHP whatever version of WordPress it
                            runs on, so this is the more useful list once the most popular are covered.
  -h, --help                Print this message.
USAGE_EOF
}

error() {
	printf 'Error: %s\n' "${1}" >&2
	exit 1
}

while [ "$#" -gt 0 ]; do
	case "${1}" in
		--count=* ) COUNT="${1#*=}" ;;
		--all ) COUNT="all" ;;
		--updated-since=* ) UPDATED_SINCE="${1#*=}" ;;
		-h | --help ) usage; exit 0 ;;
		* ) usage >&2; error "Unknown option ${1}." ;;
	esac
	shift
done

if [ -n "${UPDATED_SINCE}" ]; then
	printf '%s' "${UPDATED_SINCE}" | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' \
		|| error "--updated-since must be a date written YYYY-MM-DD, got \"${UPDATED_SINCE}\"."
	COUNT="${COUNT:-all}"
	BROWSE="updated"
elif [ "${COUNT}" = "all" ]; then
	# Popularity is a poor order to page through all of: tens of thousands of plugins share the same rounded
	# install count, their order shifts from one page to the next, and paging to the end returns only about
	# 57,000 different plugins. Sorted by update, paging returns all of them, about 78,000.
	BROWSE="updated"
else
	BROWSE="popular"
fi

[ -n "${COUNT}" ] || { usage >&2; error "Pass --count, --all or --updated-since."; }

if [ "${COUNT}" != "all" ] && ! printf '%s' "${COUNT}" | grep -Eq '^[1-9][0-9]*$'; then
	error "--count must be a positive integer, got \"${COUNT}\"."
fi

for REQUIRED_COMMAND in curl jq; do
	command -v "${REQUIRED_COMMAND}" > /dev/null 2>&1 || error "${REQUIRED_COMMAND} is required."
done

PAGE_FILE="$( mktemp )"
RAW_FILE="$( mktemp )"
trap 'rm -f "${PAGE_FILE}" "${RAW_FILE}"' EXIT

PAGE=1
UNIQUE_COUNT=0

while : ; do
	# The unneeded response fields are turned off to keep the payload small.
	curl -sS --fail --retry 3 --retry-delay 5 \
		--get 'https://api.wordpress.org/plugins/info/1.2/' \
		--data-urlencode 'action=query_plugins' \
		--data-urlencode "request[browse]=${BROWSE}" \
		--data-urlencode "request[per_page]=${PAGE_SIZE}" \
		--data-urlencode "request[page]=${PAGE}" \
		--data-urlencode 'request[fields][short_description]=0' \
		--data-urlencode 'request[fields][sections]=0' \
		--data-urlencode 'request[fields][icons]=0' \
		--data-urlencode 'request[fields][banners]=0' \
		--data-urlencode 'request[fields][ratings]=0' \
		--data-urlencode 'request[fields][tags]=0' \
		--data-urlencode 'request[fields][compatibility]=0' \
		--data-urlencode 'request[fields][screenshots]=0' \
		-o "${PAGE_FILE}" || error "Page ${PAGE} could not be fetched from the WordPress.org API."

	PAGE_COUNT="$( jq '.plugins | length' "${PAGE_FILE}" )" || error "Page ${PAGE} was not valid JSON."

	# `last_updated` reads like "2024-11-25 3:02pm GMT", so its first ten characters compare as a date.
	jq -r --arg since "${UPDATED_SINCE}" \
		'.plugins[] | select( $since == "" or ( .last_updated // "" )[0:10] >= $since ) | .slug // empty' \
		"${PAGE_FILE}" >> "${RAW_FILE}"

	# Ordering can shift between two requests, so the same slug can turn up on more than one page. Only its
	# first appearance counts.
	UNIQUE_COUNT="$( awk 'NF > 0 && !seen[$0]++' "${RAW_FILE}" | wc -l | tr -d ' ' )"

	printf 'Page %s returned %s plugins, %s listed so far.\n' "${PAGE}" "${PAGE_COUNT}" "${UNIQUE_COUNT}" >&2

	if [ "${COUNT}" != "all" ] && [ "${UNIQUE_COUNT}" -ge "${COUNT}" ]; then
		break
	fi

	# A short page means the directory has nothing left to give.
	if [ "${PAGE_COUNT}" -lt "${PAGE_SIZE}" ]; then
		break
	fi

	# Sorted by update, a page ending before the cutoff means every page after it does too.
	if [ -n "${UPDATED_SINCE}" ] \
		&& [ "$( jq -r '.plugins[-1].last_updated // ""' "${PAGE_FILE}" | cut -c 1-10 )" \< "${UPDATED_SINCE}" ]; then
		break
	fi

	PAGE=$(( PAGE + 1 ))
done

[ "${UNIQUE_COUNT}" -gt 0 ] || error "The WordPress.org API did not return any plugins."

if [ "${COUNT}" = "all" ]; then
	awk 'NF > 0 && !seen[$0]++' "${RAW_FILE}"
	exit 0
fi

awk 'NF > 0 && !seen[$0]++' "${RAW_FILE}" | head -n "${COUNT}"

if [ "${UNIQUE_COUNT}" -lt "${COUNT}" ]; then
	printf 'Only %s plugins were available, fewer than the %s requested.\n' "${UNIQUE_COUNT}" "${COUNT}" >&2
fi
