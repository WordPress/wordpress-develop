<?php
/**
 * Test case for wp_print_font_faces_from_style_variations().
 *
 * @package    WordPress
 * @subpackage Fonts
 *
 * @since 6.7.0
 *
 * @group fonts
 * @group fontface
 *
 * @covers wp_print_font_faces_from_style_variations
 */
class Tests_Fonts_WpPrintFontFacesFromStyleVariations extends WP_Font_Face_UnitTestCase {
	const FONTS_THEME = 'fonts-block-theme';

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$requires_switch_theme_fixtures = true;
	}

	/**
	 * Ensure that no fonts are printed when the theme has no fonts.
	 *
	 * @ticket 62231
	 */
	public function test_should_not_print_when_no_fonts() {
		switch_theme( 'block-theme' );

		$this->expectOutputString( '' );
		wp_print_font_faces_from_style_variations();
	}

	/**
	 * Ensure that all fonts are printed from the theme style variations.
	 *
	 * @ticket 62231
	 */
	public function test_should_print_fonts_in_style_variations() {
		switch_theme( static::FONTS_THEME );

		$expected        = $this->get_custom_style_variations( 'expected_styles' );
		$expected_output = $this->get_expected_styles_output( $expected );

		$this->expectOutputString( $expected_output );
		wp_print_font_faces_from_style_variations();
	}

	private function get_expected_styles_output( $styles ) {
		$style_element = "<style class='wp-fonts-local' type='text/css'>\n%s\n</style>\n";
		return sprintf( $style_element, $styles );
	}
}
