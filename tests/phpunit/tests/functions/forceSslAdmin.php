<?php
/**
 * Tests for the force_ssl_admin function.
 *
 * @group functions.php
 *
 * @covers ::force_ssl_admin
 */
class Tests_Functions_ForceSslAdmin extends WP_UnitTestCase {

	/**
	 * @ticket 57261
	 */
	public function test_force_ssl_admin() {
		$default = force_ssl_admin();
		$this->assertFalse( $default, 'default' );
		$this->assertFalse( force_ssl_admin( true ), 'set true' );
		$this->assertTrue( force_ssl_admin(), 'check is still true' );

		// Reset to default.
		$this->assertTrue( force_ssl_admin( $default ), 'set false and back to default' );
	}
}
