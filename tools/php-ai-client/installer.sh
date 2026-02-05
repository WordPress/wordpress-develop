#!/usr/bin/env bash
#
# Installer script for bundling wordpress/php-ai-client into WordPress Core.
#
# Fetches the package, scopes Http\* dependencies via PHP-Scoper, generates
# a manual autoloader, and places everything into src/wp-includes/php-ai-client/.
#
# Usage:
#   bash tools/php-ai-client/installer.sh --branch=refactor/removes-providers
#   bash tools/php-ai-client/installer.sh --version=1.0.0
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

SCOPER_VERSION="0.18.17"
SCOPER_URL="https://github.com/humbug/php-scoper/releases/download/${SCOPER_VERSION}/php-scoper.phar"
GITHUB_REPO="https://github.com/WordPress/php-ai-client.git"

TARGET_DIR="src/wp-includes/php-ai-client"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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
			echo "  --branch=BRANCH   Fetch from a branch (e.g. refactor/removes-providers)"
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

check_command php
check_command composer
check_command git

# Verify we're running from the repo root.
if [ ! -f "wp-cli.yml" ] && [ ! -f "wp-config-sample.php" ] && [ ! -d "src/wp-includes" ]; then
	echo "Error: This script must be run from the WordPress development repository root."
	exit 1
fi

echo "==> Starting php-ai-client installer..."

# ---------------------------------------------------------------------------
# Temp directory (cleaned on exit)
# ---------------------------------------------------------------------------

TEMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TEMP_DIR"' EXIT

echo "==> Using temp directory: $TEMP_DIR"

# ---------------------------------------------------------------------------
# Fetch package
# ---------------------------------------------------------------------------

if [ -n "$BRANCH" ]; then
	echo "==> Cloning branch '$BRANCH' from $GITHUB_REPO..."
	git clone --depth 1 --branch "$BRANCH" "$GITHUB_REPO" "$TEMP_DIR/package"
	echo "==> Installing Composer dependencies..."
	composer install --no-dev --no-interaction --working-dir="$TEMP_DIR/package"
	VENDOR_DIR="$TEMP_DIR/package/vendor"
	CLIENT_SRC="$TEMP_DIR/package/src"
else
	echo "==> Fetching version '$VERSION' via Composer..."
	mkdir -p "$TEMP_DIR/package"
	composer init --no-interaction --name="temp/installer" --working-dir="$TEMP_DIR/package"
	composer require "wordpress/php-ai-client:${VERSION}" --no-dev --no-interaction --working-dir="$TEMP_DIR/package"
	VENDOR_DIR="$TEMP_DIR/package/vendor"
	CLIENT_SRC="$VENDOR_DIR/wordpress/php-ai-client/src"
fi

if [ ! -d "$VENDOR_DIR" ]; then
	echo "Error: vendor directory not found at $VENDOR_DIR"
	exit 1
fi

echo "==> Package fetched successfully."

# ---------------------------------------------------------------------------
# Clean target directory
# ---------------------------------------------------------------------------

if [ -d "$TARGET_DIR" ]; then
	echo "==> Removing existing $TARGET_DIR..."
	rm -rf "$TARGET_DIR"
fi

# ---------------------------------------------------------------------------
# Scope dependencies with PHP-Scoper
# ---------------------------------------------------------------------------

SCOPER_PHAR="$TEMP_DIR/php-scoper.phar"

echo "==> Downloading PHP-Scoper ${SCOPER_VERSION}..."
curl -fsSL "$SCOPER_URL" -o "$SCOPER_PHAR"
chmod +x "$SCOPER_PHAR"

# Copy scoper config into temp dir.
cp "$SCRIPT_DIR/scoper.inc.php" "$TEMP_DIR/scoper.inc.php"

SCOPED_DIR="$TEMP_DIR/scoped"

echo "==> Running PHP-Scoper..."
php "$SCOPER_PHAR" add-prefix \
	--working-dir="$TEMP_DIR/package" \
	--config="$TEMP_DIR/scoper.inc.php" \
	--output-dir="$SCOPED_DIR" \
	--force \
	--no-interaction

echo "==> Scoping complete."

# ---------------------------------------------------------------------------
# Reorganize scoped output into namespace-based layout
# ---------------------------------------------------------------------------

THIRD_PARTY_DIR="$TEMP_DIR/third-party"

echo "==> Reorganizing dependencies..."
php "$SCRIPT_DIR/reorganize.php" \
	"$VENDOR_DIR/composer/installed.json" \
	"$SCOPED_DIR/vendor" \
	"$THIRD_PARTY_DIR"

echo "==> Reorganization complete."

# ---------------------------------------------------------------------------
# Copy files to target
# ---------------------------------------------------------------------------

echo "==> Copying files to $TARGET_DIR..."

mkdir -p "$TARGET_DIR/src"
mkdir -p "$TARGET_DIR/third-party"

# Copy scoped AI client source.
# If installed via branch, scoped source is at scoped/src/.
# If installed via version, scoped source is at scoped/vendor/wordpress/php-ai-client/src/.
if [ -n "$BRANCH" ]; then
	cp -R "$SCOPED_DIR/src/." "$TARGET_DIR/src/"
else
	cp -R "$SCOPED_DIR/vendor/wordpress/php-ai-client/src/." "$TARGET_DIR/src/"
fi

# Copy reorganized third-party dependencies.
cp -R "$THIRD_PARTY_DIR/." "$TARGET_DIR/third-party/"

