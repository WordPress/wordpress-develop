<?php

/**
 * Tests for the network administration bootstrap.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Tests for the network administration bootstrap.
 */
class Tests_Admin_NetworkAdmin extends WP_UnitTestCase {

	/**
	 * Tests that plugin pages are not loaded on a single site.
	 *
	 * @ticket 38076
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_plugin_pages_are_not_loaded_on_a_single_site() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This test is for single site installations.' );
		}

		$plugin_page_loaded = false;
		add_action(
			'network_admin_menu',
			function () use ( &$plugin_page_loaded ) {
				add_menu_page(
					'Test Plugin Page',
					'Test Plugin Page',
					'manage_options',
					'test-plugin-page',
					function () use ( &$plugin_page_loaded ) {
						$plugin_page_loaded = true;
					}
				);
			}
		);

		$_GET['page'] = 'test-plugin-page';

		try {
			require ABSPATH . 'wp-admin/network/admin.php';
			$this->fail( 'The network administration bootstrap should stop on a single site.' );
		} catch ( WPDieException $exception ) {
			$this->assertSame( 'Multisite support is not enabled.', $exception->getMessage() );
		}

		$this->assertFalse( $plugin_page_loaded );
	}
}
