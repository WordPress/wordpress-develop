<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `__return_false()` function.
 *
 * @since 5.1.0
 *
 * @group functions
 *
 * @covers ::__return_false
 */
class ReturnFalseTest extends WP_UnitTestCase {

	public function test___return_false() {
		$this->assertFalse( __return_false() );
	}
}