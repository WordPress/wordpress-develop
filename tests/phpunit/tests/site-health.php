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
	 * Tests all possible scenarios given by dataProvider.
	 * @ticket 56041
	 *
	 * @dataProvider data_page_cache_test
	 * @covers ::get_test_page_cache()
	 * @covers ::get_page_cache_detail()
	 * @covers ::get_page_cache_headers()
	 * @covers ::check_for_page_caching()
	 */
	public function test_get_page_cache( $responses, $expected_status, $expected_label, $good_basic_auth = null, $delay_the_response = false ) {
		$wp_site_health = new WP_Site_Health();

		$expected_props = array(
			'badge'  => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'test'   => 'page_cache',
			'status' => $expected_status,
			'label'  => $expected_label,
		);

		if ( null !== $good_basic_auth ) {
			$_SERVER['PHP_AUTH_USER'] = 'admin';
			$_SERVER['PHP_AUTH_PW']   = 'password';
		}

		$threshold = 10;
		if ( $delay_the_response ) {
			add_filter(
				'site_status_page_cache_good_response_time_threshold',
				static function () use ( $threshold ) {
					return $threshold;
				}
			);
		}

		add_filter(
			'pre_http_request',
			function ( $r, $parsed_args ) use ( &$responses, &$is_unauthorized, $good_basic_auth, $delay_the_response, $threshold ) {

				$expected_response = array_shift( $responses );

				if ( $delay_the_response ) {
					usleep( $threshold * 1000 + 1 );
				}

				if ( 'unauthorized' === $expected_response ) {
					$is_unauthorized = true;

					return array(
						'response' => array(
							'code'    => 401,
							'message' => 'Unauthorized',
						),
					);
				}

				if ( null !== $good_basic_auth ) {
					$this->assertArrayHasKey(
						'Authorization',
						$parsed_args['headers']
					);
				}

				$this->assertIsArray( $expected_response );

				return array(
					'headers'  => $expected_response,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			20,
			2
		);

		$actual = $wp_site_health->get_test_page_cache();
		$this->assertArrayHasKey( 'description', $actual );
		$this->assertArrayHasKey( 'actions', $actual );
		if ( $is_unauthorized ) {
			$this->assertStringContainsString( 'Unauthorized', $actual['description'] );
		} else {
			$this->assertStringNotContainsString( 'Unauthorized', $actual['description'] );
		}

		$this->assertEquals(
			$expected_props,
			wp_array_slice_assoc( $actual, array_keys( $expected_props ) )
		);
	}

	/**
	 * Gets response data for get_test_page_cache().
	 * @ticket 56041
	 *
	 * @return array[]
	 */
	public function data_page_cache_test() {
		$recommended_label = 'Page caching is not detected but the server response time is OK';
		$good_label        = 'Page caching is detected and the server response time is good';
		$critical_label    = 'Page caching is not detected and the server response time is slow';
		$error_label       = 'Unable to detect the presence of page caching';

		return array(
			'basic-auth-fail'                        => array(
				'responses'       => array(
					'unauthorized',
				),
				'expected_status' => 'recommended',
				'expected_label'  => $error_label,
				'good_basic_auth' => false,
			),
			'no-cache-control'                       => array(
				'responses'          => array_fill( 0, 3, array() ),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'no-cache'                               => array(
				'responses'       => array_fill( 0, 3, array( 'cache-control' => 'no-cache' ) ),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'no-cache-arrays'                        => array(
				'responses'       => array_fill( 0, 3, array( 'cache-control' => array( 'no-cache', 'no-store' ) ) ),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'no-cache-with-delayed-response'         => array(
				'responses'          => array_fill( 0, 3, array( 'cache-control' => 'no-cache' ) ),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'age'                                    => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'age' => '1345' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-max-age'                  => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cache-control' => 'public; max-age=600' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'etag'                                   => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'etag' => '"1234567890"' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-max-age-after-2-requests' => array(
				'responses'       => array(
					array(),
					array(),
					array( 'cache-control' => 'public; max-age=600' ),
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-with-future-expires'      => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'expires' => gmdate( 'r', time() + MINUTE_IN_SECONDS * 10 ) )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-with-past-expires'        => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'expires' => gmdate( 'r', time() - MINUTE_IN_SECONDS * 10 ) )
				),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'cache-control-with-basic-auth'          => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cache-control' => 'public; max-age=600' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
				'good_basic_auth' => true,
			),
			'x-cache-enabled'                        => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-cache-enabled' => 'true' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'x-cache-enabled-with-delay'             => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'x-cache-enabled' => 'false' )
				),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'x-cache-disabled'                       => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-cache-disabled' => 'off' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
		);
	}
}
