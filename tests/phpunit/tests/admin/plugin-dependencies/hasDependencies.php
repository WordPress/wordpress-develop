<?php
/**
 * Tests for the WP_Plugin_Dependencies::has_dependencies() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::has_dependencies
 */
class Tests_Admin_WPPluginDependencies_HasDependencies extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that a plugin with dependencies will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_dependencies() {
		$this->set_property_value( 'dependencies', array( 'dependent/dependent.php' => array() ) );
		$this->assertTrue( self::$instance::has_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with no dependencies will return false.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_a_plugin_has_no_dependencies() {
		$this->set_property_value( 'dependencies', array( 'dependent2/dependent2.php' => array() ) );
		$this->assertFalse( self::$instance::has_dependencies( 'dependent/dependent.php' ) );
	}
}
