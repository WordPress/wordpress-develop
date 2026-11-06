#!/usr/bin/env bash
#
# Installer script for bundling wordpress/mysql-on-sqlite into WordPress Core.
#
# Fetches the SQLite Database Integration monorepo, and splits the source of its
# MySQL-on-SQLite driver package into these directories:
#
#   src/wp-includes/parser/                    The generic parser.
#   src/wp-includes/database/mysql-parser/     The MySQL lexer, parser, and grammar.
#   src/wp-includes/database/mysql-on-sqlite/  The SQLite driver, and the loader.
#
# The files are copied without changes, except for the paths between them.
#
# Usage:
#   bash tools/mysql-on-sqlite/installer.sh --version=3.1.2
#   bash tools/mysql-on-sqlite/installer.sh --branch=trunk
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

GITHUB_REPO="https://github.com/WordPress/sqlite-database-integration.git"
PACKAGE_SRC="packages/mysql-on-sqlite/src"

PARSER_DIR="src/wp-includes/parser"
MYSQL_PARSER_DIR="src/wp-includes/database/mysql-parser"
DRIVER_DIR="src/wp-includes/database/mysql-on-sqlite"

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------

VERSION=""
BRANCH=""

for arg in "$@"; do
	case "$arg" in
		--version=*)
			VERSION="${arg#--version=}"
			;;
		--branch=*)
			BRANCH="${arg#--branch=}"
			;;
		--help|-h)
			echo "Usage: $0 [--version=X.Y.Z | --branch=BRANCH]"
			echo ""
			echo "Options:"
			echo "  --version=X.Y.Z   Fetch a specific release version"
			echo "  --branch=BRANCH   Fetch from a branch (e.g. trunk)"
			echo ""
			echo "Must be run from the WordPress development repository root."
			exit 0
			;;
		*)
			echo "Error: Unknown argument: $arg"
			echo "Run '$0 --help' for usage."
			exit 1
			;;
	esac
done

if [ -n "$VERSION" ] && [ -n "$BRANCH" ]; then
	echo "Error: Cannot specify both --version and --branch."
	exit 1
fi

if [ -z "$VERSION" ] && [ -z "$BRANCH" ]; then
	echo "Error: Must specify either --version=X.Y.Z or --branch=BRANCH."
	exit 1
fi

# ---------------------------------------------------------------------------
# Prerequisites
# ---------------------------------------------------------------------------

check_command() {
	if ! command -v "$1" &> /dev/null; then
		echo "Error: '$1' is required but not found in PATH."
		exit 1
	fi
}

check_command git
check_command php

if [ ! -f "wp-config-sample.php" ] || [ ! -d "src/wp-includes" ]; then
	echo "Error: This script must be run from the WordPress development repository root."
	exit 1
fi

echo "==> Starting mysql-on-sqlite installer..."

# ---------------------------------------------------------------------------
# Temp directory (cleaned on exit)
# ---------------------------------------------------------------------------

TEMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TEMP_DIR"' EXIT

# ---------------------------------------------------------------------------
# Fetch package
# ---------------------------------------------------------------------------

if [ -n "$BRANCH" ]; then
	REF="$BRANCH"
	echo "==> Cloning branch '$REF' from $GITHUB_REPO..."
else
	# Releases are tagged with a "v" prefix.
	REF="v$VERSION"
	echo "==> Cloning tag '$REF' from $GITHUB_REPO..."
fi

git -c advice.detachedHead=false clone --quiet --depth 1 --branch "$REF" "$GITHUB_REPO" "$TEMP_DIR/repo"
COMMIT="$(git -C "$TEMP_DIR/repo" rev-parse HEAD)"
SRC="$TEMP_DIR/repo/$PACKAGE_SRC"

if [ ! -f "$SRC/load.php" ]; then
	echo "Error: The driver loader was not found at $PACKAGE_SRC/load.php."
	exit 1
fi

# The driver version is defined in "version.php" as SQLITE_DRIVER_VERSION.
DRIVER_VERSION="$(sed -n "s/^define( 'SQLITE_DRIVER_VERSION', '\(.*\)' );$/\1/p" "$SRC/version.php")"

