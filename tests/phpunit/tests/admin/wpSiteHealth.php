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

	/**
	 * The value of the WP_UPDATE_API_BASE environment variable before the test ran.
	 *
	 * @since 7.2.0
	 *
	 * @var string|false
	 */
	private $original_update_api_base;

	/**
	 * URLs requested during the test via the HTTP API.
	 *
	 * @since 7.2.0
	 *
	 * @var string[]
	 */
	private $requested_urls = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Include the `WP_Site_Health` file.
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
	}

	/**
	 * Performs setup tasks for every test.
	 *
	 * @since 6.1.0
	 * @since 7.2.0 Unsets the WP_UPDATE_API_BASE environment variable.
	 */
	public function set_up() {
		parent::set_up();

		$this->instance = new WP_Site_Health();

		// Ensure tests start with the default update API, regardless of the host environment.
		$this->original_update_api_base = getenv( 'WP_UPDATE_API_BASE' );
		putenv( 'WP_UPDATE_API_BASE' );
	}

	/**
	 * Performs cleanup tasks for every test.
	 *
	 * @since 7.2.0
	 */
	public function tear_down() {
		if ( false === $this->original_update_api_base ) {
			putenv( 'WP_UPDATE_API_BASE' );
		} else {
			putenv( 'WP_UPDATE_API_BASE=' . $this->original_update_api_base );
		}

		$this->requested_urls = array();

		parent::tear_down();
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

	/**
	 * Tests that the alternate update API test passes when the endpoint is reachable.
	 *
	 * @ticket 62132
	 * @covers ::get_test_alt_update_api_communication
	 */
	public function test_get_test_alt_update_api_communication_good() {
		putenv( 'WP_UPDATE_API_BASE=updates.example.invalid' );
		$this->mock_http_response(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			)
		);

		$result = $this->instance->get_test_alt_update_api_communication();

		$this->assertSame( array( 'https://updates.example.invalid/' ), $this->requested_urls, 'The configured update API should be requested.' );
		$this->assertSame( 'alt_update_api_communication', $result['test'] );
		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( __( 'Can communicate with update API' ), $result['label'] );
		$this->assertSame(
			array(
				'label' => __( 'Security' ),
				'color' => 'blue',
			),
			$result['badge']
		);
		$this->assertSame( '', $result['actions'], 'No actions should be suggested when the update API is reachable.' );
	}

	/**
	 * Tests that the alternate update API test is critical when the endpoint is unreachable.
	 *
	 * @ticket 62132
	 *
	 * @covers ::get_test_alt_update_api_communication
	 */
	public function test_get_test_alt_update_api_communication_critical() {
		putenv( 'WP_UPDATE_API_BASE=updates.example.invalid' );
		$this->mock_http_response( new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' ) );

		$result = $this->instance->get_test_alt_update_api_communication();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( __( 'Could not reach update API' ), $result['label'] );
		$this->assertStringContainsString( 'https://updates.example.invalid/', $result['description'], 'The description should name the update API URL.' );
		$this->assertStringContainsString( 'cURL error 6: Could not resolve host', $result['description'], 'The description should include the request error.' );
		$this->assertStringContainsString( 'https://updates.example.invalid/', $result['actions'], 'The actions should name the update API URL.' );
	}

	/**
	 * Tests that the alternate update API test respects the `wp_api_request` filter.
	 *
	 * @ticket 62132
	 * @covers ::get_test_alt_update_api_communication
	 */
	public function test_get_test_alt_update_api_communication_uses_filtered_url() {
		add_filter(
			'wp_api_request',
			static function () {
				return 'https://filtered.example.invalid/';
			}
		);
		$this->mock_http_response(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			)
		);

		$this->instance->get_test_alt_update_api_communication();

		$this->assertSame( array( 'https://filtered.example.invalid/' ), $this->requested_urls );
	}

	/**
	 * Tests that the alternate update API test is not registered when the default API is in use.
	 *
	 * @ticket 62132
	 *
	 * @dataProvider data_default_update_api_base
	 *
	 * @covers ::get_tests
	 *
	 * @param string|null $update_api_base Value for the WP_UPDATE_API_BASE environment variable, or null to leave it unset.
	 */
	public function test_get_tests_excludes_alt_update_api_communication_by_default( $update_api_base ) {
		if ( null !== $update_api_base ) {
			putenv( 'WP_UPDATE_API_BASE=' . $update_api_base );
		}

		$tests = WP_Site_Health::get_tests();

		$this->assertArrayNotHasKey( 'alt_update_api_communication', $tests['async'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_default_update_api_base() {
		return array(
			'environment variable unset'          => array( null ),
			'environment variable set to default' => array( 'api.wordpress.org' ),
		);
	}

	/**
	 * Tests that the alternate update API test is registered when an alternate API is configured.
	 *
	 * @ticket 62132
	 *
	 * @covers ::get_tests
	 */
	public function test_get_tests_includes_alt_update_api_communication_when_configured() {
		putenv( 'WP_UPDATE_API_BASE=updates.example.invalid' );

		$tests = WP_Site_Health::get_tests();

		$this->assertArrayHasKey( 'alt_update_api_communication', $tests['async'] );

		$test = $tests['async']['alt_update_api_communication'];
		$this->assertSame( rest_url( 'wp-site-health/v1/tests/alt-update-api-communication' ), $test['test'] );
		$this->assertTrue( $test['has_rest'] );
		$this->assertIsCallable( $test['async_direct_test'] );
	}

	/**
	 * Tests that the alternate update API test is registered when the URL is changed via filter.
	 *
	 * @ticket 62132
	 *
	 * @covers ::get_tests
	 */
	public function test_get_tests_includes_alt_update_api_communication_when_filtered() {
		add_filter(
			'wp_api_request',
			static function () {
				return 'https://filtered.example.invalid/';
			}
		);

		$tests = WP_Site_Health::get_tests();

		$this->assertArrayHasKey( 'alt_update_api_communication', $tests['async'] );
	}

	/**
	 * Tests that the WordPress.org communication test still targets WordPress.org
	 * when an alternate update API is configured.
	 *
	 * @ticket 62132
	 *
	 * @covers ::get_test_dotorg_communication
	 */
	public function test_get_test_dotorg_communication_ignores_alternate_update_api() {
		putenv( 'WP_UPDATE_API_BASE=updates.example.invalid' );
		$this->mock_http_response(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			)
		);

		$this->instance->get_test_dotorg_communication();
		$this->assertSame( array( 'https://api.wordpress.org/' ), $this->requested_urls );
	}

	/**
	 * Mocks every HTTP API response, recording each requested URL.
	 *
	 * @since 7.2.0
	 *
	 * @param array|WP_Error $response The response to return for every request.
	 */
	private function mock_http_response( $response ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $response ) {
				$this->requested_urls[] = $url;
				return $response;
			},
			10,
			3
		);
	}
}
