<?php

/**
 * Tests for the force_ssl_admin function.
 *
 * @group Functions
 *
 * @covers ::force_ssl_admin
 */
class Tests_Functions_force_ssl_admin extends WP_UnitTestCase {

	/**
	 * @ticket 60018
	 */
	public function test_force_ssl_admin() {

		$this->assertFalse( force_ssl_admin() );

		$this->assertFalse( force_ssl_admin( true ) );
		$this->assertTrue( force_ssl_admin() );
		$this->assertTrue( force_ssl_admin() );

		$this->assertTrue( force_ssl_admin( true ) );
		$this->assertTrue( force_ssl_admin() );

		$this->assertTrue( force_ssl_admin( false ) );
		$this->assertFalse( force_ssl_admin() );

		$this->assertFalse( force_ssl_admin() );

		$this->assertFalse( force_ssl_admin( 'not_bool' ) );

		$this->assertSame( 'not_bool', force_ssl_admin() );
	}
}
