<?php
/**
 * Tests for the WP_Plugin_Dependencies::get_plugins() method.
 *
 * @package WP_Plugin_Dependencies
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::get_plugins
 */
class Tests_Admin_WPPluginDependencies_GetPlugins extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that `::get_plugins()` sets the `$plugins` property.
	 */
	public function test_get_plugins_should_return_an_array_of_plugin_data() {
		$get_plugins = $this->make_method_accessible( 'get_plugins' );
		$get_plugins->invoke( self::$instance );
		$get_plugins->setAccessible( false );
		$actual = $this->get_property_value( 'plugins' );

		$this->assertIsArray( $actual, 'Did not return an array.' );
		$this->assertNotEmpty( $actual, 'The plugin data array is empty.' );
	}

}
