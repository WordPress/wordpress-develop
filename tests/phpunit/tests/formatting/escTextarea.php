<?php

/**
 * @group formatting
 *
 * @covers ::esc_textarea
 */
class Tests_Formatting_EscTextarea extends WP_UnitTestCase {

	public function charset_iso_8859_1() {
		return 'iso-8859-1';
	}

	/*
	 * Only fails in PHP 5.4 onwards
	 * @ticket 23688
	 */
	public function test_esc_textarea_charset_iso_8859_1() {
		add_filter( 'pre_option_blog_charset', array( $this, 'charset_iso_8859_1' ) );
		$iso8859_1 = 'Fran' . chr( 135 ) . 'ais';
		$this->assertSame( $iso8859_1, esc_textarea( $iso8859_1 ) );
		remove_filter( 'pre_option_blog_charset', array( $this, 'charset_iso_8859_1' ) );
	}

	public function charset_utf_8() {
		return 'UTF-8';
	}

	/*
	 * @ticket 23688
	 */
	public function test_esc_textarea_charset_utf_8() {
		add_filter( 'pre_option_blog_charset', array( $this, 'charset_utf_8' ) );
		$utf8 = 'Fran' . chr( 195 ) . chr( 167 ) . 'ais';
		$this->assertSame( $utf8, esc_textarea( $utf8 ) );
		remove_filter( 'pre_option_blog_charset', array( $this, 'charset_utf_8' ) );
	}
}
