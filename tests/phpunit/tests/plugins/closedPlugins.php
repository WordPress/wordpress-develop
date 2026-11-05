<?php

/**
 * Test closed, outdated, and security-withdrawn plugins handling.
 *
 * @group plugins
 * @group update
 */
class Tests_Plugins_ClosedPlugins extends WP_UnitTestCase {

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
			set_current_screen( 'plugins-network' );
		} else {
			set_current_screen( 'plugins.php' );
		}
		wp_set_current_user( $admin_id );
	}

	/**
	 * Cleans up options and transients after each test.
	 */
	public function tear_down() {
		delete_site_transient( 'update_plugins' );
		delete_option( 'active_plugins' );
		wp_cache_delete( 'plugins', 'plugins' );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'all_plugins' );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Tests that wp_update_plugins preserves closed, outdated, and security flags.
	 *
	 * @covers ::wp_update_plugins
	 */
	public function test_wp_update_plugins_preserves_closed_outdated_and_security_metadata() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-includes/update.php';

		$test_plugins = array(
			'sec-plugin/sec-plugin.php'  => array(
				'Name'       => 'Security Plugin',
				'Version'    => '1.0.0',
				'PluginURI'  => 'https://wordpress.org/plugins/sec-plugin/',
				'Author'     => 'Tester',
				'TextDomain' => 'sec-plugin',
				'UpdateURI'  => '',
			),
			'outdated/outdated.php'      => array(
				'Name'       => 'Outdated Plugin',
				'Version'    => '2.0.0',
				'PluginURI'  => 'https://wordpress.org/plugins/outdated/',
				'Author'     => 'Tester',
				'TextDomain' => 'outdated',
				'UpdateURI'  => '',
			),
			'general-closed/general.php' => array(
				'Name'       => 'General Closed Plugin',
				'Version'    => '1.5.0',
				'PluginURI'  => 'https://wordpress.org/plugins/general-closed/',
				'Author'     => 'Tester',
				'TextDomain' => 'general-closed',
				'UpdateURI'  => '',
			),
		);

		add_filter(
			'all_plugins',
			static function () use ( $test_plugins ) {
				return $test_plugins;
			}
		);

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( false === strpos( $url, 'api.wordpress.org/plugins/update-check' ) ) {
					return $response;
				}

				$mock_body = array(
					'plugins'      => array(
						'sec-plugin/sec-plugin.php' => (object) array(
							'id'            => 'w.org/plugins/sec-plugin',
							'slug'          => 'sec-plugin',
							'plugin'        => 'sec-plugin/sec-plugin.php',
							'new_version'   => '1.1.0',
							'url'           => 'https://wordpress.org/plugins/sec-plugin/',
							'package'       => '',
							'closed'        => true,
							'closed_date'   => '2025-01-15',
							'closed_reason' => 'security-issue',
							'reason'        => 'security-issue',
							'reason_text'   => 'Security Issue',
							'is_security'   => true,
							'is_outdated'   => false,
						),
					),
					'translations' => array(),
					'no_update'    => array(
						'outdated/outdated.php' => (object) array(
							'id'              => 'w.org/plugins/outdated',
							'slug'            => 'outdated',
							'plugin'          => 'outdated/outdated.php',
							'new_version'     => '2.0.0',
							'url'             => 'https://wordpress.org/plugins/outdated/',
							'is_outdated'     => true,
							'outdated_notice' => 'This plugin has not been tested with the latest 3 major releases.',
							'closed'          => false,
						),
					),
					'closed'       => array(
						'general-closed/general.php' => (object) array(
							'id'            => 'w.org/plugins/general-closed',
							'slug'          => 'general-closed',
							'plugin'        => 'general-closed/general.php',
							'new_version'   => '1.5.0',
							'url'           => 'https://wordpress.org/plugins/general-closed/',
							'closed'        => true,
							'closed_date'   => '2024-06-20',
							'closed_reason' => 'author-request',
							'reason'        => 'author-request',
							'reason_text'   => 'Author Request',
							'is_security'   => false,
							'is_outdated'   => false,
						),
					),
				);

				return array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => wp_json_encode( $mock_body ),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		delete_site_transient( 'update_plugins' );
		wp_update_plugins( array( 'core' => 'test' ) );

		$transient = get_site_transient( 'update_plugins' );
		$this->assertIsObject( $transient, 'Transient should be an object.' );
		$this->assertArrayHasKey( 'sec-plugin/sec-plugin.php', $transient->response );

		$sec_item = $transient->response['sec-plugin/sec-plugin.php'];
		$this->assertTrue( $sec_item->closed );
		$this->assertTrue( $sec_item->is_security );
		$this->assertSame( '2025-01-15', $sec_item->closed_date );
		$this->assertSame( 'security-issue', $sec_item->closed_reason );

		$this->assertArrayHasKey( 'outdated/outdated.php', $transient->no_update );
		$outdated_item = $transient->no_update['outdated/outdated.php'];
		$this->assertTrue( $outdated_item->is_outdated );
		$this->assertSame( 'This plugin has not been tested with the latest 3 major releases.', $outdated_item->outdated_notice );

		$this->assertArrayHasKey( 'general-closed/general.php', $transient->no_update );
		$closed_item = $transient->no_update['general-closed/general.php'];
		$this->assertTrue( $closed_item->closed );
		$this->assertFalse( $closed_item->is_security );
		$this->assertSame( '2024-06-20', $closed_item->closed_date );
		$this->assertSame( 'author-request', $closed_item->closed_reason );
	}

	/**
	 * Tests that wp_plugin_update_row outputs a critical security alert notice for security closures.
	 *
	 * @covers ::wp_plugin_update_row
	 */
	public function test_wp_plugin_update_row_security_closure() {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$file        = 'sec-plugin/sec-plugin.php';
		$plugin_data = array(
			'Name'    => 'Security Plugin',
			'Version' => '1.0.0',
		);

		$transient            = new stdClass();
		$transient->response  = array(
			$file => (object) array(
				'slug'          => 'sec-plugin',
				'new_version'   => '1.0.0',
				'closed'        => true,
				'closed_date'   => '2025-01-15',
				'closed_reason' => 'security-issue',
				'is_security'   => true,
			),
		);
		$transient->no_update = array();
		set_site_transient( 'update_plugins', $transient );

		ob_start();
		wp_plugin_update_row( $file, $plugin_data );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'role="alert"', $output );
		$this->assertStringContainsString( 'Warning: This plugin was closed on', $output );
		$this->assertStringContainsString( 'due to a security issue and is no longer available for download. It should be uninstalled or replaced immediately.', $output );
	}

	/**
	 * Tests that wp_plugin_update_row outputs a warning notice for general closures.
	 *
	 * @covers ::wp_plugin_update_row
	 */
	public function test_wp_plugin_update_row_general_closure() {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$file        = 'closed-plugin/closed-plugin.php';
		$plugin_data = array(
			'Name'    => 'Closed Plugin',
			'Version' => '1.0.0',
		);

		$transient            = new stdClass();
		$transient->response  = array();
		$transient->no_update = array(
			$file => (object) array(
				'slug'        => 'closed-plugin',
				'new_version' => '1.0.0',
				'closed'      => true,
				'closed_date' => '2024-06-20',
				'reason_text' => 'Author Request',
				'is_security' => false,
			),
		);
		set_site_transient( 'update_plugins', $transient );

		ob_start();
		wp_plugin_update_row( $file, $plugin_data );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'role="status"', $output );
		$this->assertStringContainsString( 'Notice: This plugin was closed on', $output );
		$this->assertStringContainsString( 'Author Request', $output );
		$this->assertStringContainsString( 'and is no longer available for download.', $output );
	}

	/**
	 * Tests that wp_plugin_update_row outputs a warning notice for outdated plugins.
	 *
	 * @covers ::wp_plugin_update_row
	 */
	public function test_wp_plugin_update_row_outdated() {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$file        = 'outdated-plugin/outdated-plugin.php';
		$plugin_data = array(
			'Name'    => 'Outdated Plugin',
			'Version' => '1.0.0',
		);

		$transient            = new stdClass();
		$transient->response  = array();
		$transient->no_update = array(
			$file => (object) array(
				'slug'            => 'outdated-plugin',
				'new_version'     => '1.0.0',
				'is_outdated'     => true,
				'outdated_notice' => 'Custom outdated warning notice.',
			),
		);
		set_site_transient( 'update_plugins', $transient );

		ob_start();
		wp_plugin_update_row( $file, $plugin_data );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'role="status"', $output );
		$this->assertStringContainsString( 'Custom outdated warning notice.', $output );
	}

	/**
	 * Tests that WP_Plugins_List_Table renders badges for closed and outdated plugins.
	 *
	 * @covers WP_Plugins_List_Table::single_row
	 */
	public function test_wp_plugins_list_table_badges() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';

		$transient            = new stdClass();
		$transient->response  = array(
			'hello.php' => (object) array(
				'slug'        => 'hello',
				'closed'      => true,
				'is_security' => true,
			),
		);
		$transient->no_update = array(
			'internationalized-plugin.php' => (object) array(
				'slug'        => 'internationalized-plugin',
				'closed'      => true,
				'is_security' => false,
			),
			'custom-internationalized-plugin/custom-internationalized-plugin.php' => (object) array(
				'slug'        => 'custom-internationalized-plugin',
				'is_outdated' => true,
			),
		);
		set_site_transient( 'update_plugins', $transient );

		$table = new WP_Plugins_List_Table( array( 'screen' => get_current_screen() ) );

		// Security closed plugin.
		ob_start();
		$table->single_row(
			array(
				'hello.php',
				array(
					'Name'        => 'Hello Dolly',
					'Version'     => '1.0.0',
					'Description' => 'Test',
				),
			)
		);
		$output_sec = ob_get_clean();
		$this->assertStringContainsString( 'plugin-status-badge-security', $output_sec );
		$this->assertStringContainsString( 'Closed (Security)', $output_sec );

		// General closed plugin.
		ob_start();
		$table->single_row(
			array(
				'internationalized-plugin.php',
				array(
					'Name'        => 'Internationalized Plugin',
					'Version'     => '1.0.0',
					'Description' => 'Test',
				),
			)
		);
		$output_gen = ob_get_clean();
		$this->assertStringContainsString( 'plugin-status-badge plugin-status-badge-closed', $output_gen );
		$this->assertStringContainsString( 'Closed', $output_gen );

		// Outdated plugin.
		ob_start();
		$table->single_row(
			array(
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
				array(
					'Name'        => 'Custom Internationalized Plugin',
					'Version'     => '1.0.0',
					'Description' => 'Test',
				),
			)
		);
		$output_old = ob_get_clean();
		$this->assertStringContainsString( 'plugin-status-badge plugin-status-badge-outdated', $output_old );
		$this->assertStringContainsString( 'Outdated', $output_old );
	}

	/**
	 * Tests that plugins_api handles error => closed without converting to WP_Error.
	 *
	 * @covers ::plugins_api
	 */
	public function test_plugins_api_preserves_closed_plugin_object() {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				if ( false === strpos( $url, 'api.wordpress.org/plugins/info' ) ) {
					return $response;
				}

				return array(
					'headers'  => array(),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => wp_json_encode(
						array(
							'error'       => 'closed',
							'name'        => 'Test Closed Plugin',
							'slug'        => 'test-closed-plugin',
							'description' => 'This plugin has been closed.',
							'status'      => 'closed',
							'closed'      => true,
							'closed_date' => '2025-01-15',
							'reason'      => 'security-issue',
							'reason_text' => 'Security Issue',
							'is_security' => true,
							'is_outdated' => false,
						)
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$result = plugins_api( 'plugin_information', array( 'slug' => 'test-closed-plugin' ) );

		$this->assertNotWPError( $result );
		$this->assertIsObject( $result );
		$this->assertSame( 'closed', $result->error );
		$this->assertTrue( $result->closed );
		$this->assertTrue( $result->is_security );
		$this->assertSame( 'security-issue', $result->reason );
	}

	/**
	 * Tests Site Health check for closed plugins.
	 *
	 * @covers WP_Site_Health::get_test_closed_plugins_and_themes
	 */
	public function test_site_health_closed_plugins_check() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$mock_plugins = array(
			'safe-plugin/safe-plugin.php'     => array(
				'Name'    => 'Safe Plugin',
				'Version' => '1.0.0',
			),
			'sec-plugin/sec-plugin.php'       => array(
				'Name'    => 'Security Plugin',
				'Version' => '1.0.0',
			),
			'closed-plugin/closed-plugin.php' => array(
				'Name'    => 'Closed Plugin',
				'Version' => '1.0.0',
			),
			'old-plugin/old-plugin.php'       => array(
				'Name'    => 'Old Plugin',
				'Version' => '1.0.0',
			),
		);

		wp_cache_set( 'plugins', array( '' => $mock_plugins ), 'plugins' );

		$site_health = new WP_Site_Health();

		// Case 1: No closed active plugins -> good status.
		update_option( 'active_plugins', array( 'safe-plugin/safe-plugin.php' ) );
		delete_site_transient( 'update_plugins' );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'good', $res['status'] );

		// Case 2: Active plugin closed for security -> critical status.
		$transient           = new stdClass();
		$transient->response = array(
			'sec-plugin/sec-plugin.php' => (object) array(
				'slug'        => 'sec-plugin',
				'closed'      => true,
				'is_security' => true,
				'closed_date' => '2025-01-15',
			),
		);
		set_site_transient( 'update_plugins', $transient );
		update_option( 'active_plugins', array( 'sec-plugin/sec-plugin.php' ) );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'critical', $res['status'] );
		$this->assertStringContainsString( 'security issues', $res['label'] );
		$this->assertStringContainsString( 'action=deactivate', $res['description'] );

		// Case 3: Active plugin closed for non-security -> recommended status.
		$transient           = new stdClass();
		$transient->response = array(
			'closed-plugin/closed-plugin.php' => (object) array(
				'slug'        => 'closed-plugin',
				'closed'      => true,
				'is_security' => false,
			),
		);
		set_site_transient( 'update_plugins', $transient );
		update_option( 'active_plugins', array( 'closed-plugin/closed-plugin.php' ) );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'recommended', $res['status'] );
		$this->assertStringContainsString( 'no longer maintained or available', $res['label'] );

		// Case 4: Active plugin is outdated -> recommended status.
		$transient            = new stdClass();
		$transient->response  = array();
		$transient->no_update = array(
			'old-plugin/old-plugin.php' => (object) array(
				'slug'        => 'old-plugin',
				'is_outdated' => true,
			),
		);
		set_site_transient( 'update_plugins', $transient );
		update_option( 'active_plugins', array( 'old-plugin/old-plugin.php' ) );

		$res = $site_health->get_test_closed_plugins_and_themes();
		$this->assertSame( 'recommended', $res['status'] );
	}
}
