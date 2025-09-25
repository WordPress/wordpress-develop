<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependents() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_dependents
 */
class Tests_Admin_WPPluginDependencies_GetDependents extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that a plugin with no dependents will return an empty array.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_an_empty_array_when_a_plugin_has_no_dependents() {
		self::$instance::initialize();
		$this->assertSame(
			array(),
			self::$instance::get_dependents( 'dependency' )
		);
	}

	/**
	 * Tests that a plugin with dependents will return an array of dependents.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_an_array_of_dependents_when_a_plugin_has_dependents() {
		$this->set_property_value(
			'dependencies',
			array(
				'dependent/dependent.php'   => array( 'dependency' ),
				'dependent2/dependent2.php' => array( 'dependency' ),
			)
		);

		$this->assertSame(
			array( 'dependent/dependent.php', 'dependent2/dependent2.php' ),
			self::$instance::get_dependents( 'dependency' )
		);
	}
}
