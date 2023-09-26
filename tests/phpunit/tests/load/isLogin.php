<?php

/**
 * Tests for is_login().
 *
 * @group load.php
 * @covers ::is_login
 */
class Tests_Load_IsLogin extends WP_UnitTestCase {

	/**
	 * @ticket 19898
	 */
	public function test_is_login() {
		$this->assertFalse( is_login() );

		$_SERVER['SCRIPT_NAME'] = '/wp-login.php';

		$this->assertTrue( is_login() );
	}
}
