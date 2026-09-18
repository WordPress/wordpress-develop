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
		$style_element   = "<style class='wp-fonts-local'>\n%s\n</style>\n";
		$expected_output = sprintf( $style_element, $expected );

		$output = get_echo( array( $font_face, 'generate_and_print' ), array( $fonts ) );
		$this->assertEqualHTML( $expected_output, $output );
	}

	/**
	 * The font-family descriptor must keep the font name and stay valid CSS.
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_should_print_quoted_font_family
	 *
	 * @param string $font_family Font family value of the font face.
	 * @param string $expected    Expected font-family descriptor.
	 */
	public function test_should_print_quoted_font_family( $font_family, $expected ) {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			array(
				array(
					'font-family' => $font_family,
					'src'         => array( 'https://example.org/font.woff2' ),
				),
			),
		);

		$output = get_echo( array( $font_face, 'generate_and_print' ), array( $fonts ) );

		$this->assertStringContainsString( 'font-family:' . $expected . ';', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_print_quoted_font_family() {
		return array(
			'a plain name'            => array( 'Inter', '"Inter"' ),
			'a name with spaces'      => array( 'Open Sans', '"Open Sans"' ),
			'an apostrophe'           => array( "O'Reilly Sans", '"O\'Reilly Sans"' ),
			'a quoted apostrophe'     => array( '"O\'Reilly Sans"', '"O\'Reilly Sans"' ),
			'an escaped double quote' => array( '"O\\22 Reilly Sans"', '"O\\"Reilly Sans"' ),
			'a comma inside a name'   => array( '"ACME, Sans", sans-serif', '"ACME, Sans"' ),
			'a numeric name'          => array( '"12345"', '"12345"' ),
			'an ampersand'            => array( '"Tom \\26  Jerry"', '"Tom \\26  Jerry"' ),
			'a percent sequence'      => array( '"Font 50%AB"', '"Font 50%AB"' ),
			'two spaces'              => array( '"A  B"', '"A  B"' ),
			'a semicolon'             => array( '"A;B"', '"A;B"' ),
		);
	}

	/**
	 * A font name cannot close the style element or run script.
	 *
	 * @ticket 63568
	 */
	public function test_should_not_allow_a_name_to_close_the_style_element() {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			array(
				array(
					'font-family' => '"</Style><script>alert(1)</script>"',
					'src'         => array( 'https://example.org/font.woff2' ),
				),
			),
		);

		$output = get_echo( array( $font_face, 'generate_and_print' ), array( $fonts ) );

		$this->assertStringContainsString( 'font-family:"\\3c /Style\\3e ', $output, 'The name should use a CSS escape for "<".' );
		$this->assertStringNotContainsString( '<script', $output, 'The output should not contain a script start tag.' );

		$processor = new WP_HTML_Tag_Processor( $output );
		$tags      = array();
		while ( $processor->next_tag() ) {
			$tags[] = $processor->get_tag();
		}

		$this->assertSame( array( 'STYLE' ), $tags, 'The output should hold one style element only.' );
	}

	/**
	 * An invalid font-family value produces a diagnostic and no output.
	 *
	 * @ticket 63568
	 *
	 * @expectedIncorrectUsage WP_Font_Face::validate_font_face_declarations
	 */
	public function test_should_skip_an_invalid_font_family() {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			array(
				array(
					'font-family' => '"A"; color:red',
					'src'         => array( 'https://example.org/font.woff2' ),
				),
			),
		);

		$this->expectOutputString( '' );
		$font_face->generate_and_print( $fonts );
	}
}
