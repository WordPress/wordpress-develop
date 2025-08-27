<?php
/**
 * Tests for the wp_get_speculation_rules() function.
 *
 * @package WordPress
 * @subpackage Speculative Loading
 */

/**
 * @group speculative-loading
 * @covers ::wp_get_speculation_rules
 */
class Tests_Speculative_Loading_wpGetSpeculationRules extends WP_UnitTestCase {

	private $prefetch_config  = array(
		'mode'      => 'prefetch',
		'eagerness' => 'conservative',
	);
	private $prerender_config = array(
		'mode'      => 'prerender',
		'eagerness' => 'conservative',
	);

	public function set_up() {
		parent::set_up();

		add_filter(
			'template_directory_uri',
			static function () {
				return content_url( 'themes/template' );
			}
		);

		add_filter(
			'stylesheet_directory_uri',
			static function () {
				return content_url( 'themes/stylesheet' );
			}
		);

		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
	}

	/**
	 * Tests speculation rules output with prefetch for the different eagerness levels.
	 *
	 * @ticket 62503
	 * @dataProvider data_eagerness
	 */
	public function test_wp_get_speculation_rules_with_prefetch( string $eagerness ) {
		remove_all_filters( 'wp_speculation_rules_configuration' );
		add_filter(
			'wp_speculation_rules_configuration',
			static function () use ( $eagerness ) {
				return array(
					'mode'      => 'prefetch',
					'eagerness' => $eagerness,
				);
			}
		);

		$rules = wp_get_speculation_rules();

		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertIsArray( $rules['prefetch'] );
		foreach ( $rules['prefetch'] as $entry ) {
			$this->assertIsArray( $entry );
			$this->assertArrayHasKey( 'source', $entry );
			$this->assertSame( 'document', $entry['source'] );
			$this->assertArrayHasKey( 'eagerness', $entry );
			$this->assertSame( $eagerness, $entry['eagerness'] );
		}
	}

	/**
	 * Tests speculation rules output with prerender for the different eagerness levels.
	 *
	 * @ticket 62503
	 * @dataProvider data_eagerness
	 */
	public function test_wp_get_speculation_rules_with_prerender( string $eagerness ) {
		remove_all_filters( 'wp_speculation_rules_configuration' );
		add_filter(
			'wp_speculation_rules_configuration',
			static function () use ( $eagerness ) {
				return array(
					'mode'      => 'prerender',
					'eagerness' => $eagerness,
				);
			}
		);

		$rules = wp_get_speculation_rules();

		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertIsArray( $rules['prerender'] );
		foreach ( $rules['prerender'] as $entry ) {
			$this->assertIsArray( $entry );
			$this->assertArrayHasKey( 'source', $entry );
			$this->assertSame( 'document', $entry['source'] );
			$this->assertArrayHasKey( 'eagerness', $entry );
			$this->assertSame( $eagerness, $entry['eagerness'] );
		}
	}

	public static function data_eagerness(): array {
		return array(
			array( 'conservative' ),
			array( 'moderate' ),
			array( 'eager' ),
		);
	}

