<?php
/**
 * Benchmark and output comparison for wptexturize() implementations.
 *
 * NOT FOR COMMIT. A harness for Trac #18549 and the HTML API rewrite of
 * wptexturize(): it times two implementations on the same inputs in one process
 * and reports where their output differs.
 *
 * Two modes:
 *
 * - Candidate mode, --candidate=<function>: compares the native wptexturize()
 *   with another function that has the same interface, such as a private HTML API
 *   implementation defined alongside it. Both are native, so no copying is needed.
 *
 * - Ref mode (default): compares wptexturize() from formatting.php at a git ref
 *   (default: the merge base with upstream/trunk) with the working tree. Each
 *   version's whole formatting.php is copied with its functions renamed, so both
 *   take the same OPcache path; an A/A control checks that.
 *
 * Loads WordPress fully (SHORTINIT off) from the PHPUnit test config, so
 * get_locale(), translations, and the texturize filters behave as on a site.
 *
 * Usage (from the wordpress-develop root, after the PHPUnit suite has installed
 * the test database at least once):
 *
 *     php -d opcache.enable_cli=1 tools/benchmarks/wptexturize/benchmark.php \
 *         [--candidate=<function> | --base-ref=<git ref>] [--seed=<path>] \
 *         [--runs=200] [--warmup=20] [--json=<output path>]
 *
 * Input sets:
 * - whole-posts: tests/phpunit/data/formatting/whole-posts.php.
 * - seed-posts, seed-titles: dknauss/Dirtbag playground/seed-content.json at a
 *   pinned commit (raw authored content, not yet texturized). Measures cost on
 *   real content; it rarely crosses an inline tag with a straight quote.
 * - boundaries: short inputs where straight quotes meet inline tags, block tags,
 *   comments, and opaque elements, plus one dense paragraph built from them.
 *
 * Metrics per input and implementation: median and 95th-percentile time per call
 * (hrtime), and peak memory above baseline on PHP 8.2+ (memory_reset_peak_usage()).
 *
 * @package WordPress
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

const WPTEXTURIZE_BENCH_SEED_COMMIT = 'b68fb344d23732296b8c2731a4c95ed10621a73f';
const WPTEXTURIZE_BENCH_SEED_SHA256 = '89198b5e34facde32bfcbf54c38eb5a33b4221608272640c6d724d7453ac3ea1';
const WPTEXTURIZE_BENCH_SEED_URL    = 'https://raw.githubusercontent.com/dknauss/Dirtbag/' . WPTEXTURIZE_BENCH_SEED_COMMIT . '/playground/seed-content.json';

$root      = dirname( __DIR__, 3 );
$opts      = getopt( '', array( 'candidate:', 'base-ref:', 'seed:', 'runs:', 'warmup:', 'json:' ) );
$runs      = max( 1, (int) ( $opts['runs'] ?? 200 ) );
$warmup    = max( 0, (int) ( $opts['warmup'] ?? 20 ) );
$candidate = $opts['candidate'] ?? null;
$has_peak  = function_exists( 'memory_reset_peak_usage' );

/*
 * Load WordPress with SHORTINIT off, using the PHPUnit config for the database.
 */
$config = $root . '/wp-tests-config.php';
if ( ! file_exists( $config ) ) {
	fwrite( STDERR, "Missing wp-tests-config.php in $root.\n" );
	exit( 1 );
}
define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']   = 'example.org';
$_SERVER['SERVER_NAME'] = 'example.org';
$_SERVER['REQUEST_URI'] = '/';
require $config;
require ABSPATH . 'wp-settings.php';

if ( ! is_blog_installed() ) {
	fwrite( STDERR, "The test database is not installed. Run the PHPUnit suite once first.\n" );
	exit( 1 );
}

$head = trim( (string) shell_exec( 'git -C ' . escapeshellarg( $root ) . ' rev-parse --short=10 HEAD' ) );
if ( '' !== trim( (string) shell_exec( 'git -C ' . escapeshellarg( $root ) . ' status --porcelain -- src' ) ) ) {
	$head .= '+uncommitted';
}

/*
 * Choose the two implementations. 'base' is the reference, 'test' the one measured
 * against it; ratios are test / base.
 */
