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
 * @since 7.3.0
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
	 * The request stores both parts in one write, without deriving latest-run counts from details.
	 *
	 * @ticket 65232
	 */
	public function test_posting_counts_and_results_caches_them_together(): void {
		delete_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['counts']   = array(
			'good'        => 5,
			'recommended' => 1,
			'critical'    => 2,
		);
		$_POST['results']  = wp_slash( wp_json_encode( array( 'a' => array( 'status' => 'critical' ) ) ) );

		$writes_before = did_action( 'set_transient_' . WP_Site_Health::STATUS_RESULT_TRANSIENT );
		$this->assertTrue( $this->dispatch_result_request()['success'] );
		$this->assertSame( 1, did_action( 'set_transient_' . WP_Site_Health::STATUS_RESULT_TRANSIENT ) - $writes_before );
		$this->assertSame( $_POST['counts'], WP_Site_Health::get_site_status_counts() );
		$detail = WP_Site_Health::get_site_status_detail();
		$this->assertSame( array( 'a' ), array_keys( $detail['results'] ) );
		$this->assertSame( 'critical', $detail['results']['a']['status'] );
		$this->assertSame(
			array(
				'good'        => 0,
				'recommended' => 0,
				'critical'    => 1,
			),
			$detail['counts']
		);
	}

	/**
	 * Posting only the results updates the detailed cache without changing the latest-run counts.
	 *
	 * @ticket 65232
	 */
	public function test_posting_results_only_updates_detail_without_counts(): void {
		delete_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['results']  = wp_slash( wp_json_encode( array( 'a' => array( 'status' => 'critical' ) ) ) );

		$response = $this->dispatch_result_request();

		$this->assertTrue( $response['success'] );

		// The detailed cache is updated.
		$results = WP_Site_Health::get_site_status_detail()['results'];
		$this->assertSame( array( 'a' ), array_keys( $results ) );

		// Results-only requests preserve the default counts.
		$this->assertSame(
			array(
				'good'        => 0,
				'recommended' => 0,
				'critical'    => 0,
			),
			WP_Site_Health::get_site_status_counts()
		);
	}

	/**
	 * Posting only counts refreshes the counts and leaves verified results intact.
	 *
	 * @ticket 65232
	 */
	public function test_posting_counts_only_leaves_detail_intact(): void {
		WP_Site_Health::update_site_status_detail( array( 'seeded' => array( 'status' => 'good' ) ) );
		$detail = WP_Site_Health::get_site_status_detail();

		$this->set_user_with_site_health_capability();
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['counts']   = array(
			'good'        => 3,
			'recommended' => 0,
			'critical'    => 0,
		);

		$response = $this->dispatch_result_request();

		$this->assertTrue( $response['success'] );

		$this->assertSame(
			array(
				'good'        => 3,
				'recommended' => 0,
				'critical'    => 0,
			),
			WP_Site_Health::get_site_status_counts()
		);

		$this->assertSame( $detail, WP_Site_Health::get_site_status_detail(), 'Verified results and timestamps should be untouched.' );
	}

	/**
	 * Users without the capability cannot write to the cache.
	 *
	 * @ticket 65232
	 */
	public function test_user_without_capability_cannot_write_cache(): void {
		delete_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );

		$this->_setRole( 'subscriber' );
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$_POST['counts']   = array(
			'good'        => 1,
			'recommended' => 1,
			'critical'    => 1,
		);
		$_POST['results']  = wp_slash( wp_json_encode( array( 'a' => array( 'status' => 'critical' ) ) ) );

		$response = $this->dispatch_result_request();

		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT ) );
	}

	/**
	 * Invalid requests must not partially overwrite the shared cache.
	 *
	 * @ticket 65232
	 * @dataProvider data_invalid_cache_requests
	 *
	 * @param array       $request    Submitted fields, excluding the nonce.
	 * @param string|null $error_code Expected validation error code, if provided.
	 */
	public function test_invalid_request_preserves_existing_cache( array $request, ?string $error_code ): void {
		$this->set_user_with_site_health_capability();
		WP_Site_Health::update_site_status_detail(
			array( 'existing' => array( 'status' => 'good' ) ),
			array(
				'good'        => 1,
				'recommended' => 0,
				'critical'    => 0,
			)
		);
		$before            = get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT );
		$_POST             = wp_slash( $request );
		$_POST['_wpnonce'] = wp_create_nonce( self::ACTION );
		$response          = $this->dispatch_result_request();
		$this->assertFalse( $response['success'] );
		if ( null !== $error_code ) {
			$this->assertSame( $error_code, $response['data'][0]['code'] );
		}
		$this->assertSame( $before, get_transient( WP_Site_Health::STATUS_RESULT_TRANSIENT ) );
	}

	/**
	 * @return array Named invalid requests and their expected error codes.
	 */
	public static function data_invalid_cache_requests(): array {
		return array(
			'no fields'                         => array( array(), null ),
			'invalid counts with valid results' => array(
				array(
					'counts'  => array( 'good' => -1 ),
					'results' => '{"new":{"status":"critical"}}',
				),
				'rest_out_of_bounds',
			),
			'valid counts with malformed JSON'  => array(
				array(
					'counts'  => array( 'good' => 2 ),
					'results' => '{invalid json',
				),
				null,
			),
			'valid counts with invalid status'  => array(
				array(
					'counts'  => array( 'good' => 2 ),
					'results' => '{"new":{"status":"invalid"}}',
				),
				'rest_not_in_enum',
			),
		);
	}
}
