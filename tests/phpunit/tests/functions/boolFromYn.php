<?php

/**
 * @group functions
 *
 * @covers ::bool_from_yn
 */
class Tests_Functions_BoolFromYn extends WP_UnitTestCase {
	/**
	 * @ticket 35972
	 */
	public function test_bool_from_yn() {
		$this->assertTrue( bool_from_yn( 'Y' ) );
		$this->assertTrue( bool_from_yn( 'y' ) );
		$this->assertFalse( bool_from_yn( 'n' ) );
	}
}