if ( null !== $candidate ) {
	if ( ! function_exists( $candidate ) ) {
		fwrite( STDERR, "Candidate function $candidate() is not defined.\n" );
		exit( 1 );
	}
	$impls   = array(
		'base' => 'wptexturize',
		'test' => $candidate,
	);
	$labels  = array(
		'base' => 'wptexturize()',
		'test' => $candidate . '()',
	);
	$title   = sprintf( 'wptexturize() vs %s() at %s', $candidate, $head );
	$control = array( 'wptexturize', 'wptexturize' );
} else {
	$base_ref = $opts['base-ref'] ?? trim( (string) shell_exec( 'git -C ' . escapeshellarg( $root ) . ' merge-base HEAD upstream/trunk 2>/dev/null' ) );
	if ( '' === $base_ref ) {
		fwrite( STDERR, "Could not resolve a base ref. Pass --base-ref=<sha> or --candidate=<function>.\n" );
		exit( 1 );
	}
	$base_source = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' show ' . escapeshellarg( $base_ref . ':src/wp-includes/formatting.php' ) );
	if ( ! is_string( $base_source ) || '' === $base_source ) {
		fwrite( STDERR, "Could not read formatting.php at $base_ref.\n" );
		exit( 1 );
	}
	$head_source = (string) file_get_contents( ABSPATH . 'wp-includes/formatting.php' );
	wptexturize_bench_require_copy( $base_source, '__bench_base', $base_ref );
	wptexturize_bench_require_copy( $head_source, '__bench_head', 'working-tree-' . md5( $head_source ) );

	$base_short = substr( trim( (string) shell_exec( 'git -C ' . escapeshellarg( $root ) . ' rev-parse ' . escapeshellarg( $base_ref ) ) ), 0, 10 );
	$impls      = array(
		'base' => 'wptexturize__bench_base',
		'test' => 'wptexturize__bench_head',
	);
	$labels     = array(
		'base' => $base_short,
		'test' => $head,
	);
	$title      = sprintf( 'wptexturize() at %s vs working tree %s', $base_short, $head );
	$control    = array( 'wptexturize__bench_head', 'wptexturize' );
}

/*
 * Build the input sets.
 */
require $root . '/tests/phpunit/data/formatting/whole-posts.php';
$sets = array(
	'whole-posts' => array(),
	'seed-posts'  => array(),
	'seed-titles' => array(),
	'boundaries'  => array(),
);
foreach ( data_whole_posts() as $i => $row ) {
	$sets['whole-posts'][ "whole-post-$i" ] = $row[0];
}
$seed = wptexturize_bench_load_seed( $opts['seed'] ?? null );
foreach ( $seed['posts'] as $post ) {
	if ( '' !== (string) $post['post_content'] ) {
		$sets['seed-posts'][ $post['post_type'] . ':' . $post['post_name'] ] = (string) $post['post_content'];
	}
	if ( '' !== (string) $post['post_title'] ) {
		$sets['seed-titles'][ 'title:' . $post['post_name'] ] = (string) $post['post_title'];
	}
}
$boundaries = array(
	'closing tag'      => "<strong>He</strong>'s here.",
	'opening tag'      => "I<strong>'ve been</strong> there.",
	'closing em'       => "That<em>'s</em> right.",
	'unicode word'     => "café<em>'s</em> menu",
	'nested'           => "<strong><em>He</em></strong>'s back.",
	'quoted run'       => "She said <em>'Hello'</em> and left.",
	'double quoted'    => 'He said <em>"Hello"</em> twice.',
	'link'             => "<a href=\"#\">Dan</a>'s post",
	'space before tag' => "word <strong>'ve been</strong>",
	'block boundary'   => "word<div>'ve been</div>",
	'comment boundary' => "word<!-- gap --><em>'Hello'</em>",
	'opaque code'      => "word<code><em>'Hello'</em></code>",
);
foreach ( $boundaries as $name => $text ) {
	$sets['boundaries'][ $name ] = $text;
}
$sets['boundaries']['dense paragraph'] = '<p>' . implode( ' ', array_merge( ...array_fill( 0, 20, array_values( $boundaries ) ) ) ) . '</p>';

// Build quote tables for the current locale before anything is timed. An empty
// string returns before the tables are built, so this must be non-empty.
foreach ( array_unique( array_merge( array_values( $impls ), $control ) ) as $fn ) {
	$fn( "It's", true );
}

/*
 * A/A control on the whole-posts set: in ref mode, the copied working-tree function
 * against the native one; in candidate mode, the native function against itself.
 * A ratio far from 1.0 means the method itself skews timings.
 */
$control_ratios = array();
foreach ( $sets['whole-posts'] as $text ) {
	$pair = array();
	foreach ( $control as $fn ) {
		$pair[] = wptexturize_bench_time( $fn, $text, $warmup, $runs )[0];
	}
	$control_ratios[] = $pair[0] / max( 1, $pair[1] );
}
sort( $control_ratios );

/*
 * Measure. The two implementations alternate order between inputs.
 */
