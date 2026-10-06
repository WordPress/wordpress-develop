<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependencies() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_dependencies
 */
class Tests_Admin_WPPluginDependencies_GetDependencies extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that a plugin with no dependencies will return an empty array.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_an_empty_array_when_a_plugin_has_no_dependencies() {
		$this->assertSame( array(), self::$instance::get_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with dependencies will return an array of dependencies.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_an_array_of_dependencies_when_a_plugin_has_dependencies() {
		$expected = array( 'dependency', 'dependency2' );
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => $expected )
		);
		$this->assertSame( $expected, self::$instance::get_dependencies( 'dependent/dependent.php' ) );
	}
}
