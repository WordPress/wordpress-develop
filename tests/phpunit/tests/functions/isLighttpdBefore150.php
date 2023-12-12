<?php

/**
 * Tests for the is_lighttpd_before_150 function.
 *
 * @group Functions.php
 *
 * @covers ::is_lighttpd_before_150
 */
class Tests_Functions_isLighttpdBefore150 extends WP_UnitTestCase{

	/**
	 * @ticket 60056
	 */
	public function test_is_lighttpd_before_150() {
		$this->assertFalse(is_lighttpd_before_150());
	}
}
