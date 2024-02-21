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

	/**
	 * Tests that a 'slug' key in the Plugins API response object is not assumed.
	 *
	 * @ticket 60540
	 */
	public function test_should_not_assume_a_slug_key_exists_in_the_response() {
		global $pagenow;

		// Backup $pagenow.
		$old_pagenow = $pagenow;

		// Ensure is_admin() and screen checks pass.
		$pagenow = 'plugins.php';
		set_current_screen( 'plugins.php' );

		add_filter(
			'plugins_api',
			static function ( $bypass, $action, $args ) {
				if ( 'plugin_information' === $action && isset( $args->slug ) && 'dependency' === $args->slug ) {
					$bypass = (object) array( 'name' => 'Dependency 1' );
				}
				return $bypass;
			},
			10,
			3
		);

		$this->set_property_value(
			'plugins',
			array(
				'dependent/dependent.php' => array(
					'Name'            => 'Dependent',
					'RequiresPlugins' => 'dependency',
				),
			)
		);

		self::$instance->initialize();

		$actual = $this->get_property_value( 'dependency_api_data' );

		// Restore $pagenow.
		$pagenow = $old_pagenow;

		$this->assertSame(
			array(
				'dependency' => array(
					'name'     => 'Dependency 1',
					'external' => true,
					'Name'     => 'Dependency 1',
				),
			),
			$actual
		);
	}
}
