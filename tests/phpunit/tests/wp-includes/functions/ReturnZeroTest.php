<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `__return_zero()` function.
 *
 * @since 5.1.0
 *
 * @group functions
 *
 * @covers ::__return_zero
 */
class ReturnZeroTest extends WP_UnitTestCase {

	public function test___return_zero() {
		$this->assertSame( 0, __return_zero() );
	}
}