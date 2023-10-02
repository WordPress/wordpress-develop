<?php
/**
 * Test WP_Font_Family_Utils::get_font_mime_types().
 *
 * @package WordPress
 * @subpackage Fonts
 *
 * @group fonts
 * @group font-library
 *
 * @covers WP_Font_Family_Utils::get_font_mime_types
 */
class Tests_Fonts_WpFontsFamilyUtils_GetMimeTypes extends WP_UnitTestCase {

	public function test_should_supply_correct_mime_type_for_the_running_php_version() {
		$mimes    = WP_Font_Library::get_font_mime_types();
		$expected = $this->get_expected_mime_for_tests_php_version();
		$this->assertSame( $mimes, $expected );
	}

	/**
	 * Get the expected results for the running PHP version.
	 *
	 * @return string[]
	 */
	private function get_expected_mime_for_tests_php_version() {
		// When on less than PHP 7.3.
		if ( PHP_VERSION_ID < 70300 ) {
			return array(
				'otf'   => 'application/vnd.ms-opentype',
				'ttf'   => 'application/x-font-ttf',
				'woff'  => 'application/font-woff',
				'woff2' => 'application/font-woff2',
			);
		}

		// When on PHP 7.3.
		if ( PHP_VERSION_ID > 70300 && PHP_VERSION_ID < 70400 ) {
			return array(
				'otf'   => 'application/vnd.ms-opentype',
				'ttf'   => 'application/font-sfnt',
				'woff'  => 'application/font-woff',
				'woff2' => 'application/font-woff2',
			);
		}

		// When on PHP 7.4 or 8.0.
		if ( PHP_VERSION_ID >= 70400 && PHP_VERSION_ID < 80100 ) {
			return array(
				'otf'   => 'application/vnd.ms-opentype',
				'ttf'   => 'font/sfnt',
				'woff'  => 'application/font-woff',
				'woff2' => 'application/font-woff2',
			);
		}

		// When on PHP 8.1 or newer.
		return array(
			'otf'   => 'application/vnd.ms-opentype',
			'ttf'   => 'font/sfnt',
			'woff'  => 'font/woff',
			'woff2' => 'font/woff2',
		);
	}
}
