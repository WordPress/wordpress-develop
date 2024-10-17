<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * Tests that `WP_Upgrader::__construct()` creates a skin when one is not
 * passed to the constructor.
 *
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::__construct()
 */
class Admin_WpUpgrader_Construct_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * @ticket 54245
	 */
	public function test_constructor_should_create_skin_when_one_is_not_provided() {
		$instance = new WP_Upgrader();

		$this->assertInstanceOf( WP_Upgrader_Skin::class, $instance->skin );
	}
}
