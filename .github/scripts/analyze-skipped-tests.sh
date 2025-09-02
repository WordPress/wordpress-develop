#!/bin/bash

# Analyze skipped tests across all PHPUnit configurations
# Fails if any tests are skipped in ALL configurations (never run)

# Count total test runs
TOTAL_TEST_RUNS=$(ls -1 skipped-tests-*.txt 2>/dev/null | wc -l)

if [ "$TOTAL_TEST_RUNS" -eq 0 ]; then
  echo "ERROR: No skipped test artifacts found"
  exit 1
fi

# Collect all skipped tests from all runs
grep -E '^[A-Za-z0-9_\\]+::[A-Za-z0-9_]+$' skipped-tests-*.txt 2>/dev/null | cut -d: -f2- | sort | uniq > unique-skipped-tests.txt

# Find tests that are skipped in ALL runs
ALWAYS_SKIPPED_TESTS=""
while IFS= read -r test; do
  if [ -n "$test" ]; then
    # Count how many files contain this test
    count=$(grep -l "^${test}$" skipped-tests-*.txt | wc -l)

    # If skipped in all runs, add to list
    if [ "$count" -eq "$TOTAL_TEST_RUNS" ]; then
      ALWAYS_SKIPPED_TESTS="${ALWAYS_SKIPPED_TESTS}${test}\n"
    fi
  fi
done < unique-skipped-tests.txt

if [ -n "$ALWAYS_SKIPPED_TESTS" ]; then
  echo "ERROR: Found tests that are never run in any configuration:"
  echo -e "$ALWAYS_SKIPPED_TESTS"
  exit 1
fi