if [ -z "$DRIVER_VERSION" ]; then
	echo "Error: The driver version was not found in $PACKAGE_SRC/version.php."
	exit 1
fi

if [ -n "$VERSION" ] && [ "$VERSION" != "$DRIVER_VERSION" ]; then
	echo "Error: Tag '$REF' contains driver version $DRIVER_VERSION, not $VERSION."
	exit 1
fi

# Every file must have a known target directory, so that nothing is left out.
for entry in "$SRC"/*; do
	case "$(basename "$entry")" in
		parser|mysql|sqlite|load.php|version.php|php-polyfills.php)
			;;
		*)
			echo "Error: The installer doesn't know where to copy $PACKAGE_SRC/$(basename "$entry")."
			exit 1
			;;
	esac
done

# ---------------------------------------------------------------------------
# Copy files to targets
# ---------------------------------------------------------------------------

echo "==> Copying $PACKAGE_SRC/parser to $PARSER_DIR..."
rm -rf "$PARSER_DIR"
mkdir -p "$PARSER_DIR"
cp -R "$SRC/parser/." "$PARSER_DIR/"

echo "==> Copying $PACKAGE_SRC/mysql to $MYSQL_PARSER_DIR..."
rm -rf "$MYSQL_PARSER_DIR"
mkdir -p "$MYSQL_PARSER_DIR"
cp -R "$SRC/mysql/." "$MYSQL_PARSER_DIR/"

echo "==> Copying $PACKAGE_SRC/sqlite and the loader to $DRIVER_DIR..."
rm -rf "$DRIVER_DIR"
mkdir -p "$DRIVER_DIR"
cp -R "$SRC/sqlite/." "$DRIVER_DIR/"
cp "$SRC/load.php" "$SRC/version.php" "$SRC/php-polyfills.php" "$DRIVER_DIR/"

# ---------------------------------------------------------------------------
# Update the paths between the files
# ---------------------------------------------------------------------------

# Replaces a string in a file, and fails when the string is not found.
replace_in_file() {
	local file="$1" search="$2" replace="$3"

	if ! grep -qF -- "$search" "$file"; then
		echo "Error: \"$search\" was not found in $file. Update the installer."
		exit 1
	fi

	php -r 'file_put_contents( $argv[1], str_replace( $argv[2], $argv[3], file_get_contents( $argv[1] ) ) );' "$file" "$search" "$replace"
}

echo "==> Updating the paths between the files..."

replace_in_file "$DRIVER_DIR/load.php" "__DIR__ . '/parser/" "__DIR__ . '/../../parser/"
replace_in_file "$DRIVER_DIR/load.php" "__DIR__ . '/mysql/" "__DIR__ . '/../mysql-parser/"
replace_in_file "$DRIVER_DIR/load.php" "__DIR__ . '/sqlite/" "__DIR__ . '/"
replace_in_file "$DRIVER_DIR/class-wp-mysql-on-sqlite.php" "__DIR__ . '/../mysql/" "__DIR__ . '/../mysql-parser/"

# Paths that weren't updated above would point to the directories of the package.
if grep -rnE "'/(\.\./)?(parser|mysql|sqlite)/" "$PARSER_DIR" "$MYSQL_PARSER_DIR" "$DRIVER_DIR"; then
	echo "Error: The paths above weren't updated. Update the installer."
	exit 1
fi

# ---------------------------------------------------------------------------
# Validate the result
# ---------------------------------------------------------------------------

echo "==> Checking that the driver works..."

if ! php -r '
	require $argv[1];
	$driver = new WP_MySQL_On_SQLite( "mysql-on-sqlite:path=:memory:;dbname=wordpress" );
	exit( 1 === (int) $driver->query( "SELECT 1" )->fetchColumn() ? 0 : 1 );
' "$DRIVER_DIR/load.php"; then
	echo "Error: The driver could not run a query. It needs PHP with the pdo_sqlite extension."
	exit 1
fi

echo "==> Installed mysql-on-sqlite $DRIVER_VERSION from $REF ($COMMIT)."
