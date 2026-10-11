#!/usr/bin/env bash
#
# Installer script for bundling libvips/php-vips into WordPress Core.
#
# Fetches the package, scopes its PSR-3 dependency (Psr\Log\*) via PHP-Scoper,
# generates a manual autoloader, and places everything into
# src/wp-includes/php-vips/.
#
# Usage:
#   bash tools/php-vips/installer.sh
#   bash tools/php-vips/installer.sh --version=v2.6.1
#   bash tools/php-vips/installer.sh --branch=master
#
# Without --version or --branch, the latest release is installed.
#

set -euo pipefail

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------

# 0.18.19 or newer is required to run under PHP 8.5. Earlier versions fail to
# parse every PHP file there: the bundled nikic/php-parser triggers a
# deprecation, and PHP-Scoper's error handler turns deprecations into
# exceptions.
SCOPER_VERSION="0.18.19"
SCOPER_URL="https://github.com/humbug/php-scoper/releases/download/${SCOPER_VERSION}/php-scoper.phar"
GITHUB_REPO="https://github.com/libvips/php-vips.git"

TARGET_DIR="src/wp-includes/php-vips"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------------------------------------------------------
# Parse arguments
# -----------------------------------------------------------------------------

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
			echo "  --branch=BRANCH   Fetch from a branch"
			echo ""			 echo "With neither option, the latest release is installed."
			 echo ""			echo "Must be run from the WordPress development repository root."
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

# -----------------------------------------------------------------------------
# Prerequisites
# -----------------------------------------------------------------------------

check_command() {
	if ! command -v "$1" &> /dev/null; then
		echo "Error: '$1' is required but not found in PATH."
		exit 1
	fi
}

# Resolves the newest release tag on the remote, e.g. "v2.6.1".
latest_release_tag() {
	git ls-remote --tags --refs "$GITHUB_REPO" 2>/dev/null \
		| awk -F/ '{ print $NF }' \
		| grep -E '^v?[0-9]+(\.[0-9]+)*$' \
		| sort -V \
		| tail -n 1 \
		|| true
}

check_command php
check_command composer
check_command git
check_command curl

# Verify we're running from the repo root.
if [ ! -d "src/wp-includes" ]; then
	echo "Error: This script must be run from the WordPress development repository root."
	exit 1
fi

echo "==> Starting php-vips installer..."

# -----------------------------------------------------------------------------
# Temp directory (cleaned on exit)
# -----------------------------------------------------------------------------

TEMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TEMP_DIR"' EXIT

echo "==> Using temp directory: $TEMP_DIR"

# -----------------------------------------------------------------------------
# Fetch package
# -----------------------------------------------------------------------------

# Nothing was requested, so fall back to the latest release.
if [ -z "$BRANCH" ] && [ -z "$VERSION" ]; then
	echo "==> No version requested, resolving the latest release..."
	VERSION="$(latest_release_tag)"

	if [ -z "$VERSION" ]; then
		echo "Error: Could not resolve the latest release from $GITHUB_REPO."
		echo "Specify one explicitly with --version=X.Y.Z or --branch=BRANCH."
		exit 1
	fi

	echo "==> Latest release is $VERSION."
fi

if [ -n "$BRANCH" ]; then
	REF="$BRANCH"
	echo "==> Cloning branch '$REF' from $GITHUB_REPO..."
else
	REF="$VERSION"
	echo "==> Cloning tag '$REF' from $GITHUB_REPO..."
fi

if ! git clone --depth 1 --branch "$REF" "$GITHUB_REPO" "$TEMP_DIR/package" 2>/dev/null; then
	# Release tags in the php-vips repository are prefixed with "v", so also
	# accept a bare version number.
	case "$REF" in
		v*)
			echo "Error: Could not clone ref '$REF' from $GITHUB_REPO."
			exit 1
			;;
		*)
			echo "==> Ref '$REF' not found, retrying with 'v${REF}'..."
			REF="v${REF}"
			git clone --depth 1 --branch "$REF" "$GITHUB_REPO" "$TEMP_DIR/package"
			;;
	esac