# ---------------------------------------------------------------------------
# Generate autoload.php
# ---------------------------------------------------------------------------

echo "==> Generating autoload.php..."

cat > "$TARGET_DIR/autoload.php" << 'AUTOLOAD_PHP'
<?php
/**
 * Autoloader for the bundled PHP AI Client library.
 *
 * This file is generated by tools/php-ai-client/installer.sh.
 * Do not edit directly.
 *
 * @package WordPress
 * @subpackage AI
 * @since 6.8.0
 */

// Load polyfills (each function is guarded by function_exists).
require_once __DIR__ . '/src/polyfills.php';

spl_autoload_register(
	static function ( $class_name ) {
		// Namespace prefix for the AI client.
		$client_prefix    = 'WordPress\\AiClient\\';
		$client_prefix_len = 20; // strlen( 'WordPress\\AiClient\\' )

		// Namespace prefix for scoped dependencies.
		$scoped_prefix     = 'WordPress\\AiClientDependencies\\';
		$scoped_prefix_len = 31; // strlen( 'WordPress\\AiClientDependencies\\' )

		// PSR interface namespaces (not scoped, kept global).
		$psr_prefixes = array(
			'Psr\\Http\\Client\\'        => 16,
			'Psr\\Http\\Message\\'       => 17,
			'Psr\\EventDispatcher\\'     => 21,
			'Psr\\SimpleCache\\'         => 16,
		);

		$base_dir = __DIR__;

		// 1. WordPress\AiClient\* → src/
		if ( 0 === strncmp( $class_name, $client_prefix, $client_prefix_len ) ) {
			$relative_class = substr( $class_name, $client_prefix_len );
			$file           = $base_dir . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;
			}
			return;
		}

		// 2. WordPress\AiClientDependencies\* → third-party/ (strip prefix).
		if ( 0 === strncmp( $class_name, $scoped_prefix, $scoped_prefix_len ) ) {
			$relative_class = substr( $class_name, $scoped_prefix_len );
			$file           = $base_dir . '/third-party/' . str_replace( '\\', '/', $relative_class ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;
			}
			return;
		}

		// 3. Psr\* interfaces → third-party/Psr/...
		foreach ( $psr_prefixes as $prefix => $prefix_len ) {
			if ( 0 === strncmp( $class_name, $prefix, $prefix_len ) ) {
				$relative_class = substr( $class_name, 4 ); // Strip 'Psr\' prefix, keep sub-namespace.
				$file           = $base_dir . '/third-party/Psr/' . str_replace( '\\', '/', $relative_class ) . '.php';
				if ( file_exists( $file ) ) {
					require $file;
				}
				return;
			}
		}
	}
);
AUTOLOAD_PHP

echo "==> autoload.php generated."

# ---------------------------------------------------------------------------
# Validate output
# ---------------------------------------------------------------------------

echo "==> Validating output..."

ERRORS=0

# Check key directories exist.
for dir in "$TARGET_DIR/src" "$TARGET_DIR/third-party"; do
	if [ ! -d "$dir" ]; then
		echo "Error: Expected directory not found: $dir"
		ERRORS=$((ERRORS + 1))
	fi
done

# Check autoloader exists and has valid syntax.
if [ ! -f "$TARGET_DIR/autoload.php" ]; then
	echo "Error: autoload.php not found."
	ERRORS=$((ERRORS + 1))
else
	if ! php -l "$TARGET_DIR/autoload.php" > /dev/null 2>&1; then
		echo "Error: autoload.php has syntax errors."
		php -l "$TARGET_DIR/autoload.php"
		ERRORS=$((ERRORS + 1))
	fi
fi

# Check that AiClient.php exists in source.
if [ ! -f "$TARGET_DIR/src/AiClient.php" ]; then
	echo "Warning: src/AiClient.php not found. The package structure may differ."
fi

# Check that Http dependencies are scoped.
if [ -d "$TARGET_DIR/third-party/Http" ]; then
	SCOPED_COUNT=$(grep -rl "namespace WordPress\\\\AiClientDependencies\\\\Http" "$TARGET_DIR/third-party/Http/" 2>/dev/null | wc -l | tr -d ' ')
	if [ "$SCOPED_COUNT" -eq 0 ]; then
		echo "Warning: No scoped Http\\* namespaces found in third-party/Http/."
	else
		echo "    Found $SCOPED_COUNT scoped Http\\* files."
	fi
fi

# Check that Psr interfaces are NOT scoped.
if [ -d "$TARGET_DIR/third-party/Psr" ]; then
	UNSCOPED_PSR=$(grep -rL "namespace WordPress\\\\AiClientDependencies" "$TARGET_DIR/third-party/Psr/" 2>/dev/null | wc -l | tr -d ' ')
	echo "    Found $UNSCOPED_PSR unscoped Psr\\* files."
fi

if [ "$ERRORS" -gt 0 ]; then
	echo "Error: Validation failed with $ERRORS error(s)."
	exit 1
fi

echo "==> Validation passed."
echo "==> php-ai-client bundled successfully at $TARGET_DIR"
echo ""
echo "Next steps:"
echo "  1. Verify: ls -R $TARGET_DIR"
echo "  2. Test:   php -r \"require '$TARGET_DIR/autoload.php'; var_dump(class_exists('WordPress\\\\AiClient\\\\AiClient'));\""
echo "  3. Lint:   composer lint:errors"
