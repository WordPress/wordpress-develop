<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::init()
 */
class Admin_WpUpgrader_Init_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::init()` calls `WP_Upgrader::set_upgrader()`.
	 *
	 * @ticket 54245
	 */
	public function test_init_should_call_set_upgrader() {
		self::$upgrader_skin_mock->expects( $this->once() )->method( 'set_upgrader' )->with( self::$instance );
		self::$instance->init();
	}
}
