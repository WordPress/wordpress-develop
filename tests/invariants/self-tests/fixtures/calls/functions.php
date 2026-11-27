<?php
/**
 * Code for WP_Invariant::functions_calling() to read, which is never loaded.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Switches, as a function.
 */
function wp_invariants_calls_switches() {
	switch_to_blog( 2 );
	restore_current_blog();
}

/**
 * Switches only inside a closure, which is not this function's own code.
 */
function wp_invariants_calls_in_closure() {
	return static function () {
		switch_to_blog( 2 );
	};
}

/**
 * Calls a method of the same name, which is not the function.
 *
 * @param object $site An object.
 */
function wp_invariants_calls_method( $site ) {
	$site->switch_to_blog( 2 );
}
