<?php
/**
 * Tests for the force_ssl_admin function.
 *
 * @group Functions.php
 *
 * @covers ::force_ssl_admin
 */#
class Tests_Functions_ForceSslAdmin extends WP_UnitTestCase {

	/**
	 * @ticket 57261
	 */
	public function test_force_ssl_admin() {
		$default = force_ssl_admin();
		$this->assertFalse( $default, 'default' );
		$this->assertFalse( force_ssl_admin( true ), 'set true' );
		$this->assertTrue( force_ssl_admin(), 'check is still true' );

		// reset to dafault
		$this->assertTrue( force_ssl_admin( $default ), 'set false and back to default' );
	}

	/**
	 * Check passing string to force_ssl_admin doesn't set it.
	 * Needs #57262 for this test to work.
	 * @ticket 57261
	 *
	 * @expectedDoingItWrong
	 */
	public function test_force_ssl_admin_try_test_string_which_should_fail() {
		$expected = force_ssl_admin();
		$this->assertSame( $expected, force_ssl_admin(), 'default' );

		$this->assertSame( $expected, force_ssl_admin( 'a string' ), 'try to set a string' );
		$this->setExpectedIncorrectUsage( 'force_ssl_admin' );
		$this->assertSame( $expected, force_ssl_admin(), 'check is still as expecting after setting a string' );
	}
}
