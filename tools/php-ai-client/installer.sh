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
	REF="$BRANCH"
	echo "==> Cloning branch '$REF' from $GITHUB_REPO..."
else
	REF="$VERSION"
	echo "==> Cloning tag '$REF' from $GITHUB_REPO..."
fi

git clone --depth 1 --branch "$REF" "$GITHUB_REPO" "$TEMP_DIR/package"
echo "==> Installing Composer dependencies..."
composer install --no-dev --no-interaction --working-dir="$TEMP_DIR/package"
VENDOR_DIR="$TEMP_DIR/package/vendor"
CLIENT_SRC="$TEMP_DIR/package/src"

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
cp -R "$SCOPED_DIR/src/." "$TARGET_DIR/src/"

# Copy reorganized third-party dependencies.
cp -R "$THIRD_PARTY_DIR/." "$TARGET_DIR/third-party/"

# Third-party paths to remove (not needed at runtime).
REMOVE_PATHS=(
	# Composer plugin (build-time only).
	"Http/Discovery/Composer"

	# HTTPlug client library (SDK uses PSR-18 directly).
	"Http/Client"

	# Promise/async support (SDK is synchronous).
	"Http/Promise"

	# Deprecated discovery classes superseded by Psr18ClientDiscovery / Psr17FactoryDiscovery.
	"Http/Discovery/HttpClientDiscovery.php"
	"Http/Discovery/HttpAsyncClientDiscovery.php"
	"Http/Discovery/MessageFactoryDiscovery.php"
	"Http/Discovery/UriFactoryDiscovery.php"
	"Http/Discovery/StreamFactoryDiscovery.php"
	"Http/Discovery/NotFoundException.php"

	# Convenience wrappers not used by the SDK.
	"Http/Discovery/Psr17Factory.php"
	"Http/Discovery/Psr18Client.php"

	# Mock strategy (not in default strategy list).
	"Http/Discovery/Strategy/MockClientStrategy.php"

	# PSR-14 interfaces not used by the event dispatcher.
	"Psr/EventDispatcher/ListenerProviderInterface.php"
	"Psr/EventDispatcher/StoppableEventInterface.php"

	# PSR-16 cache exception interfaces (never thrown or caught).
	"Psr/SimpleCache/CacheException.php"
	"Psr/SimpleCache/InvalidArgumentException.php"
)

for path in "${REMOVE_PATHS[@]}"; do
	rm -rf "$TARGET_DIR/third-party/$path"
done

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
 * @since 7.0.0
 */

spl_autoload_register(
	static function ( $class_name ) {
		// Namespace prefix for the AI client.
		$client_prefix     = 'WordPress\\AiClient\\';
		$client_prefix_len = 19; // strlen( 'WordPress\\AiClient\\' )

		// Namespace prefix for scoped dependencies (includes Psr\*, Http\*, etc.).
		$scoped_prefix     = 'WordPress\\AiClientDependencies\\';
		$scoped_prefix_len = 31; // strlen( 'WordPress\\AiClientDependencies\\' )

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

# Check that Psr interfaces are scoped.
if [ -d "$TARGET_DIR/third-party/Psr" ]; then
	SCOPED_PSR=$(grep -rl "namespace WordPress\\\\AiClientDependencies\\\\Psr" "$TARGET_DIR/third-party/Psr/" 2>/dev/null | wc -l | tr -d ' ')
	if [ "$SCOPED_PSR" -eq 0 ]; then
		echo "Warning: No scoped Psr\\* namespaces found in third-party/Psr/."
	else
		echo "    Found $SCOPED_PSR scoped Psr\\* files."
	fi
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
