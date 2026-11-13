<?php

/**
 * Tests for wp_set_internal_encoding().
 *
 * @group load
 *
 * @covers ::wp_set_internal_encoding
 */
class Tests_Load_WpSetInternalEncoding extends WP_UnitTestCase {

	/**
	 * Original mb_internal_encoding value.
	 *
	 * @var string|false
	 */
	private $original_encoding;

	/**
	 * Original blog_charset option.
	 *
	 * @var mixed
	 */
	private $original_blog_charset;

	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'mb_internal_encoding' ) ) {
			$this->markTestSkipped( 'The mbstring extension is required.' );
		}

		$this->original_encoding     = mb_internal_encoding();
		$this->original_blog_charset = get_option( 'blog_charset' );
	}

	public function tear_down() {
		if ( function_exists( 'mb_internal_encoding' ) && false !== $this->original_encoding ) {
			mb_internal_encoding( $this->original_encoding );
		}

		if ( null !== $this->original_blog_charset ) {
			update_option( 'blog_charset', $this->original_blog_charset );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_uses_blog_charset_when_supported() {
		update_option( 'blog_charset', 'ISO-8859-1' );

		wp_set_internal_encoding();

		$this->assertSame( 'ISO-8859-1', mb_internal_encoding() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_falls_back_to_utf8_when_blog_charset_is_empty() {
		update_option( 'blog_charset', '' );

		mb_internal_encoding( 'ISO-8859-1' );
		wp_set_internal_encoding();

		$this->assertSame( 'UTF-8', mb_internal_encoding() );
	}
}
