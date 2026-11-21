<?php

/**
 * Tests for wp_clean_plugins_cache().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::wp_clean_plugins_cache
 */
class Tests_Admin_Includes_Plugin_WpCleanPluginsCache extends WP_UnitTestCase {

	/**
	 * Sample plugins cache payload.
	 *
	 * @var array
	 */
	private $plugins_cache_data;

	/**
	 * Sample update_plugins site transient payload.
	 *
	 * @var object
	 */
	private $update_plugins_data;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->plugins_cache_data = array(
			'' => array(
				'hello.php' => array(
					'Name' => 'Hello Dolly',
				),
			),
		);

		$this->update_plugins_data = (object) array(
			'last_checked' => time(),
			'response'     => array(),
		);

		wp_cache_set( 'plugins', $this->plugins_cache_data, 'plugins' );
		set_site_transient( 'update_plugins', $this->update_plugins_data );
	}

	public function tear_down() {
		wp_cache_delete( 'plugins', 'plugins' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_clears_plugins_cache_and_update_transient_by_default() {
		$this->assertSame(
			$this->plugins_cache_data,
			wp_cache_get( 'plugins', 'plugins' ),
			'Plugins cache should be primed before the call.'
		);
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			$this->update_plugins_data,
			get_site_transient( 'update_plugins' ),
			'update_plugins transient should be primed before the call.'
		);

		wp_clean_plugins_cache();

		$this->assertFalse(
			wp_cache_get( 'plugins', 'plugins' ),
			'Plugins object cache should be cleared.'
		);
		$this->assertFalse(
			get_site_transient( 'update_plugins' ),
			'update_plugins site transient should be cleared by default.'
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_keeps_update_transient_when_clear_update_cache_is_false() {
		wp_clean_plugins_cache( false );

		$this->assertFalse(
			wp_cache_get( 'plugins', 'plugins' ),
			'Plugins object cache should still be cleared.'
		);
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals(
			$this->update_plugins_data,
			get_site_transient( 'update_plugins' ),
			'update_plugins site transient should be kept when $clear_update_cache is false.'
		);
	}
}
