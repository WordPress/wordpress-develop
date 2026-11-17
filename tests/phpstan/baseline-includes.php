<?php
/**
 * Finds the PHPStan baselines in a directory.
 *
 * Every baselines directory has a `loader.php`, which a configuration includes
 * rather than listing the baselines one by one, so that adding a baseline, or
 * deleting one that has reached zero, needs no edit of the configuration. A NEON
 * configuration can only include files by name, but a PHP one returns the same
 * structure and can find them itself. Each loader requires this file and returns
 * get_baseline_includes() for its own directory, and `generate-baselines.php`
 * requires it too, so the loaders and the generator agree on what a baseline is.
 *
 * PHPStan keys its cache of the configuration on the files it includes, so a
 * baseline added or deleted takes effect on the next run.
 *
 * @package WordPress
 */

namespace WordPress\PHPStan;

use RuntimeException;

/**
 * Environment variable that tells a baselines loader to include nothing.
 *
 * `composer phpstan:baselines` sets it to the directory it is writing baselines
 * to while it analyzes, so that the errors those baselines suppress are reported
 * again for it to write back out. It is the directory's real path, which is what
 * each loader's __DIR__ is, and each loader compares it with its own, so this
 * works however the configuration includes the loader, even through another
 * configuration that includes the one that includes it.
 *
 * The baselines in any other directory stay in force. Those regenerated are then
 * only the errors the others leave, so that an error is baselined once: the
 * baselines of a stricter configuration that includes `phpstan.neon.dist` hold
 * what its baselines do not. Any other value, such as a variable left set by
 * mistake, matches no directory.
 */
const GENERATING_BASELINES_ENV = 'WP_PHPSTAN_GENERATING_BASELINES';

/**
 * Pattern matching the file name of a baseline.
 *
 * The name is the error identifier, matched as PHPStan validates identifiers,
 * followed by `.neon`, so that a stray file such as a merge tool's
 * `argument.type_BACKUP_1234.neon` is neither loaded nor retired. It ends in `\z`
 * rather than `$`, which would also match a name ending in a newline.
 */
const BASELINE_FILE_PATTERN = '/^[a-zA-Z0-9](?:[a-zA-Z0-9.]*[a-zA-Z0-9])?\.neon\z/';

/**
 * Lists the per-identifier baselines present on disk.
 *
 * The directory is listed rather than globbed, since glob() would read any of its
 * metacharacters in the directory's own path, such as the brackets of a checkout
 * in `wp [old]/`, as a pattern, and match nothing.
 *
 * A directory that cannot be listed is an error rather than one with no
 * baselines, since treating it as empty would drop every suppression at once,
 * with nothing to say why.
 *
 * @param non-empty-string $directory Absolute path to the baseline directory.
 * @return list<non-empty-string> File names, sorted.
 *
 * @throws RuntimeException When the directory cannot be listed.
 */
function find_baselines( string $directory ): array {
	$entries = is_dir( $directory ) ? scandir( $directory ) : false;

	if ( false === $entries ) {
		throw new RuntimeException( "Could not list the PHPStan baselines in $directory." );
	}

	$names = array();
	foreach ( $entries as $name ) {
		if ( '' !== $name && 1 === preg_match( BASELINE_FILE_PATTERN, $name ) ) {
			$names[] = $name;
		}
	}

	return $names;
}

/**
 * Returns the configuration a baselines loader hands to PHPStan.
 *
 * Each baseline is included by its bare file name, which PHPStan resolves against
 * the loader's path as the configuration reached it. A loader's __DIR__ is the
 * real path, with any symbolic link resolved, so an absolute path built from it
 * would place the baselines, and the paths their entries name relative to
 * themselves, outside a checkout reached through a link, where they match none
 * of the analyzed files.
 *
 * @param non-empty-string $directory The loader's directory, as its __DIR__.
 * @return array{includes: list<non-empty-string>} Configuration.
 *
 * @throws RuntimeException When the directory cannot be listed.
 */
function get_baseline_includes( string $directory ): array {
	if ( getenv( GENERATING_BASELINES_ENV ) === $directory ) {
		return array( 'includes' => array() );
	}

	return array( 'includes' => find_baselines( $directory ) );
}
