<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * Test class for Plugin_Upgrader::upgrade().
 *
 * @group  upgrader
 * @group  plugin_upgrader
 *
 * @covers Plugin_Upgrader::upgrade
 */
class Tests_Admin_Includes_PluginUpgrader_Upgrade extends WP_Upgrader_TestCase {
	/**
	 * Tests that Plugin_Upgrader::upgrade() does not send error data.
	 *
	 * @ticket 51928
	 *
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @param array $plugin         Array of plugin information.
	 * @param array $update_plugins Value for the "update_plugins" transient.
	 * @param array $expected       Array of expected results and/or messages.
	 */
	public function test_should_not_send_error_data( $plugin, $update_plugins, $expected ) {
		$this->plugin = $plugin;

		$plugin_upgrader = $this
			->getMockBuilder( Plugin_Upgrader::class )
			->setConstructorArgs( array( $this->mock_skin_feedback() ) )
			->setMethods( array( 'send_error_data' ) )
			->getMock();

		$plugin_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		$this->install_older_version( $plugin_upgrader, $plugin['install'], $update_plugins );

		// Do the upgrade.
		ob_start();
		$result         = $plugin_upgrader->upgrade( $plugin['plugin'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade happened.
		$this->assertTrue( $result, 'The upgrade did not succeed.' );
		$this->assertContainsAdminMessages(
			$expected['messages'],
			$actual_message,
			'The actual messages did not match the expected messages.'
		);

		// Validate there's no error data.
		$this->assertEmpty(
			$this->error_data,
			'The error data was not empty.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_not_send_error_data() {
		$this->init_plugin_data_provider();

		return array(
			'when local zip file exists' => array(
				'plugin'         => array(
					'install' => $this->plugin_data_provider->packages['old'],
					'plugin'  => $this->plugin_data_provider->plugin_name,
				),
				'update_plugins' => $this->plugin_data_provider->get_update_plugins( 'new' ),
				'expected'       => array(
					'messages' => $this->plugin_data_provider->get_messages( 'success_upgrade' ),
				),
			),
		);
	}

	/**
	 * Tests that Plugin_Upgrader::upgrade() sends error data.
	 *
	 * @ticket 51928
	 *
	 * @dataProvider data_should_send_error_data
	 *
	 * @param array $plugin         Array of plugin information.
	 * @param array $update_plugins Value for the "update_plugins" transient.
	 * @param array $expected       Array of expected messages and stats.
	 */
	public function test_should_send_error_data( $plugin, $update_plugins, $expected ) {
		$this->plugin = $plugin;

		$this->shortcircuit_w_org_download();
		$this->capture_error_data();

		$plugin_upgrader = new Plugin_Upgrader( $this->mock_skin_feedback() );

		$this->install_older_version( $plugin_upgrader, $plugin['install'], $update_plugins );

		// Do the upgrade.
		ob_start();
		$result         = $plugin_upgrader->upgrade( $plugin['plugin'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade did not happen.
		$this->assertNull( $result, 'The upgrade was successful.' );
		$this->assertContainsAdminMessages(
			$expected['messages'],
			$actual_message,
			'The actual messages did not match the expected messages.'
		);

		// Validate the sent error data.
		$this->assertContainsErrorDataStats(
			$expected['stats'],
			'Incorrect error data was returned.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_send_error_data() {
		$this->init_plugin_data_provider();

		return array(
			'when new version does not exist' => array(
				'plugin'         => array(
					'install' => $this->plugin_data_provider->packages['old'],
					'plugin'  => $this->plugin_data_provider->plugin_name,
				),
				'update_plugins' => $this->plugin_data_provider->get_update_plugins( 'doesnotexist' ),
				'expected'       => array(
					'messages' => $this->plugin_data_provider->get_messages( 'failed_update', 'doesnotexist' ),
					'stats'    => $this->plugin_data_provider->error_data_stats,
				),
			),
		);
	}
}
