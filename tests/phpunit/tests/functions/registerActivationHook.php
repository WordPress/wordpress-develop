<?php

/**
 * Tests for register_activation_hook().
 *
 * @group functions
 * @group plugins
 *
 * @covers ::register_activation_hook
 */
class Tests_Functions_RegisterActivationHook extends WP_UnitTestCase {

	/**
	 * Tests that the callback is hooked to the activation action of the plugin basename.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_plugin_files
	 *
	 * @param string $file     The plugin file.
	 * @param string $basename The expected plugin basename.
	 */
	public function test_should_hook_callback_to_activation_action( $file, $basename ) {
		register_activation_hook( $file, 'my_plugin_activate' );

		$this->assertSame( 10, has_action( 'activate_' . $basename, 'my_plugin_activate' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugin_files() {
		return array(
			'plugin in a directory' => array( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', 'my-plugin/my-plugin.php' ),
			'single-file plugin'    => array( WP_PLUGIN_DIR . '/my-plugin.php', 'my-plugin.php' ),
			'relative path'         => array( 'my-plugin/my-plugin.php', 'my-plugin/my-plugin.php' ),
			'must-use plugin'       => array( WPMU_PLUGIN_DIR . '/my-mu-plugin.php', 'my-mu-plugin.php' ),
			'backslash separators'  => array( str_replace( '/', '\\', WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' ), 'my-plugin/my-plugin.php' ),
		);
	}

	/**
	 * Tests that the callback runs when the plugin is activated.
	 *
	 * @ticket 65819
	 */
	public function test_should_run_callback_on_activation() {
		$action = new MockAction();

		register_activation_hook( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php', array( $action, 'action' ) );

		$plugin = 'my-plugin/my-plugin.php';
		do_action( "activate_{$plugin}", true );

		$this->assertSame( 1, $action->get_call_count(), 'The callback should run once.' );
		$this->assertSame( array( array( true ) ), $action->get_args(), 'The callback should receive the network-wide flag.' );
	}
}
