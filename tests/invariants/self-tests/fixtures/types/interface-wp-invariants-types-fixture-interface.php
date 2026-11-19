<?php
/**
 * An interface, whose methods the types report leaves out.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Not a target: its methods have no body.
 */
interface WP_Invariants_Types_Fixture_Interface {

	/**
	 * Declared only.
	 */
	public function declared();
}
