<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax handler for checking plugin dependencies.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.2.0
 *
 * @group ajax
 * @group ms-required
 *
 * @covers WP_Plugin_Dependencies::check_plugin_dependencies_during_ajax
 */
class Tests_Ajax_wpAjaxCheckPluginDependencies extends WP_Ajax_UnitTestCase {

	/**
	 * A user with super admin privileges.
	 */
	protected static int $super_admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$super_admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( self::$super_admin_id );
	}

	public function set_up() {
		parent::set_up();
		add_action( 'wp_ajax_check_plugin_dependencies', array( 'WP_Plugin_Dependencies', 'check_plugin_dependencies_during_ajax' ) );
		wp_set_current_user( self::$super_admin_id );
	}

	/**
	 * Runs the Ajax handler for the Hello Dolly plugin and returns the decoded response.
	 *
	 * @return array The decoded response.
	 */
	protected function check_hello_dolly_dependencies(): array {
		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['slug']        = 'hello-dolly';

		try {
			$this->_handleAjax( 'check_plugin_dependencies' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * @ticket 66346
	 */
	public function test_network_active_plugin_should_not_have_activate_url() {
		update_site_option( 'active_sitewide_plugins', array( 'hello.php' => time() ) );

		$response = $this->check_hello_dolly_dependencies();

		$this->assertTrue( $response['success'], 'The request should succeed.' );
		$this->assertArrayNotHasKey( 'activateUrl', $response['data'], 'An active plugin should not have an activation URL.' );
	}

	/**
	 * @ticket 66346
	 */
	public function test_inactive_plugin_should_have_network_activate_url() {
		update_site_option( 'active_sitewide_plugins', array() );

		$response = $this->check_hello_dolly_dependencies();

		$this->assertTrue( $response['success'], 'The request should succeed.' );
		$this->assertArrayHasKey( 'activateUrl', $response['data'], 'An inactive plugin should have an activation URL.' );
		$this->assertStringContainsString( 'networkwide=1', $response['data']['activateUrl'], 'The activation URL should activate the plugin network-wide.' );
	}
}
