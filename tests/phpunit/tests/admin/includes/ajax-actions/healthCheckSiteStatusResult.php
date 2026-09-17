<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_health_check_site_status_result() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.2.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_health_check_site_status_result
 */
class Tests_wp_ajax_health_check_site_status_result extends WP_Ajax_UnitTestCase {

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
		add_action( 'wp_ajax_health-check-site-status-result', 'wp_ajax_health_check_site_status_result', 1 );

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
	 * Tests success for wp_ajax_health_check_site_status_result().
	 *
	 * @ticket 65252
	 */
	public function test_health_check_site_status_result_success(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'health-check-site-status-result' );
		$_POST['counts']      = array(
			'good'        => 1,
			'recommended' => 2,
			'critical'    => 3,
		);

		try {
			$this->_handleAjax( 'health-check-site-status-result' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertTrue( $response['success'], 'AJAX response should be successful' );

		$transient = get_transient( 'health-check-site-status-result' );
		$this->assertNotFalse( $transient, 'Transient should be set' );
		$this->assertEquals( wp_json_encode( $_POST['counts'] ), $transient, 'Transient value should match POST data' );
	}

	/**
	 * Tests failure with invalid nonce for wp_ajax_health_check_site_status_result().
	 *
	 * @ticket 65252
	 */
	public function test_health_check_site_status_result_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST['_ajax_nonce'] = 'invalid-nonce';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'health-check-site-status-result' );
	}

	/**
	 * Tests failure with insufficient permissions for wp_ajax_health_check_site_status_result().
	 *
	 * @ticket 65252
	 */
	public function test_health_check_site_status_result_insufficient_permissions(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['_ajax_nonce'] = wp_create_nonce( 'health-check-site-status-result' );

		try {
			$this->_handleAjax( 'health-check-site-status-result' );
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertFalse( $response['success'], 'AJAX response should be unsuccessful' );
	}
}