fi

# Record the exact commit, so the generated autoloader states what was vendored.
PACKAGE_SHA="$( git -C "$TEMP_DIR/package" rev-parse HEAD )"

echo "==> Installing Composer dependencies..."
# --ignore-platform-reqs: only the source is needed here, and php-vips requires
# ext-ffi, which the machine running the installer may not have.
composer install --no-dev --no-interaction --ignore-platform-reqs --working-dir="$TEMP_DIR/package"

VENDOR_DIR="$TEMP_DIR/package/vendor"

if [ ! -d "$VENDOR_DIR" ]; then
	echo "Error: vendor directory not found at $VENDOR_DIR"
	exit 1
fi

# The scoped dependencies are copied to a fixed third-party/Psr/Log path below,
# so this installer assumes psr/log is the only dependency. Fail loudly if
# upstream adds another one, so the mapping is updated deliberately.
UNEXPECTED="$(find "$VENDOR_DIR" -mindepth 2 -maxdepth 2 -type d \
	-not -path "$VENDOR_DIR/composer*" \
	-not -path "$VENDOR_DIR/psr*" | head -5)"

if [ -n "$UNEXPECTED" ]; then
	echo "Error: Unexpected Composer dependencies found:"
	echo "$UNEXPECTED"
	echo "Update this installer to map them into third-party/."
	exit 1
fi

echo "==> Package fetched successfully."

# -----------------------------------------------------------------------------
# Clean target directory
# -----------------------------------------------------------------------------

if [ -d "$TARGET_DIR" ]; then
	echo "==> Removing existing $TARGET_DIR..."
	rm -rf "$TARGET_DIR"
fi

# -----------------------------------------------------------------------------
# Scope dependencies with PHP-Scoper
# -----------------------------------------------------------------------------

SCOPER_PHAR="$TEMP_DIR/php-scoper.phar"

echo "==> Downloading PHP-Scoper ${SCOPER_VERSION}..."
curl -fsSL "$SCOPER_URL" -o "$SCOPER_PHAR"
chmod +x "$SCOPER_PHAR"

# Copy scoper config into temp dir.
cp "$SCRIPT_DIR/scoper.inc.php" "$TEMP_DIR/scoper.inc.php"

SCOPED_DIR="$TEMP_DIR/scoped"

echo "==> Running PHP-Scoper..."

SCOPER_LOG="$TEMP_DIR/scoper.log"

# E_DEPRECATED is suppressed because PHP-Scoper's error handler turns
# deprecations into exceptions. A deprecation raised by the bundled
# nikic/php-parser on a newer PHP would otherwise make every file fail to parse.
php -d error_reporting='E_ALL & ~E_DEPRECATED' "$SCOPER_PHAR" add-prefix \
	--working-dir="$TEMP_DIR/package" \
	--config="$TEMP_DIR/scoper.inc.php" \
	--output-dir="$SCOPED_DIR" \
	--force \
	--no-interaction \
	2>&1 | tee "$SCOPER_LOG"

# PHP-Scoper exits 0 even when it failed to parse every single file, and still
# reports "Successfully prefixed N files". It writes the files either way, so
# the only reliable signal that scoping actually happened is the error log.
FAILED_FILES="$( grep -c '\[NO\]' "$SCOPER_LOG" || true )"

if [ "$FAILED_FILES" -gt 0 ]; then
	echo "Error: PHP-Scoper could not parse $FAILED_FILES file(s):"
	grep '\[NO\]' "$SCOPER_LOG" | head -20
	exit 1
fi

echo "==> Scoping complete."

# -----------------------------------------------------------------------------
# Copy files to target
# -----------------------------------------------------------------------------

echo "==> Copying files to $TARGET_DIR..."

mkdir -p "$TARGET_DIR/src"
mkdir -p "$TARGET_DIR/third-party/Psr/Log"

# php-vips itself is not scoped.
cp -R "$SCOPED_DIR/src/." "$TARGET_DIR/src/"

