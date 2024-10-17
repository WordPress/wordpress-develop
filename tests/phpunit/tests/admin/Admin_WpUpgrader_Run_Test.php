<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::run()
 */
class Admin_WpUpgrader_Run_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::run()` returns `false` when
	 * requesting filesystem credentials fails.
	 *
	 * @ticket 54245
	 */
	public function test_run_should_return_false_when_requesting_filesystem_credentials_fails() {
		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'request_filesystem_credentials' )
				->willReturn( false );

		self::$upgrader_skin_mock
				->expects( $this->once() )
				->method( 'footer' );

		$this->assertFalse( self::$instance->run( array() ) );
	}
}
