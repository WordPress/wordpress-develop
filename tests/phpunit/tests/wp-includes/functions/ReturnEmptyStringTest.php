<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `__return_empty_string()` function.
 *
 * @since 5.1.0
 *
 * @group functions
 *
 * @covers ::__return_empty_string
 */
class ReturnEmptyStringTest extends WP_UnitTestCase {

	public function test___return_empty_string() {
		$this->assertSame( '', __return_empty_string() );
	}
}