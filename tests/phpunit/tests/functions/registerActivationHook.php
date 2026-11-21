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
