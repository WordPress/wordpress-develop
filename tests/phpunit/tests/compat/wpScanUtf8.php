<?php
/**
 * Unit tests covering _wp_scan_utf8().
 *
 * @package    WordPress
 * @subpackage Charset
 *
 * @group      compat
 *
 * @covers ::_wp_scan_utf8
 */
class Tests_Compat_wpScanUtf8 extends WP_UnitTestCase {

	/**
	 * Tests scanning a valid ASCII string.
	 *
	 * @ticket 65817
	 */
	public function test_scan_ascii_string() {
		$text              = 'Hello, World!';
		$at                = 0;
		$invalid_length    = 0;
		$has_noncharacters = null;

		$scanned = _wp_scan_utf8( $text, $at, $invalid_length, null, null, $has_noncharacters );

		$this->assertSame( 13, $scanned );
		$this->assertSame( 0, $invalid_length );
		$this->assertIsBool( $has_noncharacters );
		$this->assertFalse( $has_noncharacters );
	}

	/**
	 * Tests scanning UTF-8 string containing noncharacters.
	 *
	 * @ticket 65817
	 *
	 * @dataProvider data_strings_with_noncharacters
	 *
	 * @param string $text UTF-8 string containing a noncharacter.
	 */
	public function test_scan_string_with_noncharacters( string $text ) {
		$at                = 0;
		$invalid_length    = 0;
		$has_noncharacters = null;

		_wp_scan_utf8( $text, $at, $invalid_length, null, null, $has_noncharacters );

		$this->assertIsBool( $has_noncharacters );
		$this->assertTrue( $has_noncharacters );
	}

	/**
	 * Data provider for noncharacters.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_strings_with_noncharacters() {
		return array(
			'U+FFFF (3-byte noncharacter)'  => array( "test \xEF\xBF\xBF end" ),
			'U+FFFE (3-byte noncharacter)'  => array( "test \xEF\xBF\xBE end" ),
			'U+FDD0 (3-byte noncharacter)'  => array( "test \xEF\xB7\x90 end" ),
			'U+1FFFF (4-byte noncharacter)' => array( "test \xF0\x9F\xBF\xBF end" ),
		);
	}

	/**
	 * Tests scanning UTF-8 string without noncharacters.
	 *
	 * @ticket 65817
	 */
	public function test_scan_string_without_noncharacters() {
		$text              = 'WordPress: 日本語 and Piña!';
		$at                = 0;
		$invalid_length    = 0;
		$has_noncharacters = null;

		_wp_scan_utf8( $text, $at, $invalid_length, null, null, $has_noncharacters );

		$this->assertIsBool( $has_noncharacters );
		$this->assertFalse( $has_noncharacters );
	}
}
