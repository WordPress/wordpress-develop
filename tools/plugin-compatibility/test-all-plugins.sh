#!/usr/bin/env bash
##
# Tests a large list of plugins - up to the whole WordPress.org directory - against a baseline and a candidate
# version of WordPress, and reports the plugins that pass on the baseline and fail on the candidate.
#
# This is the run to make ahead of a major or security release, on a developer's machine or a spare server
# rather than in Actions: the whole directory is roughly 78,000 plugins, which at about 7 seconds each is far
# more runner time than a shared CI pool should give to one job.
#
#     tools/plugin-compatibility/test-all-plugins.sh --count=5000 --wp-version=7.2-RC1 --jobs=8
#     tools/plugin-compatibility/test-all-plugins.sh --updated-since=2024-11-01 --wp-version=7.2-RC1
#     tools/plugin-compatibility/test-all-plugins.sh --all --wp-version=7.2-RC1 --jobs=8
#
# It splits the list into --jobs parts and runs that many copies of test-plugins.sh side by side, each with
# its own containers, first against the baseline and then against the candidate. Every result is written to
# the output directory as it is recorded, so running the same command again after an interruption carries on
# where it stopped. Downloads are cached there too, so the candidate run does not download every plugin again.
#
# Needs Docker, curl and jq.
##

set -uo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" > /dev/null 2>&1 && pwd )"

LIST_ARGS=()
PLUGINS_FILE=""
WP_VERSION="nightly"
BASELINE_VERSION="latest"
JOBS=4
OUT_DIR="plugin-compatibility-results"
PASSTHROUGH_ARGS=()
# How many times a part is started again when it stops before testing everything, as long as each attempt
# gets further than the one before.
MAX_ATTEMPTS=3

usage() {
	cat <<'USAGE_EOF'
Usage: test-all-plugins.sh (--count=<n> | --all | --updated-since=<date> | --plugins-file=<file>) [options]

Tests many plugins against a baseline and a candidate version of WordPress, and reports the plugins that
pass on the baseline and fail on the candidate.

Which plugins:
  --count=<n>              The n most popular plugins.
  --all                    Every plugin in the directory.
  --updated-since=<date>   Plugins updated on or after <date>, written YYYY-MM-DD. Combine with --count to cap.
  --plugins-file=<file>    Slugs from a file, one per line.

Options:
  --wp-version=<ver>       The candidate: the version of WordPress being checked. Pass an exact version such as
                           7.2-RC1 for a run that may be resumed on another day, because nightly moves.
                           Default: nightly.
  --baseline=<ver>         The version to compare with, or "none" to only test the candidate. Default: latest.
  --jobs=<n>               How many copies to run side by side. Each runs its own database and PHP containers.
                           Default: 4.
  --out=<dir>              Where the plugin list, results, logs, download cache and report are written.
                           Default: plugin-compatibility-results.
  --php-image=<image>      Passed on to test-plugins.sh.
  --db-image=<image>       Passed on to test-plugins.sh.
  --timeout=<seconds>      Passed on to test-plugins.sh.
  -h, --help               Print this message.

The report is written to <out>/report.md. Exits 1 when a plugin regressed, and 0 otherwise.
USAGE_EOF
}

error() {
	printf 'Error: %s\n' "${1}" >&2
	exit 1
}

while [ "$#" -gt 0 ]; do
	case "${1}" in
		--count=* | --updated-since=* ) LIST_ARGS=( ${LIST_ARGS[@]+"${LIST_ARGS[@]}"} "${1}" ) ;;
		--all ) LIST_ARGS=( ${LIST_ARGS[@]+"${LIST_ARGS[@]}"} --all ) ;;
		--plugins-file=* ) PLUGINS_FILE="${1#*=}" ;;
		--wp-version=* ) WP_VERSION="${1#*=}" ;;
		--baseline=* ) BASELINE_VERSION="${1#*=}" ;;
		--jobs=* ) JOBS="${1#*=}" ;;
		--out=* ) OUT_DIR="${1#*=}" ;;
		--php-image=* | --db-image=* | --timeout=* ) PASSTHROUGH_ARGS=( ${PASSTHROUGH_ARGS[@]+"${PASSTHROUGH_ARGS[@]}"} "${1}" ) ;;
		-h | --help ) usage; exit 0 ;;
		* ) usage >&2; error "Unknown option ${1}." ;;
	esac
	shift
