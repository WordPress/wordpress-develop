<?php
/**
 * Tests for __return_** set of functions
 *
 * @since 5.1.0
 *
 * @group functions.php
 */
class Tests_Functions_UnderscoreReturn extends WP_UnitTestCase {

	public function test__return_true() {
		$this->assertTrue( __return_true() );
	}

	public function test__return_false() {
		$this->assertFalse( __return_false() );
	}

	public function test__return_zero() {
		$this->assertSame( 0, __return_zero() );
	}

	public function test__return_empty_array() {
		$this->assertSame( array(), __return_empty_array() );
	}

	public function test__return_null() {
		$this->assertNull( __return_null() );
	}

	public function test__return_empty_string() {
		$this->assertSame( '', __return_empty_string() );
	}
}
