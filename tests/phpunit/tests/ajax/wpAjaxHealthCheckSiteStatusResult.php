<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';

/**
 * Tests the Ajax handler that persists Site Health check results.
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
	 * The nonce action used by the Site Health result Ajax request.
	 */
	const ACTION = 'health-check-site-status-result';

	/**
	 * User ID granted super admin privileges during a multisite test.
	 */
	private int $super_admin_user_id = 0;

	/**
	 * Sets up the test fixture.
	 */
	public function set_up(): void {
		parent::set_up();

		// This Ajax action is not part of the core actions registered by the base test case.
		add_action( 'wp_ajax_' . self::ACTION, 'wp_ajax_health_check_site_status_result', 1 );
	}

	/**
	 * Cleans up the test fixture.
	 */
	public function tear_down(): void {
		if ( is_multisite() && $this->super_admin_user_id ) {
			revoke_super_admin( $this->super_admin_user_id );
			$this->super_admin_user_id = 0;
		}

		parent::tear_down();
	}

	/**
	 * Sets the current user to one that can view Site Health checks.
	 */
	private function set_user_with_site_health_capability(): void {
		$this->_setRole( 'administrator' );

		if ( is_multisite() ) {
			$this->super_admin_user_id = get_current_user_id();
			grant_super_admin( $this->super_admin_user_id );
		}
	}

	/**
	 * Dispatches the Ajax request and returns the decoded JSON response.
	 *
	 * @return array{ success: bool, ... } The decoded response.
	 */
	private function dispatch_result_request(): array {
		$this->_last_response = '';

		try {
			$this->_handleAjax( self::ACTION );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		assert( is_array( $response ) );
		return $response;
	}

	/**
	 * The aggregate counts are cached on their own, while the results submitted by
	 * the Site Health screen are sanitized and cached separately.
	 *
	 * @ticket 65232
	 */
	public function test_posting_counts_and_results_caches_them_separately(): void {
		delete_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		delete_transient( WP_Site_Health::STATUS_DETAIL_TRANSIENT );

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['counts']   = array(
			'good'        => 5,
			'recommended' => 1,
			'critical'    => 2,
		);
		$_POST['results']  = wp_slash(
			wp_json_encode(
				array(
					'a' => array(
						'status' => 'critical',
					),
					'b' => array(
						'status' => 'recommended',
					),
					'c' => array(
						'status' => 'good',
					),
					'd' => array(
						// An unrecognized status is rejected.
						'status' => 'bogus',
					),
				)
			)
		);

		$response = $this->dispatch_result_request();
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'rest_not_in_enum', $response['data'][0]['code'] );

		$_POST['results'] = wp_slash(
			wp_json_encode(
				array(
					'a' => array(
						'status' => 'critical',
					),
					'b' => array(
						'status' => 'recommended',
					),
					'c' => array(
						'status' => 'good',
					),
				)
			)
		);

		$response = $this->dispatch_result_request();
		$this->assertTrue( $response['success'] );

		// The counts transient holds only the aggregate counts.
		$transient = get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		$this->assertIsString( $transient );
		$this->assertSame(
			array(
				'good'        => 5,
				'recommended' => 1,
				'critical'    => 2,
			),
			json_decode( $transient, true )
		);

		// Only results with a recognized status are cached.
		$results = WP_Site_Health::get_site_status_detail()['results'];
		$this->assertSame( array( 'a', 'b', 'c' ), array_keys( $results ) );

		// Only locale-independent fields are cached: the test name, status, and timestamp.
		$this->assertSameSets(
			array( 'status', 'timestamp' ),
			array_keys( $results['a'] ),
			'No translated or HTML fields should be cached.'
		);
		$this->assertSame( 'critical', $results['a']['status'] );

		// Each cached result carries a timestamp.
		$this->assertIsInt( $results['a']['timestamp'] );
		$this->assertGreaterThan( 0, $results['a']['timestamp'] );

		// The detail cache exposes counts derived from its own (sanitized) results.
		$this->assertSame(
			array(
				'good'        => 1,
				'recommended' => 1,
				'critical'    => 1,
			),
			WP_Site_Health::get_site_status_detail()['counts']
		);
	}

	/**
	 * Posting only the results updates the detailed cache without touching the counts cache.
	 *
	 * @ticket 65232
	 */
	public function test_posting_results_only_updates_detail_without_counts(): void {
		delete_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		delete_transient( WP_Site_Health::STATUS_DETAIL_TRANSIENT );

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['results']  = wp_slash(
			wp_json_encode(
				array(
					'a' => array(
						'status' => 'critical',
					),
				)
			)
		);

		$response = $this->dispatch_result_request();

		$this->assertTrue( $response['success'] );

		// The detailed cache is updated.
		$results = WP_Site_Health::get_site_status_detail()['results'];
		$this->assertSame( array( 'a' ), array_keys( $results ) );

		// The counts cache is not written by a results-only request.
		$this->assertFalse( get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT ) );
	}

	/**
	 * Posting only counts refreshes the counts cache and leaves the detailed cache intact.
	 *
	 * @ticket 65232
	 */
	public function test_posting_counts_only_leaves_detail_intact(): void {
		$now = time();
		set_transient(
			WP_Site_Health::STATUS_DETAIL_TRANSIENT,
			wp_json_encode(
				array(
					'results'   => array(
						'seeded' => array(
							'status'    => 'good',
							'timestamp' => $now,
						),
					),
					'timestamp' => $now,
				)
			),
			MONTH_IN_SECONDS
		);

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['counts']   = array(
			'good'        => 3,
			'recommended' => 0,
			'critical'    => 0,
		);

		$response = $this->dispatch_result_request();

		$this->assertTrue( $response['success'] );

		$transient = get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		$this->assertIsString( $transient );
		$this->assertSame(
			array(
				'good'        => 3,
				'recommended' => 0,
				'critical'    => 0,
			),
			json_decode( $transient, true )
		);

		$results = WP_Site_Health::get_site_status_detail()['results'];
		$this->assertSame( array( 'seeded' ), array_keys( $results ), 'The detailed cache should be untouched.' );
	}

	/**
	 * Non-array counts are rejected and leave both caches untouched.
	 *
	 * @ticket 65232
	 */
	public function test_invalid_counts_return_error_and_write_nothing(): void {
		$existing = array( 'good' => 9 );
		set_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT, wp_json_encode( $existing ) );

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		// No 'counts' are supplied.

		$response = $this->dispatch_result_request();

		$this->assertFalse( $response['success'] );

		$transient = get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		$this->assertIsString( $transient );
		$this->assertSame( $existing, json_decode( $transient, true ), 'The counts cache should be untouched.' );
		$this->assertFalse( get_transient( WP_Site_Health::STATUS_DETAIL_TRANSIENT ) );
	}

	/**
	 * Users without the capability cannot write to either cache.
	 *
	 * @ticket 65232
	 */
	public function test_user_without_capability_cannot_write_cache(): void {
		delete_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		delete_transient( WP_Site_Health::STATUS_DETAIL_TRANSIENT );

		$this->_setRole( 'subscriber' );
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['counts']   = array(
			'good'        => 1,
			'recommended' => 1,
			'critical'    => 1,
		);
		$_POST['results']  = wp_slash(
			wp_json_encode(
				array(
					array(
						'test'   => 'a',
						'label'  => 'A',
						'status' => 'critical',
					),
				)
			)
		);

		$response = $this->dispatch_result_request();

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT ) );
		$this->assertFalse( get_transient( WP_Site_Health::STATUS_DETAIL_TRANSIENT ) );
	}
}
