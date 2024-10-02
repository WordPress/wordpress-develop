<?php
/**
 * Tests for the WP_Plugin_Dependencies::has_active_dependents() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::has_active_dependents
 */
class Tests_Admin_WPPluginDependencies_HasActiveDependents extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that a plugin with no dependents will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_a_plugin_has_no_dependents() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency' ) )
		);

		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$this->assertFalse( self::$instance::has_active_dependents( 'dependency2/dependency2.php' ) );
	}

	/**
	 * Tests that a plugin with active dependents will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_active_dependents() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency' ) )
		);

		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$this->assertTrue( self::$instance::has_active_dependents( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests that a plugin with one inactive and one active dependent will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_one_inactive_and_one_active_dependent() {
		$this->set_property_value(
			'dependencies',
			array(
				'dependent2/dependent2.php' => array( 'dependency' ),
				'dependent/dependent.php'   => array( 'dependency' ),
			)
		);

		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$this->assertTrue( self::$instance::has_active_dependents( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests that a plugin with one active and one inactive dependent will return true.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_a_plugin_has_one_active_and_one_inactive_dependent() {
		$this->set_property_value(
			'dependencies',
			array(
				'dependent/dependent.php'   => array( 'dependency' ),
				'dependent2/dependent2.php' => array( 'dependency' ),
			)
		);

		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$this->assertTrue( self::$instance::has_active_dependents( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests that when a plugin with active dependents is earlier in the list,
	 * it will return true if a later plugin has no active dependents.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_the_earlier_plugin_has_active_dependents_but_the_later_plugin_does_not() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent2/dependent2.php' => array( 'dependency' ) )
		);

		$this->set_property_value(
			'dependencies',
			array(
				'dependent/dependent.php'   => array( 'dependency' ),
				'dependent2/dependent2.php' => array( 'dependency2' ),
			)
		);

		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$this->assertTrue( self::$instance::has_active_dependents( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests that when a plugin with active dependents is later in the list,
	 * it will return true if an earlier plugin has no active dependents.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_true_when_the_later_plugin_has_active_dependents_but_the_earlier_plugin_does_not() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent2/dependent2.php' => array( 'dependency' ) )
		);

		$this->set_property_value(
			'dependencies',
			array(
				'dependent/dependent.php'   => array( 'dependency' ),
				'dependent2/dependent2.php' => array( 'dependency2' ),
			)
		);

		update_option( 'active_plugins', array( 'dependent2/dependent2.php' ) );

		$this->assertTrue( self::$instance::has_active_dependents( 'dependency2/dependency2.php' ) );
	}

	/**
	 * Tests that a plugin with no dependents will return false.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_a_plugin_has_no_active_dependents() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent/dependent.php' => array( 'dependency' ) )
		);

		$this->assertFalse( self::$instance::has_active_dependents( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests that when a plugin with no active dependents is earlier in the list,
	 * it will return false if a later plugin has active dependents.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_the_earlier_plugin_has_no_active_dependents_but_the_later_plugin_does() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent2/dependent2.php' => array( 'dependency' ) )
		);

		$this->set_property_value(
			'dependencies',
			array(
				'dependent/dependent.php'   => array( 'dependency' ),
				'dependent2/dependent2.php' => array( 'dependency2' ),
			)
		);

		update_option( 'active_plugins', array( 'dependent2/dependent2.php' ) );

		$this->assertFalse( self::$instance::has_active_dependents( 'dependency/dependency.php' ) );
	}

	/**
	 * Tests that when a plugin with no active dependents is later in the list,
	 * it will return false if an earlier plugin has active dependents.
	 *
	 * @ticket 22316
	 */
	public function test_should_return_false_when_the_later_plugin_has_no_active_dependents_but_the_earlier_plugin_does() {
		$this->set_property_value(
			'dependencies',
			array( 'dependent2/dependent2.php' => array( 'dependency' ) )
		);

		$this->set_property_value(
			'dependencies',
			array(
				'dependent/dependent.php'   => array( 'dependency' ),
				'dependent2/dependent2.php' => array( 'dependency2' ),
			)
		);

		update_option( 'active_plugins', array( 'dependent/dependent.php' ) );

		$this->assertFalse( self::$instance::has_active_dependents( 'dependency2/dependency2.php' ) );
	}
}
