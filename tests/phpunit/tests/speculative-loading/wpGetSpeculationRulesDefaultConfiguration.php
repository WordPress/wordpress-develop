<?php
/**
 * Tests for the wp_get_speculation_rules_default_configuration() function.
 *
 * @package WordPress
 * @subpackage Speculative Loading
 */

/**
 * @group speculative-loading
 * @covers ::wp_get_speculation_rules_default_configuration
 */
class Tests_Speculative_Loading_wpGetSpeculationRulesDefaultConfiguration extends WP_UnitTestCase {

	/**
	 * Stores the original speculative loading env values for cleanup.
	 *
	 * @var array<string, string|false>
	 */
	private array $original_env = array();

	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'WP_SPECULATIVE_LOADING_DEFAULT_MODE', 'WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS' ) as $name ) {
			$this->original_env[ $name ] = getenv( $name );
		}
	}

	public function tear_down(): void {
		foreach ( $this->original_env as $name => $value ) {
			if ( false === $value ) {
				putenv( $name );
			} else {
				putenv( "{$name}={$value}" );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that WordPress Core defaults to a conservative prefetch.
	 *
	 * @ticket 65624
	 */
	public function test_core_defaults(): void {
		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'conservative',
			),
			wp_get_speculation_rules_default_configuration()
		);
	}

	/**
	 * Tests that the defaults can be overridden by environment variables.
	 *
	 * @ticket 65624
	 * @dataProvider data_environment_variables
	 *
	 * @param string|null           $mode      Value for the mode environment variable, or null to leave it unset.
	 * @param string|null           $eagerness Value for the eagerness environment variable, or null to leave it unset.
	 * @param array<string, string> $expected  Expected default configuration.
	 */
	public function test_environment_variables( ?string $mode, ?string $eagerness, array $expected ): void {
		if ( null !== $mode ) {
			putenv( "WP_SPECULATIVE_LOADING_DEFAULT_MODE={$mode}" );
		}
		if ( null !== $eagerness ) {
			putenv( "WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS={$eagerness}" );
		}

		$this->assertSame( $expected, wp_get_speculation_rules_default_configuration() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{
	 *     0: string|null,
	 *     1: string|null,
	 *     2: array{
	 *         mode: string,
	 *         eagerness: string,
	 *     },
	 * }> Test parameters, keyed by test case name. Each entry consists of the value for the mode environment
	 *    variable, the value for the eagerness environment variable, and the expected default configuration. A
	 *    `null` environment variable value means the variable is left unset.
	 */
	public static function data_environment_variables(): array {
		return array(
			'moderate eagerness'            => array(
				null,
				'moderate',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
			),
			'eager eagerness'               => array(
				null,
				'eager',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'eager',
				),
			),
			'prerender mode'                => array(
				'prerender',
				null,
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
			),
			'both mode and eagerness'       => array(
				'prerender',
				'moderate',
				array(
					'mode'      => 'prerender',
					'eagerness' => 'moderate',
				),
			),
			/*
			 * 'immediate' is a valid eagerness, but for safety WordPress does not allow it for document-level rules,
			 * so it must not be usable as the default either.
			 */
			'immediate eagerness'           => array(
				null,
				'immediate',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'invalid mode'                  => array(
				'invalid',
				null,
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'invalid eagerness'             => array(
				null,
				'invalid',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'empty string'                  => array(
				'',
				'',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'invalid mode, valid eagerness' => array(
				'invalid',
				'moderate',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'moderate',
				),
			),
			'valid mode, invalid eagerness' => array(
				'prerender',
				'invalid',
				array(
					'mode'      => 'prerender',
					'eagerness' => 'conservative',
				),
			),
			'wrong case is not normalized'  => array(
				'PREFETCH',
				'MODERATE',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
			'auto is not a valid env value' => array(
				'auto',
				'auto',
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				),
			),
		);
	}

	/**
	 * Tests that the defaults can be overridden by constants.
	 *
	 * @ticket 65624
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constants(): void {
		define( 'WP_SPECULATIVE_LOADING_DEFAULT_MODE', 'prerender' );
		define( 'WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS', 'moderate' );

		$this->assertSame(
			array(
				'mode'      => 'prerender',
				'eagerness' => 'moderate',
			),
			wp_get_speculation_rules_default_configuration()
		);
	}

	/**
	 * Tests that a constant takes precedence over an environment variable.
	 *
	 * @ticket 65624
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_overrides_environment_variable(): void {
		putenv( 'WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS=eager' );
		define( 'WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS', 'moderate' );

		$config = wp_get_speculation_rules_default_configuration();

		$this->assertSame( 'moderate', $config['eagerness'] );
	}

	/**
	 * Tests that an invalid constant falls back to the Core default rather than to the environment variable.
	 *
	 * @ticket 65624
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_invalid_constant_falls_back_to_core_default(): void {
		putenv( 'WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS=eager' );
		define( 'WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS', 'invalid' );

		$config = wp_get_speculation_rules_default_configuration();

		$this->assertSame( 'conservative', $config['eagerness'] );
	}
}
