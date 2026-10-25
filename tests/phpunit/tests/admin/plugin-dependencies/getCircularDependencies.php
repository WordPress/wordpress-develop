<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_circular_dependencies() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_circular_dependencies
 * @covers WP_Plugin_Dependencies::check_for_circular_dependencies
 */
class Tests_Admin_WPPluginDependencies_GetCircularDependencies extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that stored circular dependencies are returned without checking again.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_stored_circular_dependencies() {
		$this->set_dependencies( array( 'a' => array( 'a' ) ) );
		$this->set_property_value( 'circular_dependencies_pairs', array( array( 'x', 'y' ) ) );

		$this->assertSame( array( array( 'x', 'y' ) ), $this->call_method( 'get_circular_dependencies' ), 'The stored pairs should be returned.' );
		$this->assertNull( $this->get_property_value( 'circular_dependencies_slugs' ), 'The slugs should not be computed.' );
	}

	/**
	 * Tests that an empty array is returned before the dependencies are read.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_empty_array_without_dependencies() {
		$this->assertSame( array(), $this->call_method( 'get_circular_dependencies' ), 'No pairs should be returned.' );
		$this->assertNull( $this->get_property_value( 'circular_dependencies_pairs' ), 'The pairs should not be stored.' );
	}

	/**
	 * Tests the circular dependency pairs and slugs found.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_dependencies
	 *
	 * @param array $dependencies The dependency slugs of each plugin, keyed by plugin slug.
	 * @param array $pairs        The expected pairs.
	 * @param array $slugs        The expected slugs, in any order.
	 */
	public function test_should_find_circular_dependencies( $dependencies, $pairs, $slugs ) {
		$this->set_dependencies( $dependencies );

		$this->assertSame( $pairs, $this->call_method( 'get_circular_dependencies' ), 'The pairs should match.' );
		$this->assertSame( $pairs, $this->get_property_value( 'circular_dependencies_pairs' ), 'The pairs should be stored.' );
		$this->assertSameSets( $slugs, array_unique( $this->get_property_value( 'circular_dependencies_slugs' ) ), 'The slugs should match.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_dependencies() {
		return array(
			'no circular dependency'                => array(
				array(
					'a' => array( 'b' ),
					'b' => array( 'c' ),
				),
				array(),
				array(),
			),
			'dependency that is not installed'      => array(
				array( 'a' => array( 'missing' ) ),
				array(),
				array(),
			),
			'plugin that depends on itself'         => array(
				array( 'a' => array( 'a' ) ),
				array( array( 'a', 'a' ) ),
				array( 'a' ),
			),
			'self-dependency next to another one'   => array(
				array(
					'a' => array( 'a', 'b' ),
					'b' => array(),
				),
				array( array( 'a', 'a' ) ),
				array( 'a' ),
			),
			'two plugins that depend on each other' => array(
				array(
					'a' => array( 'b' ),
					'b' => array( 'a' ),
				),
				array( array( 'a', 'b' ), array( 'b', 'a' ) ),
				array( 'a', 'b' ),
			),
			'three plugins in a loop'               => array(
				array(
					'a' => array( 'b' ),
					'b' => array( 'c' ),
					'c' => array( 'a' ),
				),
				array( array( 'a', 'c' ), array( 'b', 'a' ), array( 'c', 'b' ) ),
				array( 'a', 'b', 'c' ),
			),
		);
	}

	/**
	 * Sets the dependencies, keyed by plugin slug, as read from the plugin headers.
	 *
	 * @param array $dependencies The dependency slugs of each plugin, keyed by plugin slug.
	 */
	private function set_dependencies( $dependencies ) {
		$by_file    = array();
		$dependents = array();

		foreach ( $dependencies as $slug => $slugs ) {
			$file = "{$slug}/{$slug}.php";

			$by_file[ $file ]    = $slugs;
			$dependents[ $file ] = $slug;
		}

		$this->set_property_value( 'dependencies', $by_file );
		$this->set_property_value( 'dependent_slugs', $dependents );
	}
}
