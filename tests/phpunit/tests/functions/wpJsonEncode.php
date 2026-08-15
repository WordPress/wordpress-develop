<?php

/**
 * @group functions
 *
 * @covers ::wp_json_encode
 */
class Tests_Functions_WpJsonEncode extends WP_UnitTestCase {

	/**
	 * @ticket 28786
	 */
	public function test_wp_json_encode() {
		$this->assertSame( wp_json_encode( 'a' ), '"a"' );
	}

	/**
	 * @ticket 28786
	 */
	public function test_wp_json_encode_utf8() {
		$this->assertSame( wp_json_encode( '这' ), '"\u8fd9"' );
	}

	/**
	 * @ticket 28786
	 * @requires function mb_detect_order
	 */
	public function test_wp_json_encode_non_utf8() {
		$charsets     = mb_detect_order();
		$old_charsets = $charsets;
		if ( ! in_array( 'EUC-JP', $charsets, true ) ) {
			$charsets[] = 'EUC-JP';
			mb_detect_order( $charsets );
		}

		$eucjp = mb_convert_encoding( 'aあb', 'EUC-JP', 'UTF-8' );
		$utf8  = mb_convert_encoding( $eucjp, 'UTF-8', 'EUC-JP' );

		$this->assertSame( 'aあb', $utf8 );

		$this->assertSame( '"a\u3042b"', wp_json_encode( $eucjp ) );

		mb_detect_order( $old_charsets );
	}

	/**
	 * @ticket 28786
	 * @requires function mb_detect_order
	 */
	public function test_wp_json_encode_non_utf8_in_array() {
		$charsets     = mb_detect_order();
		$old_charsets = $charsets;
		if ( ! in_array( 'EUC-JP', $charsets, true ) ) {
			$charsets[] = 'EUC-JP';
			mb_detect_order( $charsets );
		}

		$eucjp = mb_convert_encoding( 'aあb', 'EUC-JP', 'UTF-8' );
		$utf8  = mb_convert_encoding( $eucjp, 'UTF-8', 'EUC-JP' );

		$this->assertSame( 'aあb', $utf8 );

		$this->assertSame( '["c","a\u3042b"]', wp_json_encode( array( 'c', $eucjp ) ) );

		mb_detect_order( $old_charsets );
	}

	/**
	 * @ticket 28786
	 */
	public function test_wp_json_encode_array() {
		$this->assertSame( wp_json_encode( array( 'a' ) ), '["a"]' );
	}

	/**
	 * @ticket 28786
	 */
	public function test_wp_json_encode_object() {
		$object    = new stdClass();
		$object->a = 'b';
		$this->assertSame( wp_json_encode( $object ), '{"a":"b"}' );
	}

	/**
	 * @ticket 28786
	 */
	public function test_wp_json_encode_depth() {
		$data = array( array( array( 1, 2, 3 ) ) );
		$json = wp_json_encode( $data, 0, 1 );
		$this->assertFalse( $json );

		$data = array( 'あ', array( array( 1, 2, 3 ) ) );
		$json = wp_json_encode( $data, 0, 1 );
		$this->assertFalse( $json );
	}
}
