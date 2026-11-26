<?php
/**
 * Generates PHPStan baselines split by error identifier.
 *
 * PHPStan's own --generate-baseline captures every error a run reports, with no
 * way to restrict it to a particular identifier. That makes it unusable for
 * refreshing one baseline among several: rerunning it would sweep every other
 * kind of error into the same file.
 *
 * This wrapper runs the analysis with the baselines being regenerated
 * suppressed, so their errors surface again, then splits the result by
 * identifier and writes one file per kind. Each file is self-describing and is
 * meant to shrink to nothing and then be deleted.
 *
 * Baselines whose identifier no longer reports anything are removed. The
 * configuration includes them through `tests/phpstan/baselines/loader.php`, which
 * includes every baseline in that directory, so adding a newly split out baseline,
 * and retiring one that has reached zero, need no edit of the configuration.
 *
 * Unless a configuration is named, this is done for each of DEFAULT_CONFIGS in
 * turn: phpstan.neon.dist, which analyzes the whole codebase, and then
 * phpstan-strict.neon.dist, which holds selected paths to a stricter rule level
 * and includes the former along with its baselines.
 *
 * With `--prune`, nothing is analyzed: the baselines that no longer list any
 * entries are deleted. That completes running PHPStan with `--fix`, which removes
 * the entries of fixed errors in place but leaves a baseline it has emptied
 * behind, and so `composer phpstan:fix` runs it with `--prune` after `--fix`.
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
 *     composer phpstan:baselines -- --prune
 *
 * @package WordPress
 */

namespace WordPress\PHPStan;

// GENERATING_BASELINES_ENV and find_baselines(), shared with the loaders.
require_once __DIR__ . '/baseline-includes.php';

/**
 * The directory baselines are written to when `--output-dir` does not name one.
 *
 * Relative to the repository root.
 */
const DEFAULT_OUTPUT_DIR = 'tests/phpstan/baselines';

/**
 * The configuration analyzed when `--config` does not name one.
 *
 * Relative to the repository root.
 */
const DEFAULT_CONFIG = 'phpstan.neon.dist';

/**
 * The configurations processed when neither `--config` nor `--output-dir` is given,
 * each with the directory its baselines are written to.
 *
 * They are processed in this order, which matters: phpstan-strict.neon.dist
 * includes phpstan.neon.dist along with its baselines, so its own are only what is
 * left over once those are up to date.
 *
 * Relative to the repository root.
 */
const DEFAULT_CONFIGS = array(
	DEFAULT_CONFIG             => DEFAULT_OUTPUT_DIR,
	'phpstan-strict.neon.dist' => 'tests/phpstan/baselines-strict',
);

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
$output_option    = null;
$config_given     = false;
$memory_limit     = '2G';
$only_identifiers = array();
$combined         = false;
$prune            = false;

foreach ( $args as $arg ) {
	if ( '--help' === $arg || '-h' === $arg ) {
		fwrite( STDOUT, get_usage() );
		exit( 0 );
	}

	if ( '--combined' === $arg ) {
		$combined = true;
		continue;
	}

	if ( '--prune' === $arg ) {
		$prune = true;
		continue;
	}

	if ( 1 === preg_match( '/^--identifier=(.+)$/', $arg, $matches ) ) {
		foreach ( explode( ',', $matches[1] ) as $identifier ) {
			$identifier = trim( $identifier );
			if ( '' === $identifier ) {
				continue;
			}

			/*
			 * The identifier names the file a baseline is written to and retired
			 * from, so one that is not an identifier, such as `../../../phpstan`,
			 * could name a file outside the baselines, which would be deleted when
			 * no such identifier is reported.
			 */
			if ( 1 !== preg_match( BASELINE_FILE_PATTERN, $identifier . '.neon' ) ) {
				fwrite( STDERR, "Not an error identifier: $identifier\n" );
				exit( 1 );
			}

			$only_identifiers[] = $identifier;
		}
		continue;
	}

	if ( 1 === preg_match( '/^--config=(.+)$/', $arg, $matches ) ) {
		$config_option = $matches[1];
		$config_given  = true;
		continue;
	}

	if ( 1 === preg_match( '/^--output-dir=(.+)$/', $arg, $matches ) ) {
		$output_option = $matches[1];
		$config_given  = true;
		continue;
	}

	if ( 1 === preg_match( '/^--memory-limit=(.+)$/', $arg, $matches ) ) {
		$memory_limit = $matches[1];
		continue;
	}

	fwrite( STDERR, "Unrecognized option: $arg\n\n" . get_usage() );
	exit( 1 );
}

