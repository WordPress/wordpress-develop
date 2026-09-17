<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `__return_true()` function.
 *
 * @since 5.1.0
 *
 * @group functions
 *
 * @covers ::__return_true
 */
class ReturnTrueTest extends WP_UnitTestCase {

	public function test___return_true() {
		$this->assertTrue( __return_true() );
	}
}