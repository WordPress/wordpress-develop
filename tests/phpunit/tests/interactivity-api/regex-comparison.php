<?php
/**
 * Regex comparison test for the Interactivity API expression splitting.
 *
 * Compares two regex variations for the `splitStatements` /
 * `split_expression_into_statements` helper:
 *
 *   - PHP version: matches escape sequences (\" , \/ , \' , \` ) as atomic
 *     two-character units. Faster in PCRE, more precise on edge cases.
 *   - JS-equivalent version: matches bare delimiter characters (/ , " , ' , ` ),
 *     relying on greedy backtracking. This matches the upstream Datastar
 *     genRx() regex.
 *
 * Usage: php tests/phpunit/tests/interactivity-api/regex-comparison.php
 *
 * @package WordPress
 * @subpackage Interactivity API
 */

$php_re = '/(\/(?:\\\\\/|[^\/])*\/|"(?:\\\\"|[^"])*"|\'(?:\\\\\'|[^\'])*\'|`(?:\\\\`|[^`])*`|\(\s*((?:function)\s*\(\s*\)|(?:\(\s*\))\s*=>)\s*(?:\{[\s\S]*?\}|[^;){]*)\s*\)\s*\(\s*\)|[^;])+/';

$js_re = '/(\/(?:\/|[^\/])*\/|"(?:\"|[^"])*"|\'(?:\'|[^\'])*\'|`(?:`|[^`])*`|\(\s*((?:function)\s*\(\s*\)|(?:\(\s*\))\s*=>)\s*(?:\{[\s\S]*?\}|[^;){]*)\s*\)\s*\(\s*\)|[^;])+/';

define( 'ITER', 100000 );

function compare( $input ) {
	global $php_re, $js_re;
	preg_match_all( $php_re, $input, $p );
	preg_match_all( $js_re, $input, $j );
	$php = $p[0] ?? array();
	$js  = $j[0] ?? array();
	return array( $php, $js, $php === $js );
}

function run_test( $input, $label ) {
	[$php, $js, $match] = compare( $input );
	echo ( $match ? 'OK' : 'MISMATCH' ) . ' ' . $label . "\n";
	if ( ! $match ) {
		echo '  PHP: ' . json_encode( $php ) . "\n  JS:  " . json_encode( $js ) . "\n";
	}
	return $match;
}

function bench( string $label, string $regex, string $input ): float {
	$t = microtime( true );
	for ( $i = 0; $i < ITER; $i++ ) {
		preg_match_all( $regex, $input, $m );
	}
	$elapsed = ( microtime( true ) - $t ) * 1000;
	printf( "%s: %.1fms\n", $label, $elapsed );
	return $elapsed;
}

$perf_input = "state.count > 0 ? 'yes;no' : 'maybe;not'; /foo\\/bar/g; `hello;world`; (() => { return 1; })(); done";

/* ───────────────────────────────────────────────────────────
 * Test 1: Common directive expressions
 * ─────────────────────────────────────────────────────────── */
$common = array( 'state.count; state.flag', '"hello;world"; foo', "'a;b'; c", '`x;y`; z', '/a;b/; c', 'a/2; b', "state.count === 0 ? 'no' : 'yes'", '(() => { const x = 1; return x; })(); done' );

echo "=== Common ===\n";

foreach ( $common as $e ) {
	run_test( $e, substr( $e, 0, 50 ) );
}

/* ───────────────────────────────────────────────────────────
 * Test 2: Edge cases with escaped delimiters
 * ─────────────────────────────────────────────────────────── */
$edge = array( '/foo\/bar;baz/g', '"hello \"world;foo\""; x', "'it\'s;ok'; y", '`back\`tick;z`; w', '/foo\\\\/bar;baz/', '/a\/b\/c;d/g', '"a\"b;c\"d"; e' );

echo "\n=== Edge ===\n";

foreach ( $edge as $e ) {
	run_test( $e, substr( $e, 0, 60 ) );
}

/* ───────────────────────────────────────────────────────────
 * Test 3: Randomized fuzz (10 000 inputs)
 * ─────────────────────────────────────────────────────────── */
echo "\n=== Fuzz (10000 random expressions) ===\n";

$chars = 'abcdefghijklmnopqrstuvwxyz0123456789;./\\\'"`(){}[]=+-*&|<> ';
$mm    = 0;

for ( $i = 0; $i < 10000; $i++ ) {
	$len = 5 + random_int( 0, 40 );
	$e   = '';
	for ( $j = 0; $j < $len; $j++ ) {
		$e .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}
	[$php, $js, $match] = compare( $e );
	if ( ! $match ) {
		++$mm;
		if ( $mm <= 3 ) {
			echo 'MISMATCH: ' . json_encode( $e ) . "\n";
			echo '  PHP: ' . json_encode( $php ) . "\n  JS:  " . json_encode( $js ) . "\n";
		}
	}
}

echo "Mismatches: $mm / 10000\n";

/* ───────────────────────────────────────────────────────────
 * Test 4: PHP vs JS-equivalent pattern performance
 * ─────────────────────────────────────────────────────────── */
echo "\n=== Performance (" . ITER . " iterations) ===\n";

$tp = bench( 'PHP version', $php_re, $perf_input );
$tj = bench( 'JS version', $js_re, $perf_input );

$d = ( $tj - $tp ) / $tp * 100;
printf( "Delta: %.1f%% (%s)\n", abs( $d ), $d > 0 ? 'PHP faster' : 'JS faster' );

/* ───────────────────────────────────────────────────────────
 * Test 5: Non-capturing (?:...) vs capturing (...) groups
 *
 * The Datastar original uses (...) for inner alternations.
 * Since preg_match_all() ignores capture groups the output is
 * identical, but skipping the capture bookkeeping is
 * measurably faster. ──────────────────────────────────────── */
echo "\n=== Non-capturing vs capturing groups ===\n";

$dstar_original = '/(\/(\\\\\/|[^\/])*\/|"(\\\\"|[^"])*"|\'(\\\\\'|[^\'])*\'|`(\\\\`|[^`])*`|\(\s*((function)\s*\(\s*\)|(\(\s*\))\s*=>)\s*(?:\{[\s\S]*?\}|[^;){]*)\s*\)\s*\(\s*\)|[^;])+/';

$our_optimized = '/(\/(?:\\\\\/|[^\/])*\/|"(?:\\\\"|[^"])*"|\'(?:\\\\\'|[^\'])*\'|`(?:\\\\`|[^`])*`|\(\s*((?:function)\s*\(\s*\)|(?:\(\s*\))\s*=>)\s*(?:\{[\s\S]*?\}|[^;){]*)\s*\)\s*\(\s*\)|[^;])+/';

$all_ok = true;
foreach ( array_merge( $common, $edge ) as $e ) {
	preg_match_all( $dstar_original, $e, $a );
	preg_match_all( $our_optimized, $e, $b );
	if ( ( $a[0] ?? array() ) !== ( $b[0] ?? array() ) ) {
		$all_ok = false;
		echo 'MISMATCH: ' . json_encode( $e ) . "\n";
	}
}
echo 'Same output? ' . ( $all_ok ? 'yes' : 'NO' ) . "\n";

$dstar_time = bench( 'Datastar original (capturing)', $dstar_original, $perf_input );
$our_time   = bench( 'Our version (non-capturing)', $our_optimized, $perf_input );

$dt = ( $our_time - $dstar_time ) / $dstar_time * 100;
printf( "Delta: %.1f%% (%s)\n", abs( $dt ), $our_time < $dstar_time ? '(?:) faster' : '(?:) slower' );
