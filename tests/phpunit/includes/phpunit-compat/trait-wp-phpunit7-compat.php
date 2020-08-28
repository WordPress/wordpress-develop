<?php
/**
 * Trait that contains any new/deprecated/removed functionality in PHPUnit 7
 */
trait WP_PHPUnit7_Compat {

	// New
	function _assertEqualsWithDelta( $expected, $actual, $delta, $message = '' ) {
		$this->assertEquals( $expected, $actual, $message, $delta );
	}

	// New
	function _assertNotEqualsWithDelta( $expected, $actual, $delta, $message = '' ) {
		$this->assertNotEquals( $expected, $actual, $message, $delta );
	}
}
