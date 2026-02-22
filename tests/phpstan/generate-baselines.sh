#!/bin/bash
# generate-baselines.sh
# Generate PHPStan baseline files for each level

set -e

MAX_LEVEL=6  # Change this when adding new levels

LEVEL_START=""
LEVEL_END=""
declare -A BEFORE_COUNTS
declare -A AFTER_COUNTS

usage() {
  cat <<EOF
Usage: $0 [OPTIONS]

Options:
  --all             Generate baselines for all levels (1-${MAX_LEVEL})
  --level=N         Generate baseline for level N (1-${MAX_LEVEL})
  --from=N --to=M   Generate baselines from level N to M (1-${MAX_LEVEL})
  --summary-only    Show summary table only (no generation)
  --help            Show this help message
EOF
}

error_exit() {
  echo "Error: $1" >&2
  exit 1
}

validate_level() {
  local lvl="$1"
  if ! [[ "$lvl" =~ ^[0-9]+$ ]] || (( lvl < 1 || lvl > MAX_LEVEL )); then
    error_exit "Level must be between 1 and ${MAX_LEVEL} (got: $lvl)"
  fi
}

check_prerequisites() {
  if [[ ! -x "vendor/bin/phpstan" ]]; then
    echo "Error: vendor/bin/phpstan not found or not executable." >&2
    echo "Action: Run 'composer install' to install PHPStan." >&2
    exit 2
  fi

  if [[ ! -f "phpstan.neon.dist" ]]; then
    echo "Error: phpstan.neon.dist not found." >&2
    exit 3
  fi

  if [[ ! -f "tests/phpstan/base.neon" ]]; then
    echo "Error: tests/phpstan/base.neon not found." >&2
    exit 4
  fi

  if [[ ! -d "tests/phpstan/baseline" ]]; then
    mkdir -p "tests/phpstan/baseline"
    echo "Created missing directory: tests/phpstan/baseline/"
  fi
}

# Count errors in a baseline PHP file by summing 'count' => N entries
count_baseline_errors() {
  local file="$1"
  local count=0

  if [[ -f "$file" ]]; then
    count=$(grep -E "'count'[[:space:]]*=>[[:space:]]*[0-9]+" "$file" 2>/dev/null | \
      grep -oE '[0-9]+' | \
      awk '{s+=$1} END {print s+0}')
  fi

  echo "$count"
}

capture_before_counts() {
  for lvl in $(seq $LEVEL_START $LEVEL_END); do
    local file="tests/phpstan/baseline/level-${lvl}.php"
    BEFORE_COUNTS[$lvl]=$(count_baseline_errors "$file")
  done
}

capture_after_counts() {
  for lvl in $(seq $LEVEL_START $LEVEL_END); do
    local file="tests/phpstan/baseline/level-${lvl}.php"
    AFTER_COUNTS[$lvl]=$(count_baseline_errors "$file")
  done
}

display_summary_table() {
  echo ""
  echo "=============================================="
  echo "BASELINE GENERATION SUMMARY"
  echo "=============================================="
  echo ""
  printf "%-10s %12s %12s %12s\n" "Level" "Before" "After" "Change"
  printf "%-10s %12s %12s %12s\n" "-----" "------" "-----" "------"

  local total_before=0
  local total_after=0

  for lvl in $(seq $LEVEL_START $LEVEL_END); do
    local before=${BEFORE_COUNTS[$lvl]:-0}
    local after=${AFTER_COUNTS[$lvl]:-0}
    local change=$((after - before))
    local change_str="+$change"

    if [[ $change -lt 0 ]]; then
      change_str="$change"
    elif [[ $change -eq 0 ]]; then
      change_str="0"
    fi

    printf "%-10s %12s %12s %12s\n" "Level $lvl" "$before" "$after" "$change_str"
    total_before=$((total_before + before))
    total_after=$((total_after + after))
  done

  printf "%-10s %12s %12s %12s\n" "-----" "------" "-----" "------"

  local total_change=$((total_after - total_before))
  local total_change_str="+$total_change"
  if [[ $total_change -lt 0 ]]; then
    total_change_str="$total_change"
  elif [[ $total_change -eq 0 ]]; then
    total_change_str="0"
  fi

  printf "%-10s %12s %12s %12s\n" "TOTAL" "$total_before" "$total_after" "$total_change_str"

  if [[ "$GENERATE" == "true" ]]; then
    echo ""
    echo "Generated files:"
    for lvl in $(seq $LEVEL_START $LEVEL_END); do
      echo "  tests/phpstan/baseline/level-${lvl}.php"
    done
    echo ""
    echo "If satisfied, commit the generated files to version control."
  fi
}