/*
 * Without a configuration named, every default configuration is processed in turn,
 * each by a run of this script naming it, so that each run is the same as one asked
 * for directly. The first failure stops the rest, since each configuration after it
 * depends on the baselines of those before it.
 *
 * `--combined` prints a single baseline, so it keeps to the first configuration.
 */
if ( ! $config_given && ! $combined ) {
	foreach ( DEFAULT_CONFIGS as $config => $output ) {
		$command = implode(
			' ',
			array_map(
				'escapeshellarg',
				array(
					PHP_BINARY,
					__FILE__,
					...$args,
					"--config=$config",
					"--output-dir=$output",
				)
			)
		);

		fwrite( STDERR, "\n== $config ==\n\n" );

		$exit_code = 0;
		passthru( $command, $exit_code );

		if ( 0 !== $exit_code ) {
			exit( $exit_code );
		}
	}
	exit( 0 );
}

/*
 * A default configuration named without an output directory writes to its own,
 * so that naming phpstan-strict.neon.dist alone cannot write its baselines over
 * those of phpstan.neon.dist. So does a local override of one, such as
 * phpstan-strict.neon, named as the `.dist` file is but without the suffix.
 * Written to the default directory instead, the stricter analysis of only the
 * strict paths, with the baselines there suppressed, would retire every baseline of
 * the whole codebase it does not report again.
 */
$config_relative   = trim( normalize_path( $config_option ), '/' );
$config_output_dir = DEFAULT_CONFIGS[ $config_relative ] ?? DEFAULT_CONFIGS[ $config_relative . '.dist' ] ?? DEFAULT_OUTPUT_DIR;

if ( null === $output_option ) {
	$output_option = $config_output_dir;
}

$config_path = $repo_root . '/' . ltrim( $config_option, '/' );
$output_dir  = $repo_root . '/' . trim( $output_option, '/' );

if ( ! is_file( $config_path ) ) {
	fwrite( STDERR, "Configuration not found: $config_option\n" );
	exit( 1 );
}

/*
 * Pruning needs no analysis. Running PHPStan with `--fix` lowers the counts of the
 * entries whose errors were fixed and removes those that no longer match, but when
 * it removes the last entry of a baseline it leaves the file behind, as
 * `ignoreErrors: []`. That file suppresses nothing, so all that remains is to
 * delete it, which is known from the file alone. The loader stops including it
 * once it is gone.
 */
if ( $prune ) {
	if ( $combined ) {
		fwrite( STDERR, "--prune and --combined cannot be used together.\n" );
		exit( 1 );
	}

	$pruned = 0;

	// A directory not written to yet has no baselines to prune.
	foreach ( is_dir( $output_dir ) ? find_baselines( $output_dir ) : array() as $name ) {
		$identifier = basename( $name, '.neon' );

		if ( $only_identifiers && ! in_array( $identifier, $only_identifiers, true ) ) {
			continue;
		}

		$file     = $output_dir . '/' . $name;
		$is_empty = is_empty_baseline( read_file( $file ) );

		if ( null === $is_empty ) {
			fwrite( STDERR, sprintf( "%s: no `ignoreErrors` list found, left untouched.\n", $output_option . '/' . $name ) );
			continue;
		}

		if ( ! $is_empty ) {
			continue;
		}

		if ( ! unlink( $file ) ) {
			fwrite( STDERR, sprintf( "%s: could not be deleted.\n", $output_option . '/' . $name ) );
			exit( 1 );
		}

		printf( "%s: no entries remain, file deleted.\n", $output_option . '/' . $name );
		++$pruned;
	}

	if ( 0 === $pruned ) {
		echo "No empty baselines found.\n";
	}

	exit( 0 );
}

