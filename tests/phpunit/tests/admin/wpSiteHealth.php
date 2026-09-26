<?php

/**
 * @group admin
 * @group site-health
 *
 * @coversDefaultClass WP_Site_Health
 */
class Tests_Admin_wpSiteHealth extends WP_UnitTestCase {

	/**
	 * An instance of the class to test.
	 *
	 * @since 6.1.0
	 */
	private WP_Site_Health $instance;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Include the `WP_Site_Health` file.
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	}

	/**
	 * Performs setup tasks for every test.
	 *
	 * @since 6.1.0
	 */
	public function set_up() {
		parent::set_up();

		$this->instance = new WP_Site_Health();
	}

	/**
	 * Performs cleanup tasks after every test.
	 */
	public function tear_down() {
		delete_option( 'email_delivery_last_tested' );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Creates and sets the current user for an email delivery test.
	 *
	 * @param string $email User email address.
	 * @return WP_User The current user.
	 */
	private function set_email_delivery_test_user( $email = 'site-health@example.org' ) {
		$user = self::factory()->user->create_and_get(
			array(
				'role'       => 'administrator',
				'user_email' => $email,
			)
		);
		wp_set_current_user( $user->ID );

		return $user;
	}

	/**
	 * @ticket 55791
	 * @covers ::__construct()
	 */
	public function test_mysql_recommended_version_matches_readme_html() {
		$reflection          = new ReflectionClass( $this->instance );
		$reflection_property = $reflection->getProperty( 'mysql_recommended_version' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_property->setAccessible( true );
		}
		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MySQL</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$this->assertSame( $matches[1], $reflection_property->getValue( $this->instance ) );
	}

	/**
	 * @ticket 55791
	 * @covers ::__construct()
	 */
	public function test_mariadb_recommended_version_matches_readme_html() {
		$reflection          = new ReflectionClass( $this->instance );
		$reflection_property = $reflection->getProperty( 'mariadb_recommended_version' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_property->setAccessible( true );
		}

		$readme = file_get_contents( ABSPATH . 'readme.html' );

		preg_match( '#Recommendations.*MariaDB</a> version <strong>([0-9.]*)#s', $readme, $matches );

		$this->assertSame( $matches[1], $reflection_property->getValue( $this->instance ) );
	}

	/**
	 * Ensure Site Health reports correctly cron job reports.
	 *
	 * @ticket 47223
	 */
	public function test_cron_health_checks_critical() {
		// Clear the cron array.
		_set_cron_array( array() );

		$cron_health = $this->instance->get_test_scheduled_events();

		$this->assertSame( 'critical', $cron_health['status'] );
		$this->assertSame( __( 'It was not possible to check your scheduled events' ), $cron_health['label'] );
		$this->assertWPError( $this->instance->has_late_cron() );
		$this->assertWPError( $this->instance->has_missed_cron() );
	}

	/**
	 * Ensure Site Health reports correctly cron job reports.
	 *
	 * @dataProvider data_cron_health_checks
	 * @ticket 47223
	 */
	public function test_cron_health_checks( $times, $expected_status, $expected_label, $expected_late, $expected_missed ) {
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

		$cron_health = $this->instance->get_test_scheduled_events();

		$this->assertSame( $expected_status, $cron_health['status'] );
		$this->assertSame( $expected_label, $cron_health['label'] );
		$this->assertSame( $expected_late, $this->instance->has_late_cron() );
		$this->assertSame( $expected_missed, $this->instance->has_missed_cron() );
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
	 * @ticket 56041
	 * @dataProvider data_get_page_cache
	 * @covers ::get_test_page_cache()
	 * @covers ::get_page_cache_detail()
	 * @covers ::get_page_cache_headers()
	 * @covers ::check_for_page_caching()
	 */
	public function test_get_page_cache( array $responses, string $expected_status, string $expected_label, bool $has_basic_auth = false, bool $delay_the_response = false ) {
		$expected_props = array(
			'badge'  => array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			'test'   => 'page_cache',
			'status' => $expected_status,
			'label'  => $expected_label,
		);

		if ( $has_basic_auth ) {
			$_SERVER['PHP_AUTH_USER'] = 'admin';
			$_SERVER['PHP_AUTH_PW']   = 'password';
		}

		$threshold = 10;
		if ( $delay_the_response ) {
			add_filter(
				'site_status_good_response_time_threshold',
				static function () use ( $threshold ) {
					return $threshold;
				}
			);
		}

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( &$responses, &$is_unauthorized, $has_basic_auth, $delay_the_response, $threshold ) {

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

				if ( $has_basic_auth ) {
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

		$actual = $this->instance->get_test_page_cache();
		$this->assertArrayHasKey( 'description', $actual );
		$this->assertArrayHasKey( 'actions', $actual );

		if ( $is_unauthorized ) {
			$this->assertStringContainsString( 'Unauthorized', $actual['description'] );
		} else {
			$this->assertStringNotContainsString( 'Unauthorized', $actual['description'] );
		}

		$this->assertSame(
			$expected_props,
			wp_array_slice_assoc( $actual, array_keys( $expected_props ) )
		);
	}

	/**
	 * Data provider for test_get_page_cache().
	 *
	 * Gets response data for WP_Site_Health::get_test_page_cache().
	 *
	 * @ticket 56041
	 *
	 * @return array<string, array{
	 *     responses: array<int, string|array<string, string|string[]>>,
	 *     expected_status: 'recommended'|'critical'|'good',
	 *     expected_label: string,
	 *     good_basic_auth?: bool,
	 *     delay_the_response?: bool,
	 * }>
	 */
	public function data_get_page_cache(): array {
		$recommended_label = 'Page cache is not detected but the server response time is OK';
		$good_label        = 'Page cache is detected and the server response time is good';
		$critical_label    = 'Page cache is not detected and the server response time is slow';
		$error_label       = 'Unable to detect the presence of page cache';

		return array(
			'basic-auth-fail'                        => array(
				'responses'       => array(
					'unauthorized',
				),
				'expected_status' => 'recommended',
				'expected_label'  => $error_label,
				'has_basic_auth'  => true,
			),
			'no-cache-control'                       => array(
				'responses'          => array_fill( 0, 3, array() ),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'has_basic_auth'     => false,
				'delay_the_response' => true,
			),
			'no-cache'                               => array(
				'responses'       => array_fill( 0, 3, array( 'cache-control' => 'no-cache' ) ),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'no-cache-arrays'                        => array(
				'responses'       => array_fill(
					0,
					3,
					array(
						'cache-control' => array(
							'no-cache',
							'no-store',
						),
					)
				),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'no-cache-with-delayed-response'         => array(
				'responses'          => array_fill( 0, 3, array( 'cache-control' => 'no-cache' ) ),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'has_basic_auth'     => false,
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
					array( 'expires' => gmdate( 'r', time() + HOUR_IN_SECONDS ) )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-with-past-expires'        => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'expires' => gmdate( 'r', time() - HOUR_IN_SECONDS ) )
				),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'has_basic_auth'     => false,
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
				'has_basic_auth'  => true,
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
				'has_basic_auth'     => false,
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
			'false-positive-hit-in-word'             => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-cache' => 'no-hit' )
				),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'varnish-header'                         => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-varnish' => '123 456' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'varnish-header-miss'                    => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-varnish' => '123' )
				),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'srcache-store-status'                   => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-srcache-store-status' => 'STORE' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'srcache-store-status-bypass'            => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-srcache-store-status' => 'BYPASS' )
				),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'srcache-fetch-status'                   => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-srcache-fetch-status' => 'HIT' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'last-modified'                          => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'last-modified' => 'Wed, 21 Oct 2015 07:28:00 GMT' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'via'                                    => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'via' => '1.1 varnish' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
		);
	}

	/**
	 * @group ms-excluded
	 * @ticket 56040
	 */
	public function test_object_cache_default_thresholds_non_multisite() {
		// Set thresholds so high they should never be exceeded.
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			static function () {
				return array(
					'alloptions_count' => PHP_INT_MAX,
					'alloptions_bytes' => PHP_INT_MAX,
					'comments_count'   => PHP_INT_MAX,
					'options_count'    => PHP_INT_MAX,
					'posts_count'      => PHP_INT_MAX,
					'terms_count'      => PHP_INT_MAX,
					'users_count'      => PHP_INT_MAX,
				);
			}
		);

		$this->assertFalse(
			$this->instance->should_suggest_persistent_object_cache()
		);
	}


	/**
	 * @group ms-required
	 * @ticket 56040
	 */
	public function test_object_cache_default_thresholds_on_multisite() {
		$this->assertTrue(
			$this->instance->should_suggest_persistent_object_cache()
		);
	}

	/**
	 * @ticket 56040
	 */
	public function test_object_cache_thresholds_check_can_be_bypassed() {
		add_filter( 'site_status_should_suggest_persistent_object_cache', '__return_true' );
		$this->assertTrue(
			$this->instance->should_suggest_persistent_object_cache()
		);

		add_filter( 'site_status_should_suggest_persistent_object_cache', '__return_false', 11 );
		$this->assertFalse(
			$this->instance->should_suggest_persistent_object_cache()
		);
	}

	/**
	 * @dataProvider data_object_cache_thresholds
	 * @ticket 56040
	 */
	public function test_object_cache_thresholds( $threshold, $count ) {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			static function ( $thresholds ) use ( $threshold, $count ) {
				return array_merge( $thresholds, array( $threshold => $count ) );
			}
		);

		$this->assertTrue(
			$this->instance->should_suggest_persistent_object_cache()
		);
	}

	/**
	 * Data provider for test_object_cache_thresholds().
	 *
	 * @ticket 56040
	 */
	public function data_object_cache_thresholds() {
		return array(
			array( 'comments_count', 0 ),
			array( 'posts_count', 0 ),
			array( 'terms_count', 0 ),
			array( 'options_count', 1 ),
			array( 'users_count', 0 ),
			array( 'alloptions_count', 1 ),
			array( 'alloptions_bytes', 10 ),
		);
	}

	/**
	 * Tests get_test_autoloaded_options() when autoloaded options less than warning size.
	 *
	 * @ticket 61276
	 *
	 * @covers ::get_test_autoloaded_options()
	 */
	public function test_wp_autoloaded_options_test_no_warning() {
		$expected_label  = esc_html__( 'Autoloaded options are acceptable' );
		$expected_status = 'good';

		$result = $this->instance->get_test_autoloaded_options();
		$this->assertSame( $expected_label, $result['label'], 'The label should indicate that autoloaded options are acceptable.' );
		$this->assertSame( $expected_status, $result['status'], 'The status should be "good" when autoloaded options are acceptable.' );
	}

	/**
	 * Tests get_test_autoloaded_options() when autoloaded options more than warning size.
	 *
	 * @ticket 61276
	 *
	 * @covers ::get_test_autoloaded_options()
	 */
	public function test_wp_autoloaded_options_test_warning() {
		self::set_autoloaded_option( 800000 );

		$expected_label  = esc_html__( 'Autoloaded options could affect performance' );
		$expected_status = 'critical';

		$result = $this->instance->get_test_autoloaded_options();
		$this->assertSame( $expected_label, $result['label'], 'The label should indicate that autoloaded options could affect performance.' );
		$this->assertSame( $expected_status, $result['status'], 'The status should be "critical" when autoloaded options could affect performance.' );
	}

	/**
	 * Tests get_autoloaded_options_size().
	 *
	 * @ticket 61276
	 *
	 * @covers ::get_autoloaded_options_size()
	 */
	public function test_get_autoloaded_options_size() {
		global $wpdb;

		$autoload_values = wp_autoload_values_to_autoload();

		$autoloaded_options_size = (int) $wpdb->get_var(
			$wpdb->prepare(
				sprintf(
					"SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload IN (%s)",
					implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) )
				),
				$autoload_values
			)
		);
		$this->assertSame( $autoloaded_options_size, $this->instance->get_autoloaded_options_size(), 'The size of autoloaded options should match the calculated size from the database.' );

		// Add autoload option.
		$test_option_string       = 'test';
		$test_option_string_bytes = mb_strlen( $test_option_string, '8bit' );
		self::set_autoloaded_option( $test_option_string_bytes );
		$this->assertSame( $autoloaded_options_size + $test_option_string_bytes, $this->instance->get_autoloaded_options_size(), 'The size of autoloaded options should increase by the size of the newly added option.' );
	}

	/**
	 * Sets a test autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( $bytes = 800000 ) {
		$heavy_option_string = wp_generate_password( $bytes );

		// Force autoloading so that WordPress core does not override it. See https://core.trac.wordpress.org/changeset/57920.
		add_option( 'test_set_autoloaded_option', $heavy_option_string, '', true );
	}

	/**
	 * Tests the email delivery Site Health status.
	 *
	 * @ticket 65891
	 * @dataProvider data_email_delivery_status
	 * @covers ::get_email_delivery_last_tested()
	 * @covers ::get_test_email_delivery()
	 *
	 * @param string $state           The stored timestamp state.
	 * @param string $expected_status The expected Site Health status.
	 */
	public function test_email_delivery_status( $state, $expected_status ) {
		$user      = $this->set_email_delivery_test_user();
		$test_data = array(
			'timestamp' => time() - DAY_IN_SECONDS,
			'user_id'   => $user->ID,
			'email'     => $user->user_email,
		);

		switch ( $state ) {
			case 'malformed':
				update_option( 'email_delivery_last_tested', 'not-a-timestamp', false );
				break;
			case 'future':
				$test_data['timestamp'] = time() + HOUR_IN_SECONDS;
				update_option( 'email_delivery_last_tested', $test_data, false );
				break;
			case 'expired':
				$test_data['timestamp'] = time() - ( 3 * MONTH_IN_SECONDS );
				update_option( 'email_delivery_last_tested', $test_data, false );
				break;
			case 'recent':
				update_option( 'email_delivery_last_tested', $test_data, false );
				break;
		}

		$result = $this->instance->get_test_email_delivery();

		$this->assertSame( $expected_status, $result['status'] );
		$this->assertSame( 'email_delivery', $result['test'] );
		$this->assertSame(
			array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			$result['badge']
		);

		$this->assertStringContainsString( 'site-health.php', $result['actions'] );
		$this->assertStringNotContainsString( 'options-general.php', $result['actions'] );
	}

	/**
	 * Data provider for test_email_delivery_status().
	 *
	 * @return array[] Test parameters.
	 */
	public function data_email_delivery_status() {
		return array(
			'missing timestamp'   => array( 'missing', 'recommended' ),
			'malformed timestamp' => array( 'malformed', 'recommended' ),
			'future timestamp'    => array( 'future', 'recommended' ),
			'expired timestamp'   => array( 'expired', 'recommended' ),
			'recent timestamp'    => array( 'recent', 'good' ),
		);
	}

	/**
	 * Tests a successful email delivery test.
	 *
	 * @ticket 65891
	 * @covers ::send_email_delivery_test()
	 */
	public function test_send_email_delivery_test_success() {
		global $wpdb;

		$user      = $this->set_email_delivery_test_user();
		$mail_data = null;
		$callback  = static function ( $short_circuit, $atts ) use ( &$mail_data ) {
			$mail_data = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );

		$result = $this->instance->send_email_delivery_test();

		remove_filter( 'pre_wp_mail', $callback );

		$this->assertTrue( $result );
		$this->assertSame( $user->user_email, $mail_data['to'] );
		$this->assertStringContainsString( get_option( 'blogname' ), $mail_data['subject'] );
		$this->assertStringContainsString( home_url( '/' ), $mail_data['message'] );

		$test_data = get_option( 'email_delivery_last_tested' );
		$this->assertGreaterThan( 0, $test_data['timestamp'] );
		$this->assertSame( $user->ID, $test_data['user_id'] );
		$this->assertSame( $user->user_email, $test_data['email'] );
		$this->assertSame( 'good', $this->instance->get_test_email_delivery()['status'] );
		$this->assertSame(
			'off',
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT autoload FROM $wpdb->options WHERE option_name = %s",
					'email_delivery_last_tested'
				)
			)
		);
	}

	/**
	 * Tests an immediate email sending failure.
	 *
	 * @ticket 65891
	 * @covers ::send_email_delivery_test()
	 */
	public function test_send_email_delivery_test_failure() {
		$this->set_email_delivery_test_user();
		add_filter( 'pre_wp_mail', '__return_false' );

		$result = $this->instance->send_email_delivery_test();

		remove_filter( 'pre_wp_mail', '__return_false' );

		$this->assertWPError( $result );
		$this->assertSame( 'send-failed', $result->get_error_code() );
		$this->assertFalse( get_option( 'email_delivery_last_tested', false ) );
	}

	/**
	 * Tests a current user without a valid email address.
	 *
	 * @ticket 65891
	 * @covers ::send_email_delivery_test()
	 */
	public function test_send_email_delivery_test_invalid_address() {
		$result = $this->instance->send_email_delivery_test();

		$this->assertWPError( $result );
		$this->assertSame( 'invalid-address', $result->get_error_code() );
		$this->assertFalse( get_option( 'email_delivery_last_tested', false ) );
	}

	/**
	 * Tests that changing the tested user's email invalidates the result.
	 *
	 * @ticket 65891
	 * @covers ::get_email_delivery_last_tested()
	 * @covers ::get_test_email_delivery()
	 */
	public function test_email_delivery_status_is_invalid_after_user_email_changes() {
		$user = $this->set_email_delivery_test_user( 'before-change@example.org' );
		update_option(
			'email_delivery_last_tested',
			array(
				'timestamp' => time() - DAY_IN_SECONDS,
				'user_id'   => $user->ID,
				'email'     => $user->user_email,
			),
			false
		);

		wp_update_user(
			array(
				'ID'         => $user->ID,
				'user_email' => 'after-change@example.org',
			)
		);

		$this->assertSame( 'recommended', $this->instance->get_test_email_delivery()['status'] );
	}

	/**
	 * Tests that deleting the tested user invalidates the result.
	 *
	 * @ticket 65891
	 * @covers ::get_email_delivery_last_tested()
	 * @covers ::get_test_email_delivery()
	 */
	public function test_email_delivery_status_is_invalid_after_user_is_deleted() {
		$user = $this->set_email_delivery_test_user();
		update_option(
			'email_delivery_last_tested',
			array(
				'timestamp' => time() - DAY_IN_SECONDS,
				'user_id'   => $user->ID,
				'email'     => $user->user_email,
			),
			false
		);

		if ( is_multisite() ) {
			wpmu_delete_user( $user->ID );
		} else {
			wp_delete_user( $user->ID );
		}
		wp_set_current_user( 0 );

		$this->assertSame( 'recommended', $this->instance->get_test_email_delivery()['status'] );
	}

	/**
	 * Tests that a recent email delivery result applies site-wide.
	 *
	 * @ticket 65891
	 * @covers ::get_email_delivery_last_tested()
	 * @covers ::get_test_email_delivery()
	 */
	public function test_email_delivery_status_applies_to_other_administrators() {
		$user = $this->set_email_delivery_test_user();
		update_option(
			'email_delivery_last_tested',
			array(
				'timestamp' => time() - DAY_IN_SECONDS,
				'user_id'   => $user->ID,
				'email'     => $user->user_email,
			),
			false
		);

		$this->set_email_delivery_test_user( 'another-admin@example.org' );

		$this->assertSame( 'good', $this->instance->get_test_email_delivery()['status'] );
	}

	/**
	 * Tests that the email delivery check is registered as a direct test.
	 *
	 * @ticket 65891
	 * @covers ::get_tests()
	 */
	public function test_email_delivery_test_is_registered() {
		$tests = WP_Site_Health::get_tests();

		$this->assertArrayHasKey( 'email_delivery', $tests['direct'] );
		$this->assertSame( 'email_delivery', $tests['direct']['email_delivery']['test'] );
	}

	/**
	 * Tests get_test_opcode_cache() return structure.
	 *
	 * @ticket 63697
	 *
	 * @covers ::get_test_opcode_cache()
	 */
	public function test_get_test_opcode_cache_return_structure() {
		$result = $this->instance->get_test_opcode_cache();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'label', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'badge', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'actions', $result );
		$this->assertArrayHasKey( 'test', $result );

		$this->assertSame( 'opcode_cache', $result['test'] );
		$this->assertSame(
			array(
				'label' => __( 'Performance' ),
				'color' => 'blue',
			),
			$result['badge']
		);
		$this->assertContains( $result['status'], array( 'good', 'recommended' ), 'Status must be good or recommended.' );
	}

	/**
	 * Tests get_test_opcode_cache() result when opcode cache is enabled or not.
	 *
	 * Covers: opcache enabled, disabled, not available, and opcache_get_status() returns false.
	 *
	 * @ticket 63697
	 *
	 * @covers ::get_test_opcode_cache()
	 */
	public function test_get_test_opcode_cache_result_by_environment() {
		$result = $this->instance->get_test_opcode_cache();

		$opcache_enabled = false;
		if ( function_exists( 'opcache_get_status' ) ) {
			$status = @opcache_get_status( false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Warning emitted in failure case.
			if ( $status && true === $status['opcache_enabled'] ) {
				$opcache_enabled = true;
			}
		}

		if ( $opcache_enabled ) {
			$this->assertSame( 'good', $result['status'], 'When opcache is enabled, status should be "good".' );
			$this->assertSame( __( 'Opcode cache is enabled' ), $result['label'] );
		} else {
			$this->assertSame( 'recommended', $result['status'] );
			$this->assertSame( __( 'Opcode cache is not enabled' ), $result['label'] );
			$this->assertStringContainsString( __( 'Enabling this cache can significantly improve the performance of your site.' ), $result['description'] );
		}
	}
}
