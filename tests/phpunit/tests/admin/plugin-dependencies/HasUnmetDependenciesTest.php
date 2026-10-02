<?php
/**
 * Tests for the WP_Plugin_Dependencies::has_unmet_dependencies() method.
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
 * @covers \WP_Plugin_Dependencies::has_unmet_dependencies
 */
class HasUnmetDependenciesTest extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests whether a plugin has unmet dependencies.
	 *
	 * @ticket 22316
	 *
	 * @dataProvider data_has_unmet_dependencies
	 *
	 * @param array  $dependencies         An array of plugin dependencies.
	 * @param array  $dependency_filepaths An array of dependency filepaths.
	 * @param array  $active_plugins       An array of active plugins.
	 * @param string $plugin_to_check      The plugin to check.
	 * @param bool   $expected             The expected result.
	 */
	public function test_has_unmet_dependencies( $dependencies, $dependency_filepaths, $active_plugins, $plugin_to_check, $expected ) {
		$this->set_property_value( 'dependencies', $dependencies );

		if ( ! empty( $dependency_filepaths ) ) {
			$this->set_property_value( 'dependency_filepaths', $dependency_filepaths );
		}

		if ( ! empty( $active_plugins ) ) {
			update_option( 'active_plugins', $active_plugins );
		}

		$this->assertSame( $expected, self::$instance::has_unmet_dependencies( $plugin_to_check ) );
	}

	/**
	 * Data provider for test_has_unmet_dependencies.
	 *
	 * @return array[]
	 */
	public function data_has_unmet_dependencies() {
		return array(
			'no dependencies for plugin' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'dependency_filepaths' => array(),
				'active_plugins'       => array(),
				'plugin_to_check'      => 'dependent2/dependent2.php',
				'expected'             => false,
			),
			'dependencies are installed and active' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'dependency_filepaths' => array( 'dependency' => 'dependency/dependency.php' ),
				'active_plugins'       => array( 'dependency/dependency.php' ),
				'plugin_to_check'      => 'dependent/dependent.php',
				'expected'             => false,
			),
			'dependency is not installed' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'dependency_filepaths' => array(),
				'active_plugins'       => array(),
				'plugin_to_check'      => 'dependent/dependent.php',
				'expected'             => true,
			),
			'dependency is inactive' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency' ) ),
				'dependency_filepaths' => array( 'dependency' => 'dependency/dependency.php' ),
				'active_plugins'       => array(),
				'plugin_to_check'      => 'dependent/dependent.php',
				'expected'             => true,
			),
			'one active and one inactive dependency' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency', 'dependency2' ) ),
				'dependency_filepaths' => array(
					'dependency'  => 'dependency/dependency.php',
					'dependency2' => 'dependency2/dependency2.php',
				),
				'active_plugins'       => array( 'dependency/dependency.php' ),
				'plugin_to_check'      => 'dependent/dependent.php',
				'expected'             => true,
			),
			'one active and one uninstalled dependency' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency', 'dependency2' ) ),
				'dependency_filepaths' => array( 'dependency' => 'dependency/dependency.php' ),
				'active_plugins'       => array( 'dependency/dependency.php' ),
				'plugin_to_check'      => 'dependent/dependent.php',
				'expected'             => true,
			),
			'one inactive and one uninstalled dependency' => array(
				'dependencies'         => array( 'dependent/dependent.php' => array( 'dependency', 'dependency2' ) ),
				'dependency_filepaths' => array( 'dependency' => 'dependency/dependency.php' ),
				'active_plugins'       => array(),
				'plugin_to_check'      => 'dependent/dependent.php',
				'expected'             => true,
			),
		);
	}
}
