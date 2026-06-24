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
	 * Name of the transient used to signal a detected page cache.
	 *
	 * Mirrors the constant referenced by `wp_get_speculation_rules_configuration()`
	 * when the default eagerness is dynamically promoted from `conservative` to
	 * `moderate` on sites with persistent object cache and a detected page cache.
	 *
	 * @var string
	 */
	const PAGE_CACHE_DETAIL_TRANSIENT = 'health_check_page_cache_detail';

	/**
	 * Initial value of `wp_using_ext_object_cache()`, restored in tear_down().
	 *
	 * @var bool
	 */
	private $initial_using_ext_object_cache;

	/**
	 * Initial value of the `WP_ENVIRONMENT_TYPE` environment variable, restored in tear_down().
	 *
	 * @var string|false
	 */
	private $initial_env_type;

	public function set_up() {
		parent::set_up();

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		$this->initial_using_ext_object_cache = wp_using_ext_object_cache();
		$this->initial_env_type               = getenv( 'WP_ENVIRONMENT_TYPE' );

		// Default the environment to `production` so that the dynamic promotion
		// branch is reachable. Individual tests override this when needed.
		putenv( 'WP_ENVIRONMENT_TYPE=production' );

		// Ensure the page-cache detail transient is cleared between tests so
		// stale data from a previous test does not leak into the assertions.
		delete_transient( self::PAGE_CACHE_DETAIL_TRANSIENT );
	}

	public function tear_down() {
		// Restore persistent object cache flag.
		wp_using_ext_object_cache( $this->initial_using_ext_object_cache );

		// Restore environment variable.
		if ( false === $this->initial_env_type ) {
			putenv( 'WP_ENVIRONMENT_TYPE' );
		} else {
			putenv( 'WP_ENVIRONMENT_TYPE=' . $this->initial_env_type );
		}

		delete_transient( self::PAGE_CACHE_DETAIL_TRANSIENT );

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
	 * Data provider for tests covering the dynamic `conservative` → `moderate`
	 * default eagerness promotion introduced for page-cache-aware behavior.
	 *
	 * Each row describes:
	 *  - $using_ext_object_cache  Whether `wp_using_ext_object_cache()` is true.
	 *  - $page_cache_detail       Value to seed the page-cache detail transient
	 *                             with, or `null` to leave the transient unset.
	 *  - $expected_eagerness      Eagerness the function should resolve to.
	 *
	 * @return array<string, array{0: bool, 1: array<string, mixed>|null, 2: string}>
	 */
	public static function data_default_eagerness_with_page_cache(): array {
		return array(
			'no object cache, no transient'               => array(
				false,
				null,
				'conservative',
			),
			'object cache, no transient'                  => array(
				true,
				null,
				'conservative',
			),
			'object cache, transient missing cache signals' => array(
				true,
				array(
					'advanced_cache_present'   => false,
					'caching_response_headers' => array(),
				),
				'conservative',
			),
			'object cache, advanced-cache only'           => array(
				true,
				array(
					'advanced_cache_present'   => true,
					'caching_response_headers' => array(),
				),
				'moderate',
			),
			'object cache, caching response headers only' => array(
				true,
				array(
					'advanced_cache_present'   => false,
					'caching_response_headers' => array( 'x-cache-enabled' ),
				),
				'moderate',
			),
			'object cache, both signals present'          => array(
				true,
				array(
					'advanced_cache_present'   => true,
					'caching_response_headers' => array( 'cache-control', 'age' ),
				),
				'moderate',
			),
		);
	}

	/**
	 * When persistent object cache and a detected page cache are both present,
	 * the default eagerness should be promoted from `conservative` to `moderate`.
	 *
	 * Otherwise (no object cache, or no page cache detected), the default
	 * eagerness should remain `conservative`.
	 *
	 * @ticket 64066
	 *
	 * @dataProvider data_default_eagerness_with_page_cache
	 */
	public function test_default_eagerness_promoted_when_page_cache_detected(
		bool $using_ext_object_cache,
		?array $page_cache_detail,
		string $expected_eagerness
	) {
		wp_using_ext_object_cache( $using_ext_object_cache );

		if ( null !== $page_cache_detail ) {
			set_transient( self::PAGE_CACHE_DETAIL_TRANSIENT, $page_cache_detail );
		}

		$config = wp_get_speculation_rules_configuration();

		$this->assertIsArray( $config );
		$this->assertSame( 'prefetch', $config['mode'] );
		$this->assertSame( $expected_eagerness, $config['eagerness'] );
	}

	/**
	 * The dynamic `moderate` promotion must only occur on production sites.
	 * On local, development, and staging environments the default should stay
	 * `conservative`, even if a page cache has previously been detected.
	 *
	 * @ticket 64066
	 *
	 * @dataProvider data_non_production_environments
	 */
	public function test_default_eagerness_not_promoted_outside_production( string $environment ) {
		wp_using_ext_object_cache( true );

		set_transient(
			self::PAGE_CACHE_DETAIL_TRANSIENT,
			array(
				'advanced_cache_present'   => true,
				'caching_response_headers' => array( 'cache-control' ),
			)
		);

		putenv( 'WP_ENVIRONMENT_TYPE=' . $environment );

		$config = wp_get_speculation_rules_configuration();

		$this->assertIsArray( $config );
		$this->assertSame( 'prefetch', $config['mode'] );
		$this->assertSame(
			'conservative',
			$config['eagerness'],
			"Eagerness should remain 'conservative' in the '{$environment}' environment."
		);
	}

	/**
	 * Data provider for non-production environment values recognized by
	 * `wp_get_environment_type()`.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_non_production_environments(): array {
		return array(
			'local'       => array( 'local' ),
			'development' => array( 'development' ),
			'staging'     => array( 'staging' ),
		);
	}

	/**
	 * A `null` page-cache detail transient must not cause an error or warning.
	 * The function should gracefully fall back to the `conservative` default.
	 *
	 * @ticket 64066
	 */
	public function test_null_page_cache_detail_transient_does_not_promote() {
		wp_using_ext_object_cache( true );

		// Explicitly ensure the transient is absent.
		delete_transient( self::PAGE_CACHE_DETAIL_TRANSIENT );

		$config = wp_get_speculation_rules_configuration();

		$this->assertIsArray( $config );
		$this->assertSame( 'conservative', $config['eagerness'] );
	}

	/**
	 * A transient whose value is not an array (e.g. a serialized boolean
	 * left over from a corrupt update) must not promote the default eagerness
	 * and must not emit warnings under `beStrictAboutOutputDuringTests`.
	 *
	 * @ticket 64066
	 */
	public function test_non_array_page_cache_detail_transient_does_not_promote() {
		wp_using_ext_object_cache( true );

		// Use the underlying option API directly to bypass array-shape validation
		// that `set_transient()` may apply in future versions.
		update_option( '_transient_' . self::PAGE_CACHE_DETAIL_TRANSIENT, 'yes' );

		$config = wp_get_speculation_rules_configuration();

		$this->assertIsArray( $config );
		$this->assertSame( 'conservative', $config['eagerness'] );

		delete_option( '_transient_' . self::PAGE_CACHE_DETAIL_TRANSIENT );
	}

	/**
	 * The dynamic promotion must respect an explicit filter value provided by
	 * a plugin. A plugin that forces `eagerness` to a non-`auto` value should
	 * not see its configuration silently overridden by the new auto-detection
	 * logic.
	 *
	 * @ticket 64066
	 */
	public function test_explicit_plugin_filter_overrides_dynamic_promotion() {
		wp_using_ext_object_cache( true );

		set_transient(
			self::PAGE_CACHE_DETAIL_TRANSIENT,
			array(
				'advanced_cache_present'   => true,
				'caching_response_headers' => array( 'cache-control' ),
			)
		);

		add_filter(
			'wp_speculation_rules_configuration',
			static function () {
				return array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				);
			}
		);

		$config = wp_get_speculation_rules_configuration();

		$this->assertIsArray( $config );
		$this->assertSame(
			'eager',
			$config['eagerness'],
			"An explicit 'eager' filter value must not be downgraded to 'moderate'."
		);
	}
}
