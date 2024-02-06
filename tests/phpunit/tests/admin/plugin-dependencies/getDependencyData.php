<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_dependency_data() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_dependency_data
 * @covers WP_Plugin_Dependencies::get_dependency_api_data
 */
class Tests_Admin_WPPluginDependencies_GetDependencyData extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that dependency data is retrieved.
	 *
	 * @ticket 22316
	 *
	 * @global string $pagenow The filename of the current screen.
	 */
	public function test_should_get_dependency_data() {
		global $pagenow;

		// Backup $pagenow.
		$old_pagenow = $pagenow;

		// Ensure is_admin() and screen checks pass.
		$pagenow = 'plugins.php';
		set_current_screen( 'plugins.php' );

		$expected = array( 'name' => 'Dependency 1' );
		$this->set_property_value( 'dependency_api_data', array( 'dependency' => $expected ) );

		$actual = self::$instance::get_dependency_data( 'dependency' );

		// Restore $pagenow.
		$pagenow = $old_pagenow;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that false is returned when no dependency data exists.
	 *
	 * @ticket 22316
	 *
	 * @global string $pagenow The filename of the current screen.
	 */
	public function test_should_return_false_when_no_dependency_data_exists() {
		global $pagenow;

		// Backup $pagenow.
		$old_pagenow = $pagenow;

		// Ensure is_admin() and screen checks pass.
		$pagenow = 'plugins.php';
		set_current_screen( 'plugins.php' );

		$this->set_property_value( 'dependency_api_data', array() );

		$actual = self::$instance::get_dependency_data( 'dependency' );

		// Restore $pagenow.
		$pagenow = $old_pagenow;

		$this->assertFalse( $actual );
	}
}
