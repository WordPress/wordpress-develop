<?php
/**
 * Test WP_Font_Utils::get_allowed_font_mime_types().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers WP_Font_Utils::get_allowed_font_mime_types
 */
class Tests_Fonts_WpFontUtils_GetAllowedFontMimeTypes extends WP_UnitTestCase {
	/**
	 * @ticket 66419
	 *
	 * @requires extension fileinfo
	 *
	 * @dataProvider data_font_file_extensions
	 *
	 * @param string $extension Font file extension.
	 */
	public function test_should_match_the_mime_type_detected_for_a_font_file( $extension ) {
		$mimes = WP_Font_Utils::get_allowed_font_mime_types();
		add_filter( 'upload_mimes', array( 'WP_Font_Utils', 'get_allowed_font_mime_types' ) );

		$filetype = wp_check_filetype_and_ext( DIR_TESTDATA . '/fonts/OpenSans-Regular.' . $extension, 'OpenSans-Regular.' . $extension, $mimes );

		$this->assertSame( $extension, $filetype['ext'], 'The font file extension should be accepted.' );
		$this->assertSame( $mimes[ $extension ], $filetype['type'], 'The font file mime type should match the allowed mime type.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_font_file_extensions() {
		return array(
			'otf'   => array( 'otf' ),
			'ttf'   => array( 'ttf' ),
			'woff'  => array( 'woff' ),
			'woff2' => array( 'woff2' ),
		);
	}
}
