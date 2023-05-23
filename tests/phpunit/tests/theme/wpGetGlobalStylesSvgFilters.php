<?php
/**
 * Tests wp_get_global_styles_svg_filters().
 *
 * @group themes
 */
class Tests_Theme_wpGetGlobalStylesSvgFilters extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		// Clear caches.
		wp_clean_themes_cache();
	}

	public function tear_down() {
		wp_clean_themes_cache();

		parent::tear_down();
	}

	/**
	 * Tests that switching themes recalculates the svgs.
	 *
	 * @covers ::wp_get_global_styles_svg_filters
	 *
	 * @ticket 57568
	 */
	public function test_switching_themes_should_recalculate_svg() {
		$svg_for_default_theme = wp_get_global_styles_svg_filters();
		switch_theme( 'block-theme' );
		$svg_for_block_theme = wp_get_global_styles_svg_filters();
		switch_theme( WP_DEFAULT_THEME );

		$this->assertStringContainsString( '<svg', $svg_for_default_theme, 'Default theme should contain SVG' );
		$this->assertStringContainsString( '<svg', $svg_for_default_theme, 'Block theme should contain SVG' );
		$this->assertNotSame( $svg_for_default_theme, $svg_for_block_theme, 'Cache value should have changed' );
	}
}
