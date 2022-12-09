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
		$default                = force_ssl_admin();
		$before_setting_to_true = force_ssl_admin( true );
		$after_setting_to_true  = force_ssl_admin();
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
	public function test_force_ssl_admin_try_test_string_which_should_set_true() {
		$expected = force_ssl_admin();

		$value = force_ssl_admin();

		$value1 = force_ssl_admin( 'a string' );
		$this->setExpectedIncorrectUsage( 'force_ssl_admin' );
		$value2 = force_ssl_admin();

		$value3 = force_ssl_admin( $expected );
		$value4 = force_ssl_admin();

		$this->assertSame( $expected, $value, 'default' );

		$this->assertSame( $expected, $value1, 'try to set a string' );
		$this->assertTrue( $value2, 'check value is still as expecting after setting a string' );

		$this->assertTrue( $value3, 'set back to default but old valuer is still true' );
		$this->assertSame( $expected, $value4, 'set back to default' );
	}

	/**
	 * Check passing string to force_ssl_admin doesn't set it.
	 *
	 * @ticket 57261
	 *
	 * @expectedDoingItWrong
	 */
	public function test_force_ssl_admin_try_test_true_and_false_strings() {
		$expected = force_ssl_admin();

		$value  = force_ssl_admin();
		$value1 = force_ssl_admin( 'true' );
		$this->setExpectedIncorrectUsage( 'force_ssl_admin' );
		$value2 = force_ssl_admin();

		$value3 = force_ssl_admin( 'false' );
		$this->setExpectedIncorrectUsage( 'force_ssl_admin' );
		$value4 = force_ssl_admin();

		$value5 = force_ssl_admin( $expected );
		$value6 = force_ssl_admin();

		$this->assertSame( $expected, $value, 'default' );
		$this->assertFalse( $value1, 'try to set a string "true"' );
		$this->assertTrue( $value2, 'check value is still as expecting after setting a string "true"' );

		$this->assertTrue( $value3, 'check value is still false' );
		$this->assertFalse( $value4, 'check value is still true veven if "false" was passed in' );

		$this->assertFalse( $value5, 'set back to default' );
		$this->assertSame( $expected, $value6, 'Check it set to set back to default' );
	}
}
