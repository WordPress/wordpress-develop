<?php
/**
 * Tests for the WP_Plugin_Dependencies::add_dependent_plugin_row_hooks() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::add_dependent_plugin_row_hooks
 */
class Tests_Admin_WPPluginDependencies_AddDependentPluginRowHooks extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that the expected callback methods are hooked.
	 *
	 * @dataProvider data_should_hook_callback
	 *
	 * @param string       $hook     The hook name.
	 * @param string|array $callback The callback.
	 */
	public function test_should_hook_callback( $hook, $callback ) {
		$add_dependent_plugin_row_hooks = $this->make_method_accessible( 'add_dependent_plugin_row_hooks' );
		$add_dependent_plugin_row_hooks->invoke( self::$instance, 'plugin1/plugin1.php' );
		$add_dependent_plugin_row_hooks->setAccessible( false );

		$this->assertIsInt( has_filter( $hook, $callback ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_hook_callback() {
		return array(
			'after_plugin_row_meta'                   => array(
				'hook'     => 'after_plugin_row_meta',
				'callback' => array(
					'WP_Plugin_Dependencies',
					'add_dependencies_to_dependent_plugin_row',
				),
			),
			'plugin_action_links_plugin1/plugin1.php' => array(
				'hook'     => 'plugin_action_links_plugin1/plugin1.php',
				'callback' => array(
					'WP_Plugin_Dependencies',
					'disable_activate_for_dependents_with_unmet_dependencies',
				),
			),
			'network_admin_plugin_action_links_plugin1/plugin1.php' => array(
				'hook'     => 'network_admin_plugin_action_links_plugin1/plugin1.php',
				'callback' => array(
					'WP_Plugin_Dependencies',
					'disable_activate_for_dependents_with_unmet_dependencies',
				),
			),
		);
	}

}
