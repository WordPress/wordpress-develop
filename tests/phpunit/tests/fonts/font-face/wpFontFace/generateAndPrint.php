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
require_once dirname( __DIR__ ) . '/wp-font-face-tests-dataset.php';

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
	 * @ticket 66429
	 */
	public function test_should_trigger_notice_when_font_family_is_invalid() {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			'Invalid Font' => array(
				array(
					'font-family' => '',
					'src'         => array( 'https://example.com/font.woff2' ),
				),
			),
		);

		$this->expectNotice();
		$this->expectNoticeMessage(
			'WP_Font_Face::validate_font_face_declarations(): Font font-family must be a non-empty string.'
		);

		$font_face->generate_and_print( $fonts );
	}

	/**
	 * @ticket 66429
	 */
	public function test_should_trigger_notice_when_font_src_is_empty_or_invalid() {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			'Open Sans' => array(
				array(
					'font-family' => 'Open Sans',
					'src'         => array(),
				),
			),
		);

		$this->expectNotice();
		$this->expectNoticeMessage(
			'WP_Font_Face::validate_font_face_declarations(): Font src must be a non-empty string or an array of strings.'
		);

		$font_face->generate_and_print( $fonts );
	}

	/**
	 * @ticket 66429
	 */
	public function test_should_trigger_notice_when_font_src_item_is_invalid() {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			'Open Sans' => array(
				array(
					'font-family' => 'Open Sans',
					'src'         => array( '' ),
				),
			),
		);

		$this->expectNotice();
		$this->expectNoticeMessage(
			'WP_Font_Face::validate_font_face_declarations(): Each font src must be a non-empty string.'
		);

		$font_face->generate_and_print( $fonts );
	}

	/**
	 * @ticket 66429
	 */
	public function test_should_trigger_notice_when_font_weight_is_invalid() {
		$font_face = new WP_Font_Face();
		$fonts     = array(
			'Open Sans' => array(
				array(
					'font-family' => 'Open Sans',
					'src'         => array( 'https://example.com/font.woff2' ),
					'font-weight' => array( 'invalid' ),
				),
			),
		);

		$this->expectNotice();
		$this->expectNoticeMessage(
			'WP_Font_Face::validate_font_face_declarations(): Font font-weight must be a properly formatted string or integer.'
		);

		$font_face->generate_and_print( $fonts );
	}
}
