<?php
/**
 * Generates PHPStan baselines split by error identifier.
 *
 * PHPStan's own --generate-baseline captures every error a run reports, with no
 * way to restrict it to a particular identifier. That makes it unusable for
 * refreshing one baseline among several: rerunning it would sweep every other
 * kind of error into the same file.
 *
 * This wrapper runs the analysis with the existing per-identifier baselines
 * suppressed, so their errors surface again, then splits the result by
 * identifier and writes one file per kind. Each file is self-describing and is
 * meant to shrink to nothing and then be deleted.
 *
 * Baselines whose identifier no longer reports anything are removed, and the
 * list of them between the `# phpstan:baselines` markers in the configuration's
 * `includes` is rewritten to match what is on disk. Adding a newly split out
 * baseline, and retiring one that has reached zero, therefore need no manual
 * edit of the configuration.
 *
 * The intermediate baseline is generated in PHPStan's PHP format and read back
 * with `require`, so the entries arrive as an array. Nothing has to parse, or
 * re-escape, the message patterns.
 *
 * Run it through Composer:
 *
 *     composer phpstan:baselines
 *     composer phpstan:baselines -- --identifier=variable.undefined
 *     composer phpstan:baselines -- --identifier=variable.undefined,isset.variable
 *     composer phpstan:baselines -- --identifier=isset.variable --identifier=empty.variable
 *     composer phpstan:baselines -- --combined > all-errors.neon
 *
 * @package WordPress
 */

namespace WordPress\PHPStan;

/**
 * Opening marker of the region of the configuration's `includes` this script owns.
 *
 * Everything between this and the closing marker is generated: it is rewritten to
 * match the baselines on disk, and suppressed while the analysis runs.
 */
const BASELINES_START_MARKER = '# phpstan:baselines start';

/**
 * Closing marker of the region of the configuration's `includes` this script owns.
 */
const BASELINES_END_MARKER = '# phpstan:baselines end';

/**
 * The configuration analyzed when `--config` does not name one.
 *
 * Relative to the repository root.
 */
const DEFAULT_CONFIG = 'phpstan.neon.dist';

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$repo_root = dirname( __DIR__, 2 );

// $argv is only populated when register_argc_argv is on, so read it defensively.
$args = array();
foreach ( (array) ( $_SERVER['argv'] ?? array() ) as $arg ) {
	if ( is_string( $arg ) ) {
		$args[] = $arg;
	}
}
array_shift( $args );

$config_option    = DEFAULT_CONFIG;
$output_option    = 'tests/phpstan/baselines';
$memory_limit     = '2G';
$only_identifiers = array();
$combined         = false;

foreach ( $args as $arg ) {
	if ( '--help' === $arg || '-h' === $arg ) {
		fwrite( STDOUT, get_usage() );
		exit( 0 );
	}

	if ( '--combined' === $arg ) {
		$combined = true;
		continue;
	}

	if ( 1 === preg_match( '/^--identifier=(.+)$/', $arg, $matches ) ) {
		foreach ( explode( ',', $matches[1] ) as $identifier ) {
			$identifier = trim( $identifier );
			if ( '' !== $identifier ) {
				$only_identifiers[] = $identifier;
			}
		}
		continue;
	}

	if ( 1 === preg_match( '/^--config=(.+)$/', $arg, $matches ) ) {
		$config_option = $matches[1];
		continue;
	}

	if ( 1 === preg_match( '/^--output-dir=(.+)$/', $arg, $matches ) ) {
		$output_option = $matches[1];
		continue;
	}

	if ( 1 === preg_match( '/^--memory-limit=(.+)$/', $arg, $matches ) ) {
		$memory_limit = $matches[1];
		continue;
	}

	fwrite( STDERR, "Unrecognized option: $arg\n\n" . get_usage() );
	exit( 1 );
}

$config_path = $repo_root . '/' . ltrim( $config_option, '/' );
$output_dir  = $repo_root . '/' . trim( $output_option, '/' );

if ( ! is_file( $config_path ) ) {
	fwrite( STDERR, "Configuration not found: $config_option\n" );
	exit( 1 );
}

