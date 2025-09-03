#!/bin/bash

# Extract skipped tests from PHPUnit JUnit XML output
# Usage: extract-skipped-tests.sh <php> <db-type> <db-version> <multisite> <memcached> <test-groups> <os> <db-innovation> <tests-domain>

# Create config ID from parameters
CONFIG_ID="php${1}_${2}${3}_${4}_${5}_${6}_${7}_${8}_${9}"
CONFIG_ID="${CONFIG_ID//[^a-zA-Z0-9_-]/_}"

echo "Extracting skipped tests for configuration: ${CONFIG_ID}"

# Output CONFIG_ID to GitHub Actions
if [ -n "$GITHUB_OUTPUT" ]; then
  echo "CONFIG_ID=${CONFIG_ID}" >> "$GITHUB_OUTPUT"
fi

# Extract skipped tests from XML files using xmllint
SKIPPED_FOUND=false

echo "DEBUG: Looking for XML files matching: phpunit-results-*.xml"
ls -la phpunit-results-*.xml 2>/dev/null || echo "DEBUG: No XML files found"

for file in phpunit-results-*.xml; do
  if [ -f "$file" ]; then
    echo "DEBUG: Processing file: $file"

    # Check if file contains skipped tests
    SKIPPED_COUNT=$(grep -c "<skipped" "$file" 2>/dev/null || echo "0")
    # Ensure SKIPPED_COUNT is a clean number (take only first line and extract digits)
    SKIPPED_COUNT=$(echo "$SKIPPED_COUNT" | head -1 | grep -o '[0-9]*' | head -1 || echo "0")
    echo "DEBUG: Found $SKIPPED_COUNT skipped elements in $file"

    if [ "$SKIPPED_COUNT" -gt 0 ]; then
      echo "DEBUG: Sample skipped elements:"
      grep -A2 -B2 "<skipped" "$file" 2>/dev/null | head -10 || true
    fi

    # Extract skipped tests in class::method format
    # Find lines with <skipped/>, look at previous line for testcase info
    # Strip data set information (everything after " with data set")
    SKIPPED_TESTS=$(grep -B1 "<skipped/>" "$file" | grep "<testcase" | sed -n 's/.*name="\([^"]*\)".*class="\([^"]*\)".*/\2::\1/p' | sed 's/ with data set.*$//' || true)

    echo "DEBUG: Extracted skipped tests: '$SKIPPED_TESTS'"

    if [ -n "$SKIPPED_TESTS" ]; then
      echo "$SKIPPED_TESTS" | sort | uniq >> "skipped-tests-${CONFIG_ID}.txt"
      SKIPPED_FOUND=true
      echo "DEBUG: Added skipped tests to skipped-tests-${CONFIG_ID}.txt"
    fi
  else
    echo "DEBUG: File $file does not exist or is not readable"
  fi
done

if [ "$SKIPPED_FOUND" = false ]; then
  echo "No skipped tests found"
else
  # Deduplicate the final file
  sort "skipped-tests-${CONFIG_ID}.txt" | uniq > "skipped-tests-${CONFIG_ID}.tmp"
  mv "skipped-tests-${CONFIG_ID}.tmp" "skipped-tests-${CONFIG_ID}.txt"
  FINAL_COUNT=$(wc -l < "skipped-tests-${CONFIG_ID}.txt")
  echo "Skipped tests saved to skipped-tests-${CONFIG_ID}.txt (${FINAL_COUNT} unique tests)"
fi
