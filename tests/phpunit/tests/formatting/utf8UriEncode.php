<?php

/**
 * @group formatting
 *
 * @covers ::utf8_uri_encode
 */
class Tests_Formatting_Utf8UriEncode extends WP_UnitTestCase {

	/**
	 * Non-ASCII UTF-8 characters should be percent-encoded. Spaces etc.
	 * are dealt with elsewhere.
	 *
	 * @dataProvider data
	 *
	 * @param string $utf8       String encoded in UTF-8 bytes.
	 * @param string $urlencoded Expected percent-escaped form of input text.
	 */
	public function test_percent_encodes_non_reserved_characters( $utf8, $urlencoded ) {
		/**
		 * Casing of percent-encoding shouldn't matter; upper-case is nominal.
		 *
		 * @see https://url.spec.whatwg.org/#percent-encoded-bytes
		 */
		$comparable = preg_replace_callback(
			'~%[A-F0-9]{2}~',
			static function ( $escaped_match ) {
				return strtolower( $escaped_match[0] );
			},
			utf8_uri_encode( $utf8 )
		);

		$this->assertSame( $urlencoded, $comparable );
	}

	/**
	 * @dataProvider data
	 */
	public function test_output_is_not_longer_than_optional_length_argument( $utf8, $unused_for_this_test ) {
		$max_length = 30;
		$this->assertLessThanOrEqual( $max_length, strlen( utf8_uri_encode( $utf8, $max_length ) ) );
	}

	public function data() {
		$utf8_urls     = file( DIR_TESTDATA . '/formatting/utf-8/utf-8.txt' );
		$urlencoded    = file( DIR_TESTDATA . '/formatting/utf-8/urlencoded.txt' );
		$data_provided = array();
		foreach ( $utf8_urls as $key => $value ) {
			$data_provided[] = array( trim( $value ), trim( $urlencoded[ $key ] ) );
		}
		return $data_provided;
	}
}
