<?php
/**
 * A class which stands in for WordPress in the self-tests.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * A class with an instance method and a static method.
 */
class WP_Invariants_Fixture {

	/**
	 * Running total.
	 *
	 * @var int
	 */
	public $total = 0;

	/**
	 * Adds to the total.
	 *
	 * @param int $n Number to add.
	 * @return int The new total.
	 */
	public function add( $n ) {
		$this->total += $n;
		return $this->total;
	}

	/**
	 * Doubles a number.
	 *
	 * @param int $n Number.
	 * @return int
	 */
	public static function twice( $n ) {
		return 2 * $n;
	}
}
