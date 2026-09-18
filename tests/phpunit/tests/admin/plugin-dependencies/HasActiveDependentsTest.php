<?php
/**
 * Tests for the WP_Plugin_Dependencies::has_active_dependents() method.
 *
 * @package WordPress
 */

namespace WordPress\Tests\Admin\PluginDependencies;

use WP_Plugin_Dependencies;
use WP_PluginDependencies_UnitTestCase;

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers \WP_Plugin_Dependencies::has_active_dependents
 */
class HasActiveDependentsTest extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests whether a plugin has active dependents.
	 *
	 * @ticket 22316
	 *
	 * @dataProvider data_has_active_dependents
	 *
	 * @param array  $dependencies    An array of plugin dependencies.
	 * @param array  $active_plugins  An array of active plugins.
	 * @param string $plugin_to_check The plugin to check.
	 * @param bool   $expected        The expected result.
	 */
	public function test_has_active_dependents( $dependencies, $active_plugins, $plugin_to_check, $expected ) {
		$this->set_property_value( 'dependencies', $dependencies );

		if ( ! empty( $active_plugins ) ) {
			update_option( 'active_plugins', $active_plugins );
		}

		$this->assertSame( $expected, self::$instance::has_active_dependents( $plugin_to_check ) );
	}

	/**
	 * Data provider for test_has_active_dependents.
	 *
	 * @return array[]
	 */
	public function data_has_active_dependents() {
		return array(
			'no dependents for plugin' => array(
				'dependencies'    => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'active_plugins'  => array( 'dependent/dependent.php' ),
				'plugin_to_check' => 'dependency2/dependency2.php',
				'expected'        => false,
			),
			'active dependent exists' => array(
				'dependencies'    => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'active_plugins'  => array( 'dependent/dependent.php' ),
				'plugin_to_check' => 'dependency/dependency.php',
				'expected'        => true,
			),
			'one inactive and one active dependent' => array(
				'dependencies'    => array(
					'dependent2/dependent2.php' => array( 'dependency' ),
					'dependent/dependent.php'   => array( 'dependency' ),
				),
				'active_plugins'  => array( 'dependent/dependent.php' ),
				'plugin_to_check' => 'dependency/dependency.php',
				'expected'        => true,
			),
			'one active and one inactive dependent' => array(
				'dependencies'    => array(
					'dependent/dependent.php'   => array( 'dependency' ),
					'dependent2/dependent2.php' => array( 'dependency' ),
				),
				'active_plugins'  => array( 'dependent/dependent.php' ),
				'plugin_to_check' => 'dependency/dependency.php',
				'expected'        => true,
			),
			'earlier plugin has active dependents, checking earlier plugin' => array(
				'dependencies'    => array(
					'dependent/dependent.php'   => array( 'dependency' ),
					'dependent2/dependent2.php' => array( 'dependency2' ),
				),
				'active_plugins'  => array( 'dependent/dependent.php' ),
				'plugin_to_check' => 'dependency/dependency.php',
				'expected'        => true,
			),
			'later plugin has active dependents, checking later plugin' => array(
				'dependencies'    => array(
					'dependent/dependent.php'   => array( 'dependency' ),
					'dependent2/dependent2.php' => array( 'dependency2' ),
				),
				'active_plugins'  => array( 'dependent2/dependent2.php' ),
				'plugin_to_check' => 'dependency2/dependency2.php',
				'expected'        => true,
			),
			'dependent is inactive' => array(
				'dependencies'    => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'active_plugins'  => array(),
				'plugin_to_check' => 'dependency/dependency.php',
				'expected'        => false,
			),
			'earlier plugin has no active dependents, checking earlier plugin' => array(
				'dependencies'    => array(
					'dependent/dependent.php'   => array( 'dependency' ),
					'dependent2/dependent2.php' => array( 'dependency2' ),
				),
				'active_plugins'  => array( 'dependent2/dependent2.php' ),
				'plugin_to_check' => 'dependency/dependency.php',
				'expected'        => false,
			),
			'later plugin has no active dependents, checking later plugin' => array(
				'dependencies'    => array(
					'dependent/dependent.php'   => array( 'dependency' ),
					'dependent2/dependent2.php' => array( 'dependency2' ),
				),
				'active_plugins'  => array( 'dependent/dependent.php' ),
				'plugin_to_check' => 'dependency2/dependency2.php',
				'expected'        => false,
			),
		);
	}
}