/*
 * Both temporary files sit beside the configuration, and so inside the
 * repository, for two separate reasons.
 *
 * A neon file's `includes` resolve relative to its own directory, so the wrapper
 * including the configuration has to live where the configuration does.
 *
 * PHPStan writes a PHP baseline's paths as __DIR__ followed by a relative chain,
 * which it can only produce when the baseline shares an ancestry with the files
 * it names. Generated somewhere else, the system temporary directory included,
 * it emits `__DIR__ . '//absolute/path'` instead, and every path in it then
 * resolves to somewhere under that directory rather than to the source file.
 */
$temp_prefix   = dirname( $config_path ) . '/.phpstan-baselines-' . getmypid();
$temp_config   = $temp_prefix . '.neon';
$temp_baseline = $temp_prefix . '.php';

register_shutdown_function(
	static function () use ( $temp_config, $temp_baseline ): void {
		foreach ( array( $temp_config, $temp_baseline ) as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
);

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
if ( DEFAULT_CONFIG !== $config_relative ) {
	$analysis_tmp_dir .= '-' . (string) preg_replace( '/[^A-Za-z0-9._]+/', '-', $config_relative );
}

/*
 * `tmpDir` is set in a wrapper including the configuration, so that the
 * configuration itself is left untouched. A file's own parameters win over those
 * of the files it includes, so the wrapper's `tmpDir` is the one that takes
 * effect.
 *
 * The baselines being regenerated need no removing from the configuration:
 * GENERATING_BASELINES_ENV names their directory for the analysis, and the loader
 * there includes nothing while it does.
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
		basename( $config_path ),
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

/*
 * The baselines only take effect through a loader in their own directory. It is
 * written on every run, so that the committed loader of the default directory, and
 * that of any other, stay what build_loader() makes rather than drifting from it
 * unnoticed. A directory given with `--output-dir` that had none is not included
 * by anything yet, so say so.
 *
 * It is written before the analysis rather than after, since a configuration may
 * already include it, and PHPStan refuses to run with an include that is missing.
 * That is how the baselines of a new configuration are first generated: add the
 * loader of its directory to its `includes`, then run this with `--config` and
 * `--output-dir` naming them. With the directory suppressed, the loader includes
 * nothing during the analysis either way.
 *
 * `--combined` writes nothing to disk, so a missing loader stays missing then.
 */
if ( ! $combined ) {
	if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0755, true ) ) {
		fwrite( STDERR, "Could not create $output_option\n" );
		exit( 1 );
	}

	$loader_path     = $output_dir . '/loader.php';
	$loader          = build_loader( $output_dir );
	$loader_existed  = is_file( $loader_path );
	$loader_contents = $loader_existed ? file_get_contents( $loader_path ) : false;

	if ( $loader !== $loader_contents ) {
		if ( false === file_put_contents( $loader_path, $loader ) ) {
			fwrite( STDERR, "Could not write $output_option/loader.php\n" );
			exit( 1 );
		}

		printf(
			$loader_existed ? "%s: rewritten.\n" : "%s: written. The configuration has to include it for these baselines to take effect.\n",
			$output_option . '/loader.php'
		);
	}
}

fwrite( STDERR, "Analyzing with $config_option, baselines in $output_option suppressed...\n" );

/*
 * PHPStan inherits it, as do the worker processes it starts. It is the real path,
 * since that is what the loader's __DIR__ is. An output directory that does not
 * exist, which only `--combined` leaves uncreated, holds no loader to match, so it
 * is not set at all then. It is cleared first either way, so that a value
 * exported in the shell, perhaps naming another directory, cannot switch off
 * baselines this run means to keep.
 */
putenv( GENERATING_BASELINES_ENV );
$resolved_output_dir = realpath( $output_dir );
if ( false !== $resolved_output_dir ) {
	putenv( GENERATING_BASELINES_ENV . '=' . $resolved_output_dir );
}

$is_default_output_dir = DEFAULT_OUTPUT_DIR === trim( normalize_path( $output_option ), '/' );

/*
 * The baselines in any other directory the configuration includes stay in force,
 * so a run expecting every error, rather than those the others leave, would come
 * up short with nothing to say why. The default configuration includes only the
 * default directory's, so that is the one combination with nothing to say. Any
 * other, whether a configuration including the default one alongside a loader of
 * its own, or a directory other than the default, may well leave some in force.
 */
