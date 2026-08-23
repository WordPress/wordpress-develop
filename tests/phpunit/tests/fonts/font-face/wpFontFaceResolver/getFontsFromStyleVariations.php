<?php
/**
 * Test case for WP_Font_Face_Resolver::get_fonts_from_style_variations().
 *
 * @package    WordPress
 * @subpackage Fonts
 *
 * @since 6.7.0
 *
 * @group fonts
 * @group fontface
 *
 * @covers WP_Font_Face_Resolver::get_fonts_from_style_variations
 */
class Tests_Fonts_WPFontFaceResolver_GetFontsFromStyleVariations extends WP_Font_Face_UnitTestCase {
	const FONTS_THEME = 'fonts-block-theme';

	public static function set_up_before_class() {
		self::$requires_switch_theme_fixtures = true;

		parent::set_up_before_class();
	}

	/**
	 * Ensure that an empty array is returned when the theme has no style variations.
	 *
	 * @ticket 62231
	 */
	public function test_should_return_empty_array_when_theme_has_no_style_variations() {
		switch_theme( 'block-theme' );

		$fonts = WP_Font_Face_Resolver::get_fonts_from_style_variations();
		$this->assertIsArray( $fonts, 'Should return an array data type' );
		$this->assertEmpty( $fonts, 'Should return an empty array' );
	}

	/**
	 * Ensure that all variations are loaded from a theme.
	 *
	 * @ticket 62231
	 */
	public function test_should_return_all_fonts_from_all_style_variations() {
		switch_theme( static::FONTS_THEME );

		$actual   = WP_Font_Face_Resolver::get_fonts_from_style_variations();
		$expected = self::get_custom_style_variations( 'expected' );

		$this->assertSame( $expected, $actual, 'All the fonts from the theme variations should be returned.' );
	}

	/**
	 * Ensure that file:./ is replaced in the src list.
	 *
	 * @ticket 62231
	 */
	public function test_should_replace_src_file_placeholder() {
		switch_theme( static::FONTS_THEME );

		$fonts = WP_Font_Face_Resolver::get_fonts_from_style_variations();

		// Check that the there is no theme relative url in the src list.
		foreach ( $fonts as $family ) {
			foreach ( $family as $font ) {
				foreach ( $font['src'] as $src ) {
					$src_basename = basename( $src );
					$this->assertStringNotContainsString( 'file:./', $src, "Font $src_basename should not contain the 'file:./' placeholder" );
				}
			}
		}
	}
}
