<?php

/**
 * Tests that recovery mode ignores errors from network-activated plugins.
 *
 * @group error-protection
 * @group ms-required
 *
 * @covers WP_Recovery_Mode::handle_error
 * @covers WP_Recovery_Mode::is_network_plugin
 */
class Tests_Error_Protection_wpRecoveryModeNetworkPlugins extends WP_UnitTestCase {

	/**
	 * Tests that an error from a network-activated plugin is rejected and not stored.
	 *
	 * @ticket 66328
	 *
	 * @dataProvider data_network_activated_plugins
	 *
	 * @param string $plugin     Plugin basename to network-activate.
	 * @param string $error_file Path of the file that caused the error, relative to the plugins directory.
	 */
	public function test_handle_error_rejects_error_from_network_activated_plugin( $plugin, $error_file ) {
		update_site_option( 'active_sitewide_plugins', array( $plugin => time() ) );

		$result = ( new WP_Recovery_Mode() )->handle_error(
			array(
				'type'    => E_ERROR,
				'file'    => WP_PLUGIN_DIR . '/' . $error_file,
				'line'    => 12,
				'message' => 'Fatal error',
			)
		);

		$this->assertWPError( $result, 'The error should be rejected.' );
		$this->assertSame( 'invalid_source', $result->get_error_code(), 'The error should not be attributed to a pausable extension.' );
		$this->assertSame( array(), wp_paused_plugins()->get_all(), 'No plugin error should be stored.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_network_activated_plugins() {
		return array(
			'plugin in a directory, main file'  => array(
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
			),
			'plugin in a directory, other file' => array(
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
				'custom-internationalized-plugin/includes/broken.php',
			),
			'single-file plugin'                => array(
				'hello.php',
				'hello.php',
			),
		);
	}

	/**
	 * Tests that an error from a plugin that is not network-activated is still handled.
	 *
	 * @ticket 66328
	 *
	 * @dataProvider data_plugins_that_are_not_network_activated
	 *
	 * @param string $network_plugin Plugin basename to network-activate.
	 * @param string $error_file     Path of the file that caused the error, relative to the plugins directory.
	 */
	public function test_handle_error_does_not_reject_error_from_other_plugin( $network_plugin, $error_file ) {
		update_site_option( 'active_sitewide_plugins', array( $network_plugin => time() ) );

		$result = ( new WP_Recovery_Mode() )->handle_error(
			array(
				'type'    => E_ERROR,
				'file'    => WP_PLUGIN_DIR . '/' . $error_file,
				'line'    => 12,
				'message' => 'Fatal error',
			)
		);

		// Recovery mode is not active and this is not a protected endpoint, so the error goes no further.
		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'non_protected_endpoint', $result->get_error_code(), 'The error should be attributed to the plugin.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugins_that_are_not_network_activated() {
		return array(
			'other directory plugin' => array(
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
				'my-plugin/my-plugin.php',
			),
			'plugin whose slug is a prefix of a network plugin' => array(
				'custom-internationalized-plugin/custom-internationalized-plugin.php',
				'custom-internationalized/plugin.php',
			),
		);
	}
}