	/**
	 * Tests that the number of entries included for prefetch configuration is correct.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_prefetch_entries() {
		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prefetch_config;
			}
		);

		$rules = wp_get_speculation_rules();

		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertCount( 4, $rules['prefetch'][0]['where']['and'] );
		$this->assertArrayHasKey( 'not', $rules['prefetch'][0]['where']['and'][3] );
		$this->assertArrayHasKey( 'selector_matches', $rules['prefetch'][0]['where']['and'][3]['not'] );
		$this->assertSame( '.no-prefetch, .no-prefetch a', $rules['prefetch'][0]['where']['and'][3]['not']['selector_matches'] );
	}

	/**
	 * Tests that the number of entries included for prerender configuration is correct.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_prerender_entries() {
		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prerender_config;
			}
		);

		$rules = wp_get_speculation_rules();

		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertCount( 5, $rules['prerender'][0]['where']['and'] );
		$this->assertArrayHasKey( 'not', $rules['prerender'][0]['where']['and'][3] );
		$this->assertArrayHasKey( 'selector_matches', $rules['prerender'][0]['where']['and'][3]['not'] );
		$this->assertSame( '.no-prerender, .no-prerender a', $rules['prerender'][0]['where']['and'][3]['not']['selector_matches'] );
		$this->assertArrayHasKey( 'not', $rules['prerender'][0]['where']['and'][4] );
		$this->assertArrayHasKey( 'selector_matches', $rules['prerender'][0]['where']['and'][4]['not'] );
		$this->assertSame( '.no-prefetch, .no-prefetch a', $rules['prerender'][0]['where']['and'][4]['not']['selector_matches'] );
	}

	/**
	 * Tests the default exclude paths and ensures they cannot be altered via filter.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_href_exclude_paths() {
		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prefetch_config;
			}
		);

		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		$this->assertSameSets(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?(.+)',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);

		// Add filter that attempts to replace base exclude paths with a custom path to exclude.
		add_filter(
			'wp_speculation_rules_href_exclude_paths',
			static function () {
				return array( 'custom-file.php' );
			}
		);

		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the base exclude paths are still present and that the custom path was formatted correctly.
		$this->assertSameSets(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?(.+)',
				'/custom-file.php',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * Tests the default exclude paths and ensures they cannot be altered via filter.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_href_exclude_paths_without_pretty_permalinks() {
		update_option( 'permalink_structure', '' );

		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prefetch_config;
			}
		);

		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		$this->assertSameSets(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?*(^|&)*nonce*=*',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * Tests that exclude paths can be altered specifically based on the mode used.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_href_exclude_paths_with_mode() {
		// Add filter that adds an exclusion only if the mode is 'prerender'.
		add_filter(
			'wp_speculation_rules_href_exclude_paths',
			static function ( $exclude_paths, $mode ) {
				if ( 'prerender' === $mode ) {
					$exclude_paths[] = '/products/*';
				}
				return $exclude_paths;
			},
			10,
			2
		);

		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prerender_config;
			}
		);
		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the additional exclusion is present because the mode is 'prerender'.
		// Also ensure keys are sequential starting from 0 (that is, that array_is_list()).
		$this->assertSame(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?(.+)',
				'/products/*',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);

		// Redo with 'prefetch'.
		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prefetch_config;
			}
		);
		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the additional exclusion is not present because the mode is 'prefetch'.
		$this->assertSame(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?(.+)',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * Tests filter that explicitly adds non-sequential keys.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_with_filtering_bad_keys() {

		add_filter(
			'wp_speculation_rules_href_exclude_paths',
			static function ( array $exclude_paths ): array {
				$exclude_paths[] = '/next/';
				array_unshift( $exclude_paths, '/unshifted/' );
				$exclude_paths[-1]  = '/negative-one/';
				$exclude_paths[100] = '/one-hundred/';
				$exclude_paths['a'] = '/letter-a/';
				return $exclude_paths;
			}
		);

		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prerender_config;
			}
		);
		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];
		$this->assertSame(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?(.+)',
				'/unshifted/',
				'/next/',
				'/negative-one/',
				'/one-hundred/',
				'/letter-a/',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * Tests scenario when the home_url and site_url have different paths.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_different_home_and_site_urls() {
		add_filter(
			'site_url',
			static function (): string {
				return 'https://example.com/wp/';
			}
		);
		add_filter(
			'home_url',
			static function (): string {
				return 'https://example.com/blog/';
			}
		);
		add_filter(
			'wp_speculation_rules_href_exclude_paths',
			static function ( array $exclude_paths ): array {
				$exclude_paths[] = '/store/*';
				return $exclude_paths;
			}
		);

		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prerender_config;
			}
		);
		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];
		$this->assertSame(
			array(
				'/wp/wp-*.php',
				'/wp/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/blog/*\\?(.+)',
				'/blog/store/*',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * Tests that passing an invalid configuration to the function does not lead to unexpected problems.
	 *
	 * This is mostly an integration test as it is resolved as part of wp_get_speculation_rules_configuration().
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_with_invalid_configuration() {
		add_filter(
			'wp_speculation_rules_configuration',
			static function () {
				return array(
					'mode'      => 'none',
					'eagerness' => 'none',
				);
			}
		);
		$rules = wp_get_speculation_rules();

		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$rules = $rules->jsonSerialize();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertSame( 'conservative', $rules['prefetch'][0]['eagerness'] );
	}

	/**
	 * Tests that passing no configuration (`null`) results in no speculation rules being returned.
	 *
	 * This is used to effectively disable the feature.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_with_null() {
		add_filter( 'wp_speculation_rules_configuration', '__return_null' );

		$rules = wp_get_speculation_rules();
		$this->assertNull( $rules );
	}

	/**
	 * Tests that the 'wp_load_speculation_rules' action allows providing additional rules.
	 *
	 * @ticket 62503
	 */
	public function test_wp_get_speculation_rules_with_additional_rules() {
		$filtered_obj = null;
		add_action(
			'wp_load_speculation_rules',
			static function ( $speculation_rules ) use ( &$filtered_obj ) {
				$filtered_obj = $speculation_rules;

				/*
				 * In practice, these rules would ensure that links marked with the classes would be opt in to
				 * prerendering with moderate and eager eagerness respectively.
				 */
				$speculation_rules->add_rule(
					'prerender',
					'prerender-moderate-marked-links',
					array(
						'source'    => 'document',
						'where'     => array(
							'selector_matches' => '.moderate-prerender, .moderate-prerender a',
						),
						'eagerness' => 'moderate',
					)
				);
				$speculation_rules->add_rule(
					'prerender',
					'prerender-eager-marked-links',
					array(
						'source'    => 'document',
						'where'     => array(
							'selector_matches' => '.eager-prerender, .eager-prerender a',
						),
						'eagerness' => 'eager',
					)
				);
			}
		);

		add_filter(
			'wp_speculation_rules_configuration',
			function () {
				return $this->prefetch_config;
			}
		);
		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( WP_Speculation_Rules::class, $rules );
		$this->assertSame( $filtered_obj, $rules );

		$rules = $rules->jsonSerialize();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertCount( 1, $rules['prefetch'] );
		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertCount( 2, $rules['prerender'] );
		$this->assertSame( 'conservative', $rules['prefetch'][0]['eagerness'] );
		$this->assertSame( 'moderate', $rules['prerender'][0]['eagerness'] );
		$this->assertSame( 'eager', $rules['prerender'][1]['eagerness'] );
	}
}
