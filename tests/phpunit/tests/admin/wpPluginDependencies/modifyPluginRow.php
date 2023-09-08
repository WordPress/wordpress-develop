<?php
/**
 * Tests for the WP_Plugin_Dependencies::modify_plugin_row() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::modify_plugin_row
 */
class Tests_Admin_WPPluginDependencies_ModifyPluginRow extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that no filters are set if not currently on 'plugins.php'.
	 *
	 * @dataProvider data_filters
	 *
	 * @param string       $hook The hook name.
	 * @param string|array $callback The callback.
	 */
	public function test_should_not_cause_filters_to_be_set_if_not_currently_on_plugins_php( $hook, $callback ) {
		$this->set_property_value(
			'plugins',
			array(
				'plugin1/plugin1.php'         => array(),
				'dependency1/dependency1.php' => array(),
			)
		);

		$this->set_property_value(
			'dependencies',
			array( 'plugin1/plugin1.php' => array( 'dependency1' ) )
		);

		self::$instance->modify_plugin_row();

		$this->assertFalse(
			has_filter( $hook, $callback ),
			( is_array( $callback ) ? implode( '::', $callback ) : $callback ) . " is hooked to $hook."
		);
	}

	/**
	 * Tests that no actions are set if not currently on 'plugins.php'.
	 *
	 * @dataProvider data_actions
	 *
	 * @param string       $hook The hook name.
	 * @param string|array $callback The callback.
	 */
	public function test_should_not_cause_actions_to_be_set_if_not_currently_on_plugins_php( $hook, $callback ) {
		$this->set_property_value(
			'plugins',
			array(
				'plugin1/plugin1.php'         => array(),
				'dependency1/dependency1.php' => array(),
			)
		);

		$this->set_property_value(
			'dependencies',
			array( 'plugin1/plugin1.php' => array( 'dependency1' ) )
		);

		self::$instance->modify_plugin_row();

		$this->assertFalse(
			has_action( $hook, $callback ),
			( is_array( $callback ) ? implode( '::', $callback ) : $callback ) . " is hooked to $hook."
		);
	}

	/**
	 * Tests that filters are set if currently on 'plugins.php'.
	 *
	 * @dataProvider data_filters
	 *
	 * @param string       $hook The hook name.
	 * @param string|array $callback The callback.
	 */
	public function test_should_cause_filters_to_be_set_if_currently_on_plugins_php( $hook, $callback ) {
		global $pagenow;
		$pagenow_backup = $pagenow;
		$pagenow        = 'plugins.php';

		$this->set_property_value(
			'plugins',
			array(
				'plugin1/plugin1.php'         => array(),
				'dependency1/dependency1.php' => array(),
			)
		);

		$this->set_property_value(
			'dependencies',
			array( 'plugin1/plugin1.php' => array( 'dependency1' ) )
		);

		$this->set_property_value(
			'dependency_slugs',
			array( 'dependency1' )
		);

		self::$instance->modify_plugin_row();

		$pagenow = $pagenow_backup;

		$this->assertIsInt(
			has_filter( $hook, $callback ),
			( is_array( $callback ) ? implode( '::', $callback ) : $callback ) . " is not hooked to $hook."
		);
	}

	/**
	 * Tests that actions are set if currently on 'plugins.php'.
	 *
	 * @dataProvider data_actions
	 *
	 * @param string       $hook The hook name.
	 * @param string|array $callback The callback.
	 */
	public function test_should_cause_actions_to_be_set_if_currently_on_plugins_php( $hook, $callback ) {
		global $pagenow;
		$pagenow_backup = $pagenow;
		$pagenow        = 'plugins.php';

		$this->set_property_value(
			'plugins',
			array(
				'plugin1/plugin1.php'         => array(),
				'dependency1/dependency1.php' => array(),
			)
		);

		$this->set_property_value(
			'dependencies',
			array( 'plugin1/plugin1.php' => array( 'dependency1' ) )
		);

		$this->set_property_value(
			'dependency_slugs',
			array( 'dependency1' )
		);


		self::$instance->modify_plugin_row();

		$pagenow = $pagenow_backup;

		$this->assertIsInt(
			has_action( $hook, $callback ),
			( is_array( $callback ) ? implode( '::', $callback ) : $callback ) . " is not hooked to $hook."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_filters() {
		return array(
			'plugin_row_hide_checkbox_dependency1/dependency1.php' => array(
				'hook'     => 'plugin_row_hide_checkbox_dependency1/dependency1.php',
				'callback' => '__return_true',
			),
			'plugin_action_links_plugin1/plugin1.php' => array(
				'hook'     => 'plugin_action_links_plugin1/plugin1.php',
				'callback' => array( 'WP_Plugin_Dependencies', 'unset_dependency_action_links' ),
			),
			'network_admin_plugin_action_links_plugin1/plugin1.php' => array(
				'hook'     => 'network_admin_plugin_action_links_plugin1/plugin1.php',
				'callback' => array( 'WP_Plugin_Dependencies', 'unset_dependency_action_links' ),
			),
			'network_admin_plugin_action_links_dependency1/dependency1.php' => array(
				'hook'     => 'network_admin_plugin_action_links_dependency1/dependency1.php',
				'callback' => array( 'WP_Plugin_Dependencies', 'unset_dependency_action_links' ),
			),
			'plugin_action_links_plugin1/plugin1.php' => array(
				'hook'     => 'plugin_action_links_plugin1/plugin1.php',
				'callback' => array( 'WP_Plugin_Dependencies', 'disable_activate_for_dependents_with_unmet_dependencies' ),
			),
			'plugin_action_links_dependency1/dependency1.php' => array(
				'hook'     => 'plugin_action_links_dependency1/dependency1.php',
				'callback' => array( 'WP_Plugin_Dependencies', 'unset_dependency_action_links' ),
			),
			'network_admin_plugin_action_links_plugin1/plugin1.php' => array(
				'hook'     => 'network_admin_plugin_action_links_plugin1/plugin1.php',
				'callback' => array( 'WP_Plugin_Dependencies', 'disable_activate_for_dependents_with_unmet_dependencies' ),
			),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_actions() {
		return array(
			'after_plugin_row_meta (dependency)' => array(
				'hook' => 'after_plugin_row_meta',
				'callback' => array( 'WP_Plugin_Dependencies', 'add_dependents_to_dependency_plugin_row' ),
			),
			'after_plugin_row_meta (dependent)' => array(
				'hook' => 'after_plugin_row_meta',
				'callback' => array( 'WP_Plugin_Dependencies', 'add_dependencies_to_dependent_plugin_row' ),
			),
		);
	}

}