done

printf '%s' "${JOBS}" | grep -Eq '^[1-9][0-9]*$' || error "--jobs must be a positive integer, got \"${JOBS}\"."

if [ "${BASELINE_VERSION}" = "${WP_VERSION}" ]; then
	error "The baseline and the candidate are both ${WP_VERSION}."
fi

mkdir -p "${OUT_DIR}" || error "The output directory could not be created."
OUT_DIR="$( cd "${OUT_DIR}" > /dev/null 2>&1 && pwd )"
LIST_FILE="${OUT_DIR}/plugins.txt"

# The list is built once and kept, so that a resumed run, and the candidate run after the baseline, test
# exactly the same plugins even if the directory has changed in the meantime.
if [ -s "${LIST_FILE}" ]; then
	printf 'Using the %s plugins already listed in %s.\n' "$( grep -c . "${LIST_FILE}" )" "${LIST_FILE}"
elif [ -n "${PLUGINS_FILE}" ]; then
	grep -v '^[[:space:]]*#' "${PLUGINS_FILE}" | grep . > "${LIST_FILE}" || error "No plugins were listed in ${PLUGINS_FILE}."
elif [ "${#LIST_ARGS[@]}" -gt 0 ]; then
	"${SCRIPT_DIR}/list-plugins.sh" "${LIST_ARGS[@]}" > "${LIST_FILE}.partial" || error "The plugin list could not be built."
	mv "${LIST_FILE}.partial" "${LIST_FILE}"
else
	usage >&2
	error "Pass --count, --all, --updated-since or --plugins-file."
fi

printf 'Testing %s plugins with %s jobs, writing to %s.\n' "$( grep -c . "${LIST_FILE}" )" "${JOBS}" "${OUT_DIR}"

# Each copy's own interrupt trap cannot be relied on here: a background job in a script ignores SIGINT. So an
# interrupt stops the copies explicitly, and each one cleans up its containers on the way out.
RUNNING_PIDS=()

# shellcheck disable=SC2329 # Called from the traps below.
stop_jobs() {
	if [ "${#RUNNING_PIDS[@]}" -gt 0 ]; then
		kill -TERM "${RUNNING_PIDS[@]}" > /dev/null 2>&1
		wait "${RUNNING_PIDS[@]}" > /dev/null 2>&1
	fi
}

trap 'printf "\nStopping. Run the same command again to carry on.\n" >&2; stop_jobs; exit 130' INT
trap 'stop_jobs; exit 143' TERM

# remaining_plugins <results dir>
#
# Prints the listed plugins that have no result in any of the results files in <results dir>.
remaining_plugins() {
	# The leading line keeps the first input from ever being empty, which would make `NR == FNR` true for the
	# list as well and leave nothing to test.
	{ printf '#\n'; cat "${1}"/part-*.tsv 2>/dev/null; } | awk -F '\t' 'NR == FNR { done[$1] = 1; next } NF > 0 && !( $0 in done )' - "${LIST_FILE}"
}

