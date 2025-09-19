<?php
/**
 * Test case for WP_Font_Face::generate_and_print().
 *
 * @package    WordPress
 * @subpackage Fonts
 *
 * @since 6.4.0
 *
 * @group fonts
 * @group fontface
 *
 * @covers WP_Font_Face::generate_and_print
 */
class Tests_Fonts_WPFontFace_GenerateAndPrint extends WP_UnitTestCase {
	use WP_Font_Face_Tests_Datasets;

	public function test_should_not_generate_and_print_when_no_fonts() {
		$font_face = new WP_Font_Face();
		$fonts     = array();

		$this->expectOutputString( '' );
		$font_face->generate_and_print( $fonts );
	}

	/**
	 * @dataProvider data_should_print_given_fonts
	 *
	 * @param array  $fonts Prepared fonts.
	 * @param string $expected Expected CSS.
	 */
	public function test_should_generate_and_print_given_fonts( array $fonts, $expected ) {
		$font_face       = new WP_Font_Face();
		$style_element   = "<style class='wp-fonts-local' type='text/css'>\n%s\n</style>\n";
		$expected_output = sprintf( $style_element, $expected );

		$this->expectOutputString( $expected_output );
		$font_face->generate_and_print( $fonts );
	}


	/**
	 * @ticket 63568
	 *
	 * @dataProvider data_font_family_normalization
	 */
	public function test_font_family_css_normalization( string $font_name, string $expected ) {
		$normalizer = new class() extends WP_Font_Face {
			public function test_normalization( string $font_name ): string {
				return $this->normalize_css_font_family( $font_name );
			}
		};
		$this->assertSame( $expected, $normalizer->test_normalization( $font_name ) );
	}

	public static function data_font_family_normalization() {
		return array(
			'Typical name'           => array( 'A font name', '"A font name"' ),
			'Generic collision'      => array( 'serif', '"serif"' ),
			'Trims whitespace'       => array( '   A font name    ', '"A font name"' ),
			'Name with \' character' => array( 'O\'Reilly Sans', '"O\'Reilly Sans"' ),
			'Unrealistically tricky' => array( "BS\\Quot\"Apos'Semi;Comma,Newline\nLT<Oh😵My!", '"BS\\5C Quot\\22 Apos\'Semi;Comma\\2C Newline\\A LT\\3C Oh😵My!"' ),
		);
	}

	/**
	 * Ensure already-quoted font family names emit doing it wrong notice and skip normalization.
	 *
	 * @expectedIncorrectUsage WP_Font_Face::normalize_css_font_family
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_quoted_font_family_normalization
	 */
	public function test_quoted_font_family_doing_it_wrong_no_normalization( string $font_name, string $expected ) {
		$normalizer = new class() extends WP_Font_Face {
			public function test_normalization( string $font_name ): string {
				return $this->normalize_css_font_family( $font_name );
			}
		};
		$this->assertSame( $expected, $normalizer->test_normalization( $font_name ) );
	}

	public static function data_quoted_font_family_normalization() {
		return array(
			"Quoted with '"         => array( "'A font name'", "'A font name'" ),
			'Quoted with "'         => array( '"A font name"', '"A font name"' ),
			'Quoted is not escaped' => array( '"""', '"""' ),
		);
	}
}
