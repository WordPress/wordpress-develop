<?php
/**
 * A class with hidden properties, for the self-tests of WP_Invariant::property().
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Has a private and a protected property, and counts reads through __get().
 */
class WP_Invariants_Fixture_Properties {

	/**
	 * How many times __get() ran.
	 *
	 * @var int
	 */
	public $magic_reads = 0;

	/**
	 * A private property.
	 *
	 * @var string
	 */
	private $hidden = 'private';

	/**
	 * A protected property.
	 *
	 * @var string
	 */
	protected $kept = 'protected';

	/**
	 * Counts reads of properties which cannot be read from outside.
	 *
	 * @param string $name Property.
	 * @return null
	 */
	public function __get( $name ) {
		++$this->magic_reads;
		return null;
	}
}
