<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_toggle_auto_updates() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.5.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_toggle_auto_updates
 */
class Tests_wp_ajax_toggle_auto_updates extends WP_Ajax_UnitTestCase {

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

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	public function set_up(): void {
		parent::set_up();
		add_action( 'wp_ajax_toggle-auto-updates', 'wp_ajax_toggle_auto_updates', 1 );

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
	 * Tests success for toggling plugin auto-updates.
	 *
	 * @ticket 65252
	 */
	public function test_toggle_auto_updates_plugin_success(): void {
		wp_set_current_user( self::$admin_id );

		$plugin               = 'hello.php'; // Standard WP plugin.
		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['type']        = 'plugin';
		$_POST['asset']       = $plugin;
		$_POST['state']       = 'enable';

		try {
			$this->_handleAjax( 'toggle-auto-updates' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertContains( $plugin, get_site_option( 'auto_update_plugins', array() ), 'Plugin should be in auto-update list' );

		// Now disable it.
		$this->_last_response = '';
		$_POST['state']       = 'disable';

		try {
			$this->_handleAjax( 'toggle-auto-updates' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertNotContains( $plugin, get_site_option( 'auto_update_plugins', array() ), 'Plugin should not be in auto-update list' );
	}

	/**
	 * Tests success for toggling theme auto-updates.
	 *
	 * @ticket 65252
	 */
	public function test_toggle_auto_updates_theme_success(): void {
		wp_set_current_user( self::$admin_id );

		$theme                = 'twentytwentyone';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['type']        = 'theme';
		$_POST['asset']       = $theme;
		$_POST['state']       = 'enable';

		try {
			$this->_handleAjax( 'toggle-auto-updates' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertContains( $theme, get_site_option( 'auto_update_themes', array() ), 'Theme should be in auto-update list' );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_toggle_auto_updates().
	 *
	 * @ticket 65252
	 */
	public function test_toggle_auto_updates_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = 'invalid-nonce';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'toggle-auto-updates' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_toggle_auto_updates().
	 *
	 * @ticket 65252
	 */
	public function test_toggle_auto_updates_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['type']        = 'plugin';
		$_POST['asset']       = 'hello.php';
		$_POST['state']       = 'enable';

		try {
			$this->_handleAjax( 'toggle-auto-updates' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'Sorry, you are not allowed to modify plugins.', $response['data']['error'] );
	}

	/**
	 * Tests failure with missing parameters for wp_ajax_toggle_auto_updates().
	 *
	 * @ticket 65252
	 */
	public function test_toggle_auto_updates_missing_parameters(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		// Missing 'type', 'asset', 'state'.

		try {
			$this->_handleAjax( 'toggle-auto-updates' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'Invalid data. No selected item.', $response['data']['error'] );
	}
}
