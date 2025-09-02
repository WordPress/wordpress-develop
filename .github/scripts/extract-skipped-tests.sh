#!/bin/bash

# Extract skipped tests from PHPUnit JUnit XML output
# Usage: extract-skipped-tests.sh <php> <db-type> <db-version> <multisite> <memcached> <test-groups>

# Create config ID from parameters
CONFIG_ID="php${1}_${2}${3}_${4}_${5}_${6}"
CONFIG_ID="${CONFIG_ID//[^a-zA-Z0-9_-]/_}"

echo "Extracting skipped tests for configuration: ${CONFIG_ID}"

# Extract skipped tests from XML files using xmllint
touch "skipped-tests-${CONFIG_ID}.txt"

for file in phpunit-results-*.xml; do
  if [ -f "$file" ]; then
    # Extract skipped tests in class::method format
    xmllint --format "$file" 2>/dev/null | grep -B1 "<skipped" | grep "testcase" | sed -n 's/.*name="\([^"]*\)".*classname="\([^"]*\)".*/\2::\1/p' >> "skipped-tests-${CONFIG_ID}.txt" || true
  fi
done

echo "Skipped tests saved to skipped-tests-${CONFIG_ID}.txt"
echo "CONFIG_ID=${CONFIG_ID}"