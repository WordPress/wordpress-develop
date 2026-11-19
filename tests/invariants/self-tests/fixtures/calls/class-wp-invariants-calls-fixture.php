<?php
/**
 * A class for WP_Invariant::functions_calling() to read, which is never loaded.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Switches in a method.
 */
class WP_Invariants_Calls_Fixture {

	/**
	 * Switches, as a method.
	 */
	public function run() {
		if ( true ) {
			SWITCH_TO_BLOG( 3 );
		}
	}

	/**
	 * Declares a method of the same name, which is not a call.
	 */
	public function switch_to_blog() {}
}
