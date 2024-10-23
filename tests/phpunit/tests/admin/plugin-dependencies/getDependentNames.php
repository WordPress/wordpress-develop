<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependent_names() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_dependent_names
 * @covers WP_Plugin_Dependencies::get_plugins
 * @covers WP_Plugin_Dependencies::convert_to_slug
 * @covers WP_Plugin_Dependencies::get_dependents
 */
class Tests_Admin_WPPluginDependencies_GetDependentNames extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that dependent names are retrieved.
	 *
	 * @ticket 22316
	 */
	public function test_should_get_dependent_names() {
		$this->set_property_value(
			'plugins',
			array(
				'dependent/dependent.php'   => array(
					'Name'            => 'Dependent 1',
					'RequiresPlugins' => 'dependency',
				),
				'dependent2/dependent2.php' => array(
					'Name'            => 'Dependent 2',
					'RequiresPlugins' => 'dependency',
				),
			)
		);

		self::$instance::initialize();

		$this->assertSame(
			array( 'Dependent 1', 'Dependent 2' ),
			self::$instance::get_dependent_names( 'dependency/dependency.php' )
		);
	}

	/**
	 * Tests that dependent names are sorted.
	 *
	 * @ticket 22316
	 */
	public function test_should_sort_dependent_names() {
		$this->set_property_value(
			'plugins',
			array(
				'dependent2/dependent2.php' => array(
					'Name'            => 'Dependent 2',
					'RequiresPlugins' => 'dependency',
				),
				'dependent/dependent.php'   => array(
					'Name'            => 'Dependent 1',
					'RequiresPlugins' => 'dependency',
				),
			)
		);

		self::$instance::initialize();

		$this->assertSame(
			array( 'Dependent 1', 'Dependent 2' ),
			self::$instance::get_dependent_names( 'dependency/dependency.php' )
		);
	}
}
