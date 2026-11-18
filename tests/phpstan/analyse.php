<?php
/**
 * Runs the PHPStan analyses of WordPress core.
 *
 * Core is analyzed twice: the whole codebase with phpstan.neon.dist, and then the
 * paths held to a stricter rule level with phpstan-strict.neon.dist. A Composer
 * script listing the two would stop at the first, since PHPStan exits with a
 * failure status whenever it reports an error, so this runs each in turn whatever
 * the other reports, and exits with the highest status of them.
 *
 * Each analysis honors a local override of its configuration, the same way
 * PHPStan does: phpstan.neon in place of phpstan.neon.dist, which PHPStan finds by
 * itself, and phpstan-strict.neon in place of phpstan-strict.neon.dist, which
 * this script chooses. When a local phpstan.neon analyzes at the strict rule
 * level or above, the second analysis is skipped, since for the paths the first
 * analyzed it could only repeat some of what that reported. Only the levels are
 * compared, so a local phpstan.neon narrowing the paths it analyzes may leave
 * strict paths out, which `composer phpstan:strict` still analyzes.
 *
 * With `--fix`, the stale entries of both sets of baselines are fixed instead,
 * along with anything else PHPStan can fix. That always uses the two .dist
 * configurations, whatever a local override says, because a configuration at
 * another rule level can report a baselined error with a different message, and
 * `--fix` would then remove an entry the default configuration still needs. The
 * baselines left without entries are then deleted, and both analyses run again
 * to show what remains.
 *
 * Every other argument is passed to each analysis, except that an argument which
 * only makes sense for one, such as paths to analyze, a configuration, a level, or
 * a report format for a program to read, runs only the first, as PHPStan itself
 * would.
 *
 * Run it through Composer:
 *
 *     composer phpstan
 *     composer phpstan -- --memory-limit=4G
 *     composer phpstan:fix
 *
 * @package WordPress
 */

namespace WordPress\PHPStan;

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

/**
 * The configuration analyzing the whole codebase.
 */
const PROJECT_CONFIG = 'phpstan.neon.dist';

/**
 * The configuration holding selected paths to a stricter rule level.
 */
const STRICT_CONFIG = 'phpstan-strict.neon.dist';

$repo_root = dirname( __DIR__, 2 );
$phpstan   = $repo_root . '/vendor/bin/phpstan';

// $argv is only populated when register_argc_argv is on, so read it defensively.
$args = array();
foreach ( (array) ( $_SERVER['argv'] ?? array() ) as $arg ) {
	if ( is_string( $arg ) ) {
		$args[] = $arg;
	}
}
array_shift( $args );

$fix = in_array( '--fix', $args, true );
if ( $fix ) {
	$args = array_values( array_diff( $args, array( '--fix' ) ) );
}

/*
 * Some arguments only make sense for a single analysis. Paths would replace the
 * strict paths of the second one, holding whatever they name to the stricter
 * level; a level would analyze the strict paths at another; a report format meant
 * to be read by a program would print two reports in a row; and a baseline would
 * be written by each in turn. With any of them, only the first analysis runs, as it did before
 * there were two.
 */
$single_reason = get_single_analysis_reason( $args );

if ( null !== $single_reason ) {
	if ( $fix ) {
		fwrite( STDERR, "--fix always uses the default configurations and every path, so it cannot be combined with $single_reason.\n" );
		exit( 1 );
	}
	fwrite( STDERR, "Running a single analysis, because of $single_reason.\n" );
	exit( analyse( 'Analyzing', $phpstan, $args ) );
}

if ( $fix ) {
	foreach ( array( PROJECT_CONFIG, STRICT_CONFIG ) as $config ) {
		$status = analyse( "Fixing with $config", $phpstan, array( "--configuration=$config", '--fix', ...$args ) );

		// PHPStan exits with 1 both when it fixed something and when there was nothing to fix.
		if ( $status > 1 ) {
			exit( $status );
		}
	}

	$status = run( 'Deleting the baselines left without entries', __DIR__ . '/generate-baselines.php', array( '--prune' ) );
	if ( 0 !== $status ) {
		exit( $status );
	}

	$exit_code = 0;
	foreach ( array( PROJECT_CONFIG, STRICT_CONFIG ) as $config ) {
		$exit_code = max( $exit_code, analyse( "Analyzing with $config", $phpstan, array( "--configuration=$config", ...$args ) ) );
	}
	exit( $exit_code );
}

$local_config  = $repo_root . '/phpstan.neon';
$strict_config = is_file( $repo_root . '/phpstan-strict.neon' ) ? 'phpstan-strict.neon' : STRICT_CONFIG;

// PHPStan reads phpstan.neon in place of phpstan.neon.dist by itself, so the first analysis names neither.
$exit_code = analyse( 'Analyzing with ' . ( is_file( $local_config ) ? 'phpstan.neon' : PROJECT_CONFIG ), $phpstan, $args );

if ( is_file( $local_config ) ) {
	$local_level  = get_level( $phpstan, $local_config );
	$strict_level = get_level( $phpstan, $repo_root . '/' . $strict_config );

	if ( null !== $local_level && null !== $strict_level && is_level_at_least( $local_level, $strict_level ) ) {
		fwrite( STDERR, "\nSkipping $strict_config: phpstan.neon analyzes at level $local_level, and the strict paths are held to $strict_level. If phpstan.neon leaves any of them out, run `composer phpstan:strict` too.\n" );
		exit( $exit_code );
	}
}

