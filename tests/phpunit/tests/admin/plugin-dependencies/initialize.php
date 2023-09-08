<?php
/**
 * Tests for the WP_Plugin_Dependencies::initialize() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::initialize
 */
class Tests_Admin_WPPluginDependencies_Initialize extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that the `$dependencies` and `$dependency_api_data` properties are set to
	 * empty arrays on initialization.
	 */
	public function test_initialize_should_set_dependencies_and_dependency_api_data_to_empty_arrays() {
		self::$instance->initialize();
		$dependencies        = $this->get_property_value( 'dependencies' );
		$dependency_api_data = $this->get_property_value( 'dependency_api_data' );

		$this->assertIsArray( $dependencies, '$dependencies is not an array.' );
		$this->assertEmpty( $dependencies, '$dependencies is not empty.' );
		$this->assertIsArray( $dependency_api_data, '$dependency_api_data is not an array.' );
		$this->assertEmpty( $dependency_api_data, '$dependency_api_data is not empty.' );
	}
}
