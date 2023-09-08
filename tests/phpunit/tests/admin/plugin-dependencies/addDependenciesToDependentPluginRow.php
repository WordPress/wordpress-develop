<?php
/**
 * Tests for the WP_Plugin_Dependencies::add_dependencies_to_dependent_plugin_row() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::add_dependencies_to_dependent_plugin_row
 */
class Tests_Admin_WPPluginDependencies_AddDependenciesToDependentPluginRow extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that there is no output when there are no dependency names.
	 */
	public function test_should_not_output_when_there_are_no_dependency_names() {
		$this->set_property_value( 'plugins', array() );
		self::$instance->add_dependencies_to_dependent_plugin_row( 'plugin1/plugin1.php' );

		$this->expectOutputString( '' );
	}

	/**
	 * Tests that dependency names are output.
	 *
	 * @dataProvider data_should_output_the_dependency_slug_when_the_dependency_name_is_not_available
	 *
	 * @param array  $dependency_slugs An array of dependency slugs.
	 * @param array  $plugins          An array of data for all plugins.
	 * @param string $expected         The expected dependency names.
	 */
	public function test_should_output_the_dependency_slug_when_the_dependency_name_is_not_available( $dependency_slugs, $plugins, $expected ) {
		$this->set_property_value( 'dependency_slugs', $dependency_slugs );
		$this->set_property_value( 'plugins', $plugins );
		$this->set_property_value(
			'dependencies',
			array( 'plugin1/plugin1.php' => $dependency_slugs )
		);

		$actual = get_echo(
			array( self::$instance, 'add_dependencies_to_dependent_plugin_row' ),
			array( 'plugin1/plugin1.php' )
		);

		$this->assertStringContainsString( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_output_the_dependency_slug_when_the_dependency_name_is_not_available() {
		return array(
			'one dependency' => array(
				'dependency_slugs' => array( 'dependency1' ),
				'plugins'          => array(
					'dependency1/dependency1.php' => array(),
				),
				'expected'         => 'dependency1',
			),
			'two dependents' => array(
				'plugin_data' => array( 'dependency1', 'dependency2' ),
				'plugins'     => array(
					'dependency1/dependency1.php' => array(),
					'dependency2/dependency2.php' => array(),
				),
				'expected'    => 'dependency1, dependency2',
			),
		);
	}
}
