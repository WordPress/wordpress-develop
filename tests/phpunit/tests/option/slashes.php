<?php

/**
 * @group option
 * @group slashes
 * @ticket 21767
 */
class Tests_Option_Slashes extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	/**
	 * Tests the model function that expects un-slashed data
	 */
	public function test_add_option() {
		add_option( 'slash_test_1', self::SLASH_1 );
		add_option( 'slash_test_2', self::SLASH_2 );
		add_option( 'slash_test_3', self::SLASH_3 );
		add_option( 'slash_test_4', self::SLASH_4 );

		$this->assertSame( self::SLASH_1, get_option( 'slash_test_1' ) );
		$this->assertSame( self::SLASH_2, get_option( 'slash_test_2' ) );
		$this->assertSame( self::SLASH_3, get_option( 'slash_test_3' ) );
		$this->assertSame( self::SLASH_4, get_option( 'slash_test_4' ) );
	}

	/**
	 * Tests the model function that expects un-slashed data
	 */
	public function test_update_option() {
		add_option( 'slash_test_5', 'foo' );

		update_option( 'slash_test_5', self::SLASH_1 );
		$this->assertSame( self::SLASH_1, get_option( 'slash_test_5' ) );

		update_option( 'slash_test_5', self::SLASH_2 );
		$this->assertSame( self::SLASH_2, get_option( 'slash_test_5' ) );

		update_option( 'slash_test_5', self::SLASH_3 );
		$this->assertSame( self::SLASH_3, get_option( 'slash_test_5' ) );

		update_option( 'slash_test_5', self::SLASH_4 );
		$this->assertSame( self::SLASH_4, get_option( 'slash_test_5' ) );
	}
}
