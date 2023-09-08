<?php
/**
 * Tests for the WP_Plugin_Dependencies::add_dependents_to_dependency_plugin_row() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::add_dependents_to_dependency_plugin_row
 */
class Tests_Admin_WPPluginDependencies_AddDependentsToDependencyPluginRow extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that there is no output when there are no dependent names.
	 */
	public function test_should_not_output_when_there_are_no_dependent_names() {
		$this->set_property_value( 'plugins', array() );

		self::$instance->add_dependents_to_dependency_plugin_row(
			'plugin1/plugin1.php',
			array()
		);

		$this->expectOutputString( '' );
	}

	/**
	 * Tests that dependent names are output.
	 *
	 * @dataProvider data_should_output_dependent_names
	 *
	 * @param array  $plugin_data An array of data for a specific plugin.
	 * @param array  $plugins     An array of data for all plugins.
	 * @param string $expected    The expected dependent names.
	 */
	public function test_should_output_dependent_names( $plugin_data, $plugins, $expected ) {
		$this->set_property_value( 'plugins', $plugins );

		$actual = get_echo(
			array( self::$instance, 'add_dependents_to_dependency_plugin_row' ),
			array(
				'plugin1/plugin1.php',
				$plugin_data,
			)
		);

		$this->assertStringContainsString( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_output_dependent_names() {
		return array(
			'one dependent'  => array(
				'plugin_data' => array(
					'slug' => 'dependency1',
				),
				'plugins'     => array(
					'dependent1/dependent1.php' => array(
						'Name'            => 'Dependent 1',
						'RequiresPlugins' => array( 'dependency1' ),
					),
				),
				'expected'    => 'Dependent 1',
			),
			'two dependents' => array(
				'plugin_data' => array(
					'slug' => 'dependency1',
				),
				'plugins'     => array(
					'dependent1/dependent1.php' => array(
						'Name'            => 'Dependent 1',
						'RequiresPlugins' => array( 'dependency1' ),
					),
					'dependent2/dependent2.php' => array(
						'Name'            => 'Dependent 2',
						'RequiresPlugins' => array( 'dependency1' ),
					),
				),
				'expected'    => 'Dependent 1, Dependent 2',
			),
		);
	}
}
