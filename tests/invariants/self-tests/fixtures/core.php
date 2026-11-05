<?php
/**
 * Functions and a class which stand in for WordPress in the self-tests.
 *
 * Calls made from this file count as calls from the code under test.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Calls a function from the code under test, as WordPress would.
 *
 * @param callable $callback  Function to call.
 * @param mixed    ...$args   Arguments.
 * @return mixed What it returns.
 */
function wp_invariants_fixture_call( $callback, ...$args ) {
	return $callback( ...$args );
}

/**
 * Returns the number it was given, after changing its own parameter.
 *
 * @param int    $n     A number.
 * @param string $label A label, which has a default.
 * @return int The number as it was given.
 */
function wp_invariants_fixture_positive( $n, $label = 'default' ) {
	$given = $n;
	$n     = -999;
	return $given;
}

/**
 * Calls itself down to 0, then returns the number it was given.
 *
 * @param int $n Depth.
 * @return int
 */
function wp_invariants_fixture_recursive( $n ) {
	if ( $n > 0 ) {
		wp_invariants_fixture_recursive( $n - 1 );
	}
	return $n;
}

/**
 * Calls itself down to 0, where it throws; each level above catches it and returns.
 *
 * @param int $n Depth.
 * @return int
 */
function wp_invariants_fixture_throwing( $n ) {
	if ( 0 === $n ) {
		throw new RuntimeException( 'The innermost call throws.' );
	}

	try {
		wp_invariants_fixture_throwing( $n - 1 );
	} catch ( RuntimeException $e ) {
		unset( $e );
	}
	return $n;
}

/**
 * Does nothing; the invariants count its calls.
 */
function wp_invariants_fixture_counter() {
}

/**
 * Does nothing; an invariant's check on it throws.
 *
 * @param mixed $value Anything.
 * @return mixed The value.
 */
function wp_invariants_fixture_error( $value ) {
	return $value;
}

/**
 * Calls wp_invariants_fixture_inner(), one call deeper.
 *
 * @return int
 */
function wp_invariants_fixture_outer() {
	return wp_invariants_fixture_inner();
}

/**
 * Does nothing; it is called by wp_invariants_fixture_outer().
 *
 * @return int
 */
function wp_invariants_fixture_inner() {
	return 1;
}
