<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::release_lock()
 */
class Admin_WpUpgrader_ReleaseLock_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::release_lock()` removes the 'lock' option.
	 *
	 * @ticket 54245
	 */
	public function test_release_lock_should_remove_lock_option() {
		global $wpdb;

		$this->assertSame(
			1,
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => 'lock.lock',
					'option_value' => 'content',
				),
				'%s'
			),
			'The initial lock was not created.'
		);

		WP_Upgrader::release_lock( 'lock' );

		$this->assertNotSame( 'content', get_option( 'lock.lock' ) );
	}
}
