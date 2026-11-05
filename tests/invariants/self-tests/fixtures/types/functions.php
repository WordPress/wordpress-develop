<?php
/**
 * Functions for the types report to count, which stand in for WordPress functions.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Returns its first argument as a string.
 *
 * @param string|int $value   The value.
 * @param array      $options {
 *     Optional. Options.
 *
 *     @type int $x An option.
 * }
 * @param mixed      ...$rest More values.
 * @return string The value.
 *
 * @phpstan-param non-empty-string|int $value
 * @phpstan-param array{
 *     x?: int,
 * } $options
 * @phpstan-return non-empty-string
 */
function wp_invariants_types_fixture( $value, $options = array(), ...$rest ) {
	return (string) $value;
}

/**
 * Calls wp_invariants_types_fixture(), as WordPress code would.
 *
 * @param mixed ...$args Arguments.
 * @return string
 */
function wp_invariants_types_fixture_caller( ...$args ) {
	return wp_invariants_types_fixture( ...$args );
}

/**
 * Takes two arguments, but is called with more.
 *
 * @param int $a A number.
 */
function wp_invariants_types_fixture_extra( $a ) {
	return func_get_args();
}

/**
 * Throws.
 *
 * @param string $reason Why.
 */
function wp_invariants_types_fixture_throws( $reason ) {
	throw new RuntimeException( $reason );
}

/**
 * Private by its name.
 */
function _wp_invariants_types_fixture_private() {}

/**
 * Private by its documentation.
 *
 * @access private
 * @deprecated 1.0.0
 */
function wp_invariants_types_fixture_documented_private() {}

if ( ! function_exists( 'wp_invariants_types_fixture_fallback' ) ) {
	/**
	 * Declared only when it is not already.
	 */
	function wp_invariants_types_fixture_fallback() {}
}

/**
 * Returns by reference.
 *
 * @return array
 */
function &wp_invariants_types_fixture_reference() {
	static $value = array();
	return $value;
}
