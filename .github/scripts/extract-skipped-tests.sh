#!/bin/bash

# Extract skipped tests from PHPUnit JUnit XML output
# Usage: extract-skipped-tests.sh <php> <db-type> <db-version> <multisite> <memcached> <test-groups> <os> <db-innovation> <tests-domain>

# Create config ID from parameters
CONFIG_ID="php${1}_${2}${3}_${4}_${5}_${6}_${7}_${8}_${9}"
CONFIG_ID="${CONFIG_ID//[^a-zA-Z0-9_-]/_}"

echo "Extracting skipped tests for configuration: ${CONFIG_ID}"

# Extract skipped tests from XML files using xmllint
SKIPPED_FOUND=false

for file in phpunit-results-*.xml; do
  if [ -f "$file" ]; then
    # Extract skipped tests in class::method format
    SKIPPED_TESTS=$(xmllint --format "$file" 2>/dev/null | grep -B1 "<skipped" | grep "testcase" | sed -n 's/.*name="\([^"]*\)".*classname="\([^"]*\)".*/\2::\1/p' || true)

    if [ -n "$SKIPPED_TESTS" ]; then
      echo "$SKIPPED_TESTS" >> "skipped-tests-${CONFIG_ID}.txt"
      SKIPPED_FOUND=true
    fi
  fi
done

if [ "$SKIPPED_FOUND" = false ]; then
  echo "No skipped tests found"
else
  echo "Skipped tests saved to skipped-tests-${CONFIG_ID}.txt"
fi
