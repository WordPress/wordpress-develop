<?php

require_once __DIR__ . '/trait-wp-phpunit-compat-methods-caller.php';

trait WP_PHPUnit7_Compat {
	use WP_PHPUnit_Compat_Methods_Caller;

	// New
	function _assertEqualsWithDelta($expected, $actual, $delta, $message = '') {
		$this->assertEquals( $expected, $actual, $message, $delta );
	}

	// New
	function _assertNotEqualsWithDelta($expected, $actual, $delta, $message = '') {
		$this->assertNotEquals( $expected, $actual, $message, $delta );
	}
}