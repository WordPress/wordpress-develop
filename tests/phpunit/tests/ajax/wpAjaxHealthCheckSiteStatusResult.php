<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests the Ajax handler that persists Site Health check result counts.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.1.0
 *
 * @group ajax
 * @group site-health
 *
 * @covers ::wp_ajax_health_check_site_status_result
 */
class Tests_Ajax_wpAjaxHealthCheckSiteStatusResult extends WP_Ajax_UnitTestCase {

	/**
	 * The Site Health result transient and nonce action name.
	 */
	const TRANSIENT = 'health-check-site-status-result';

	/**
	 * User ID granted super admin privileges during a multisite test.
	 *
	 * @var int
	 */
	private $super_admin_user_id = 0;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();

		// This Ajax action is not part of the core actions registered by the base test case.
		add_action( 'wp_ajax_' . self::TRANSIENT, 'wp_ajax_health_check_site_status_result', 1 );
	}

	/**
	 * Cleans up the test fixture.
	 */
	public function tear_down() {
		if ( is_multisite() && $this->super_admin_user_id ) {
			revoke_super_admin( $this->super_admin_user_id );
			$this->super_admin_user_id = 0;
		}

		parent::tear_down();
	}

	/**
	 * Sets the current user to one that can view Site Health checks.
	 */
	private function set_user_with_site_health_capability() {
		$this->_setRole( 'administrator' );

		if ( is_multisite() ) {
			$this->super_admin_user_id = get_current_user_id();
			grant_super_admin( $this->super_admin_user_id );
		}
	}

	/**
	 * Dispatches the Ajax request and returns the decoded JSON response.
	 *
	 * @return array|null The decoded response, or null when no JSON was returned.
	 */
	private function dispatch_result_request() {
		$this->_last_response = '';

		try {
			$this->_handleAjax( self::TRANSIENT );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * The browser only refreshes the counts, so the full results and timestamp
	 * cached by the scheduled check must be preserved.
	 *
	 * @ticket 65232
	 */
	public function test_refreshing_counts_preserves_cached_results_and_timestamp() {
		$timestamp = 1715714399;
		$results   = array(
			array(
				'test'        => 'fake_critical',
				'label'       => 'Critical label',
				'status'      => 'critical',
				'description' => '<p>Critical description.</p>',
			),
		);

		set_transient(
			self::TRANSIENT,
			wp_json_encode(
				array(
					'good'        => 5,
					'recommended' => 0,
					'critical'    => 1,
					'results'     => $results,
					'timestamp'   => $timestamp,
				)
			)
		);

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::TRANSIENT );
		$_POST['counts']   = array(
			'good'        => 6,
			'recommended' => 2,
			'critical'    => 0,
		);

		$response = $this->dispatch_result_request();

		$this->assertTrue( $response['success'] );

		$cached = json_decode( get_transient( self::TRANSIENT ), true );

		// The aggregate counts are refreshed from the request.
		$this->assertSame( 6, $cached['good'] );
		$this->assertSame( 2, $cached['recommended'] );
		$this->assertSame( 0, $cached['critical'] );

		// The results collected by the scheduled check and their timestamp are kept intact.
		$this->assertSame( $results, $cached['results'], 'Cached results should be preserved.' );
		$this->assertSame( $timestamp, $cached['timestamp'], 'The timestamp should not be changed by a counts update.' );
	}

	/**
	 * When nothing has been cached yet, only the counts are stored.
	 *
	 * @ticket 65232
	 */
	public function test_storing_counts_without_cached_results() {
		delete_transient( self::TRANSIENT );

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::TRANSIENT );
		$_POST['counts']   = array(
			'good'        => 3,
			'recommended' => 1,
			'critical'    => 0,
		);

		$response = $this->dispatch_result_request();

		$this->assertTrue( $response['success'] );

		$cached = json_decode( get_transient( self::TRANSIENT ), true );

		$this->assertSame( 3, $cached['good'] );
		$this->assertSame( 1, $cached['recommended'] );
		$this->assertSame( 0, $cached['critical'] );
		$this->assertArrayNotHasKey( 'results', $cached );
		$this->assertArrayNotHasKey( 'timestamp', $cached );
	}

	/**
	 * Non-array counts are rejected and leave the cached result untouched.
	 *
	 * @ticket 65232
	 */
	public function test_invalid_counts_return_error_and_leave_cache_untouched() {
		$existing = array( 'good' => 9 );
		set_transient( self::TRANSIENT, wp_json_encode( $existing ) );

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::TRANSIENT );
		// No 'counts' are supplied.

		$response = $this->dispatch_result_request();

		$this->assertFalse( $response['success'] );

		$cached = json_decode( get_transient( self::TRANSIENT ), true );
		$this->assertSame( $existing, $cached, 'The cached result should be untouched.' );
	}

	/**
	 * Users without the capability cannot write to the cache.
	 *
	 * @ticket 65232
	 */
	public function test_user_without_capability_cannot_write_cache() {
		delete_transient( self::TRANSIENT );

		$this->_setRole( 'subscriber' );
		$_POST['_wpnonce'] = wp_create_nonce( self::TRANSIENT );
		$_POST['counts']   = array(
			'good'        => 1,
			'recommended' => 1,
			'critical'    => 1,
		);

		$response = $this->dispatch_result_request();

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_transient( self::TRANSIENT ), 'No cache should be written for an unauthorized user.' );
	}
}
