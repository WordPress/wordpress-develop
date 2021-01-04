<?php

require_once __DIR__ . '/testcase.php';

/**
 * @covers Plugin_Upgrader::bulk_upgrade
 *
 * @group  updater
 * @group  plugin_updater
 */
class Tests_Admin_Includes_PluginUpdater_BulkUpgrade extends Admin_Includes_PluginUpdater_TestCase {

	/**
	 * @dataProvider data_should_not_send_error_data
	 *
	 * @group        51928b
	 *
	 * @param array $plugins        Array of plugins information.
	 * @param array $update_plugins Value for the "update_plugins" transient.
	 * @param array $expected       Array of expected results and messages.
	 */
	public function test_should_not_send_error_data( $plugins, $update_plugins, $expected ) {
		$this->plugin = $plugins;

		$plugin_upgrader = $this
			->getMockBuilder( Plugin_Upgrader::class )
			->setConstructorArgs( array( $this->mock_skin_feedback() ) )
			->setMethods( array( 'send_error_data' ) )
			->getMock();

		$plugin_upgrader
			->expects( $this->never() )
			->method( 'send_error_data' );

		$this->install_older_version( $plugin_upgrader, $plugins['install'], $update_plugins );

		// Do the bulk upgrade.
		ob_start();
		$actual_results = $plugin_upgrader->bulk_upgrade( $plugins['plugins'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade happened.
		$this->assertArrayHasKey( 'hello-dolly/hello.php', $actual_results );
		$this->assertArrayHasKey( 'source_files', $actual_results['hello-dolly/hello.php'] );
		$this->assertSame(
			$expected['results']['hello-dolly/hello.php']['source_files'],
			$actual_results['hello-dolly/hello.php']['source_files']
		);
		foreach ( $expected['messages'] as $expected ) {
			$this->assertContains( $expected, $actual_message );
		}
	}

	public function data_should_not_send_error_data() {
		return array(
			'when local zip file exists'  => array(
				'plugins'        => array(
					'install'   => DIR_TESTDATA . '/plugins/hello-1.6/hello-dolly.zip',
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'plugins'   => array( 'hello-dolly/hello.php' ),
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
					'results'  => array(
						'hello-dolly/hello.php' => array(
							'source_files' => array( 'hello.php' ),
						),
					),
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
					'plugins'   => array( 'hello-dolly/hello.php' ),
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
					'results'  => array(
						'hello-dolly/hello.php' => array(
							'source_files' => array( 'hello.php', 'readme.txt' ),
						),
					),
					'messages' => array(
						<<<MESSAGE
<p>Downloading update from <span class="code">%s</span>&#8230;</p>
<p>The authenticity of <span class="code">hello-dolly.1.7.2.zip</span> could not be verified as no signature was found.</p>
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
	 * @param array $plugins        Array of plugins information.
	 * @param array $update_plugins Value for the "update_plugins" transient.
	 * @param array $expected       Array of expected results, messages, and stats.
	 */
	public function test_should_send_error_data( $plugins, $update_plugins, $expected ) {
		$this->plugin = $plugins;

		$this->shortcircuit_w_org_download();
		$this->capture_error_report();

		$plugin_upgrader = new Plugin_Upgrader( $this->mock_skin_feedback() );

		$this->install_older_version( $plugin_upgrader, $plugins['install'], $update_plugins );

		// Do the bulk upgrade.
		ob_start();
		$actual_results = $plugin_upgrader->bulk_upgrade( $plugins['plugins'] );
		$actual_message = ob_get_clean();

		// Validate the upgrade did not happen.
		$this->assertArrayHasKey( 'hello-dolly/hello.php', $actual_results );
		$this->assertNull( $actual_results['hello-dolly/hello.php'] );
		foreach ( $expected['messages'] as $expected ) {
			$this->assertContains( $expected, $actual_message );
		}

		// Validate the sent error data.
		foreach ( $this->error_report as $index => $stats ) {
			$this->assertContains( $expected['stats'][ $index ], $stats );
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
			'when new version does not exist' => array(
				'plugins'        => array(
					'install'   => DIR_TESTDATA . '/plugins/hello-1.6/hello-dolly.zip',
					'dest_file' => WP_PLUGIN_DIR . '/hello-dolly/hello.php',
					'dest_dir'  => WP_PLUGIN_DIR . '/hello-dolly',
					'plugins'   => array( 'hello-dolly/hello.php' ),
				),
				'update_plugins' => (object) array(
					'last_checked' => time(),
					'checked'      => array(
						'hello.php' => '1.6',
					),
					'response'     => array(
						'hello-dolly/hello.php' => (object) array(
							'id'          => 'w.org/plugins/hello-dolly',
							'slug'        => 'hello-dolly',
							'plugin'      => 'hello.php',
							'new_version' => '99999',
							'url'         => 'https://wordpress.org/plugins/hello-dolly/',
							'package'     => DIR_TESTDATA . '/plugins/hello-99999/hello.zip',
						),
					),
				),
				'expected'       => array(
					'messages' => array(
						'data/plugins/hello-99999/hello.zip',
						<<<ERROR_MESSAGE
<p>Unpacking the update&#8230;</p>
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
