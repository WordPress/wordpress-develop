<?php
/**
 * Test WP_Font_Utils::allow_ttf_mime_types().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers WP_Font_Utils::allow_ttf_mime_types
 */
class Tests_Fonts_WpFontUtils_AllowTtfMimeTypes extends WP_UnitTestCase {
	/**
	 * @ticket 66419
	 *
	 * @dataProvider data_ttf_mime_types
	 *
	 * @param string $real_mime MIME type detected by fileinfo.
	 */
	public function test_should_accept_a_ttf_file_detected_as_either_truetype_mime_type( $real_mime ) {
		$rejected = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$data = WP_Font_Utils::allow_ttf_mime_types( $rejected, '/tmp/font.ttf', 'OpenSans-Regular.ttf', WP_Font_Utils::get_allowed_font_mime_types(), $real_mime );

		$this->assertSame( 'ttf', $data['ext'] );
		$this->assertSame( $real_mime, $data['type'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_ttf_mime_types() {
		return array(
			'libmagic before 5.47' => array( 'font/sfnt' ),
			'libmagic 5.47 and up' => array( 'font/ttf' ),
		);
	}

	/**
	 * @ticket 66419
	 *
	 * @dataProvider data_should_not_accept
	 *
	 * @param string       $filename  Uploaded file name.
	 * @param string|false $real_mime MIME type detected by fileinfo.
	 */
	public function test_should_not_accept_other_files( $filename, $real_mime ) {
		$rejected = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$data = WP_Font_Utils::allow_ttf_mime_types( $rejected, '/tmp/file', $filename, WP_Font_Utils::get_allowed_font_mime_types(), $real_mime );

		$this->assertSame( $rejected, $data );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_not_accept() {
		return array(
			'ttf detected as a generic binary' => array( 'OpenSans-Regular.ttf', 'application/octet-stream' ),
			'ttf not detected'                 => array( 'OpenSans-Regular.ttf', false ),
			'truetype content in a php file'   => array( 'shell.php', 'font/ttf' ),
			'truetype content in an otf file'  => array( 'OpenSans-Regular.otf', 'font/ttf' ),
		);
	}

	/**
	 * @ticket 66419
	 */
	public function test_should_keep_an_already_accepted_file() {
		$accepted = array(
			'ext'             => 'ttf',
			'type'            => 'font/sfnt',
			'proper_filename' => false,
		);

		$this->assertSame( $accepted, WP_Font_Utils::allow_ttf_mime_types( $accepted, '/tmp/font.ttf', 'OpenSans-Regular.ttf', WP_Font_Utils::get_allowed_font_mime_types(), 'font/sfnt' ) );
	}
}
