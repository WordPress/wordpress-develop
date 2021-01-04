<?php

require_once __DIR__ . '/testcase.php';

/**
 * @covers Plugin_Upgrader::install
 *
 * @group  upgrader
 * @group  plugin_upgrader
 */
class Tests_Admin_Includes_PluginUpgrader_Install extends Admin_Includes_PluginUpgrader_TestCase {

	/**
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @group        51928
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
		$this->assertTrue( $actual );
		foreach ( $expected['messages'] as $expected_message ) {
			$this->assertContains( $expected_message, $actual_message );
		}
	}

	public function data_should_not_send_error_data() {
		return array(
			'when local zip file exists'  => array(
				'plugin'   => array(
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'package'   => DIR_TESTDATA . '/plugins/hello-1.6/hello-dolly.zip',
				),
				'expected' => array(
					'messages' => array(
						<<<MESSAGE
<p>Unpacking the package&#8230;</p>
<p>Installing the plugin&#8230;</p>
<p>Plugin installed successfully.</p>
</div>
MESSAGE
					,
					),
				),
			),
			'when downloading from w.org' => array(
				'plugin'   => array(
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'package'   => 'https://downloads.wordpress.org/plugin/hello-dolly.1.7.2.zip',
				),
				'expected' => array(
					'messages' => array(
						'<div class="wrap"><h1></h1><p>Downloading installation package from <span class="code">%s</span>&#8230;</p>',
						'<p>The authenticity of <span class="code">hello-dolly.1.7.2.zip</span> could not be verified as no signature was found.',
						<<<MESSAGE
<p>Unpacking the package&#8230;</p>
<p>Installing the plugin&#8230;</p>
<p>Plugin installed successfully.</p>
</div>
MESSAGE
					,
					),
				),
			),
		);
	}

	/**
	 * @dataProvider data_should_send_error_data
	 *
	 * @group        51928
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
		$this->assertNull( $result );
		foreach ( $expected['messages'] as $expected_message ) {
			$this->assertContains( $expected_message, $actual_message );
		}

		// Validate the sent error data.
		$expected_stats = $expected['stats'];
		foreach ( $this->error_data as $index => $stats ) {
			$this->assertContains( $expected_stats[ $index ], $stats );
			$this->assertGreaterThan( 0.0, $stats['time_taken'] );
		}
	}

	public function data_should_send_error_data() {
		$not_available_stats = array(
			array(
				'process'          => 'download_package',
				'update_type'      => 'automatic_plugin_update',
				'name'             => null,
				'update_version'   => null,
				'success'          => false,
				'fs_method'        => 'direct',
				'fs_method_forced' => true,
				'fs_method_direct' => '',
				'error_code'       => 'no_package',
				'error_message'    => 'Installation package not available.',
				'error_data'       => null,
			),
			array(
				'process'          => 'plugin_install',
				'update_type'      => 'automatic_plugin_update',
				'name'             => null,
				'update_version'   => null,
				'success'          => false,
				'fs_method'        => 'direct',
				'fs_method_forced' => true,
				'fs_method_direct' => '',
				'error_code'       => 'no_package',
				'error_message'    => 'Installation package not available.',
				'error_data'       => null,
			),
		);

		return array(
			'no_package when empty package given' => array(
				'plugin'   => array(
					'dest_file' => '',
					'dest_dir'  => '',
					'package'   => '',
				),
				'expected' => array(
					'messages' => array(
						'<p>Installation package not available.</p>',
					),
					'stats'    => $not_available_stats,
				),
			),
			'when package does not exist'         => array(
				'plugin'   => array(
					'dest_file' => '',
					'dest_dir'  => '',
					'package'   => DIR_TESTDATA . '/plugins/hello-1.7.2/doesnotexist.zip',
				),
				'expected' => array(
					'messages' => array(
						<<<ERROR_MESSAGE
<p>Unpacking the package&#8230;</p>
<p>The package could not be installed. PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file
ERROR_MESSAGE
					,
					),
					'stats'    => $not_available_stats,
				),
			),
		);
	}
}
