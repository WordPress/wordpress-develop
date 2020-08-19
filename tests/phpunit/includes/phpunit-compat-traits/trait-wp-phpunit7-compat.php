<?php

trait WPPHPUnit7Compat {

	// New
	function _assertEqualsWithDelta($expected, $actual, $delta, $message = '') {
		$this->assertEquals( $expected, $actual, $message, $delta );
	}

	// New
	function _assertNotEqualsWithDelta($expected, $actual, $delta, $message = '') {
		$this->assertNotEquals( $expected, $actual, $message, $delta );
	}
}