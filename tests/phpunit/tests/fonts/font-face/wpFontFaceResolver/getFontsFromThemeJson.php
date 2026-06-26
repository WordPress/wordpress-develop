<?php
/**
 * Test case for WP_Font_Face_Resolver::get_fonts_from_theme_json().
 *
 * @package    WordPress
 * @subpackage Fonts
 *
 * @since 6.4.0
 *
 * @group fonts
 * @group fontface
 *
 * @covers WP_Font_Face_Resolver::get_fonts_from_theme_json
 */
class Tests_Fonts_WPFontFaceResolver_GetFontsFromThemeJson extends WP_Font_Face_UnitTestCase {
	const FONTS_THEME = 'fonts-block-theme';

	public static function set_up_before_class() {
		self::$requires_switch_theme_fixtures = true;

		parent::set_up_before_class();
	}

	public function test_should_return_empty_array_when_no_fonts_defined_in_theme() {
		switch_theme( 'block-theme' );

		$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		$this->assertIsArray( $fonts, 'Should return an array data type' );
		$this->assertEmpty( $fonts, 'Should return an empty array' );
	}

	public function test_should_return_all_fonts_from_theme() {
		switch_theme( static::FONTS_THEME );

		$actual   = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		$expected = $this->get_expected_fonts_for_fonts_block_theme( 'fonts' );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 60605
	 */
	public function test_should_return_all_fonts_from_all_theme_origins() {
		switch_theme( static::FONTS_THEME );

		$add_custom_fonts = static function ( $theme_json_data ) {
			$data = $theme_json_data->get_data();
			// Add font families to the custom origin of theme json.
			$data['settings']['typography']['fontFamilies']['custom'] = self::get_custom_font_families( 'input' );
			return new WP_Theme_JSON_Data( $data );
		};

		add_filter( 'wp_theme_json_data_theme', $add_custom_fonts );
		$actual = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		remove_filter( 'wp_theme_json_data_theme', $add_custom_fonts );

		$expected = array_merge(
			$this->get_expected_fonts_for_fonts_block_theme( 'fonts' ),
			$this->get_custom_font_families( 'expected' )
		);

		$this->assertSame( $expected, $actual, 'Both the fonts from the theme and the custom origin should be returned.' );
	}

	/**
	 * @dataProvider data_should_replace_src_file_placeholder
	 *
	 * @param string $font_name  Font's name.
	 * @param string $font_weight Font's weight.
	 * @param string $font_style  Font's style.
	 * @param string $expected   Expected src.
	 */
	public function test_should_replace_src_file_placeholder( $font_name, $font_weight, $font_style, $expected ) {
		switch_theme( static::FONTS_THEME );

		$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		$fonts = array_merge( array(), ...array_map( 'array_values', $fonts ) );

		$font = array_filter(
			$fonts,
			static function ( $font ) use ( $font_name, $font_weight, $font_style ) {
				return $font['font-family'] === $font_name
				&& $font['font-weight'] === $font_weight
				&& $font['font-style'] === $font_style;
			}
		);

		$font = reset( $font );

		$expected = get_stylesheet_directory_uri() . $expected;
		$actual   = $font['src'][0];

		$this->assertStringNotContainsString( 'file:./', $actual, 'Font src should not contain the "file:./" placeholder' );
		$this->assertSame( $expected, $actual, 'Font src should be an URL to its file' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_replace_src_file_placeholder() {
		return array(
			// Theme's theme.json.
			'DM Sans: 400 normal'              => array(
				'font_name'   => 'DM Sans',
				'font_weight' => '400',
				'font_style'  => 'normal',
				'expected'    => '/assets/fonts/dm-sans/DMSans-Regular.woff2',
			),
			'DM Sans: 400 italic'              => array(
				'font_name'   => 'DM Sans',
				'font_weight' => '400',
				'font_style'  => 'italic',
				'expected'    => '/assets/fonts/dm-sans/DMSans-Regular-Italic.woff2',
			),
			'DM Sans: 700 normal'              => array(
				'font_name'   => 'DM Sans',
				'font_weight' => '700',
				'font_style'  => 'normal',
				'expected'    => '/assets/fonts/dm-sans/DMSans-Bold.woff2',
			),
			'DM Sans: 700 italic'              => array(
				'font_name'   => 'DM Sans',
				'font_weight' => '700',
				'font_style'  => 'italic',
				'expected'    => '/assets/fonts/dm-sans/DMSans-Bold-Italic.woff2',
			),
			'Source Serif Pro: 200-900 normal' => array(
				'font_name'   => 'Source Serif Pro',
				'font_weight' => '200 900',
				'font_style'  => 'normal',
				'expected'    => '/assets/fonts/source-serif-pro/SourceSerif4Variable-Roman.ttf.woff2',
			),
			'Source Serif Pro: 200-900 italic' => array(
				'font_name'   => 'Source Serif Pro',
				'font_weight' => '200 900',
				'font_style'  => 'italic',
				'expected'    => '/assets/fonts/source-serif-pro/SourceSerif4Variable-Italic.ttf.woff2',
			),
		);
	}

	/**
	 * @dataProvider data_should_get_font_family_name
	 *
	 * @param array  $fonts         Fonts to test.
	 * @param string $expected_name Expected font-family name.
	 */
	public function test_should_get_font_family_name( $fonts, $expected_name ) {
		switch_theme( static::FONTS_THEME );

		$replace_fonts = static function ( $theme_json_data ) use ( $fonts ) {
			$data = $theme_json_data->get_data();

			// Replace typography.fontFamilies.
			$data['settings']['typography']['fontFamilies']['theme'] = $fonts;

			return new WP_Theme_JSON_Data( $data );
		};
		add_filter( 'wp_theme_json_data_theme', $replace_fonts );
		$fonts = WP_Font_Face_Resolver::get_fonts_from_theme_json();
		remove_filter( 'wp_theme_json_data_theme', $replace_fonts );

		// flatten the array to make it easier to test.
		$fonts = array_merge( array(), ...array_map( 'array_values', $fonts ) );

		$fonts_found = array_filter(
			$fonts,
			function ( $font ) use ( $expected_name ) {
				return $font['font-family'] === $expected_name;
			}
		);

		$this->assertNotEmpty( $fonts_found, 'Expected font-family name not found in the array' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_get_font_family_name() {
		$font_face = array(
			array(
				'fontFamily'  => 'DM Sans',
				'fontStretch' => 'normal',
				'fontStyle'   => 'normal',
				'fontWeight'  => '400',
				'src'         => array(
					'file:./assets/fonts/dm-sans/DMSans-Regular.woff2',
				),
			),
			array(
				'fontFamily'  => 'DM Sans',
				'fontStretch' => 'normal',
				'fontStyle'   => 'italic',
				'fontWeight'  => '400',
				'src'         => array(
					'file:./assets/fonts/dm-sans/DMSans-Regular-Italic.woff2',
				),
			),
			array(
				'fontFamily'  => 'DM Sans',
				'fontStretch' => 'normal',
				'fontStyle'   => 'italic',
				'fontWeight'  => '700',
				'src'         => array(
					'file:./assets/fonts/dm-sans/DMSans-Bold.woff2',
				),
			),
			array(
				'fontFamily'  => 'DM Sans',
				'fontStretch' => 'normal',
				'fontStyle'   => 'italic',
				'fontWeight'  => '700',
				'src'         => array(
					'file:./assets/fonts/dm-sans/DMSans-Bold-Italic.woff2',
				),
			),
		);

		return array(
			'name declared'                   => array(
				'fonts'         => array(
					array(
						'fontFamily' => 'DM Sans',
						'name'       => 'DM Sans Family',
						'slug'       => 'dm-sans',
						'fontFace'   => $font_face,
					),
				),
				'expected_name' => 'DM Sans',
			),
			'name not declared'               => array(
				'fonts'         => array(
					array(
						'fontFamily' => 'DM Sans',
						'slug'       => 'dm-sans',
						'fontFace'   => $font_face,
					),
				),
				'expected_name' => 'DM Sans',
			),
			'fontFamily comma-separated list' => array(
				'fonts'         => array(
					array(
						'fontFamily' => '"DM Sans", sans-serif',
						'slug'       => 'dm-sans',
						'fontFace'   => $font_face,
					),
				),
				'expected_name' => 'DM Sans',
			),
		);
	}
}
