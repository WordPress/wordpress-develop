<?php
/**
 * Tests for the force_ssl_admin function.
 *
 * @group functions.php
 *
 * @covers ::force_ssl_admin
 */#
class Tests_Functions_ForceSslAdmin extends WP_UnitTestCase {

	/**
	 * @ticket 57261
	 */
	public function test_force_ssl_admin() {
		$default = force_ssl_admin();
		$before_setting_to_true = force_ssl_admin( true );
		$after_setting_to_true = force_ssl_admin();
		// Reset to default.
		$before_reset = force_ssl_admin( $default );

		$this->assertFalse( $default, 'is was set to false as the default value' );
		$this->assertFalse( $before_setting_to_true, 'when setting to true the previous call to the function changed the value from false' );
		$this->assertTrue( $after_setting_to_true, 'check the new value is true' );
		$this->assertTrue( $before_reset, 'set false and back to default' );
	}

	/**
	 * Check passing string to force_ssl_admin doesn't set it.
	 *
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
