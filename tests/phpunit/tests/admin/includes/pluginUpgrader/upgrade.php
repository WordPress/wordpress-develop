<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * @covers Plugin_Upgrader::upgrade
 *
 * @group  upgrader
 * @group  plugin_upgrader
 */
class Tests_Admin_Includes_PluginUpgrader_Upgrade extends WP_Upgrader_TestCase {

	/**
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @group        51928
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
		$this->assertTrue( $result );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate there's no error data.
		$this->assertEmpty( $this->error_data );
	}

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
	 * @dataProvider data_should_send_error_data
	 *
	 * @group        51928
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
		$this->assertNull( $result );
		$this->assertContainsAdminMessages( $expected['messages'], $actual_message );

		// Validate the sent error data.
		$this->assertContainsErrorDataStats( $expected['stats'] );
	}

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