exit( max( $exit_code, analyse( "Analyzing the strict paths with $strict_config", $phpstan, array( "--configuration=$strict_config", ...$args ) ) ) );

/**
 * Runs a command with a heading, passing its output through.
 *
 * The heading goes to stderr, so that what the command writes to stdout, such as
 * a report in a machine-readable format, is left as it is.
 *
 * The script runs on the PHP running this one, which Composer's `@php` chose,
 * rather than on whichever `php` the script's shebang finds first.
 *
 * @param string   $heading Heading describing the run.
 * @param string   $script  Path to the PHP script to run.
 * @param string[] $args    Arguments, which are escaped for the shell here.
 * @return int Exit status of the script.
 */
function run( string $heading, string $script, array $args ): int {
	fwrite( STDERR, "\n== $heading ==\n\n" );

	$parts = array( escapeshellarg( PHP_BINARY ), escapeshellarg( $script ) );
	foreach ( $args as $arg ) {
		$parts[] = escapeshellarg( $arg );
	}

	$exit_code = 0;
	passthru( implode( ' ', $parts ), $exit_code );

	return $exit_code;
}

/**
 * Runs PHPStan's `analyse` command with a heading, passing its output through.
 *
 * @param string       $heading Heading describing the run.
 * @param string       $phpstan Path to the PHPStan executable.
 * @param list<string> $args    Arguments to the command, which are escaped for the shell.
 * @return int Exit status of PHPStan.
 */
function analyse( string $heading, string $phpstan, array $args ): int {
	return run( $heading, $phpstan, array( 'analyse', ...$args ) );
}

/**
 * Gets the rule level a configuration analyzes at.
 *
 * Only the line of the parameter is matched, rather than decoding the whole dump,
 * because the JSON PHPStan prints can be invalid: it writes the `\<` in a message
 * pattern as is, which is not a valid JSON escape.
 *
 * @param string $phpstan Path to the PHPStan executable.
 * @param string $config  Absolute path to the configuration file.
 * @return string|null The rule level, as digits or `max`, or null if it could not be read.
 */
function get_level( string $phpstan, string $config ): ?string {
	$output    = array();
	$exit_code = 0;
	exec( sprintf( '%s %s dump-parameters --configuration=%s --json 2>/dev/null', escapeshellarg( PHP_BINARY ), escapeshellarg( $phpstan ), escapeshellarg( $config ) ), $output, $exit_code );

	// A configuration PHPStan cannot load, for one, leaves no parameters to read.
	if ( 0 !== $exit_code ) {
		return null;
	}

	foreach ( $output as $line ) {
		if ( 1 === preg_match( '/^\s*"level":\s*("max"|"?\d+"?),?$/', $line, $matches ) ) {
			return trim( $matches[1], '"' );
		}
	}

	return null;
}

/**
 * Determines whether a rule level is at least as strict as another.
 *
 * `max` is the highest level of whichever PHPStan version is installed, so it is
 * at least any other. A numbered level is not taken to be at least `max`, since
 * that would only hold until PHPStan adds a level above it.
 *
 * @param string $level   Rule level, as digits or `max`.
 * @param string $minimum Rule level to compare it with, as digits or `max`.
 * @return bool Whether the level is at least the minimum.
 */
function is_level_at_least( string $level, string $minimum ): bool {
	if ( 'max' === $level ) {
		return true;
	}
	if ( 'max' === $minimum ) {
		return false;
	}
	return (int) $level >= (int) $minimum;
}

/**
 * Finds an argument that only makes sense for a single analysis.
 *
 * Those are a configuration, a level, a report format, a baseline to generate,
 * `--fix-ignored`, and the paths to analyze. An option's value can follow it as the
 * next argument, so that is skipped rather than taken for a path.
 *
 * @param list<string> $args Arguments.
 * @return string|null Description of the first such argument, or null if there is none.
 */
function get_single_analysis_reason( array $args ): ?string {
	// The options whose value can be given as the next argument rather than after `=`.
	$takes_value = array( '-c', '--configuration', '-l', '--level', '-a', '--autoload-file', '--error-format', '--memory-limit', '--tmp-file', '--instead-of' );
	$single      = array( '-c', '--configuration', '-l', '--level', '--error-format', '--generate-baseline', '-b', '--fix-ignored' );

	$is_value = false;
	foreach ( $args as $i => $arg ) {
		// The value of the option before, given as an argument of its own.
		if ( $is_value ) {
			$is_value = false;
			continue;
		}

		if ( '--' === $arg ) {
			return $i + 1 < count( $args ) ? 'the paths given' : null;
		}

		if ( '' === $arg || '-' !== $arg[0] ) {
			return 'the paths given';
		}

		// A long option's value follows `=`, and a short option's follows its letter directly, as in `-l6`.
		if ( 0 === strpos( $arg, '--' ) ) {
			$name      = explode( '=', $arg, 2 )[0];
			$has_value = false !== strpos( $arg, '=' );
		} else {
			$name      = substr( $arg, 0, 2 );
			$has_value = strlen( $arg ) > 2;
		}

		if ( in_array( $name, $single, true ) ) {
			return $name;
		}

		if ( ! $has_value && in_array( $name, $takes_value, true ) ) {
			$is_value = true;
		}
	}

	return null;
}