# run_version <version>
#
# Tests every listed plugin against one version of WordPress, in parallel, and merges the results into
# <out>/<version>.tsv.
run_version() {
	VERSION="${1}"
	LABEL="$( printf '%s' "${VERSION}" | tr -c 'A-Za-z0-9.-' '_' )"
	RESULTS_DIR="${OUT_DIR}/${LABEL}"
	mkdir -p "${RESULTS_DIR}" || error "${RESULTS_DIR} could not be created."

	ATTEMPT=1
	REMAINING_COUNT="$( remaining_plugins "${RESULTS_DIR}" | grep -c . )"

	while [ "${REMAINING_COUNT}" -gt 0 ] && [ "${ATTEMPT}" -le "${MAX_ATTEMPTS}" ]; do
		printf '\nWordPress %s: %s plugins to test, attempt %s.\n' "${VERSION}" "${REMAINING_COUNT}" "${ATTEMPT}"

		# Dealt out like cards rather than cut into blocks, so that every part gets a share of the large,
		# slow plugins at the top of a popularity list.
		rm -f "${RESULTS_DIR}"/todo-*.txt
		remaining_plugins "${RESULTS_DIR}" | awk -v jobs="${JOBS}" -v dir="${RESULTS_DIR}" '{ print > ( dir "/todo-" ( ( NR - 1 ) % jobs + 1 ) ".txt" ) }'

		RUNNING_PIDS=()

		for TODO_FILE in "${RESULTS_DIR}"/todo-*.txt; do
			PART="$( basename "${TODO_FILE}" .txt )"
			PART="${PART#todo-}"

			"${SCRIPT_DIR}/test-plugins.sh" \
				--wp-version="${VERSION}" \
				--plugins-file="${TODO_FILE}" \
				--results="${RESULTS_DIR}/part-${PART}.tsv" \
				--cache-dir="${OUT_DIR}/cache" \
				${PASSTHROUGH_ARGS[@]+"${PASSTHROUGH_ARGS[@]}"} \
				>> "${RESULTS_DIR}/part-${PART}.log" 2>&1 &
			RUNNING_PIDS=( ${RUNNING_PIDS[@]+"${RUNNING_PIDS[@]}"} "$!" )

			# Staggered, so that the copies are not all downloading WordPress into the shared cache at once.
			sleep 5
		done

		# The copies exit 1 whenever a plugin fails, which is expected here. Progress is measured by what is
		# left, not by exit codes.
		wait "${RUNNING_PIDS[@]}"
		RUNNING_PIDS=()
		rm -f "${RESULTS_DIR}"/todo-*.txt

		PREVIOUS_COUNT="${REMAINING_COUNT}"
		REMAINING_COUNT="$( remaining_plugins "${RESULTS_DIR}" | grep -c . )"

		# A copy that stops early, because a plugin left the site in a state it could not be reset from, has
		# still recorded that plugin, so starting again gets past it. One that made no progress at all will not.
		if [ "${REMAINING_COUNT}" -ge "${PREVIOUS_COUNT}" ]; then
			break
		fi

		ATTEMPT=$(( ATTEMPT + 1 ))
	done

	cat "${RESULTS_DIR}"/part-*.tsv 2>/dev/null > "${OUT_DIR}/${LABEL}.tsv"

	printf 'WordPress %s: %s passed, %s failed, %s skipped. Results in %s.\n' "${VERSION}" \
		"$( awk -F '\t' '$3 == "PASS" { n++ } END { print n + 0 }' "${OUT_DIR}/${LABEL}.tsv" )" \
		"$( awk -F '\t' '$3 == "FAIL" { n++ } END { print n + 0 }' "${OUT_DIR}/${LABEL}.tsv" )" \
		"$( awk -F '\t' '$3 == "SKIPPED" { n++ } END { print n + 0 }' "${OUT_DIR}/${LABEL}.tsv" )" \
		"${OUT_DIR}/${LABEL}.tsv"

	if [ "${REMAINING_COUNT}" -gt 0 ]; then
		printf 'Warning: %s plugins have no result against WordPress %s. See the logs in %s.\n' "${REMAINING_COUNT}" "${VERSION}" "${RESULTS_DIR}" >&2
	fi

	MERGED_RESULTS="${OUT_DIR}/${LABEL}.tsv"
}

if [ "${BASELINE_VERSION}" = "none" ]; then
	run_version "${WP_VERSION}"
	printf '\nNo baseline was tested, so there is nothing to compare. Failures are listed in %s.\n' "${MERGED_RESULTS}"
	awk -F '\t' '$3 == "FAIL" { found = 1 } END { exit found }' "${MERGED_RESULTS}"
	exit
fi

run_version "${BASELINE_VERSION}"
BASELINE_RESULTS="${MERGED_RESULTS}"

run_version "${WP_VERSION}"
CANDIDATE_RESULTS="${MERGED_RESULTS}"

printf '\n'
"${SCRIPT_DIR}/compare-results.sh" "${BASELINE_RESULTS}" "${CANDIDATE_RESULTS}" | tee "${OUT_DIR}/report.md"
COMPARE_EXIT_CODE="${PIPESTATUS[0]}"

printf '\nThe report is in %s.\n' "${OUT_DIR}/report.md"
exit "${COMPARE_EXIT_CODE}"