/*
 * All three temporary files sit beside the configuration, and so inside the
 * repository, for two separate reasons.
 *
 * A neon file's `includes` resolve relative to its own directory, so the copy of
 * the configuration, and the wrapper including it, have to live where the
 * original did.
 *
 * PHPStan writes a PHP baseline's paths as __DIR__ followed by a relative chain,
 * which it can only produce when the baseline shares an ancestry with the files
 * it names. Generated somewhere else, the system temporary directory included,
 * it emits `__DIR__ . '//absolute/path'` instead, and every path in it then
 * resolves to somewhere under that directory rather than to the source file.
 */
$temp_prefix   = dirname( $config_path ) . '/.phpstan-baselines-' . getmypid();
$temp_config   = $temp_prefix . '.neon';
$temp_stripped = $temp_prefix . '-stripped.neon';
$temp_baseline = $temp_prefix . '.php';

register_shutdown_function(
	static function () use ( $temp_config, $temp_stripped, $temp_baseline ): void {
		foreach ( array( $temp_config, $temp_stripped, $temp_baseline ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
);

file_put_contents( $temp_stripped, strip_baseline_includes( $config_path, $output_dir ) );

/*
 * The analysis gets a scratch directory of its own, rather than the `tmpDir` that
 * `tests/phpstan/base.neon` configures for everything else.
 *
 * That directory holds more than the analysis results. PHPStan also stores what
 * it read out of each source file there, the docblocks and signatures it found,
 * keyed by that file's contents and nothing else. The extensions in this
 * directory change what reading a file yields without changing the file:
 * HashNotationVisitor rewrites a docblock in the syntax tree, and the bytes on
 * disk stay as they were.
 *
 * That rewriting only reaches the files PHPStan routes to its rich parser, which
 * is the set of files being analyzed. A run narrowed to a few paths — an editor
 * analyzing the file being typed in, or one scoped to a diff — therefore caches
 * the unrewritten reading of every file outside those paths, and a later full run
 * sharing the directory restores it. The baseline generated from that run records
 * messages derived from types the extensions would have replaced.
 *
 * A directory only ever written by this script cannot be poisoned that way,
 * because this script only ever analyzes the full set of configured paths.
 */
$analysis_tmp_dir = $repo_root . '/.cache/baselines';

/*
 * A configuration other than the default gets a directory to itself.
 *
 * "The full set of configured paths" is only as wide as the configuration saying
 * what they are, and `--config` can name one covering less than the default does.
 * A run of that configuration is a full run of it, but measured against the
 * default it is a narrowed one, and it would leave behind the same unrewritten
 * readings of everything it does not cover. Keying the directory on the
 * configuration keeps each one's reflection to itself.
 */
$config_relative = trim( normalize_path( $config_option ), '/' );

if ( DEFAULT_CONFIG !== $config_relative ) {
	$analysis_tmp_dir .= '-' . (string) preg_replace( '/[^A-Za-z0-9._]+/', '-', $config_relative );
}

/*
 * `tmpDir` is set in a wrapper including the stripped copy, rather than in the
 * copy itself, because neon rejects a duplicated key and the copy already carries
 * the `parameters` section it was made from. A file's own parameters win over
 * those of the files it includes, so the wrapper's `tmpDir` is the one that takes
 * effect.
 */
file_put_contents(
	$temp_config,
	sprintf(
		<<<'NEON'
		includes:
			- %s
		parameters:
			tmpDir: %s
		NEON,
		basename( $temp_stripped ),
		quote_neon_value( $analysis_tmp_dir )
	)
);

/*
 * PHPStan reports on stdout, which --combined reserves for the baseline itself,
 * so its output is sent to stderr. That keeps it visible on a terminal while
 * leaving stdout parseable when it is redirected.
 */
$command = sprintf(
	'%s analyse --configuration=%s --generate-baseline=%s --allow-empty-baseline --no-progress --memory-limit=%s 1>&2',
	escapeshellarg( $repo_root . '/vendor/bin/phpstan' ),
	escapeshellarg( $temp_config ),
	escapeshellarg( $temp_baseline ),
	escapeshellarg( $memory_limit )
);

fwrite( STDERR, "Analyzing with $config_option, existing baselines suppressed...\n" );

$exit_code = 0;
passthru( $command, $exit_code );

if ( 0 !== $exit_code || ! is_file( $temp_baseline ) ) {
	fwrite( STDERR, "PHPStan failed, nothing written.\n" );
	exit( 1 );
}

/**
 * The entries of each error, grouped by the identifier of the error it suppresses.
 *
 * @var array<non-falsy-string, list<array{message: string, identifier: non-falsy-string, count: int<0, max>, path: non-empty-string}>> $grouped
 */
$grouped = array();

foreach ( read_baseline( $temp_baseline ) as $entry ) {
	$grouped[ $entry['identifier'] ][] = $entry;
}
ksort( $grouped );

if ( $only_identifiers ) {
	$grouped = array_intersect_key( $grouped, array_flip( $only_identifiers ) );
}

if ( $combined ) {
	$all = array();
	foreach ( $grouped as $entries ) {
		$all = array_merge( $all, $entries );
	}
	echo build_baseline( $all, $output_dir, "# Every identifier, combined.\n" );
	exit( 0 );
}

if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0755, true ) ) {
	fwrite( STDERR, "Could not create $output_option\n" );
	exit( 1 );
}

foreach ( $grouped as $identifier => $entries ) {
	file_put_contents(
		$output_dir . '/' . $identifier . '.neon',
		build_baseline( $entries, $output_dir, build_baseline_header( $identifier, $config_option ) )
	);

	printf(
		"%s: %d entries, %d errors\n",
		$output_option . '/' . $identifier . '.neon',
		count( $entries ),
		count_errors( $entries )
	);
}

/*
 * An identifier that reports nothing has been driven to zero, so retire its file
 * rather than leaving a stale one behind whose entries would then be reported as
 * unmatched ignores.
 *
 * A run restricted to particular identifiers only knows about those, so it may
 * only retire those. A full run has seen everything and may retire any file that
 * no longer corresponds to a reported identifier.
 */
$retired = $only_identifiers;

if ( ! $only_identifiers ) {
	foreach ( find_baselines( $output_dir ) as $file ) {
		$retired[] = basename( $file, '.neon' );
	}
}

foreach ( $retired as $identifier ) {
	if ( isset( $grouped[ $identifier ] ) ) {
		continue;
	}

	$file = $output_dir . '/' . $identifier . '.neon';
	if ( is_file( $file ) && unlink( $file ) ) {
		printf( "%s: no errors remain, file deleted.\n", $output_option . '/' . $identifier . '.neon' );
	} else {
		printf( "%s: no errors reported.\n", $identifier );
	}
}

update_config_includes( $config_path, $config_option, $output_dir );

/**
 * Returns the usage message.
 *
 * @return non-falsy-string Usage message.
 */
function get_usage(): string {
	return <<<'TEXT'
		Generates PHPStan baselines split by error identifier.

		Writes one baseline per identifier, retires any whose identifier no longer
		reports anything, and rewrites the list of them between the
		`# phpstan:baselines` markers in the configuration's `includes`, so that
		neither addition nor removal has to be done by hand.

		Usage:
		  composer phpstan:baselines [-- <options>]

		Options:
		  --identifier=<id>    Only write the baseline for this identifier. Repeatable,
		                       or comma separated. When an identifier is named and the
		                       analysis reports none of it, its baseline file is deleted
		                       rather than left behind empty.
		                       Default: every identifier reported.
		  --config=<path>      Configuration to analyze with, relative to the repository
		                       root. Default: phpstan.neon.dist
		  --output-dir=<path>  Where the per-identifier baselines are written, relative
		                       to the repository root. Paths inside them are written
		                       relative to this directory.
		                       Default: tests/phpstan/baselines
		  --combined           Print one combined baseline to stdout instead of writing
		                       per-identifier files. Nothing is written to disk.
		  --memory-limit=<v>   Passed through to PHPStan. Default: 2G
		  -h, --help           Show this message.

		Examples:
		  Refresh every baseline:
		    composer phpstan:baselines

		  Refresh one:
		    composer phpstan:baselines -- --identifier=variable.undefined

		  Refresh several, either comma separated or by repeating the option:
		    composer phpstan:baselines -- --identifier=variable.undefined,isset.variable
		    composer phpstan:baselines -- --identifier=isset.variable --identifier=empty.variable

		  Inspect everything as one baseline without writing any files:
		    composer phpstan:baselines -- --combined

		TEXT;
}

/**
 * Reads a file, failing loudly rather than continuing with false.
 *
 * @param non-falsy-string $path Absolute path to the file.
 * @return string File contents.
 */
function read_file( string $path ): string {
	$contents = file_get_contents( $path );

	if ( false === $contents ) {
		fwrite( STDERR, "Could not read $path\n" );
		exit( 1 );
	}

	return $contents;
}

/**
 * Reads a baseline generated in PHPStan's PHP format.
 *
 * The file returns the entries as an array, so it is required rather than
 * parsed. Its `path` values are built from __DIR__ and so arrive absolute.
 *
 * @param non-falsy-string $path Absolute path to the generated baseline.
 * @return list<array{message: string, identifier: non-falsy-string, count: int<0, max>, path: non-empty-string}> Baseline entries.
 */
function read_baseline( string $path ): array {
	$data = require $path;

	$parameters    = is_array( $data ) ? ( $data['parameters'] ?? null ) : null;
	$ignore_errors = is_array( $parameters ) ? ( $parameters['ignoreErrors'] ?? null ) : null;

	if ( ! is_array( $ignore_errors ) ) {
		fwrite( STDERR, "Unexpected baseline structure in $path\n" );
		exit( 1 );
	}

	$entries = array();

	foreach ( $ignore_errors as $entry ) {
		if ( ! is_array( $entry )
			|| ! isset( $entry['message'], $entry['identifier'], $entry['count'], $entry['path'] )
			|| ! is_string( $entry['message'] )
			|| ! is_string( $entry['identifier'] )
			|| ! is_int( $entry['count'] )
			|| $entry['count'] < 0
			|| ! is_string( $entry['path'] )
			|| '' === $entry['path']
		) {
			fwrite( STDERR, "Unexpected baseline entry in $path.\n" );
			exit( 1 );
		}

		/*
		 * PHPStan attaches an identifier to every error it reports, so an entry
		 * without a usable one means this is not a baseline that can be split by
		 * identifier. Skipping it would quietly drop a suppression.
		 */
		if ( '' === $entry['identifier'] || '0' === $entry['identifier'] ) {
			fwrite( STDERR, "Baseline entry in $path has no identifier.\n" );
			exit( 1 );
		}

		$entries[] = array(
			'message'    => $entry['message'],
			'identifier' => $entry['identifier'],
			'count'      => $entry['count'],
			'path'       => $entry['path'],
		);
	}

	return $entries;
}

/**
 * Returns the configuration with the baseline `includes` removed.
 *
 * Those files suppress the very errors being regenerated, so they have to be out
 * of the way for the analysis to report anything.
 *
 * Two kinds of include are dropped. Everything between the `# phpstan:baselines`
 * markers goes, whatever it points at, because that region is generated and every
 * baseline in force is listed there. Any other include resolving inside the output
 * directory goes as well, so that a baseline listed by hand outside the markers is
 * still suppressed when it is about to be rewritten.
 *
 * The distinction matters to `--output-dir`. Were only the output directory
 * considered, writing somewhere other than the default would leave the baselines
 * already in force active, and the analysis would report just the errors they do
 * not cover: a differential baseline rather than a complete one.
 *
 * @param non-falsy-string $config_path Absolute path to the configuration file.
 * @param non-falsy-string $output_dir  Absolute path to the baseline directory.
 * @return string Configuration contents.
 */
function strip_baseline_includes( string $config_path, string $output_dir ): string {
	$config_dir = dirname( $config_path );
	$in_block   = false;
	$in_managed = false;
	$kept       = array();

	foreach ( explode( "\n", read_file( $config_path ) ) as $line ) {
		if ( 1 === preg_match( '/^includes:/', $line ) ) {
			$in_block = true;
			$kept[]   = $line;
			continue;
		}

		// A non-indented, non-blank line ends the block.
		if ( $in_block && '' !== trim( $line ) && 1 !== preg_match( '/^\s/', $line ) ) {
			$in_block   = false;
			$in_managed = false;
		}

		if ( $in_block ) {
			if ( BASELINES_START_MARKER === trim( $line ) ) {
				$in_managed = true;
			} elseif ( BASELINES_END_MARKER === trim( $line ) ) {
				$in_managed = false;
			}
		}

		if ( $in_block && 1 === preg_match( '/^\s*-\s*(\S+)\s*$/', $line, $matches ) ) {
			if ( $in_managed ) {
				continue;
			}

			$included = $matches[1];
			$absolute = ( '/' === $included[0] ) ? $included : $config_dir . '/' . $included;

			if ( 0 === strpos( normalize_path( $absolute ), normalize_path( $output_dir ) . '/' ) ) {
				continue;
			}
		}

		$kept[] = $line;
	}

	return implode( "\n", $kept );
}

/**
 * Lists the per-identifier baselines present on disk.
 *
 * @param non-empty-string $output_dir Absolute path to the baseline directory.
 * @return list<non-empty-string> Absolute paths, sorted by name.
 */
function find_baselines( string $output_dir ): array {
	$found = glob( $output_dir . '/*.neon' );

	if ( false === $found ) {
		return array();
	}

	sort( $found );

	$files = array();
	foreach ( $found as $file ) {
		if ( '' !== $file ) {
			$files[] = $file;
		}
	}

	return $files;
}

/**
 * Rewrites the managed region of the configuration's `includes` list.
 *
 * The region is delimited by marker comments, so the hand written entries around
 * it are never touched. Where the markers are absent they are appended to the end
 * of the `includes` block, which is what happens the first time this is run
 * against a configuration.
 *
 * @param non-falsy-string $config_path   Absolute path to the configuration file.
 * @param non-empty-string $config_option Configuration path, as passed on the command line.
 * @param non-empty-string $output_dir    Absolute path to the baseline directory.
 */
function update_config_includes( string $config_path, string $config_option, string $output_dir ): void {
	$start_marker = BASELINES_START_MARKER;
	$end_marker   = BASELINES_END_MARKER;

	$before = read_file( $config_path );
	$lines  = explode( "\n", $before );

	$start = null;
	$end   = null;
	foreach ( $lines as $i => $line ) {
		if ( $start_marker === trim( $line ) ) {
			$start = $i;
		}
		if ( $end_marker === trim( $line ) ) {
			$end = $i;
		}
	}

	$region = array( "\t" . $start_marker );
	foreach ( find_baselines( $output_dir ) as $file ) {
		$region[] = "\t- " . get_relative_path( dirname( $config_path ), $file );
	}
	$region[] = "\t" . $end_marker;

	if ( null !== $start && null !== $end && $start < $end ) {
		$updated = array_merge(
			array_slice( $lines, 0, $start ),
			$region,
			array_slice( $lines, $end + 1 )
		);
	} else {
		$insert = find_includes_end( $lines );

		if ( null === $insert ) {
			fwrite( STDERR, "No `includes` block found in $config_option, left untouched.\n" );
			return;
		}

		$updated = array_merge(
			array_slice( $lines, 0, $insert ),
			array( '' ),
			$region,
			array_slice( $lines, $insert )
		);
	}

	$after = implode( "\n", $updated );

	if ( $before === $after ) {
		return;
	}

	file_put_contents( $config_path, $after );
	printf( "%s: `includes` updated.\n", $config_option );
}

/**
 * Finds where the `includes` block ends.
 *
 * @param list<string> $lines Configuration lines.
 * @return int|null Index of the first line after the block, or null when there is none.
 */
function find_includes_end( array $lines ): ?int {
	$in_block = false;
	$last     = null;

	foreach ( $lines as $i => $line ) {
		if ( 1 === preg_match( '/^includes:/', $line ) ) {
			$in_block = true;
			$last     = $i;
			continue;
		}

		if ( ! $in_block || '' === trim( $line ) ) {
			continue;
		}

		// A non-indented line ends the block.
		if ( 1 !== preg_match( '/^\s/', $line ) ) {
			break;
		}

		$last = $i;
	}

	return null === $last ? null : $last + 1;
}

/**
 * Resolves ".." segments in a path without requiring it to exist.
 *
 * @param non-empty-string $path Path to normalize.
 * @return non-falsy-string Normalized path, always absolute.
 */
function normalize_path( string $path ): string {
	$parts = array();

	foreach ( explode( '/', $path ) as $part ) {
		if ( '' === $part || '.' === $part ) {
			continue;
		}
		if ( '..' === $part ) {
			array_pop( $parts );
			continue;
		}
		$parts[] = $part;
	}

	return '/' . implode( '/', $parts );
}

/**
 * Expresses one absolute path relative to a directory.
 *
 * A result of "0" is possible in principle, when the target is a single segment
 * named "0" directly inside $from_dir, so this is non-empty rather than non-falsy.
 *
 * @param non-empty-string $from_dir Directory to express the path relative to.
 * @param non-empty-string $to_path  Path to express.
 * @return non-empty-string Relative path, or "." when the two are the same.
 */
function get_relative_path( string $from_dir, string $to_path ): string {
	$from = explode( '/', trim( normalize_path( $from_dir ), '/' ) );
	$to   = explode( '/', trim( normalize_path( $to_path ), '/' ) );

	while ( $from && $to && $from[0] === $to[0] ) {
		array_shift( $from );
		array_shift( $to );
	}

	$relative = str_repeat( '../', count( $from ) ) . implode( '/', $to );

	return '' === $relative ? '.' : $relative;
}

/**
 * Totals the `count` values across a set of entries.
 *
 * @param list<array{message: string, identifier: non-falsy-string, count: int<0, max>, path: non-empty-string}> $entries Baseline entries.
 * @return int<0, max> Total number of errors.
 */
function count_errors( array $entries ): int {
	$total = 0;

	foreach ( $entries as $entry ) {
		$total += $entry['count'];
	}

	return $total;
}

/**
 * Builds a baseline file in PHPStan's NEON format.
 *
 * The entry layout matches what PHPStan itself writes, so a regenerated file can
 * be diffed against one it produced. Paths are rewritten relative to the file's
 * own directory, since that is what a NEON `path` resolves against.
 *
 * @param list<array{message: string, identifier: non-falsy-string, count: int<0, max>, path: non-empty-string}> $entries    Baseline entries.
 * @param non-empty-string                                                                                       $output_dir Directory the file is written to.
 * @param string                                                                                                 $header     Comment block, or an empty string.
 * @return non-falsy-string Baseline file contents.
 */
function build_baseline( array $entries, string $output_dir, string $header ): string {
	$contents = ( '' === $header ? '' : $header . "\n" ) . "parameters:\n\tignoreErrors:\n";

	foreach ( $entries as $entry ) {
		$contents .= "\t\t-\n"
			. "\t\t\tmessage: " . quote_neon_value( $entry['message'] ) . "\n"
			. "\t\t\tidentifier: " . $entry['identifier'] . "\n"
			. "\t\t\tcount: " . $entry['count'] . "\n"
			. "\t\t\tpath: " . get_relative_path( $output_dir, $entry['path'] ) . "\n";
	}

	return $contents;
}

/**
 * Quotes a value for NEON.
 *
 * A single quoted NEON string has no escape sequences other than a doubled
 * quote, so the backslashes in a message pattern survive as written. This is the
 * same quoting PHPStan applies when it generates a baseline itself.
 *
 * @param string $value Value to quote.
 * @return non-falsy-string Quoted value.
 */
function quote_neon_value( string $value ): string {
	return "'" . str_replace( "'", "''", $value ) . "'";
}

/**
 * Builds the header comment for a per-identifier baseline.
 *
 * @param non-falsy-string $identifier Error identifier, a group followed by a code.
 * @param non-empty-string $config     Configuration path, as passed on the command line.
 * @return non-falsy-string Comment block.
 */
function build_baseline_header( string $identifier, string $config ): string {
	return <<<TEXT
		# PHPStan baseline for the `$identifier` errors in WordPress core.
		#
		# https://phpstan.org/error-identifiers/$identifier
		#
		# Each entry is scoped to a single file and carries an exact occurrence count,
		# so that a new instance is reported as a new error rather than being absorbed
		# silently. Fixing an occurrence therefore means decrementing or removing its
		# entry here as part of the same change.
		#
		# The goal is to empty this file and delete it, along with the `includes` entry
		# for it in $config.
		#
		# Generated by `composer phpstan:baselines`. Do not edit by hand; regenerate with
		#
		#     composer phpstan:baselines -- --identifier=$identifier
		#
		# which reruns the analysis with this file suppressed so the errors surface again.

		TEXT;
}
