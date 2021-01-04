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
		foreach ( $expected['messages'] as $expected_message ) {
			$this->assertContains( $expected_message, $actual_message );
		}
	}

	public function data_should_not_send_error_data() {
		return array(
			'when local zip file exists'  => array(
				'plugin'         => array(
					'install'   => DIR_TESTDATA . '/plugins/hello-1.6/hello-dolly.zip',
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'plugin'    => 'hello-dolly/hello.php',
				),
				'update_plugins' => (object) array(
					'last_checked' => time(),
					'checked'      => array(
						'hello-dolly/hello.php' => '1.6',
					),
					'response'     => array(
						'hello-dolly/hello.php' => (object) array(
							'id'          => 'w.org/plugins/hello-dolly',
							'slug'        => 'hello-dolly',
							'plugin'      => 'hello-dolly/hello.php',
							'new_version' => '1.7.2',
							'url'         => 'https://wordpress.org/plugins/hello-dolly/',
							'package'     => DIR_TESTDATA . '/plugins/hello-1.7.2/hello-dolly.zip',
						),
					),
				),
				'expected'       => array(
					'messages' => array(
						<<<MESSAGE
<p>Unpacking the update&#8230;</p>
<p>Installing the latest version&#8230;</p>
<p>Removing the old version of the plugin&#8230;</p>
<p>Plugin updated successfully.</p>
MESSAGE
					,
					),
				),
			),
			'when downloading from w.org' => array(
				'plugin'         => array(
					'install'   => DIR_TESTDATA . '/plugins/hello-1.6/hello-dolly.zip',
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'plugin'    => 'hello-dolly/hello.php',
				),
				'update_plugins' => (object) array(
					'last_checked' => time(),
					'checked'      => array(
						'hello-dolly/hello.php' => '1.6',
					),
					'response'     => array(
						'hello-dolly/hello.php' => (object) array(
							'id'          => 'w.org/plugins/hello-dolly',
							'slug'        => 'hello-dolly',
							'plugin'      => 'hello-dolly/hello.php',
							'new_version' => '1.7.2',
							'url'         => 'https://wordpress.org/plugins/hello-dolly/',
							'package'     => 'https://downloads.wordpress.org/plugin/hello-dolly.1.7.2.zip',
						),
					),
				),
				'expected'       => array(
					'messages' => array(
						<<<MESSAGE
<p>Downloading update from <span class="code">%s</span>&#8230;</p>
<p>The authenticity of <span class="code">hello-dolly.1.7.2.zip</span> could not be verified as no signature was found.</p>
MESSAGE
					,
						<<<MESSAGE
<p>Unpacking the update&#8230;</p>
<p>Installing the latest version&#8230;</p>
<p>Removing the old version of the plugin&#8230;</p>
<p>Plugin updated successfully.</p>
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
		return array(
			'when new version does not exist' => array(
				'plugin'         => array(
					'install'   => DIR_TESTDATA . '/plugins/hello-1.6/hello-dolly.zip',
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'plugin'    => 'hello-dolly/hello.php',
				),
				'update_plugins' => (object) array(
					'last_checked' => time(),
					'checked'      => array(
						'hello-dolly/hello.php' => '1.6',
					),
					'response'     => array(
						'hello-dolly/hello.php' => (object) array(
							'id'          => 'w.org/plugins/hello-dolly',
							'slug'        => 'hello-dolly',
							'plugin'      => 'hello-dolly/hello.php',
							'new_version' => '99999',
							'url'         => 'https://wordpress.org/plugins/hello-dolly/',
							'package'     => DIR_TESTDATA . '/plugins/hello-99999/hello-dolly.zip',
						),
					),
				),
				'expected'       => array(
					'messages' => array(
						'data/plugins/hello-99999/hello-dolly.zip',
						<<<ERROR_MESSAGE
<p>Unpacking the update&#8230;</p>
<p>The package could not be installed. PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file
ERROR_MESSAGE
					,
					),
					'stats'    => array(
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
					),
				),
			),
		);
	}
}
