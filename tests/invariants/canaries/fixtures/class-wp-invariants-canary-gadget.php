<?php
/**
 * A class for the canaries to unserialize.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * An object through which unserialize() could run code: it has a destructor.
 */
class WP_Invariants_Canary_Gadget {

	/**
	 * Would run when the object is destroyed.
	 */
	public function __destruct() {}
}
