#!/usr/bin/env bash
##
# Compares two plugin compatibility runs and prints the plugins whose result changed between them.
#
# Across the whole directory, thousands of plugins fail on a current PHP whatever version of WordPress they
# run on, so the list of failures from a single run is mostly noise. What matters before a release is the
# plugins that pass on the current release and fail on the next one. This finds those by comparing the
# results files `test-plugins.sh --results` writes for each version:
#
#     tools/plugin-compatibility/compare-results.sh latest.tsv nightly.tsv
#
# Prints a markdown report, and exits 1 when at least one plugin regressed.
##

set -uo pipefail

usage() {
	cat <<'USAGE_EOF'
Usage: compare-results.sh <baseline results> <candidate results>

Compares two results files written by test-plugins.sh --results, such as one run against the current
release and one against nightly, and prints a markdown report of what changed.

  Regressed     passed on the baseline, failed on the candidate. These are the ones to look at.
  Fixed         failed on the baseline, passed on the candidate.
  Fail on both  failed either way, so not caused by the candidate. Counted, not listed.

Plugins that were not tested in both runs, or were skipped in either, are counted but not compared. A
regressed plugin whose version differs between the runs is flagged, since the plugin changed as well.

Exits 1 when a plugin regressed and 0 otherwise.
USAGE_EOF
}

case "${1:-}" in
	-h | --help ) usage; exit 0 ;;
esac

if [ "$#" -ne 2 ]; then
	usage >&2
	exit 2
fi

BASELINE="${1}"
CANDIDATE="${2}"

for FILE in "${BASELINE}" "${CANDIDATE}"; do
	[ -s "${FILE}" ] || { printf 'Error: %s does not exist or is empty.\n' "${FILE}" >&2; exit 2; }
done

# One row per plugin that is in both files: slug, baseline version, baseline result, candidate version,
# candidate result, candidate reason. A plugin listed twice in a file keeps its last row, which is the one a
# resumed run recorded.
JOINED="$( awk -F '\t' -v OFS='\t' '
	NR == FNR { version[$1] = $2; status[$1] = $3; next }
	{ cand_version[$1] = $2; cand_status[$1] = $3; cand_reason[$1] = $5 }
	END {
		for ( slug in cand_status ) {
			if ( slug in status ) {
				print slug, version[slug], status[slug], cand_version[slug], cand_status[slug], cand_reason[slug]
			}
		}
	}
' "${BASELINE}" "${CANDIDATE}" | sort )"

BASELINE_TOTAL="$( cut -f 1 "${BASELINE}" | sort -u | grep -c . )"
CANDIDATE_TOTAL="$( cut -f 1 "${CANDIDATE}" | sort -u | grep -c . )"
BOTH_TOTAL="$( printf '%s' "${JOINED}" | grep -c . )"

REGRESSED="$( printf '%s\n' "${JOINED}" | awk -F '\t' '$3 == "PASS" && $5 == "FAIL"' )"
FIXED="$( printf '%s\n' "${JOINED}" | awk -F '\t' '$3 == "FAIL" && $5 == "PASS"' )"
REGRESSED_COUNT="$( printf '%s' "${REGRESSED}" | grep -c . )"
FIXED_COUNT="$( printf '%s' "${FIXED}" | grep -c . )"
BOTH_FAIL_COUNT="$( printf '%s\n' "${JOINED}" | awk -F '\t' '$3 == "FAIL" && $5 == "FAIL" { n++ } END { print n + 0 }' )"
BOTH_PASS_COUNT="$( printf '%s\n' "${JOINED}" | awk -F '\t' '$3 == "PASS" && $5 == "PASS" { n++ } END { print n + 0 }' )"
NOT_COMPARED_COUNT="$(( BOTH_TOTAL - REGRESSED_COUNT - FIXED_COUNT - BOTH_FAIL_COUNT - BOTH_PASS_COUNT ))"

printf '## Plugin compatibility: %s compared with %s\n\n' "$( basename "${CANDIDATE}" )" "$( basename "${BASELINE}" )"
printf '%s plugins in the baseline, %s in the candidate, %s in both.\n\n' "${BASELINE_TOTAL}" "${CANDIDATE_TOTAL}" "${BOTH_TOTAL}"
printf '| Regressed | Fixed | Pass on both | Fail on both | Skipped in either |\n'
printf '| --- | --- | --- | --- | --- |\n'
printf '| %s | %s | %s | %s | %s |\n\n' "${REGRESSED_COUNT}" "${FIXED_COUNT}" "${BOTH_PASS_COUNT}" "${BOTH_FAIL_COUNT}" "${NOT_COMPARED_COUNT}"

print_rows() {
	printf '| Plugin | Version | Details |\n'
	printf '| --- | --- | --- |\n'

	printf '%s\n' "${1}" | awk -F '\t' 'NF > 0 {
		version = ( $2 == $4 ) ? $4 : $2 " to " $4 " (plugin changed too)"
		details = ( $6 == "-" ) ? "" : $6
		gsub( /\|/, "\\|", details )
		printf "| [%s](https://wordpress.org/plugins/%s/) | %s | %s |\n", $1, $1, version, details
	}'

	printf '\n'
}

if [ "${REGRESSED_COUNT}" -gt 0 ]; then
	printf '### Regressed\n\nPassed on the baseline and failed on the candidate.\n\n'
	print_rows "${REGRESSED}"
else
	printf 'No plugin that passed on the baseline failed on the candidate.\n\n'
fi

if [ "${FIXED_COUNT}" -gt 0 ]; then
	printf '### Fixed\n\nFailed on the baseline and passed on the candidate.\n\n'
	print_rows "${FIXED}"
fi

[ "${REGRESSED_COUNT}" -eq 0 ]
