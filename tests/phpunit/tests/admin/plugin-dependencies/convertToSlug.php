<?php
/**
 * Tests for the WP_Plugin_Dependencies::convert_to_slug() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::convert_to_slug
 */
class Tests_Admin_WPPluginDependencies_ConvertToSlug extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that a plugin in a directory is slugified.
	 */
	public function test_should_return_slug_for_a_plugin_in_a_directory() {
		$this->set_property_value( 'dependency_slugs', array( 'dependent' ) );
		$this->assertTrue( $this->call_method( 'has_dependents', 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a single file plugin with dependents will return true.
	 */
	public function test_should_return_true_when_a_single_file_plugin_has_dependents() {
		$this->set_property_value( 'dependency_slugs', array( 'dependent' ) );
		$this->assertTrue( $this->call_method( 'has_dependents', 'dependent.php' ) );
	}

	/**
	 * Tests that a plugin with no dependents will return false.
	 */
	public function test_should_return_false_when_a_plugin_has_no_dependents() {
		$this->set_property_value( 'dependency_slugs', array( 'dependent2' ) );
		$this->assertFalse( $this->call_method( 'has_dependents', 'dependent/dependent.php' ) );
	}
}
