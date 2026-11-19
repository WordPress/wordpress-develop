<?php
/**
 * A class for the types report to count, with methods of each kind.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Methods of each kind.
 */
class WP_Invariants_Types_Fixture {

	/**
	 * Returns its argument.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function instance( $value ) {
		return $value;
	}

	/**
	 * Makes an instance.
	 *
	 * @param string $name Name.
	 * @return WP_Invariants_Types_Fixture
	 */
	public static function make( $name ) {
		return new self();
	}

	/**
	 * Protected.
	 */
	protected function hidden() {}

	/**
	 * Private.
	 */
	private function secret() {}

	/**
	 * Magic.
	 *
	 * @param string $name Property.
	 */
	public function __get( $name ) {
		return null;
	}
}
