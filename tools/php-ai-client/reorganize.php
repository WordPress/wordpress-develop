<?php
/**
 * Dependency reorganizer for php-ai-client bundling.
 *
 * Reads vendor/composer/installed.json, maps PSR-4 namespace prefixes to
 * directory paths, and copies scoped dependency files into a namespace-based
 * layout under third-party/.
 *
 * Usage:
 *   php reorganize.php <installed.json> <scoped-vendor-dir> <output-dir>
 *
 * @package WordPress
 */

if ( $argc < 4 ) {
	fwrite( STDERR, "Usage: php reorganize.php <installed.json> <scoped-vendor-dir> <output-dir>\n" );
	exit( 1 );
}

$installed_json_path = $argv[1];
$scoped_vendor_dir   = rtrim( $argv[2], '/' );
$output_dir          = rtrim( $argv[3], '/' );

if ( ! file_exists( $installed_json_path ) ) {
	fwrite( STDERR, "Error: installed.json not found at: $installed_json_path\n" );
	exit( 1 );
}

if ( ! is_dir( $scoped_vendor_dir ) ) {
	fwrite( STDERR, "Error: Scoped vendor directory not found at: $scoped_vendor_dir\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Parse installed.json (handles Composer v1 and v2 formats).
// ---------------------------------------------------------------------------

$installed_data = json_decode( file_get_contents( $installed_json_path ), true );

if ( null === $installed_data ) {
	fwrite( STDERR, "Error: Failed to parse installed.json.\n" );
	exit( 1 );
}

// Composer v2 wraps packages in a "packages" key; v1 is a flat array.
if ( isset( $installed_data['packages'] ) && is_array( $installed_data['packages'] ) ) {
	$packages = $installed_data['packages'];
} elseif ( isset( $installed_data[0] ) ) {
	$packages = $installed_data;
} else {
	fwrite( STDERR, "Error: Unrecognized installed.json format.\n" );
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Process each dependency package.
// ---------------------------------------------------------------------------

$files_autoload = array();

foreach ( $packages as $package ) {
	$name = $package['name'] ?? '';

	// Skip the AI client package itself.
	if ( 'wordpress/php-ai-client' === $name ) {
		continue;
	}

	// Get PSR-4 autoload mappings.
	$psr4 = $package['autoload']['psr-4'] ?? array();

	if ( empty( $psr4 ) ) {
		// Check for PSR-0 as fallback.
		$psr0 = $package['autoload']['psr-0'] ?? array();
		if ( ! empty( $psr0 ) ) {
			fwrite( STDERR, "Warning: Package '$name' uses PSR-0 autoloading (not fully supported). Skipping.\n" );
		}
		// Still check for files autoload below.
	}

	// Collect "files" autoload entries for future use.
	$files = $package['autoload']['files'] ?? array();
	if ( ! empty( $files ) ) {
		foreach ( $files as $file ) {
			$files_autoload[] = array(
				'package' => $name,
				'file'    => $file,
			);
		}
	}

	// Process PSR-4 mappings.
	foreach ( $psr4 as $namespace_prefix => $source_dirs ) {
		// Normalize source_dirs to array.
		if ( ! is_array( $source_dirs ) ) {
			$source_dirs = array( $source_dirs );
		}

		// Convert namespace prefix to directory path.
		// e.g., "Http\\Client\\" → "Http/Client"
		$namespace_path = rtrim( str_replace( '\\', '/', $namespace_prefix ), '/' );

		// Determine the source directory in the scoped vendor output.
		// Composer packages are at vendor/{package-name}/{source-dir}/.
		foreach ( $source_dirs as $source_dir ) {
			$source_dir = rtrim( $source_dir, '/' );

			// Build the source path in the scoped vendor directory.
			$source_path = $scoped_vendor_dir . '/' . $name;
			if ( '' !== $source_dir ) {
				$source_path .= '/' . $source_dir;
			}

			if ( ! is_dir( $source_path ) ) {
				fwrite( STDERR, "Warning: Source directory not found for '$name' at: $source_path\n" );
				continue;
			}

			// Build the target path.
			$target_path = $output_dir . '/' . $namespace_path;

			// Create target directory.
			if ( ! is_dir( $target_path ) ) {
				mkdir( $target_path, 0755, true );
			}

			// Copy files recursively.
			copy_directory( $source_path, $target_path );

			echo "  Copied: $name ($namespace_prefix) → $namespace_path\n";
		}
	}
}

if ( ! empty( $files_autoload ) ) {
	fwrite( STDERR, "\nNote: The following packages have 'files' autoload entries that may need manual handling:\n" );
	foreach ( $files_autoload as $entry ) {
		fwrite( STDERR, "  - {$entry['package']}: {$entry['file']}\n" );
	}
}

echo "\nReorganization complete.\n";

// ---------------------------------------------------------------------------
// Helper functions.
// ---------------------------------------------------------------------------

/**
 * Recursively copy a directory.
 *
 * @param string $source Source directory path.
 * @param string $dest   Destination directory path.
 */
function copy_directory( string $source, string $dest ): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$target = $dest . '/' . $iterator->getSubPathname();

		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) ) {
				mkdir( $target, 0755, true );
			}
		} else {
			// Ensure parent directory exists.
			$parent = dirname( $target );
			if ( ! is_dir( $parent ) ) {
				mkdir( $parent, 0755, true );
			}
			copy( $item->getPathname(), $target );
		}
	}
}
