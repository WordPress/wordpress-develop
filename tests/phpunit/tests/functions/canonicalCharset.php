<?php

/**
 * Validate that badly named charsets always return the correct format for UTF-8 and ISO-8859-1.
 *
 * @since 4.8.0
 *
 * @group functions
 *
 * @covers ::_canonical_charset
 */
class Tests_Functions_CanonicalCharset extends WP_UnitTestCase {
	/**
	 * Ensures that charset variants for common encodings normalize to the expected form.
	 *
	 * @ticket 61182
	 *
	 * @dataProvider data_charset_normalizations
	 *
	 * @param string $given_charset      Potential charset provided by user.
	 * @param string $normalized_charset Expected normalized form of charset.
	 */
	public function test_properly_normalizes_charset_variants( $given_charset, $normalized_charset ) {
		$this->assertSame(
			$normalized_charset,
			_canonical_charset( $given_charset ),
			'Did not properly transform the provided charset into its normalized form.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_charset_normalizations() {
		return array(
			// UTF-8 family.
			array( 'UTF-8', 'UTF-8' ),
			array( 'Utf-8', 'UTF-8' ),
			array( 'Utf-8', 'UTF-8' ),
			array( 'UTF8', 'UTF-8' ),

			// Almost UTF-8.
			array( 'UTF-8*', 'UTF-8*' ),
			array( 'UTF.8', 'UTF.8' ),
			array( 'UTF88', 'UTF88' ),
			array( 'UTF-7', 'UTF-7' ),
			array( 'X-UTF-8', 'X-UTF-8' ),

			// ISO-8859-1 family.
			array( 'iso-8859-1', 'ISO-8859-1' ),
			array( 'ISO-8859-1', 'ISO-8859-1' ),
			array( 'Iso-8859-1', 'ISO-8859-1' ),
			array( 'ISO8859-1', 'ISO-8859-1' ),

			// Other charset slugs should not be adjusted.
			array( 'random', 'random' ),
			array( '', '' ),
		);
	}

	/**
	 * @ticket 23688
	 *
	 * @covers ::get_option
	 */
	public function test_update_option_blog_charset() {
		$orig_blog_charset = get_option( 'blog_charset' );

		update_option( 'blog_charset', 'utf8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'utf-8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'UTF8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'UTF-8' );
		$this->assertSame( 'UTF-8', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'ISO-8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'ISO8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'iso8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', 'iso-8859-1' );
		$this->assertSame( 'ISO-8859-1', get_option( 'blog_charset' ) );

		// Arbitrary strings are passed through.
		update_option( 'blog_charset', 'foobarbaz' );
		$this->assertSame( 'foobarbaz', get_option( 'blog_charset' ) );

		update_option( 'blog_charset', $orig_blog_charset );
	}
}
