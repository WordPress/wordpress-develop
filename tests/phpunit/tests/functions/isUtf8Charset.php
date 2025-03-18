<?php
/**
 * Tests for the is_utf8_charset() function.
 *
 * @group functions
 * @group charset
 *
 * @covers ::is_utf8_charset
 */
class Tests_Functions_IsUtf8Charset extends WP_UnitTestCase {
	/**
	 * Tests that is_utf8_charset handles null by getting the blog_charset option.
	 *
	 * @ticket 25693
	 */
	public function test_handles_null_by_getting_option() {
		$original_charset = get_option( 'blog_charset' );

		update_option( 'blog_charset', 'UTF-8' );
		$this->assertTrue(
			is_utf8_charset( null ),
			'Should return true when null is passed and blog_charset is UTF-8'
		);

		update_option( 'blog_charset', 'ISO-8859-1' );
		$this->assertFalse(
			is_utf8_charset( null ),
			'Should return false when null is passed and blog_charset is not UTF-8'
		);

		update_option( 'blog_charset', $original_charset );
	}

	/**
	 * Tests that is_utf8_charset returns false for empty values.
	 *
	 * @ticket 25693
	 *
	 * @dataProvider data_empty_charset_values
	 *
	 * @param mixed $empty_charset Empty or null charset value.
	 */
	public function test_handles_empty_values( $empty_charset ) {
		$this->assertFalse(
			is_utf8_charset( $empty_charset ),
			'Should return false when empty values are explicitly passed'
		);
	}

	/**
	 * Tests that is_utf8_charset correctly identifies UTF-8 variants.
	 *
	 * @ticket 25693
	 *
	 * @dataProvider data_utf8_charset_variants
	 *
	 * @param string $utf8_charset A UTF-8 charset variant.
	 */
	public function test_identifies_utf8_variants( $utf8_charset ) {
		$this->assertTrue(
			is_utf8_charset( $utf8_charset ),
			'Should identify valid UTF-8 charset variants'
		);
	}

	/**
	 * Tests that is_utf8_charset correctly rejects non-UTF-8 charsets.
	 *
	 * @ticket 25693
	 *
	 * @dataProvider data_non_utf8_charset_values
	 *
	 * @param string $non_utf8_charset A non-UTF-8 charset.
	 */
	public function test_rejects_non_utf8_charsets( $non_utf8_charset ) {
		$this->assertFalse(
			is_utf8_charset( $non_utf8_charset ),
			'Should reject non-UTF-8 charsets'
		);
	}

	/**
	 * Data provider for empty charset values.
	 *
	 * @return array[].
	 */
	public static function data_empty_charset_values() {
		return array(
			array( '' ),
			array( false ),
			array( 0 ),
			array( '0' ),
		);
	}

	/**
	 * Data provider for UTF-8 charset variants.
	 *
	 * @return array[].
	 */
	public static function data_utf8_charset_variants() {
		return array(
			array( 'UTF-8' ),
			array( 'utf-8' ),
			array( 'utf8' ),
			array( 'UTF8' ),
		);
	}

	/**
	 * Data provider for non-UTF-8 charset values.
	 *
	 * @return array[].
	 */
	public static function data_non_utf8_charset_values() {
		return array(
			array( 'ISO-8859-1' ),
			array( 'Windows-1252' ),
			array( 'ASCII' ),
			array( 'EUC-JP' ),
		);
	}
}
