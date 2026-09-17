<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `__return_null()` function.
 *
 * @since 5.1.0
 *
 * @group functions
 *
 * @covers ::__return_null
 */
class ReturnNullTest extends WP_UnitTestCase {

	public function test___return_null() {
		$this->assertNull( __return_null() );
	}
}