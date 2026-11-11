<?php

/**
 * Tests for Plugin_Upgrader::plugin_info().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Upgrader::plugin_info
 */
class Tests_Admin_PluginUpgraderPluginInfo extends WP_UnitTestCase {

	/**
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Clears the plugins cache.
	 */
	public function tear_down() {
		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that false is returned without a usable upgrade result.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unusable_results
	 *
	 * @param mixed $result The upgrade result.
	 */
	public function test_should_return_false_without_destination( $result ) {
		wp_cache_set(
			'plugins',
			array(
				'/0' => array( 'main.php' => array( 'Name' => 'Zero Directory Plugin' ) ),
			),
			'plugins'
		);

		$this->assertFalse( $this->get_upgrader( $result )->plugin_info() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unusable_results() {
		return array(
			'no result'              => array( null ),
			'false'                  => array( false ),
			'error'                  => array( new WP_Error( 'install_failed', 'Failed.' ) ),
			'no destination name'    => array( array( 'destination' => '/plugins/my-plugin/' ) ),
			'empty destination name' => array( array( 'destination_name' => '' ) ),
			'zero destination name'  => array( array( 'destination_name' => '0' ) ),
		);
	}

	/**
	 * Tests that false is returned when the installed folder holds no plugin.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_false_when_folder_has_no_plugin() {
		wp_cache_set( 'plugins', array( '/my-plugin' => array() ), 'plugins' );

		$this->assertFalse( $this->get_upgrader( array( 'destination_name' => 'my-plugin' ) )->plugin_info() );
	}

	/**
	 * Tests that the first plugin file in the installed folder is returned.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_first_plugin_file_in_folder() {
		wp_cache_set(
			'plugins',
			array(
				'/my-plugin' => array(
					'main.php'  => array( 'Name' => 'Main' ),
					'other.php' => array( 'Name' => 'Other' ),
				),
			),
			'plugins'
		);

		$this->assertSame( 'my-plugin/main.php', $this->get_upgrader( array( 'destination_name' => 'my-plugin' ) )->plugin_info() );
	}

	/**
	 * Tests that the plugin of a real installed folder is found.
	 *
	 * @ticket 65819
	 */
	public function test_should_find_plugin_in_installed_folder() {
		$this->assertSame(
			'custom-internationalized-plugin/custom-internationalized-plugin.php',
			$this->get_upgrader( array( 'destination_name' => 'custom-internationalized-plugin' ) )->plugin_info()
		);
	}

	/**
	 * Returns an upgrader with the given result.
	 *
	 * @param mixed $result The upgrade result.
	 * @return Plugin_Upgrader The upgrader.
	 */
	private function get_upgrader( $result ) {
		$upgrader         = new Plugin_Upgrader( $this->createMock( 'WP_Upgrader_Skin' ) );
		$upgrader->result = $result;

		return $upgrader;
	}
}
