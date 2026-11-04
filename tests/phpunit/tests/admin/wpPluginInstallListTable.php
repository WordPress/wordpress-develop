<?php

/**
 * @group admin
 *
 * @covers WP_Plugin_Install_List_Table
 */
class Tests_Admin_wpPluginInstallListTable extends WP_UnitTestCase {
	/**
	 * @var WP_Plugin_Install_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Plugin_Install_List_Table', array( 'screen' => 'plugin-install' ) );
	}

	public function tear_down() {
		delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Plugin_Install_List_Table::get_views
	 */
	public function test_get_views_should_return_no_views_by_default() {
		$this->assertSame( array(), $this->table->get_views() );
	}

	/**
	 * @ticket 33330
	 *
	 * @covers WP_Plugin_Install_List_Table::get_installed_plugins
	 */
	public function test_get_installed_plugins_should_return_empty_array_by_default() {
		$this->assertSame( array(), $this->call_protected_method( 'get_installed_plugins' ) );
	}

	/**
	 * @ticket 33330
	 *
	 * @covers WP_Plugin_Install_List_Table::get_installed_plugins
	 */
	public function test_get_installed_plugins_should_key_results_by_slug_and_flag_upgrade_availability() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'hello-dolly/hello.php' => (object) array( 'slug' => 'hello-dolly' ),
				),
				'response'  => array(
					'akismet/akismet.php' => (object) array( 'slug' => 'akismet' ),
				),
			)
		);

		$installed_plugins = $this->call_protected_method( 'get_installed_plugins' );

		$this->assertSame( array( 'hello-dolly', 'akismet' ), array_keys( $installed_plugins ) );
		$this->assertFalse( $installed_plugins['hello-dolly']->upgrade, 'A plugin in no_update should not be flagged for upgrade.' );
		$this->assertTrue( $installed_plugins['akismet']->upgrade, 'A plugin in response should be flagged for upgrade.' );
	}

	/**
	 * @ticket 33330
	 *
	 * @covers WP_Plugin_Install_List_Table::get_installed_plugins
	 */
	public function test_get_installed_plugins_should_skip_entries_missing_a_slug() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'malformed/malformed.php' => (object) array( 'Name' => 'Malformed Plugin' ),
				),
				'response'  => array(
					'akismet/akismet.php'     => (object) array( 'slug' => 'akismet' ),
					'malformed/malformed.php' => (object) array( 'Name' => 'Malformed Plugin' ),
				),
			)
		);

		$this->assertSame( array( 'akismet' ), array_keys( $this->call_protected_method( 'get_installed_plugins' ) ) );
	}

	/**
	 * @ticket 33330
	 *
	 * @covers WP_Plugin_Install_List_Table::get_installed_plugin_slugs
	 */
	public function test_get_installed_plugin_slugs_should_match_keys_of_get_installed_plugins() {
		set_site_transient(
			'update_plugins',
			(object) array(
				'no_update' => array(
					'hello-dolly/hello.php' => (object) array( 'slug' => 'hello-dolly' ),
				),
			)
		);

		$this->assertSame( array( 'hello-dolly' ), $this->call_protected_method( 'get_installed_plugin_slugs' ) );
	}

	/**
	 * Calls a protected WP_Plugin_Install_List_Table method on $this->table.
	 *
	 * @param string $method Method name.
	 * @return mixed Return value of the method.
	 */
	private function call_protected_method( $method ) {
		$reflection_method = new ReflectionMethod( $this->table, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_method->setAccessible( true );
		}

		return $reflection_method->invoke( $this->table );
	}
}
