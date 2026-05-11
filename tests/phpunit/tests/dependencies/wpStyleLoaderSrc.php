<?php

/**
 * Test wp_style_loader_src().
 *
 * @group dependencies
 * @group scripts
 *
 * @covers ::wp_style_loader_src
 */
class Tests_Dependencies_wpStyleLoaderSrc extends WP_UnitTestCase {

	public ?WP_Styles $original_styles;

	public ?WP_Screen $original_screen;

	public function set_up(): void {
		global $wp_styles, $current_screen;
		parent::set_up();

		$this->original_styles = $wp_styles;
		$this->original_screen = $current_screen;
		unset( $wp_styles, $current_screen );
	}

	public function tear_down(): void {
		global $wp_styles, $current_screen;
		$wp_styles      = $this->original_styles;
		$current_screen = $this->original_screen;
		parent::tear_down();
	}

	/**
	 * Tests that PHP warnings are not thrown when wp_style_loader_src() is called
	 * before the `$_wp_admin_css_colors` global is set within the admin.
	 *
	 * The warnings that we should not see:
	 * `Warning: Trying to access array offset on null`.
	 * `Warning: Attempt to read property "url" on null`.
	 *
	 * @ticket 61302
	 * @ticket 64762
	 *
	 * @covers ::wp_admin_bar_add_color_scheme_to_front_end
	 */
	public function test_without_wp_admin_css_colors_global_frontend(): void {
		unset( $GLOBALS['current_screen'] );
		$this->assertFalse( is_admin(), 'Expected not admin.' );
		wp_styles();
		wp_admin_bar_add_color_scheme_to_front_end();
		$inline_styles = wp_styles()->get_data( 'admin-bar', 'after' );
		$this->assertIsArray( $inline_styles, 'Expected inline styles to be added.' );
		$inline_css = implode( "\n", $inline_styles );
		$this->assertStringContainsString( '#wpadminbar', $inline_css );
		$this->assertStringNotContainsString( '/* Pointers */', $inline_css );
		$this->assertStringNotContainsString( '.wp-pointer', $inline_css );

		$style_loader_src = wp_style_loader_src( '', 'colors' );
		$this->assertIsString( $style_loader_src );
		$this->assertStringContainsString( '/colors.css', $style_loader_src );
	}

	/**
	 * Tests that nothing is done when in the admin.
	 *
	 * @ticket 61302
	 * @ticket 64762
	 *
	 * @covers ::wp_admin_bar_add_color_scheme_to_front_end
	 */
	public function test_without_wp_admin_css_colors_global_admin(): void {
		global $wp_styles;
		set_current_screen( 'index.php' );
		$wp_styles = null;
		$this->assertTrue( is_admin(), 'Expected admin.' );
		wp_styles();
		wp_admin_bar_add_color_scheme_to_front_end();
		$this->assertFalse( wp_styles()->get_data( 'admin-bar', 'after' ), 'Expected no inline style to be added in the admin.' );
	}
}
