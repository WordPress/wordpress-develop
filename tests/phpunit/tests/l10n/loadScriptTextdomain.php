<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::load_script_textdomain
 */
class Tests_L10n_LoadScriptTextdomain extends WP_UnitTestCase {

	/**
	 * @ticket 45528
	 * @ticket 46336
	 * @ticket 46387
	 * @ticket 49145
	 * @ticket 60891
	 * @ticket 62016
	 *
	 * @dataProvider data_resolve_relative_path
	 */
	public function test_resolve_relative_path( $translation_path, $handle, $src, $textdomain, $filter = array() ) {
		if ( ! empty( $filter ) ) {
			add_filter( $filter[0], $filter[1], 10, $filter[2] ?? 1 );
		}
		wp_enqueue_script( $handle, $src, array(), null );

		$expected = file_get_contents( DIR_TESTDATA . $translation_path );
		$this->assertSame( $expected, load_script_textdomain( $handle, $textdomain, DIR_TESTDATA . '/languages' ) );
	}

	public static function data_resolve_relative_path() {
		return array(
			// @ticket 45528
			array(
				'/languages/en_US-813e104eb47e13dd4cc5af844c618754.json',
				'test-example-root',
				'/wp-includes/js/script.js',
				'default',
			),
			// Assets on a CDN.
			array(
				'/languages/en_US-813e104eb47e13dd4cc5af844c618754.json',
				'test-example-cdn',
				'https://my-cdn.com/wordpress/wp-includes/js/script.js',
				'default',
				array( 'load_script_textdomain_relative_path', array( __CLASS__, 'relative_path_from_cdn' ), 2 ),
			),
			// Test for WordPress installs in a subdirectory.
			array(
				'/languages/en_US-813e104eb47e13dd4cc5af844c618754.json',
				'test-example-subdir',
				'/wp/wp-includes/js/script.js',
				'default',
				array(
					'site_url',
					static function ( $site_url ) {
						return $site_url . '/wp';
					},
				),
			),
			// @ticket 46336
			array(
				'/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
				'plugin-example-1',
				'https://plugins.example.com/my-plugin/js/script.js',
				'internationalized-plugin',
				array(
					'plugins_url',
					static function () {
						return 'https://plugins.example.com';
					},
				),
			),
			// @ticket 46387
			array(
				'/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
				'plugin-example-2',
				'https://content.example.com/plugins/my-plugin/js/script.js',
				'internationalized-plugin',
				array(
					'content_url',
					static function () {
						return 'https://content.example.com';
					},
				),
			),
			// @ticket 49145
			array(
				'/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
				'test-when-no-content_url-host',
				'https://content.example.com/plugins/my-plugin/js/script.js',
				'internationalized-plugin',
				array(
					'content_url',
					static function () {
						return '/';
					},
				),
			),
			// @ticket 49145
			array(
				'/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
				'test-when-no-plugins_url-host',
				'https://plugins.example.com/my-plugin/js/script.js',
				'internationalized-plugin',
				array(
					'plugins_url',
					static function () {
						return '/';
					},
				),
			),
			// @ticket 49145
			array(
				'/languages/en_US-813e104eb47e13dd4cc5af844c618754.json',
				'test-when-no-site_url-host',
				'/wp/wp-includes/js/script.js',
				'default',
				array(
					'site_url',
					static function () {
						return '/wp';
					},
				),
			),
			// @ticket 60891
			array(
				'/languages/plugins/internationalized-plugin-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
				'plugin-in-custom-plugin-dir',
				'/wp-content/mods/my-plugin/js/script.js',
				'internationalized-plugin',
				array(
					'plugins_url',
					static function () {
						return 'https://example.com/wp-content/mods';
					},
				),
			),
			// @ticket 62016
			array(
				'/languages/themes/internationalized-theme-en_US-2f86cb96a0233e7cb3b6f03ad573be0b.json',
				'theme-with-script-translations',
				'/wp-content/themes/my-theme/js/script.js',
				'internationalized-theme',
			),
		);
	}

	public static function relative_path_from_cdn( $relative, $src ) {
		if ( 0 === strpos( $src, 'https://my-cdn.com/wordpress/' ) ) {
			return substr( $src, strlen( 'https://my-cdn.com/wordpress/' ) );
		}

		return $relative;
	}