$results = array();
$flip    = false;
foreach ( $sets as $set => $inputs ) {
	foreach ( $inputs as $name => $text ) {
		$order   = $flip ? array_reverse( $impls, true ) : $impls;
		$flip    = ! $flip;
		$row     = array(
			'set'   => $set,
			'name'  => $name,
			'bytes' => strlen( $text ),
		);
		$outputs = array();
		foreach ( $order as $key => $fn ) {
			list( $median, $p95 ) = wptexturize_bench_time( $fn, $text, $warmup, $runs );

			$peak = null;
			if ( $has_peak ) {
				gc_collect_cycles();
				$baseline = memory_get_usage();
				memory_reset_peak_usage();
				$fn( $text );
				$peak = memory_get_peak_usage() - $baseline;
			}

			$row[ $key ]     = array(
				'median_ns' => $median,
				'p95_ns'    => $p95,
				'peak_b'    => $peak,
			);
			$outputs[ $key ] = $fn( $text );
		}
		$row['ratio']    = $row['test']['median_ns'] / max( 1, $row['base']['median_ns'] );
		$row['same_out'] = $outputs['base'] === $outputs['test'];
		if ( ! $row['same_out'] ) {
			$row['output'] = $outputs;
		}
		$results[] = $row;
	}
}

/*
 * Report.
 */
$opcache = function_exists( 'opcache_get_status' ) && is_array( @opcache_get_status( false ) ) ? 'on' : 'off';
printf( "%s\n", $title );
printf( "PHP %s, OPcache %s, Xdebug %s, locale %s, %d timed runs after %d warm-up per input\n", PHP_VERSION, $opcache, extension_loaded( 'xdebug' ) ? 'LOADED' : 'off', get_locale(), $runs, $warmup );
printf( "Seed: dknauss/Dirtbag@%s playground/seed-content.json\n", substr( WPTEXTURIZE_BENCH_SEED_COMMIT, 0, 10 ) );
printf( "A/A control (%s vs %s, whole-posts): median ratio %.3f\n", $control[0], $control[1], wptexturize_bench_percentile( $control_ratios, 50 ) );
printf( "base = %s, test = %s\n\n", $labels['base'], $labels['test'] );

printf( "%-12s %6s %10s | %10s %10s %8s | %10s %10s %8s | %7s %7s %5s\n", 'set', 'inputs', 'median B', 'base med', 'base p95', 'base mem', 'test med', 'test p95', 'test mem', 'ratio~', 'worst', 'diff' );
foreach ( array_keys( $sets ) as $set ) {
	$rows = array_values( array_filter( $results, static fn( $r ) => $r['set'] === $set ) );
	if ( ! $rows ) {
		continue;
	}
	$col    = static function ( $key, $metric ) use ( $rows ) {
		$v = array_map( static fn( $r ) => $r[ $key ][ $metric ], $rows );
		sort( $v );
		return wptexturize_bench_percentile( $v, 50 );
	};
	$mem    = static fn( $key ) => null === $rows[0][ $key ]['peak_b'] ? '     n/a' : sprintf( '%7.1fK', $col( $key, 'peak_b' ) / 1024 );
	$ratios = array_map( static fn( $r ) => $r['ratio'], $rows );
	sort( $ratios );
	$bytes = array_map( static fn( $r ) => $r['bytes'], $rows );
	sort( $bytes );
	printf(
		"%-12s %6d %10d | %8.1fµs %8.1fµs %s | %8.1fµs %8.1fµs %s | %7.3f %7.3f %5d\n",
		$set,
		count( $rows ),
		wptexturize_bench_percentile( $bytes, 50 ),
		$col( 'base', 'median_ns' ) / 1000,
		$col( 'base', 'p95_ns' ) / 1000,
		$mem( 'base' ),
		$col( 'test', 'median_ns' ) / 1000,
		$col( 'test', 'p95_ns' ) / 1000,
		$mem( 'test' ),
		wptexturize_bench_percentile( $ratios, 50 ),
		end( $ratios ),
		count( array_filter( $rows, static fn( $r ) => ! $r['same_out'] ) )
	);
}
echo "\nTimes and memory are the median across inputs of each input's median / p95 / peak.\n";
echo "ratio~ = median of per-input (test median / base median); worst = the largest per-input ratio.\n";
echo "diff = inputs where the two outputs differ; --json lists both outputs for each.\n";

$slowest = $results;
usort( $slowest, static fn( $a, $b ) => $b['ratio'] <=> $a['ratio'] );
echo "\nHighest per-input ratios:\n";
foreach ( array_slice( $slowest, 0, 5 ) as $r ) {
	printf( "  %.3f  %-12s %-32s %7d B  base %.1fµs  test %.1fµs\n", $r['ratio'], $r['set'], substr( $r['name'], 0, 32 ), $r['bytes'], $r['base']['median_ns'] / 1000, $r['test']['median_ns'] / 1000 );
}

