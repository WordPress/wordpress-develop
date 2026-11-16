<?php
/**
 * Runs the PHPStan analyses of WordPress core.
 *
 * Core is analyzed twice: the whole codebase with phpstan.neon.dist, and then the
 * paths held to a stricter rule level with phpstan-level-10.neon.dist. A Composer
 * script listing the two would stop at the first, since PHPStan exits with a
 * failure status whenever it reports an error, so this runs each in turn whatever
 * the other reports, and exits with the highest status of them.
 *
 * Each analysis honors a local override of its configuration, the same way
 * PHPStan does: phpstan.neon in place of phpstan.neon.dist, which PHPStan finds by
 * itself, and phpstan-level-10.neon in place of phpstan-level-10.neon.dist, which
 * this script chooses. When a local phpstan.neon already analyzes the whole
 * codebase at level 10, the second analysis could only repeat some of what the
 * first reported, so it is skipped.
 *
 * With `--fix`, the stale entries of both sets of baselines are fixed instead,
 * along with anything else PHPStan can fix. That always uses the two .dist
 * configurations, whatever a local override says, because a configuration at
 * another rule level can report a baselined error with a different message, and
 * `--fix` would then remove an entry the default configuration still needs. The
 * baselines left without entries are then deleted, and both analyses run again
 * to show what remains.
 *
 * Every other argument is passed to each analysis. Naming a configuration with
 * `--configuration` or `-c` runs only that one, as PHPStan itself would.
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
const STRICT_CONFIG = 'phpstan-level-10.neon.dist';

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

foreach ( $args as $arg ) {
	if ( '-c' === $arg || str_starts_with( $arg, '--configuration' ) || ( str_starts_with( $arg, '-c' ) && ! str_starts_with( $arg, '--' ) ) ) {
		if ( $fix ) {
			fwrite( STDERR, "--fix always uses the default configurations, so it cannot be combined with --configuration.\n" );
			exit( 1 );
		}
		exit( run( 'Analyzing with the configuration given', $phpstan, $args ) );
	}
}

if ( $fix ) {
	foreach ( array( PROJECT_CONFIG, STRICT_CONFIG ) as $config ) {
		$status = run( "Fixing with $config", $phpstan, array_merge( array( "--configuration=$config", '--fix' ), $args ) );

		// PHPStan exits with 1 both when it fixed something and when there was nothing to fix.
		if ( $status > 1 ) {
			exit( $status );
		}
	}

	$status = run( 'Deleting the baselines left without entries', PHP_BINARY, array( __DIR__ . '/generate-baselines.php', '--prune' ), false );
	if ( 0 !== $status ) {
		exit( $status );
	}

	$exit_code = 0;
	foreach ( array( PROJECT_CONFIG, STRICT_CONFIG ) as $config ) {
		$exit_code = max( $exit_code, run( "Analyzing with $config", $phpstan, array_merge( array( "--configuration=$config" ), $args ) ) );
	}
	exit( $exit_code );
}

$local_config  = $repo_root . '/phpstan.neon';
$strict_config = is_file( $repo_root . '/phpstan-level-10.neon' ) ? 'phpstan-level-10.neon' : STRICT_CONFIG;

// PHPStan reads phpstan.neon in place of phpstan.neon.dist by itself, so the first analysis names neither.
$exit_code = run( 'Analyzing with ' . ( is_file( $local_config ) ? 'phpstan.neon' : PROJECT_CONFIG ), $phpstan, $args );

if ( is_file( $local_config ) && is_strictest_level( $phpstan, $local_config ) ) {
	fwrite( STDERR, "\nSkipping $strict_config: phpstan.neon already analyzes every path at level 10.\n" );
	exit( $exit_code );
}

exit( max( $exit_code, run( "Analyzing the strict paths with $strict_config", $phpstan, array_merge( array( "--configuration=$strict_config" ), $args ) ) ) );

/**
 * Runs a command with a heading, passing its output through.
 *
 * The heading goes to stderr, so that what the command writes to stdout, such as
 * a report in a machine-readable format, is left as it is.
 *
 * @param string   $heading     Heading describing the run.
 * @param string   $command     Command to run.
 * @param string[] $args        Arguments, each escaped for the shell.
 * @param bool     $is_analysis Whether the command is a PHPStan analysis, to which `analyse` is passed first.
 * @return int Exit status of the command.
 */
function run( string $heading, string $command, array $args, bool $is_analysis = true ): int {
	fwrite( STDERR, "\n== $heading ==\n\n" );

	$parts = array( escapeshellarg( $command ) );
	if ( $is_analysis ) {
		$parts[] = 'analyse';
	}
	foreach ( $args as $arg ) {
		$parts[] = escapeshellarg( $arg );
	}

	$exit_code = 0;
	passthru( implode( ' ', $parts ), $exit_code );

	return $exit_code;
}

/**
 * Determines whether a configuration analyzes at the strictest rule level.
 *
 * Only the line of the parameter is matched, rather than decoding the whole dump,
 * because the JSON PHPStan prints can be invalid: it writes the `\<` in a message
 * pattern as is, which is not a valid JSON escape.
 *
 * @param string $phpstan Path to the PHPStan executable.
 * @param string $config  Absolute path to the configuration file.
 * @return bool Whether the configuration's rule level is 10 or `max`.
 */
function is_strictest_level( string $phpstan, string $config ): bool {
	$output    = array();
	$exit_code = 0;
	exec( sprintf( '%s dump-parameters --configuration=%s --json 2>/dev/null', escapeshellarg( $phpstan ), escapeshellarg( $config ) ), $output, $exit_code );

	foreach ( $output as $line ) {
		if ( 1 === preg_match( '/^\s*"level": ("max"|"?\d+"?),?$/', $line, $matches ) ) {
			$level = trim( $matches[1], '"' );
			return 'max' === $level || (int) $level >= 10;
		}
	}

	return false;
}
