<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_activate_plugin() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.0.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_activate_plugin
 */
class Tests_wp_ajax_activate_plugin extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_activate-plugin', 'wp_ajax_activate_plugin', 1 );

		// Hook into wp_die to prevent execution from stopping.
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
	}

	public function tear_down(): void {
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ) );
		parent::tear_down();
	}

	/**
	 * Returns our custom die handler.
	 *
	 * @return callable
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Custom die handler that throws an exception.
	 *
	 * @param string|WP_Error $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				$this->_last_response = (string) $message;
			} else {
				$this->_last_response = '0';
			}
		}

		if ( '-1' === $this->_last_response || ( is_int( $message ) && -1 === $message ) ) {
			throw new WPAjaxDieStopException( $this->_last_response );
		}

		throw new WPAjaxDieContinueException( $this->_last_response );
	}

	/**
	 * Tests success for wp_ajax_activate_plugin().
	 *
	 * @ticket 65252
	 */
	public function test_activate_plugin_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['name']        = 'Hello Dolly';
		$_POST['slug']        = 'hello-dolly';
		$_POST['plugin']      = 'hello.php';

		// hello-dolly is a default plugin.
		try {
			$this->_handleAjax( 'activate-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertTrue( is_plugin_active( 'hello.php' ), 'Plugin should be active' );

		// Clean up.
		deactivate_plugins( 'hello.php' );
	}

	/**
	 * Tests failure with missing plugin for wp_ajax_activate_plugin().
	 *
	 * @ticket 65252
	 */
	public function test_activate_plugin_missing_plugin(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		// No plugin.

		try {
			$this->_handleAjax( 'activate-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_activate_plugin().
	 *
	 * @ticket 65252
	 */
	public function test_activate_plugin_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['name']        = 'Hello Dolly';
		$_POST['slug']        = 'hello-dolly';
		$_POST['plugin']      = 'hello.php';

		try {
			$this->_handleAjax( 'activate-plugin' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertStringContainsString( 'not allowed to activate plugins', $response['data']['errorMessage'] );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_activate_plugin().
	 *
	 * @ticket 65252
	 */
	public function test_activate_plugin_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = 'invalid-nonce';
		$_POST['name']        = 'Hello Dolly';
		$_POST['slug']        = 'hello-dolly';
		$_POST['plugin']      = 'hello.php';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'activate-plugin' );
	}
}
