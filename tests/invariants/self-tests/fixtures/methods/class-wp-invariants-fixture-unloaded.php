<?php
/**
 * A class which the self-tests read as tokens, and never load.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Has methods with and without the pattern the self-tests look for.
 */
class WP_Invariants_Fixture_Unloaded {

	/**
	 * Matches.
	 */
	public function get_items_permissions_check() {
		$callback = function () {
			return WP_Invariants_Fixture_Unloaded::class;
		};
		return "{$callback()}";
	}

	/**
	 * Does not match.
	 */
	public function get_items() {
		return new class() {
			/**
			 * In an anonymous class, which is not listed.
			 */
			public function nested_permissions_check() {}
		};
	}

	/**
	 * Matches.
	 */
	protected function get_item_permissions_check() {}
}

/**
 * Not in a class, so not listed.
 */
function wp_invariants_fixture_unloaded_permissions_check() {}