# Parse arguments
if [[ $# -eq 0 ]]; then
  usage
  exit 1
fi

GENERATE=true

while [[ $# -gt 0 ]]; do
  case "$1" in
    --all)
      LEVEL_START=1
      LEVEL_END=$MAX_LEVEL
      shift
      ;;
    --level=*)
      LEVEL_START="${1#--level=}"
      LEVEL_END="$LEVEL_START"
      validate_level "$LEVEL_START"
      shift
      ;;
    --from=*)
      LEVEL_START="${1#--from=}"
      validate_level "$LEVEL_START"
      shift
      ;;
    --to=*)
      LEVEL_END="${1#--to=}"
      validate_level "$LEVEL_END"
      shift
      ;;
    --summary-only)
      GENERATE=false
      shift
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      usage
      error_exit "Unknown argument: $1"
      ;;
  esac
done

# If no level range specified, default to all levels
if [[ -z "$LEVEL_START" || -z "$LEVEL_END" ]]; then
  LEVEL_START=1
  LEVEL_END=$MAX_LEVEL
fi

if [[ -n "$LEVEL_START" && -n "$LEVEL_END" ]]; then
  if (( LEVEL_START > LEVEL_END )); then
    error_exit "LEVEL_START ($LEVEL_START) cannot be greater than LEVEL_END ($LEVEL_END)"
  fi
fi

check_prerequisites

echo "Capturing current baseline error counts..."
capture_before_counts

generate_temp_config() {
  local level="$1"
  local temp_file=".phpstan-temp-${level}.neon"
  local higher_level

  cp phpstan.neon.dist "$temp_file"

  # Comment out baseline includes for this level and higher (N through MAX_LEVEL)
  higher_level=$level
  while (( higher_level <= MAX_LEVEL )); do
    sed -i "s|^\([[:space:]]*\)- tests/phpstan/baseline/level-${higher_level}\.php$|\1# - tests/phpstan/baseline/level-${higher_level}.php|" "$temp_file"
    ((higher_level++))
  done

  # Change parameters.level to target level
  sed -i "s/^\([[:space:]]*\)level: [0-9]\+$/\1level: ${level}/" "$temp_file"

  # Comment out ignoreErrors entries for levels higher than target (POSIX-compliant awk)
  awk -v target_level="$level" '
    BEGIN { current_level = 0; in_ignore = 0; commenting = 0 }
    /^[[:space:]]*ignoreErrors:/ { in_ignore = 1 }
    /^[[:space:]]*parameters:/ && in_ignore { in_ignore = 0; commenting = 0 }
    in_ignore && /^[[:space:]]*# Level [0-9]+:/ {
      if (match($0, /[0-9]+/)) {
        current_level = substr($0, RSTART, RLENGTH) + 0
        commenting = (current_level > target_level) ? 1 : 0
      }
    }
    commenting && !/^[[:space:]]*# Level [0-9]+:/ {
      print "# " $0
      next
    }
    { print }
  ' "$temp_file" > "${temp_file}.tmp" && mv "${temp_file}.tmp" "$temp_file"

  echo "$temp_file"
}

run_phpstan() {
  local level="$1"
  local config_path="$2"
  local baseline_file="tests/phpstan/baseline/level-${level}.php"

  echo ""
  echo "=== Generating baseline for level ${level} ==="

  set +e
  vendor/bin/phpstan analyse -vvv --memory-limit=2G --configuration="$config_path" --generate-baseline="$baseline_file"
  local exit_code=$?
  set -e

  if [[ $exit_code -ne 0 ]]; then
    echo "Error: PHPStan failed at level ${level} with exit code ${exit_code}" >&2
    exit 1
  fi

  if [[ ! -f "$baseline_file" ]]; then
    echo "Error: Baseline file was not created at ${baseline_file}" >&2
    exit 1
  fi

  echo "Successfully generated: ${baseline_file}"
}

cleanup_temp_files() {
  rm -f .phpstan-temp-*.neon
}
trap cleanup_temp_files EXIT INT TERM

# Main loop
if [[ "$GENERATE" == "true" ]]; then
  for level in $(seq $LEVEL_START $LEVEL_END); do
    config=$(generate_temp_config $level)
    run_phpstan $level "$config"
    rm -f "$config"
  done
fi

capture_after_counts
display_summary_table

exit 0
