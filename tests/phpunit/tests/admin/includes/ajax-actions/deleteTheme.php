<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_theme() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.6.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_theme
 */
class Tests_wp_ajax_delete_theme extends WP_Ajax_UnitTestCase {

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
		add_action( 'wp_ajax_delete-theme', 'wp_ajax_delete_theme', 1 );

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
	 * Tests failure with missing slug for wp_ajax_delete_theme().
	 *
	 * @ticket 65252
	 */
	public function test_delete_theme_missing_slug(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		// No slug.

		try {
			$this->_handleAjax( 'delete-theme' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertEquals( 'no_theme_specified', $response['data']['errorCode'] );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_delete_theme().
	 *
	 * @ticket 65252
	 */
	public function test_delete_theme_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'updates' );
		$_POST['slug']        = 'twentytwentyone';

		try {
			$this->_handleAjax( 'delete-theme' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
		$this->assertStringContainsString( 'not allowed to delete themes', $response['data']['errorMessage'] );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_delete_theme().
	 *
	 * @ticket 65252
	 */
	public function test_delete_theme_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = 'invalid-nonce';
		$_POST['slug']        = 'twentytwentyone';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'delete-theme' );
	}
}