	/**
	 * Tests that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when passing the default `$path` to untrailingslashit() in the function.
	 *
	 * The notice that we should not see:
	 * `Deprecated: rtrim(): Passing null to parameter #1 ($string) of type string is deprecated`.
	 *
	 * @ticket 55967
	 */
	public function test_does_not_throw_deprecation_notice_for_rtrim_with_default_parameters() {
		$handle = 'test-example-root';
		$src    = '/wp-includes/js/script.js';

		wp_enqueue_script( $handle, $src );

		$expected = file_get_contents( DIR_TESTDATA . '/languages/en_US-813e104eb47e13dd4cc5af844c618754.json' );
		$this->assertSame( $expected, load_script_textdomain( $handle ) );
	}

	/**
	 * Tests that an unparseable script source URL short-circuits to
	 * `load_script_translations( false, ... )` instead of falling through
	 * to the relative-path computation.
	 *
	 * @ticket 65015
	 */
	public function test_unparseable_src_returns_false(): void {
		$handle = 'test-unparseable-src';
		$src    = 'http:///example';

		$this->assertFalse( wp_parse_url( $src ), 'Test prerequisite failed: the test src should be unparseable.' );

		wp_enqueue_script( $handle, $src, array(), null );

		$this->assertFalse( load_script_textdomain( $handle, 'default', DIR_TESTDATA . '/languages' ) );
	}

	/**
	 * Tests that an unparseable `content_url()` return value short-circuits
	 * to `load_script_translations( false, ... )` instead of computing
	 * `$relative` from a corrupted parsed-URL array.
	 *
	 * The `MockAction` spy on `pre_load_script_translations` is necessary
	 * here because the function's tail end also calls `load_script_translations( false, ... )`,
	 * so a regression that bypasses the early return would still return false
	 * via the fallback path. Asserting on the recorded `$file` arguments pins
	 * the test to the intended branch.
	 *
	 * @ticket 65015
	 */
	public function test_unparseable_content_url_returns_false(): void {
		$handle = 'test-unparseable-content-url';
		$src    = '/wp-includes/js/script.js';

		add_filter(
			'content_url',
			static function () {
				return 'http:///example';
			}
		);

		$mock = new MockAction();
		add_filter( 'pre_load_script_translations', array( $mock, 'filter' ), 10, 4 );

		wp_enqueue_script( $handle, $src, array(), null );

		$this->assertFalse( load_script_textdomain( $handle, 'default', DIR_TESTDATA . '/languages' ) );
		$this->assertSame(
			array(
				DIR_TESTDATA . '/languages/en_US-' . $handle . '.json',
				false,
			),
			array_column( $mock->get_args(), 1 ),
			'Expected the unparseable content_url branch to short-circuit before any relative-path lookup.'
		);
	}

	/**
	 * Tests that the `load_script_textdomain_relative_path` filter returning
	 * a non-string, non-false value (e.g., a callback that forgets to return)
	 * short-circuits via the `! is_string( $relative )` guard rather than
	 * falling through to string functions like `str_ends_with()` and `md5()`.
	 *
	 * @ticket 65015
	 */
	public function test_non_string_relative_path_filter_returns_false(): void {
		$handle = 'test-non-string-relative-path';
		$src    = '/wp-includes/js/script.js';

		add_filter( 'load_script_textdomain_relative_path', '__return_null' );

		wp_enqueue_script( $handle, $src, array(), null );

		$this->assertFalse( load_script_textdomain( $handle, 'default', DIR_TESTDATA . '/languages' ) );
	}

	/**
	 * Tests that a script source URL with no path component does not trigger
	 * an undefined index warning when the path is read further down in the
	 * function. The result is reached via the regular fallback path
	 * (no host/path match) rather than an early return.
	 *
	 * @ticket 65015
	 */
	public function test_src_without_path_component_does_not_warn(): void {
		$handle = 'test-src-without-path';
		$src    = 'https://example.com';

		$parsed = wp_parse_url( $src );
		$this->assertIsArray( $parsed, 'Test prerequisite failed: the test src should parse.' );
		$this->assertArrayNotHasKey( 'path', $parsed, 'Test prerequisite failed: the test src should have no path component.' );

		wp_enqueue_script( $handle, $src, array(), null );

		$this->assertFalse( load_script_textdomain( $handle, 'default', DIR_TESTDATA . '/languages' ) );
	}
}
