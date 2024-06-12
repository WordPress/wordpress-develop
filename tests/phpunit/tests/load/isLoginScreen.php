<?php

/**
 * Tests for is_login_screen().
 *
 * @group load.php
 * @covers ::is_login_screen
 */
class Tests_Load_IsLoginScreen extends WP_UnitTestCase {

	/**
	 * @ticket 19898
	 */
	public function test_is_login_screen() {
		$this->assertFalse( is_login_screen() );

		$_SERVER['SCRIPT_NAME'] = '/wp-login.php';

		$this->assertTrue( is_login_screen() );
	}
}
