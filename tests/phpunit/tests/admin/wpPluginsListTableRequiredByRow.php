<?php

require_once __DIR__ . '/plugin-dependencies/base.php';

/**
 * Tests for the "Required by" row of WP_Plugins_List_Table.
 *
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugins_List_Table::add_dependents_to_dependency_plugin_row
 */
class Tests_Admin_wpPluginsListTableRequiredByRow extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that nothing is output for a plugin that no plugin requires.
	 *
	 * @ticket 65819
	 */
	public function test_should_output_nothing_without_dependents() {
		$this->set_dependencies( array( 'dependent/dependent.php' => array( 'other' ) ) );

		$this->assertSame( '', $this->get_row( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests the row for one plugin that requires the dependency.
	 *
	 * @ticket 65819
	 */
	public function test_should_list_dependent() {
		$this->set_dependencies(
			array(
				'zeta/zeta.php'           => array( 'dependency', 'other' ),
				'alpha/alpha.php'         => array( 'dependency' ),
				'unrelated/unrelated.php' => array( 'other' ),
			)
		);

		$this->assertSame(
			'<div class="required-by"><p><strong>Required by:</strong> Alpha Plugin, Zeta Plugin</p>'
			. '<p>Note: This plugin cannot be deactivated or deleted until the plugins that require it are deactivated or deleted.</p></div>',
			$this->get_row( 'dependency/dependency.php' )
		);
	}

	/**
	 * Tests that a single-file plugin is matched by its slug.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_single_file_plugins
	 *
	 * @param string $plugin_file The plugin file.
	 * @param string $slug        The slug that dependents require.
	 */
	public function test_should_match_single_file_dependency_by_slug( $plugin_file, $slug ) {
		$this->set_dependencies( array( 'dependent/dependent.php' => array( $slug ) ) );

		$this->assertStringStartsWith( '<div class="required-by"><p><strong>Required by:</strong> Dependent Plugin</p>', $this->get_row( $plugin_file ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_single_file_plugins() {
		return array(
			'single-file plugin' => array( 'my-plugin.php', 'my-plugin' ),
			'Hello Dolly'        => array( 'hello.php', 'hello-dolly' ),
		);
	}

	/**
	 * Sets the dependencies and the names of the dependent plugins.
	 *
	 * @param array $dependencies The dependency slugs of each plugin, keyed by plugin file.
	 */
	private function set_dependencies( $dependencies ) {
		$plugins = array();
		foreach ( array_keys( $dependencies ) as $file ) {
			$plugins[ $file ] = array( 'Name' => ucfirst( dirname( $file ) ) . ' Plugin' );
		}

		$this->set_property_value( 'plugins', $plugins );
		$this->set_property_value( 'dependencies', $dependencies );
	}

	/**
	 * Returns the "Required by" row of a plugin.
	 *
	 * @param string $plugin_file The plugin file.
	 * @return string The output.
	 */
	private function get_row( $plugin_file ) {
		$table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );

		$method = new ReflectionMethod( $table, 'add_dependents_to_dependency_plugin_row' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return get_echo( array( $method, 'invoke' ), array( $table, $plugin_file ) );
	}
}