# Scoped PSR-3 interfaces.
cp -R "$SCOPED_DIR/vendor/psr/log/src/." "$TARGET_DIR/third-party/Psr/Log/"

# Licenses.
cp "$TEMP_DIR/package/LICENSE.txt" "$TARGET_DIR/LICENSE.txt"
cp "$VENDOR_DIR/psr/log/LICENSE" "$TARGET_DIR/third-party/Psr/Log/LICENSE"

# -----------------------------------------------------------------------------
# Generate autoload.php
# -----------------------------------------------------------------------------

echo "==> Generating autoload.php..."

cat > "$TARGET_DIR/autoload.php" << AUTOLOAD_HEADER
<?php
/**
 * Autoloader for the bundled php-vips library.
 *
 * This file is generated by tools/php-vips/installer.sh.
 * Do not edit directly.
 *
 * Vendored from ${GITHUB_REPO} at ${REF} (${PACKAGE_SHA}).
 *
 * @package WordPress
 * @subpackage Image_Editor
 * @since 7.2.0
 */

AUTOLOAD_HEADER

cat >> "$TARGET_DIR/autoload.php" << 'AUTOLOAD_BODY'
spl_autoload_register(
	static function ( $class_name ) {
		// Namespace prefix for php-vips.
		$vips_prefix     = 'Jcupitt\\Vips\\';
		$vips_prefix_len = 13; // strlen( 'Jcupitt\\Vips\\' )

		// Namespace prefix for scoped dependencies (Psr\Log\*).
		$scoped_prefix     = 'WordPress\\VipsDependencies\\';
		$scoped_prefix_len = 27; // strlen( 'WordPress\\VipsDependencies\\' )

		$base_dir = __DIR__;

		// 1. Jcupitt\Vips\* → src/
		if ( 0 === strncmp( $class_name, $vips_prefix, $vips_prefix_len ) ) {
			$relative_class = substr( $class_name, $vips_prefix_len );
			$file           = $base_dir . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;
			}
			return;
		}

		// 2. WordPress\VipsDependencies\* → third-party/ (strip prefix).
		if ( 0 === strncmp( $class_name, $scoped_prefix, $scoped_prefix_len ) ) {
			$relative_class = substr( $class_name, $scoped_prefix_len );
			$file           = $base_dir . '/third-party/' . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;
			}
			return;
		}
	}
);
AUTOLOAD_BODY

echo "==> autoload.php generated."

# -----------------------------------------------------------------------------
# Validate output
# -----------------------------------------------------------------------------

echo "==> Validating output..."

ERRORS=0

for path in \
	"$TARGET_DIR/autoload.php" \
	"$TARGET_DIR/src/Image.php" \
	"$TARGET_DIR/third-party/Psr/Log/LoggerInterface.php"; do
	if [ ! -f "$path" ]; then
		echo "Error: Missing expected file: $path"
		ERRORS=$((ERRORS + 1))
	fi
done

# The PSR-3 interfaces must carry the prefix, and php-vips must not.
if ! grep -q 'namespace WordPress\\VipsDependencies\\Psr\\Log;' "$TARGET_DIR/third-party/Psr/Log/LoggerInterface.php"; then
	echo "Error: Psr\\Log was not scoped as expected."
	ERRORS=$((ERRORS + 1))
fi

if ! grep -q 'namespace Jcupitt\\Vips;' "$TARGET_DIR/src/Image.php"; then
	echo "Error: Jcupitt\\Vips was unexpectedly scoped."
	ERRORS=$((ERRORS + 1))
fi

# php-vips must reference the scoped logger, not the global one.
if ! grep -q 'WordPress\\VipsDependencies\\Psr\\Log\\LoggerInterface' "$TARGET_DIR/src/Config.php"; then
	echo "Error: php-vips does not reference the scoped Psr\\Log interface."
	ERRORS=$((ERRORS + 1))
fi

if [ "$ERRORS" -gt 0 ]; then
	echo "==> Validation failed with $ERRORS error(s)."
	exit 1
fi

echo "==> Validation passed."
echo "==> php-vips installed into $TARGET_DIR."
