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
	 *
	 * @var WP_Site_Health
	 */
	private $instance;

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
	public function test_get_page_cache( $responses, $expected_status, $expected_label, $good_basic_auth = null, $delay_the_response = false ) {
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
				'site_status_good_response_time_threshold',
				static function () use ( $threshold ) {
					return $threshold;
				}
			);
		}

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( &$responses, &$is_unauthorized, $good_basic_auth, $delay_the_response, $threshold ) {

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
	 * @return array[]
	 */
	public function data_get_page_cache() {
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
	 * Helper method to set up WP_Site_Health instance with debug properties.
	 *
	 * @ticket 64071
	 *
	 * @param bool        $wp_debug         Value for wp_debug property.
	 * @param bool|string $wp_debug_log     Value for wp_debug_log property.
	 * @param bool|null   $wp_debug_display Value for wp_debug_display property.
	 *
	 * @return WP_Site_Health
	 */
	private function setup_site_health_with_debug_properties( bool $wp_debug = false, $wp_debug_log = false, ?bool $wp_debug_display = null ) {
		$site_health = new WP_Site_Health();
		$reflection  = new ReflectionClass( $site_health );

		$wp_debug_property = $reflection->getProperty( 'wp_debug' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_property->setAccessible( true );
		}
		$wp_debug_property->setValue( $site_health, $wp_debug );

		$wp_debug_log_property = $reflection->getProperty( 'wp_debug_log' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_log_property->setAccessible( true );
		}
		$wp_debug_log_property->setValue( $site_health, $wp_debug_log );

		$wp_debug_display_property = $reflection->getProperty( 'wp_debug_display' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_display_property->setAccessible( true );
		}
		$wp_debug_display_property->setValue( $site_health, $wp_debug_display );

		return $site_health;
	}

	/**
	 * Helper method to set error_log ini setting and restore it later.
	 *
	 * @ticket 64071
	 *
	 * @param string $log_path Path to set for error_log.
	 *
	 * @return string|false Original error_log value.
	 */
	private function set_error_log_path( string $log_path = '' ) {
		$original_error_log = ini_get( 'error_log' );
		ini_set( 'error_log', $log_path );
		return $original_error_log;
	}

	/**
	 * Helper method to restore error_log ini setting.
	 *
	 * @ticket 64071
	 *
	 * @param string|false $original_value Original error_log value.
	 */
	private function restore_error_log_path( $original_value ) {
		ini_set( 'error_log', $original_value );
	}

	/**
	 * Returns the expected result array when debug mode is disabled.
	 *
	 * @ticket 64071
	 *
	 * @return array
	 */
	private function get_debug_mode_disabled_result() {
		return array(
			'status' => 'good',
			'label'  => 'Your site is not set to output debug information',
			'test'   => 'is_in_debug_mode',
			'badge'  => array(
				'label' => 'Security',
				'color' => 'blue',
			),
		);
	}

	/**
	 * Returns the expected result array when debug log is in a public location.
	 *
	 * @ticket 64071
	 *
	 * @param bool $wp_debug_log_defined Whether WP_DEBUG_LOG is defined.
	 *
	 * @return array
	 */
	private function get_debug_error_log_public_result( bool $wp_debug_log_defined = true ) {

		$result = array(
			'status'      => 'critical',
			'label'       => 'Your site is set to log errors to a potentially public file',
			'description' => 'The constant, <code>WP_DEBUG_LOG</code>, has been added to this website&#8217;s configuration file. This means any errors on the site will be written to a file which is likely publicly accessible.',
			'test'        => 'is_in_debug_mode',
		);

		if ( ! $wp_debug_log_defined ) {
			$result['description'] = 'The error log path has been configured to a file within the WordPress directory. This means any errors on the site will be written to a file which is likely publicly accessible.';
		}

		return $result;
	}

	/**
	 * Returns the expected result array when debug log is in a private location.
	 *
	 * @ticket 64071
	 *
	 * @param bool $wp_debug_log_defined Whether WP_DEBUG_LOG is defined.
	 *
	 * @return array
	 */
	private function get_debug_error_log_private_result( bool $wp_debug_log_defined = true ) {

		$result = array(
			'status'      => 'good',
			'label'       => 'Your site is set to log errors to a file outside the document root',
			'description' => 'The configuration constant, <code>WP_DEBUG_LOG</code>, is enabled. In addition, your site is set to write errors to a file outside the WordPress directory, which is a good practice as the log file should not be publicly accessible.',
			'test'        => 'is_in_debug_mode',
		);

		if ( ! $wp_debug_log_defined ) {
			$result['description'] = 'The error log path has been configured to a file outside the WordPress directory. This is a good practice as the log file should not be publicly accessible.';
		}

		return $result;
	}

	/**
	 * Returns the expected result array when debug log path does not exist.
	 *
	 * @ticket 64071
	 *
	 * @param bool $wp_debug_log_defined Whether WP_DEBUG_LOG is defined.
	 *
	 * @return array
	 */
	private function get_debug_log_non_existent_path_result( bool $wp_debug_log_defined = true ) {

		$result = array(
			'status'      => 'critical',
			'label'       => 'Unable to determine error log file location',
			'description' => 'The configuration constant, <code>WP_DEBUG_LOG</code>, is enabled, but the log file location could not be determined.',
			'test'        => 'is_in_debug_mode',
		);

		if ( ! $wp_debug_log_defined ) {
			$result['description'] = 'The error log path could not be determined. Please check your PHP configuration.';
		}

		return $result;
	}

	/**
	 * Tests get_test_is_in_debug_mode() when debug mode is disabled.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_disabled() {
		$site_health     = $this->setup_site_health_with_debug_properties( false, false, null );
		$actual_result   = $site_health->get_test_is_in_debug_mode();
		$expected_result = $this->get_debug_mode_disabled_result();

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "good" when debug mode is disabled.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate debug mode is disabled.' );
		$this->assertSame( $expected_result['test'], $actual_result['test'], 'Test identifier should be "is_in_debug_mode".' );
		$this->assertArrayHasKey( 'badge', $actual_result, 'Result should have a badge.' );
		$this->assertSame( $expected_result['badge']['label'], $actual_result['badge']['label'], 'Badge label should be "Security".' );
		$this->assertSame( $expected_result['badge']['color'], $actual_result['badge']['color'], 'Badge color should be "blue".' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when WP_DEBUG is enabled without error logging.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_enabled_no_error_log() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, false, null );
		$original_error_log = $this->set_error_log_path( '' );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_mode_disabled_result();

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "good" when no error log is configured.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate no error log is configured.' );
		$this->assertSame( $expected_result['test'], $actual_result['test'], 'Test identifier should be "is_in_debug_mode".' );
		$this->assertArrayHasKey( 'badge', $actual_result, 'Result should have a badge.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when error log is in a public location.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_error_log_public() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, true, null );
		$public_log_path    = ABSPATH . 'wp-content/debug.log';
		$original_error_log = $this->set_error_log_path( $public_log_path );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_error_log_public_result( true );

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "critical" when error log is in a public location.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate error log is in a public location.' );
		$this->assertStringContainsString( $expected_result['description'], $actual_result['description'], 'Description should display error log is configured with WP_DEBUG_LOG and is in a public directory.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when error log is public without WP_DEBUG_LOG.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_error_log_public_without_wp_debug_log() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, false, null );
		$public_log_path    = ABSPATH . 'wp-content/debug.log';
		$original_error_log = $this->set_error_log_path( $public_log_path );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_error_log_public_result( false );

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "critical" when error log is in a public location.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate error log is in a public location.' );
		$this->assertStringContainsString( $expected_result['description'], $actual_result['description'], 'Description should mention error log is configured without WP_DEBUG_LOG and in public directory.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when error log is in a private location.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_error_log_private() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, true, null );
		$private_log_path   = '/var/log/php-error.log';
		$original_error_log = $this->set_error_log_path( $private_log_path );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_error_log_private_result( true );

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "good" when error log is in a private location.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate error log is in a private location.' );
		$this->assertStringContainsString( $expected_result['description'], $actual_result['description'], 'Description should mention error log is configured outside WordPress directory.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when error log is private without WP_DEBUG_LOG.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_error_log_private_without_wp_debug_log() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, false, null );
		$private_log_path   = '/var/log/php-error.log';
		$original_error_log = $this->set_error_log_path( $private_log_path );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_error_log_private_result( false );

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "good" when error log is in a private location.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate error log is in a private location.' );
		$this->assertStringContainsString( $expected_result['description'], $actual_result['description'], 'Description should mention error log is configured outside WordPress directory.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when error log path cannot be determined.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_error_log_non_existent() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, true, null );
		$invalid_log_path   = '/nonexistent/path/that/does/not/exist/debug.log';
		$original_error_log = $this->set_error_log_path( $invalid_log_path );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_log_non_existent_path_result( true );

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "critical" when error log location cannot be determined.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate that error log location could not be determined.' );
		$this->assertStringContainsString( $expected_result['description'], $actual_result['description'], 'Description should mention error log path is nonexistent.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when error log path cannot be determined and WP_DEBUG_LOG is not defined.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_error_log_non_existent_without_wp_debug_log() {
		$site_health        = $this->setup_site_health_with_debug_properties( true, false, null );
		$invalid_log_path   = '/nonexistent/path/that/does/not/exist/debug.log';
		$original_error_log = $this->set_error_log_path( $invalid_log_path );
		$actual_result      = $site_health->get_test_is_in_debug_mode();
		$expected_result    = $this->get_debug_log_non_existent_path_result( false );

		$this->restore_error_log_path( $original_error_log );

		$this->assertSame( $expected_result['status'], $actual_result['status'], 'Status should be "critical" when error log location cannot be determined.' );
		$this->assertSame( $expected_result['label'], $actual_result['label'], 'Label should indicate that error log location could not be determined.' );
		$this->assertStringContainsString( $expected_result['description'], $actual_result['description'], 'Description should mention error log path is nonexistent.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when WP_DEBUG_DISPLAY is enabled in production.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_display_enabled_production() {
		$site_health_mock = $this->getMockBuilder( 'WP_Site_Health' )
			->onlyMethods( array( 'is_development_environment' ) )
			->getMock();

		$site_health_mock->method( 'is_development_environment' )
			->willReturn( false );

		$site_health     = new WP_Site_Health();
		$reflection_mock = new ReflectionClass( $site_health );

		$wp_debug_property_mock = $reflection_mock->getProperty( 'wp_debug' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_property_mock->setAccessible( true );
		}
		$wp_debug_property_mock->setValue( $site_health_mock, true );

		$wp_debug_display_property_mock = $reflection_mock->getProperty( 'wp_debug_display' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_display_property_mock->setAccessible( true );
		}
		$wp_debug_display_property_mock->setValue( $site_health_mock, true );

		$actual_result = $site_health_mock->get_test_is_in_debug_mode();

		$this->assertSame( 'critical', $actual_result['status'], 'Status should be "critical" when WP_DEBUG_DISPLAY is enabled in production.' );
		$this->assertSame( 'Your site is set to display errors to site visitors', $actual_result['label'], 'Label should indicate that errors are displayed to visitors.' );
		$this->assertStringContainsString( 'WP_DEBUG_DISPLAY', $actual_result['description'], 'Description should contain WP_DEBUG_DISPLAY.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() when WP_DEBUG_DISPLAY is enabled in development.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_display_enabled_development() {
		$site_health_mock = $this->getMockBuilder( 'WP_Site_Health' )
			->onlyMethods( array( 'is_development_environment' ) )
			->getMock();

		$site_health_mock->method( 'is_development_environment' )
			->willReturn( true );

		$site_health     = new WP_Site_Health();
		$reflection_mock = new ReflectionClass( $site_health );

		$wp_debug_property_mock = $reflection_mock->getProperty( 'wp_debug' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_property_mock->setAccessible( true );
		}
		$wp_debug_property_mock->setValue( $site_health_mock, true );

		$wp_debug_display_property_mock = $reflection_mock->getProperty( 'wp_debug_display' );
		if ( PHP_VERSION_ID < 80100 ) {
			$wp_debug_display_property_mock->setAccessible( true );
		}
		$wp_debug_display_property_mock->setValue( $site_health_mock, true );

		$actual_result = $site_health_mock->get_test_is_in_debug_mode();

		$this->assertSame( 'recommended', $actual_result['status'], 'Status should be "recommended" when WP_DEBUG_DISPLAY is enabled in development.' );
		$this->assertSame( 'Your site is set to display errors to site visitors', $actual_result['label'], 'Label should indicate that errors are displayed to visitors.' );
		$this->assertStringContainsString( 'WP_DEBUG_DISPLAY', $actual_result['description'], 'Description should contain WP_DEBUG_DISPLAY.' );
	}

	/**
	 * Tests get_test_is_in_debug_mode() validates actual_result structure.
	 *
	 * @ticket 64071
	 *
	 * @covers ::get_test_is_in_debug_mode()
	 */
	public function test_is_in_debug_mode_result_structure() {
		$site_health   = new WP_Site_Health();
		$actual_result = $site_health->get_test_is_in_debug_mode();

		$this->assertArrayHasKey( 'label', $actual_result, 'Result should have a label.' );
		$this->assertArrayHasKey( 'status', $actual_result, 'Result should have a status.' );
		$this->assertArrayHasKey( 'badge', $actual_result, 'Result should have a badge.' );
		$this->assertArrayHasKey( 'description', $actual_result, 'Result should have a description.' );
		$this->assertArrayHasKey( 'actions', $actual_result, 'Result should have actions.' );
		$this->assertArrayHasKey( 'test', $actual_result, 'Result should have a test identifier.' );
		$this->assertIsArray( $actual_result['badge'], 'Badge should be an array.' );
		$this->assertArrayHasKey( 'label', $actual_result['badge'], 'Badge should have a label.' );
		$this->assertArrayHasKey( 'color', $actual_result['badge'], 'Badge should have a color.' );
		$this->assertContains( $actual_result['status'], array( 'good', 'recommended', 'critical' ), 'Status should be one of: good, recommended, critical.' );
	}
}