if ( isset( $opts['json'] ) ) {
	file_put_contents( $opts['json'], json_encode( $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	echo "\nPer-input results written to {$opts['json']}\n";
}

/**
 * Times a function on one input.
 *
 * @param callable $fn     Function to time.
 * @param string   $text   Input.
 * @param int      $warmup Untimed calls first.
 * @param int      $runs   Timed calls.
 * @return array Median and 95th-percentile nanoseconds.
 */
function wptexturize_bench_time( $fn, $text, $warmup, $runs ) {
	for ( $i = 0; $i < $warmup; $i++ ) {
		$fn( $text );
	}
	$times = array();
	for ( $i = 0; $i < $runs; $i++ ) {
		$start   = hrtime( true );
		$fn( $text );
		$times[] = hrtime( true ) - $start;
	}
	sort( $times );
	return array( wptexturize_bench_percentile( $times, 50 ), wptexturize_bench_percentile( $times, 95 ) );
}

/**
 * Returns the nearest-rank percentile of a sorted list.
 *
 * @param array $sorted Sorted numbers.
 * @param int   $pct    Percentile, 1 to 100.
 * @return float|int
 */
function wptexturize_bench_percentile( array $sorted, $pct ) {
	$n = count( $sorted );
	return $sorted[ max( 0, (int) ceil( $pct / 100 * $n ) - 1 ) ];
}

/**
 * Copies a formatting.php source to a temporary file with every function declared
 * in it renamed by a suffix, and requires that file.
 *
 * Copying only wptexturize() is not enough: OPcache binds calls to functions declared
 * in the same file at compile time, so a lone copy would call its helpers by name.
 *
 * @param string $source PHP source of formatting.php (function declarations only).
 * @param string $suffix Suffix for every declared function name.
 * @param string $tag    Cache tag for the file name.
 */
function wptexturize_bench_require_copy( $source, $suffix, $tag ) {
	$ampersand = defined( 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' ) ? T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG : -1;
	$skip      = array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_CONST );
	if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
		$skip[] = T_NULLSAFE_OBJECT_OPERATOR;
	}

	$tokens   = token_get_all( $source );
	$declared = array();
	foreach ( $tokens as $i => $t ) {
		if ( is_array( $t ) && T_FUNCTION === $t[0] ) {
			$j = $i + 1;
			while ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				++$j;
			}
			if ( '&' === $tokens[ $j ] || ( is_array( $tokens[ $j ] ) && $ampersand === $tokens[ $j ][0] ) ) {
				++$j;
				while ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
					++$j;
				}
			}
			if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$declared[ strtolower( $tokens[ $j ][1] ) ] = true;
			}
		}
	}

	$out  = '';
	$prev = null;
	foreach ( $tokens as $t ) {
		$text = is_array( $t ) ? $t[1] : $t;
		if ( is_array( $t ) && T_STRING === $t[0] && isset( $declared[ strtolower( $text ) ] )
			&& ! ( is_array( $prev ) && in_array( $prev[0], $skip, true ) ) ) {
			$text .= $suffix;
		}
		$out .= $text;
		if ( ! is_array( $t ) || ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$prev = $t;
		}
	}

	$file = sys_get_temp_dir() . '/wptexturize-bench' . $suffix . '-' . preg_replace( '/[^A-Za-z0-9._-]/', '', $tag ) . '.php';
	file_put_contents( $file, $out );
	// OPcache will not cache a file modified within opcache.file_update_protection
	// seconds (default 2), so a just-written copy would run unoptimized. Backdate it.
	touch( $file, time() - 60 );
	clearstatcache( true, $file );
	require $file;
}

/**
 * Loads the Dirtbag seed content, from a path or the pinned commit, and checks its hash.
 *
 * @param string|null $path Local path, or null to download.
 * @return array Decoded seed content.
 */
function wptexturize_bench_load_seed( $path ) {
	if ( null === $path ) {
		$path = sys_get_temp_dir() . '/wptexturize-bench-seed-' . substr( WPTEXTURIZE_BENCH_SEED_COMMIT, 0, 10 ) . '.json';
		if ( ! file_exists( $path ) ) {
			$body = file_get_contents( WPTEXTURIZE_BENCH_SEED_URL );
			if ( false === $body ) {
				fwrite( STDERR, 'Could not download ' . WPTEXTURIZE_BENCH_SEED_URL . "\n" );
				exit( 1 );
			}
			file_put_contents( $path, $body );
		}
	}
	$body = (string) file_get_contents( $path );
	if ( WPTEXTURIZE_BENCH_SEED_SHA256 !== hash( 'sha256', $body ) ) {
		fwrite( STDERR, "Seed content hash mismatch for $path; expected the file at Dirtbag@" . WPTEXTURIZE_BENCH_SEED_COMMIT . ".\n" );
		exit( 1 );
	}
	return json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
}
