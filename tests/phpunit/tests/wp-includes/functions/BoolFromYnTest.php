<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * @group functions
 *
 *
 * @covers ::bool_from_yn
 */
class BoolFromYnTest extends WP_UnitTestCase {
	/**
	 * @ticket 35972
	 */
	public function test_bool_from_yn() {
		$this->assertTrue( bool_from_yn( 'Y' ) );
		$this->assertTrue( bool_from_yn( 'y' ) );
		$this->assertFalse( bool_from_yn( 'n' ) );
	}
}
