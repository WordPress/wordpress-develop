<?php
/**
 * Tests for native performance optimization functions.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Tests performance optimization helpers.
 *
 * @group performance
 */
class Tests_Performance extends WP_UnitTestCase {
	/**
	 * Cleans up settings after each test.
	 */
	public function tear_down() {
		delete_option( 'performance_optimization' );
		parent::tear_down();
	}

	/**
	 * Tests sanitizing performance settings.
	 */
	public function test_wp_sanitize_performance_optimization_settings_preserves_defaults() {
		$settings = wp_sanitize_performance_optimization_settings(
			array(
				'page_cache'    => '1',
				'lazy_loading'  => '0',
				'unknown_value' => true,
			)
		);

		$this->assertTrue( $settings['page_cache'] );
		$this->assertFalse( $settings['lazy_loading'] );
		$this->assertFalse( $settings['minify_assets'] );
		$this->assertArrayNotHasKey( 'unknown_value', $settings );
	}

	/**
	 * Tests disabling lazy loading removes only lazy loading attributes.
	 */
	public function test_wp_performance_filter_loading_optimization_attributes_removes_lazy_loading_when_disabled() {
		update_option(
			'performance_optimization',
			array(
				'lazy_loading' => false,
			)
		);

		$attributes = wp_performance_filter_loading_optimization_attributes(
			array(
				'loading'       => 'lazy',
				'fetchpriority' => 'high',
			),
			'img'
		);

		$this->assertArrayNotHasKey( 'loading', $attributes );
		$this->assertSame( 'high', $attributes['fetchpriority'] );
	}

	/**
	 * Tests image output mappings are unchanged while image optimization is disabled.
	 */
	public function test_wp_performance_filter_image_editor_output_format_returns_existing_map_when_disabled() {
		$output_format = array(
			'image/heic' => 'image/jpeg',
		);

		$this->assertSame( $output_format, wp_performance_filter_image_editor_output_format( $output_format ) );
	}

	/**
	 * Tests critical CSS generation from inline styles.
	 */
	public function test_wp_add_performance_critical_css_adds_inline_block() {
		$html = '<html><head><style>.site { color: red; }</style></head><body></body></html>';

		$this->assertStringContainsString(
			'<style id="wp-critical-css">.site{color:red;}</style>',
			wp_add_performance_critical_css( $html )
		);
	}

	/**
	 * Tests output minification.
	 */
	public function test_wp_minify_performance_output_minifies_inline_css_and_html() {
		$html = "<html>\n<head><style>.site { color: red; }</style></head>\n<body><!-- remove me --><p>Test</p></body>\n</html>";

		$this->assertSame(
			'<html><head><style>.site{color:red;}</style></head><body><p>Test</p></body></html>',
			wp_minify_performance_output( $html )
		);
	}
}
