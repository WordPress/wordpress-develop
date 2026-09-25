<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_save_wporg_username() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.4.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_save_wporg_username
 */
class Tests_wp_ajax_save_wporg_username extends WP_Ajax_UnitTestCase {

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
		add_action( 'wp_ajax_save-wporg-username', 'wp_ajax_save_wporg_username', 1 );

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
	 * Tests success for wp_ajax_save_wporg_username().
	 *
	 * @ticket 65252
	 */
	public function test_save_wporg_username_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'save_wporg_username_' . self::$admin_id );
		$_POST['username']    = 'wordpress_user';

		try {
			$this->_handleAjax( 'save-wporg-username' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertEquals( 'wordpress_user', get_user_meta( self::$admin_id, 'wporg_favorites', true ), 'WordPress.org username should be saved in user meta' );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_save_wporg_username().
	 *
	 * @ticket 65252
	 */
	public function test_save_wporg_username_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = 'invalid-nonce';
		$_POST['username']    = 'wordpress_user';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'save-wporg-username' );
	}

	/**
	 * Tests failure with missing username for wp_ajax_save_wporg_username().
	 *
	 * @ticket 65252
	 */
	public function test_save_wporg_username_missing_username(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'save_wporg_username_' . self::$admin_id );
		// No username.

		try {
			$this->_handleAjax( 'save-wporg-username' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