if ( DEFAULT_CONFIG !== $config_relative || ! $is_default_output_dir ) {
	fwrite( STDERR, "Baselines in any other directory the configuration includes stay in force, so only the errors they leave are written.\n" );
}

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

/*
 * The command each header gives for regenerating its baseline has to name the
 * configuration this run used, even the default one, since without a
 * configuration named, every one in DEFAULT_CONFIGS is processed, and the
 * baselines of the same identifier in each of their directories are rewritten.
 * The directory is only named where it is not the configuration's own, since
 * naming the configuration alone writes there.
 */
$regenerate_options = ' --config=' . quote_shell_value( $config_option );
if ( $config_output_dir !== trim( normalize_path( $output_option ), '/' ) ) {
	$regenerate_options .= ' --output-dir=' . quote_shell_value( $output_option );
}

foreach ( $grouped as $identifier => $entries ) {
	$written = file_put_contents(
		$output_dir . '/' . $identifier . '.neon',
		build_baseline( $entries, $output_dir, build_baseline_header( $identifier, $regenerate_options ) )
	);

	if ( false === $written ) {
		fwrite( STDERR, "Could not write $output_option/$identifier.neon\n" );
		exit( 1 );
	}

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
	foreach ( find_baselines( $output_dir ) as $name ) {
		$retired[] = basename( $name, '.neon' );
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

/**
 * Returns the usage message.
 *
 * @return non-falsy-string Usage message.
 */
function get_usage(): string {
	return <<<'TEXT'
		Generates PHPStan baselines split by error identifier.

		Writes one baseline per identifier, and retires any whose identifier no
		longer reports anything. The configuration includes every baseline through
		tests/phpstan/baselines/loader.php, so neither addition nor removal needs an
		edit of it.

		Without --config or --output-dir, this is done for phpstan.neon.dist into
		tests/phpstan/baselines, and then for phpstan-strict.neon.dist into
		tests/phpstan/baselines-strict. The other options apply to both.

		Usage:
		  composer phpstan:baselines [-- <options>]

		Options:
		  --identifier=<id>    Only write the baseline for this identifier. Repeatable,
		                       or comma separated. When an identifier is named and the
		                       analysis reports none of it, its baseline file is deleted
		                       rather than left behind empty.
		                       Default: every identifier reported.
		  --config=<path>      Configuration to analyze with, relative to the repository
		                       root. Naming one processes only that configuration.
		                       Default: both of the above.
		  --output-dir=<path>  Where the per-identifier baselines are written, relative
		                       to the repository root. Paths inside them are written
		                       relative to this directory. A loader is written there
		                       too, which the configuration has to include. Baselines
		                       in other directories stay in force, so only the errors
		                       they leave are written.
		                       Default: the directory of the configuration named
		                       above, or of the one it overrides locally, such as
		                       phpstan-strict.neon, or else tests/phpstan/baselines
		  --combined           Print one combined baseline to stdout instead of writing
		                       per-identifier files. Nothing is written to disk. Only
		                       phpstan.neon.dist is analyzed, unless --config names
		                       another configuration.
		  --prune              Without analyzing anything, delete the baselines that no
		                       longer list any entries, such as one emptied by running
		                       PHPStan with --fix. With --identifier, only those
		                       baselines are considered.
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

		  Remove the entries of fixed errors, and any baseline left empty:
		    composer phpstan:fix

		  Remove only the baselines that list no entries:
		    composer phpstan:baselines -- --prune

		TEXT;
}

/**
 * Reads a file, failing loudly rather than continuing with false.
 *
 * @param non-empty-string $path Absolute path to the file.
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

	/*
	 * PHPStan writes a baseline of no errors as an empty array, without the
	 * `parameters` it otherwise has. That is the usual outcome for a directory
	 * whose errors the baselines elsewhere already cover.
	 */
	if ( array() === $data ) {
		return array();
	}

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
 * Builds the loader for a baselines directory.
 *
 * The loader holds no logic of its own. It requires `baseline-includes.php`, by a
 * path relative to its directory, and returns what get_baseline_includes() finds
 * there, so a fix to that reaches every directory's loader alike.
 * `baselines/loader.php` is the one this builds for the default directory.
 *
 * The relative path is worked out between the real paths, since that is how the
 * loader's __DIR__ is resolved when the path is followed. Worked out from the
 * directory as given, a symbolic link in it would send the loader looking
 * somewhere else.
 *
 * @param non-empty-string $output_dir Absolute path to the baseline directory, which exists.
 * @return non-falsy-string Loader contents.
 */
function build_loader( string $output_dir ): string {
	$from = realpath( $output_dir );
	$to   = realpath( __DIR__ . '/baseline-includes.php' );

	if ( false === $from || false === $to ) {
		fwrite( STDERR, "Could not resolve $output_dir or the shared baseline includes.\n" );
		exit( 1 );
	}

	return sprintf(
		<<<'PHP'
		<?php
		/**
		 * Includes every PHPStan baseline in this directory.
		 *
		 * A configuration includes this file rather than listing the baselines one by
		 * one. `composer phpstan:baselines` writes it on every run, so it is not to be
		 * edited by hand.
		 *
		 * @see \WordPress\PHPStan\get_baseline_includes()
		 *
		 * @package WordPress
		 */

		require_once __DIR__ . %s;

		return \WordPress\PHPStan\get_baseline_includes( __DIR__ );

		PHP,
		var_export( '/' . get_relative_path( $from, $to ), true )
	);
}

/**
 * Determines whether a baseline ignores no errors.
 *
 * That is the case when its `ignoreErrors` is an empty inline list, as PHPStan's
 * `--fix` writes after removing the last entry, or a key with nothing nested under
 * it. Only whole-line comments are skipped, since a message pattern such as
 * `'#^…$#'` contains `#` and so a trailing comment cannot be told apart from it
 * without parsing the NEON.
 *
 * @param string $contents Baseline file contents.
 * @return bool|null Whether the baseline is empty, or null when it has no `ignoreErrors` key.
 */
function is_empty_baseline( string $contents ): ?bool {
	$lines = explode( "\n", $contents );

	foreach ( $lines as $i => $line ) {
		if ( 1 !== preg_match( '/^(\s*)ignoreErrors:\s*(.*)$/', $line, $matches ) ) {
			continue;
		}

		$value = trim( $matches[2] );

		if ( '' !== $value && '#' !== $value[0] ) {
			return 1 === preg_match( '/^\[\s*\](\s*#.*)?$/', $value );
		}

		// Nothing after the key, so its value is whatever is nested beneath it.
		$indent = strlen( $matches[1] );

		foreach ( array_slice( $lines, $i + 1 ) as $next ) {
			$trimmed = trim( $next );

			if ( '' === $trimmed || '#' === $trimmed[0] ) {
				continue;
			}

			$next_indent = strlen( $next ) - strlen( ltrim( $next ) );

			return $next_indent <= $indent;
		}

		return true;
	}

	return null;
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
 * Quotes a value for a command shown to be run in a shell, where it needs it.
 *
 * The command is only written into a baseline's header, to be copied from there,
 * so a value is left as it is when the shell would read it that way, as the paths
 * of the default configurations and their directories are. Any other value, such
 * as a path with a space in it, is quoted, so the copied command still passes it
 * as one argument.
 *
 * @param string $value Value to quote.
 * @return string Value, quoted if the shell would otherwise split or expand it.
 */
function quote_shell_value( string $value ): string {
	return 1 === preg_match( '#^[\w./-]+$#', $value ) ? $value : escapeshellarg( $value );
}

/**
 * Builds the header comment for a per-identifier baseline.
 *
 * @param non-falsy-string $identifier Error identifier, a group followed by a code.
 * @param string           $options    Options other than `--identifier` the
 *                                     regenerate command needs, each preceded
 *                                     by a space. Default empty.
 * @return non-falsy-string Comment block.
 */
function build_baseline_header( string $identifier, string $options = '' ): string {
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
		# The goal is to empty this file and delete it.
		#
		# Generated by `composer phpstan:baselines`. Do not edit by hand. Regenerate with:
		#
		#     composer phpstan:baselines -- --identifier=$identifier$options
		#
		# This reruns the analysis with the baselines in this directory suppressed, so
		# that their errors surface again, and rewrites only this file.

		TEXT;
}
