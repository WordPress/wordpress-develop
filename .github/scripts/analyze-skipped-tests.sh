#!/bin/bash

# Analyze skipped tests across all PHPUnit configurations
# Fails if any tests are skipped in ALL configurations (never run)

echo "DEBUG: Starting skipped test analysis..."

# Count total test runs
TOTAL_TEST_RUNS=$(ls -1 skipped-tests-*.txt 2>/dev/null | wc -l)
echo "DEBUG: Found $TOTAL_TEST_RUNS test configuration files"

if [ "$TOTAL_TEST_RUNS" -eq 0 ]; then
  echo "ERROR: No skipped test artifacts found"
  exit 1
fi

echo "DEBUG: Available skipped test files:"
ls -la skipped-tests-*.txt

# Collect all skipped tests from all runs
echo "DEBUG: Collecting unique skipped tests from all configurations..."
grep -E '^[A-Za-z0-9_\\]+::[A-Za-z0-9_]+$' skipped-tests-*.txt 2>/dev/null | cut -d: -f2- | sort | uniq > unique-skipped-tests.txt

UNIQUE_COUNT=$(wc -l < unique-skipped-tests.txt)
echo "DEBUG: Found $UNIQUE_COUNT unique skipped tests across all configurations"

if [ "$UNIQUE_COUNT" -gt 0 ]; then
  echo "DEBUG: Unique skipped tests:"
  cat unique-skipped-tests.txt
fi

# Find tests that are skipped in ALL runs
echo "DEBUG: Checking for tests that are always skipped (never run)..."
ALWAYS_SKIPPED_TESTS=""
ALWAYS_SKIPPED_COUNT=0

while IFS= read -r test; do
  if [ -n "$test" ]; then
    # Count how many files contain this test
    count=$(grep -l -F -x "$test" skipped-tests-*.txt | wc -l)
    echo "DEBUG: Test '$test' is skipped in $count out of $TOTAL_TEST_RUNS configurations"

    # If skipped in all runs, add to list
    if [ "$count" -eq "$TOTAL_TEST_RUNS" ]; then
      ALWAYS_SKIPPED_TESTS="${ALWAYS_SKIPPED_TESTS}${test}\n"
      ALWAYS_SKIPPED_COUNT=$((ALWAYS_SKIPPED_COUNT + 1))
    fi
  fi
done < unique-skipped-tests.txt

echo "DEBUG: Found $ALWAYS_SKIPPED_COUNT tests that are always skipped"

if [ -n "$ALWAYS_SKIPPED_TESTS" ]; then
  echo "ERROR: Found tests that are never run in any configuration:"
  echo -e "$ALWAYS_SKIPPED_TESTS"
  exit 1
else
  echo "SUCCESS: All skipped tests run in at least one configuration"
fi
