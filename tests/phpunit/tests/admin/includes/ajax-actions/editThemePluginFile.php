<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_edit_theme_plugin_file() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.9.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_edit_theme_plugin_file
 */
class Tests_wp_ajax_edit_theme_plugin_file extends WP_Ajax_UnitTestCase {

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
		add_action( 'wp_ajax_edit-theme-plugin-file', 'wp_ajax_edit_theme_plugin_file', 1 );

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
	 * Tests failure with missing parameters for wp_ajax_edit_theme_plugin_file().
	 *
	 * @ticket 65252
	 */
	public function test_edit_theme_plugin_file_missing_params(): void {
		wp_set_current_user( self::$admin_id );

		try {
			$this->_handleAjax( 'edit-theme-plugin-file' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'missing_file', $response['data']['code'] );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_edit_theme_plugin_file().
	 *
	 * @ticket 65252
	 */
	public function test_edit_theme_plugin_file_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['file']       = 'style.css';
		$_POST['theme']      = 'twentytwentyone';
		$_POST['newcontent'] = '/* test */';
		$_POST['nonce']      = 'invalid-nonce';

		try {
			$this->_handleAjax( 'edit-theme-plugin-file' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'nonce_failure', $response['data']['code'] );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_edit_theme_plugin_file().
	 *
	 * @ticket 65252
	 */
	public function test_edit_theme_plugin_file_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['file']       = 'style.css';
		$_POST['theme']      = 'twentytwentyone';
		$_POST['newcontent'] = '/* test */';
		$_POST['nonce']      = wp_create_nonce( 'edit-theme_twentytwentyone_style.css' );

		try {
			$this->_handleAjax( 'edit-theme-plugin-file' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'unauthorized', $response['data']['code'] );
	}
}
