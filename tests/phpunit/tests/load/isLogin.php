<?php

/**
 * Tests for is_login().
 *
 * @group load
 *
 * @covers ::is_login
 */
class Tests_Load_IsLogin extends WP_UnitTestCase {

	/**
	 * Original $_SERVER['SCRIPT_NAME'].
	 *
	 * @var string|null
	 */
	private $original_script_name;

	public function set_up() {
		parent::set_up();

		$this->original_script_name = $_SERVER['SCRIPT_NAME'] ?? null;
	}

	public function tear_down() {
		if ( null !== $this->original_script_name ) {
			$_SERVER['SCRIPT_NAME'] = $this->original_script_name;
		} else {
			unset( $_SERVER['SCRIPT_NAME'] );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 19898
	 */
	public function test_is_login() {
		$this->assertFalse( is_login() );

		$_SERVER['SCRIPT_NAME'] = '/wp-login.php';

		$this->assertTrue( is_login() );
	}
}
