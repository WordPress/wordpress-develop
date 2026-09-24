<?php
/**
 * Tests for the WP_Plugin_Dependencies::has_unmet_dependencies() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::has_unmet_dependencies
 */
class Tests_Admin_WPPluginDependencies_HasUnmetDependencies extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that a plugin with no dependencies will return false.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_a_plugin_has_no_dependencies() {
		$this->set_property_value( 'dependencies', array( 'dependent/dependent.php' => array( 'dependency' ) ) );
		$this->assertFalse( self::$instance::has_unmet_dependencies( 'dependent2/dependent2.php' ) );
	}

	/**
	 * Tests that a plugin whose dependencies are installed and active will return false.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_a_plugin_has_no_unmet_dependencies() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency' ) )
		);

		$this->set_property_value(
			'dependency_filepaths',
			array( 'dependency' => 'dependency/dependency.php' )
		);

		update_option( 'active_plugins', array( 'dependency/dependency.php' ) );

		$this->assertFalse( self::$instance::has_unmet_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with a dependency that is not installed will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_a_dependency_that_is_not_installed() {
		self::$instance::initialize();
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency' ) )
		);

		$this->assertTrue( self::$instance::has_unmet_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with a dependency that is inactive will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_a_dependency_that_is_inactive() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency' ) )
		);

		$this->set_property_value(
			'dependency_filepaths',
			array( 'dependency' => 'dependency/dependency.php' )
		);

		$this->assertTrue( self::$instance::has_unmet_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with one dependency that is active and one dependency that is inactive will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_one_active_dependency_and_one_inactive_dependency() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency', 'dependency2' ) )
		);

		$this->set_property_value(
			'dependency_filepaths',
			array(
				'dependency'  => 'dependency/dependency.php',
				'dependency2' => 'dependency2/dependency2.php',
			)
		);

		update_option( 'active_plugins', array( 'dependency/dependency.php' ) );

		$this->assertTrue( self::$instance::has_unmet_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with one dependency that is active and one dependency that is not installed will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_one_active_dependency_and_one_that_is_not_installed() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency', 'dependency2' ) )
		);

		$this->set_property_value(
			'dependency_filepaths',
			array( 'dependency' => 'dependency/dependency.php' )
		);

		update_option( 'active_plugins', array( 'dependency/dependency.php' ) );

		$this->assertTrue( self::$instance::has_unmet_dependencies( 'dependent/dependent.php' ) );
	}

	/**
	 * Tests that a plugin with one dependency that is inactive and one dependency that is not installed will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_one_inactive_dependency_and_one_that_is_not_installed() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency', 'dependency2' ) )
		);

		$this->set_property_value(
			'dependency_filepaths',
			array( 'dependency' => 'dependency/dependency.php' )
		);

		$this->assertTrue( self::$instance::has_unmet_dependencies( 'dependent/dependent.php' ) );
	}
}
