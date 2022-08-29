<?php

/**
 * @group site-health
 */
class Tests_Site_Health extends WP_UnitTestCase {
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Include the `WP_Site_Health` file.
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	}

	/**
	 * Ensure Site Health reports correctly cron job reports.
	 *
	 * @ticket 47223
	 */
	public function test_cron_health_checks_critical() {
		$wp_site_health = new WP_Site_Health();

		// Clear the cron array.
		_set_cron_array( array() );

		$cron_health = $wp_site_health->get_test_scheduled_events();

		$this->assertSame( 'critical', $cron_health['status'] );
		$this->assertSame( __( 'It was not possible to check your scheduled events' ), $cron_health['label'] );
		$this->assertWPError( $wp_site_health->has_late_cron() );
		$this->assertWPError( $wp_site_health->has_missed_cron() );
	}

	/**
	 * Ensure Site Health reports correctly cron job reports.
	 *
	 * @dataProvider data_cron_health_checks
	 * @ticket 47223
	 */
	public function test_cron_health_checks( $times, $expected_status, $expected_label, $expected_late, $expected_missed ) {
		$wp_site_health = new WP_Site_Health();

		/*
		 * Clear the cron array.
		 *
		 * The core jobs may register as late/missed in the test suite as they
		 * are not run. Clearing the array ensures the site health tests are only
		 * reported based on the jobs set in the test.
		 */
		_set_cron_array( array() );
		$times = (array) $times;
		foreach ( $times as $job => $time ) {
			$timestamp = strtotime( $time );
			wp_schedule_event( $timestamp, 'daily', __FUNCTION__ . "_{$job}" );
		}

		$cron_health = $wp_site_health->get_test_scheduled_events();

		$this->assertSame( $expected_status, $cron_health['status'] );
		$this->assertSame( $expected_label, $cron_health['label'] );
		$this->assertSame( $expected_late, $wp_site_health->has_late_cron() );
		$this->assertSame( $expected_missed, $wp_site_health->has_missed_cron() );
	}

	/**
	 * Data provider for Site Health cron reports.
	 *
	 * The test suite runs with `DISABLE_WP_CRON === true` so the
	 * missed and late tests need to account for the extended periods
	 * allowed for with this flag enabled.
	 *
	 * 1. string|array Times to schedule (run through strtotime())
	 * 2. string       Expected status
	 * 3. string       Expected label
	 * 4. bool         Expected outcome has_late_cron()
	 * 5. bool         Expected outcome has_missed_cron()
	 */
	public function data_cron_health_checks() {
		return array(
			array(
				'+5 minutes',
				'good',
				__( 'Scheduled events are running' ),
				false,
				false,
			),
			array(
				'-50 minutes',
				'recommended',
				__( 'A scheduled event is late' ),
				true,
				false,
			),
			array(
				'-500 minutes',
				'recommended',
				__( 'A scheduled event has failed' ),
				false,
				true,
			),
			array(
				array(
					'-50 minutes',
					'-500 minutes',
				),
				'recommended',
				__( 'A scheduled event has failed' ),
				true,
				true,
			),
		);
	}

	/**
	 * @group ms-excluded
	 * @ticket 56040
	 */
	public function test_object_cache_default_thresholds() {
		$wp_site_health = new WP_Site_Health();

		$this->assertFalse(
			$wp_site_health->should_suggest_persistent_object_cache()
		);
	}


	/**
	 * @group ms-required
	 * @ticket 56040
	 */
	public function test_object_cache_default_thresholds_on_multisite() {
		$wp_site_health = new WP_Site_Health();
		$this->assertTrue(
			$wp_site_health->should_suggest_persistent_object_cache()
		);
	}

	/**
	 * @ticket 56040
	 */
	public function test_object_cache_thresholds_check_can_be_bypassed() {
		$wp_site_health = new WP_Site_Health();
		add_filter( 'site_status_should_suggest_persistent_object_cache', '__return_true' );

		$this->assertTrue(
			$wp_site_health->should_suggest_persistent_object_cache()
		);
	}

	/**
	 * @dataProvider thresholds
	 * @ticket 56040
	 */
	public function test_object_cache_thresholds( $threshold, $count ) {
		$wp_site_health = new WP_Site_Health();
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) use ( $threshold, $count ) {
				return array_merge( $thresholds, array( $threshold => $count ) );
			}
		);

		$this->assertTrue(
			$wp_site_health->should_suggest_persistent_object_cache()
		);
	}

	/**
	 * Data provider.
	 *
	 * @ticket 56040
	 */
	public function thresholds() {
		return array(
			array( 'comments_count', 0 ),
			array( 'posts_count', 0 ),
			array( 'terms_count', 1 ),
			array( 'options_count', 100 ),
			array( 'users_count', 0 ),
			array( 'alloptions_count', 100 ),
			array( 'alloptions_bytes', 1000 ),
		);
	}
}
