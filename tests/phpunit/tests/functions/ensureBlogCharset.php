<?php
/**
 * Tests for the _wp_ensure_blog_charset() function.
 *
 * @group functions
 * @group charset
 *
 * @covers ::_wp_ensure_blog_charset
 */
class Tests_Functions_EnsureBlogCharset extends WP_UnitTestCase {
	/**
	 * Tests that _wp_ensure_blog_charset returns UTF-8 for empty or null values.
	 *
	 * @ticket 25693
	 *
	 * @dataProvider data_empty_charset_values
	 *
	 * @param mixed $empty_charset Empty or null charset value.
	 */
	public function test_returns_utf8_for_empty_values( $empty_charset ) {
		$this->assertSame(
			'UTF-8',
			_wp_ensure_blog_charset( $empty_charset ),
			'Should return UTF-8 when the blog_charset value is empty'
		);
	}

	/**
	 * Tests that _wp_ensure_blog_charset preserves valid charsets.
	 *
	 * @ticket 25693
	 *
	 * @dataProvider data_valid_charset_values
	 *
	 * @param string $valid_charset A valid charset.
	 */
	public function test_preserves_valid_charset_values( $valid_charset ) {
		$this->assertSame(
			$valid_charset,
			_wp_ensure_blog_charset( $valid_charset ),
			'Should preserve the original charset when it is not empty'
		);
	}

	/**
	 * Data provider for empty charset values.
	 *
	 * @return array[].
	 */
	public static function data_empty_charset_values() {
		return array(
			array( null ),
			array( '' ),
			array( false ),
			array( 0 ),
			array( '0' ),
		);
	}

	/**
	 * Data provider for valid charset values.
	 *
	 * @return array[].
	 */
	public static function data_valid_charset_values() {
		return array(
			array( 'UTF-8' ),
			array( 'ISO-8859-1' ),
			array( 'ASCII' ),
			array( 'Windows-1252' ),
			array( 'EUC-JP' ),
		);
	}

	/**
	 * Tests the integration of _wp_ensure_blog_charset with the option filter system.
	 *
	 * @ticket 25693
	 */
	public function test_option_filter_integration() {
		$original_charset = get_option( 'blog_charset' );

		update_option( 'blog_charset', '' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ), 'Filter should ensure UTF-8 when blog_charset is empty' );

		update_option( 'blog_charset', 'ISO-8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ), 'Filter should preserve valid blog_charset' );

		update_option( 'blog_charset', $original_charset );
	}
}
