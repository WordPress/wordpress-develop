<?php
/**
 * Tests for the wp_get_speculation_rules_configuration() function.
 *
 * @package WordPress
 * @subpackage Speculative Loading
 */

/**
 * @group speculative-loading
 * @covers ::wp_get_speculation_rules_configuration
 */
class Tests_Speculative_Loading_wpGetSpeculationRulesConfiguration extends WP_UnitTestCase {

	/**
	 * Whether an external object cache was in use before each test.
	 *
	 * @var bool
	 */
	private $initial_using_ext_object_cache;

	/**
	 * getenv( 'WP_ENVIRONMENT_TYPE' ) at the start of the test, for restoration in tear_down().
	 *
	 * @var string|false
	 */
	private $wp_environment_type_env_before_test;

	public function set_up() {
		parent::set_up();

		$this->initial_using_ext_object_cache     = wp_using_ext_object_cache();
		$this->wp_environment_type_env_before_test = getenv( 'WP_ENVIRONMENT_TYPE' );

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
	}

	public function tear_down() {
		wp_using_ext_object_cache( $this->initial_using_ext_object_cache );

		if ( false !== $this->wp_environment_type_env_before_test && '' !== $this->wp_environment_type_env_before_test ) {
			putenv( 'WP_ENVIRONMENT_TYPE=' . $this->wp_environment_type_env_before_test );
		} else {
			putenv( 'WP_ENVIRONMENT_TYPE' );
		}

		parent::tear_down();
	}

	/**
	 * Tests that the default configuration is the expected value.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_configuration_default() {
		$filter_default = null;
		add_filter(
			'wp_speculation_rules_configuration',
			function ( $config ) use ( &$filter_default ) {
				$filter_default = $config;
				return $config;
			}
		);

		$config_default = wp_get_speculation_rules_configuration();

		// The filter default uses 'auto', but for the function result this is evaluated to actual mode and eagerness.
		$this->assertSame(
			array(
				'mode'      => 'auto',
				'eagerness' => 'auto',
			),
			$filter_default
		);
		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			$config_default
		);
	}

	/**
	 * Tests that the speculative loading is disabled by default when not using pretty permalinks.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_configuration_without_pretty_permalinks() {
		update_option( 'permalink_structure', '' );
		$this->assertNull( wp_get_speculation_rules_configuration() );
	}

	/**
	 * Tests that the speculative loading is disabled by default for logged-in users.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_configuration_with_logged_in_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertNull( wp_get_speculation_rules_configuration() );
	}

	/**
	 * Tests that the configuration can be filtered and leads to the expected results.
	 *
	 * @ticket 62503
	 * @dataProvider data_wp_get_speculation_rules_configuration_filter
	 */
	public function test_wp_get_speculation_rules_configuration_filter( $filter_value, $expected ) {
		add_filter(
			'wp_speculation_rules_configuration',
			function () use ( $filter_value ) {
				return $filter_value;
			}
		);

		$this->assertSame( $expected, wp_get_speculation_rules_configuration() );
	}

	public static function data_wp_get_speculation_rules_configuration_filter(): array {
		return array(
			'conservative prefetch'  => array(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'moderate prefetch'      => array(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
			),
			'eager prefetch'         => array(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				),
			),
			'conservative prerender' => array(
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
			),
			'moderate prerender'     => array(
				array(
					'mode'      => 'prerender',
					'eagerness' => 'moderate',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'moderate',
				),
			),
			'eager prerender'        => array(
				array(
					'mode'      => 'prerender',
					'eagerness' => 'eager',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'eager',
				),
			),
			'auto'                   => array(
				array(
					'mode'      => 'auto',
					'eagerness' => 'auto',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'auto mode only'         => array(
				array(
					'mode'      => 'auto',
					'eagerness' => 'eager',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				),
			),
			'auto eagerness only'    => array(
				array(
					'mode'      => 'prerender',
					'eagerness' => 'auto',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
			),
			// 'immediate' is a valid eagerness, but for safety WordPress does not allow it for document-level rules.
			'immediate eagerness'    => array(
				array(
					'mode'      => 'auto',
					'eagerness' => 'immediate',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'null'                   => array(
				null,
				null,
			),
			'false'                  => array(
				false,
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'true'                   => array(
				true,
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'missing mode'           => array(
				array(
					'eagerness' => 'eager',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				),
			),
			'missing eagerness'      => array(
				array(
					'mode' => 'prerender',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
			),
			'empty array'            => array(
				array(),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'invalid mode'           => array(
				array(
					'mode'      => 'invalid',
					'eagerness' => 'eager',
				),
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				),
			),
			'invalid eagerness'      => array(
				array(
					'mode'      => 'prerender',
					'eagerness' => 'invalid',
				),
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
			),
			'invalid type'           => array(
				42,
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_uses_moderate_eagerness_when_production_persistent_object_cache_and_advanced_cache_detected() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		wp_using_ext_object_cache( true );
		set_transient(
			'health_check_page_cache_detail',
			array(
				'advanced_cache_present'        => true,
				'page_caching_response_headers' => array(),
				'response_timing'               => array(),
				'caching_response_headers'      => array(),
			)
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'moderate',
			),
			$config
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_uses_moderate_eagerness_when_production_persistent_object_cache_and_caching_headers_detected() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		wp_using_ext_object_cache( true );
		set_transient(
			'health_check_page_cache_detail',
			array(
				'advanced_cache_present'        => false,
				'page_caching_response_headers' => array(),
				'response_timing'               => array(),
				'caching_response_headers'      => array( 'age' ),
			)
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'moderate',
			),
			$config
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_remains_conservative_on_staging_even_with_caches_detected() {
		putenv( 'WP_ENVIRONMENT_TYPE=staging' );
		wp_using_ext_object_cache( true );
		set_transient(
			'health_check_page_cache_detail',
			array(
				'advanced_cache_present'        => true,
				'page_caching_response_headers' => array(),
				'response_timing'               => array(),
				'caching_response_headers'      => array(),
			)
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			$config
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_remains_conservative_in_non_production_even_with_caches_detected() {
		putenv( 'WP_ENVIRONMENT_TYPE=development' );
		wp_using_ext_object_cache( true );
		set_transient(
			'health_check_page_cache_detail',
			array(
				'advanced_cache_present'        => true,
				'page_caching_response_headers' => array(),
				'response_timing'               => array(),
				'caching_response_headers'      => array(),
			)
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			$config
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_remains_conservative_without_persistent_object_cache() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		wp_using_ext_object_cache( false );
		set_transient(
			'health_check_page_cache_detail',
			array(
				'advanced_cache_present'        => true,
				'page_caching_response_headers' => array(),
				'response_timing'               => array(),
				'caching_response_headers'      => array(),
			)
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			$config
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_remains_conservative_when_no_health_check_page_cache_detail() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		wp_using_ext_object_cache( true );

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			$config
		);
	}

	/**
	 * @ticket 64066
	 */
	public function test_wp_get_speculation_rules_configuration_remains_conservative_when_page_cache_detail_has_no_signals() {
		putenv( 'WP_ENVIRONMENT_TYPE=production' );
		wp_using_ext_object_cache( true );
		set_transient(
			'health_check_page_cache_detail',
			array(
				'advanced_cache_present'        => false,
				'page_caching_response_headers' => array(),
				'response_timing'               => array(),
				'caching_response_headers'      => array(),
			)
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			$config
		);
	}
}
