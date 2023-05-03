<?php

require_once dirname( __DIR__ ) . '/class-wp-upgrader-testcase.php';

/**
 * Test class for Plugin_Upgrader::install().
 *
 * @group  upgrader
 * @group  plugin_upgrader
 *
 * @covers Plugin_Upgrader::install
 */
class Tests_Admin_Includes_PluginUpgrader_Install extends WP_Upgrader_TestCase {
	/**
	 * Tests that Plugin_Upgrader::install() does not send error data.
	 *
	 * @ticket 51928
	 *
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @param array $plugin   Array of plugin information.
	 * @param array $expected Array of expected admin output messages.
	 */
	public function test_should_not_send_error_data( $plugin, $expected ) {
		$this->plugin = $plugin;

		$plugin_upgrader = $this
			->getMockBuilder( Plugin_Upgrader::class )
			->setConstructorArgs( array( $this->mock_skin_feedback() ) )
			->setMethods( array( 'send_error_data' ) )
			->getMock();

		$plugin_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		// Do the install.
		ob_start();
		$actual         = $plugin_upgrader->install( $plugin['package'] );
		$actual_message = ob_get_clean();

		// Validate the install happened.
		$this->assertTrue( $actual, 'The installation did not succeed.' );
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
				'plugin'   => array(
					'package' => $this->plugin_data_provider->packages['new'],
				),
				'expected' => array(
					'messages' => $this->plugin_data_provider->get_messages( 'success_install' ),
				),
			),
		);
	}

	/**
	 * Tests that Plugin_Upgrader::install() does sends error data.
	 *
	 * @ticket 51928
	 *
	 * @dataProvider data_should_send_error_data
	 *
	 * @param array $plugin   Array of plugin information.
	 * @param array $expected Array of expected messages and stats.
	 */
	public function test_should_send_error_data( $plugin, $expected ) {
		$this->plugin = $plugin;

		$this->shortcircuit_w_org_download();
		$this->capture_error_data();

		$plugin_upgrader = new Plugin_Upgrader( $this->mock_skin_feedback() );

		// Do the install.
		ob_start();
		$result         = $plugin_upgrader->install( $plugin['package'] );
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
			'no_package when empty package given' => array(
				'plugin'   => array(
					'package' => '',
				),
				'expected' => array(
					'messages' => $this->plugin_data_provider->get_messages( 'not_available' ),
					'stats'    => $this->plugin_data_provider->error_data_stats,
				),
			),
			'when package does not exist'         => array(
				'plugin'   => array(
					'package' => $this->plugin_data_provider->packages['doesnotexist'],
				),
				'expected' => array(
					'messages' => $this->plugin_data_provider->get_messages( 'failed_install', 'doesnotexist' ),
					'stats'    => $this->plugin_data_provider->error_data_stats,
				),
			),
		);
	}
}
