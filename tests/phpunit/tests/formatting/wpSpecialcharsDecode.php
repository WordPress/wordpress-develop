<?php

/**
 * Tests for wp_specialchars_decode().
 *
 * @group formatting
 *
 * @covers ::wp_specialchars_decode
 */
class Tests_Formatting_wpSpecialcharsDecode extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_wp_specialchars_decode
	 *
	 * @param string     $text        Text to decode.
	 * @param string     $expected    Expected decoded text.
	 * @param string|int $quote_style Optional quote style.
	 */
	public function test_wp_specialchars_decode( $text, $expected, $quote_style = ENT_NOQUOTES ) {
		$this->assertSame( $expected, wp_specialchars_decode( $text, $quote_style ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_specialchars_decode() {
		return array(
			'empty string'                   => array( '', '' ),
			'no entities fast path'          => array( 'hello world', 'hello world' ),
			'default decodes amp lt gt'      => array( 'A &amp; B &lt;C&gt;', 'A & B <C>' ),
			'default leaves double quotes'   => array( '&quot;quoted&quot;', '&quot;quoted&quot;' ),
			'default leaves single quotes'   => array( '&#039;quoted&#039;', '&#039;quoted&#039;' ),
			'ENT_QUOTES decodes both quotes' => array( '&quot;&#039;hi&#039;&quot;', '"\'hi\'"', ENT_QUOTES ),
			'ENT_COMPAT decodes doubles'     => array( '&quot;hi&#039;', '"hi&#039;', ENT_COMPAT ),
			'double style decodes doubles'   => array( '&quot;hi&#039;', '"hi&#039;', 'double' ),
			'single style decodes singles'   => array( '&quot;&#039;hi&#039;', '&quot;\'hi\'', 'single' ),
			'zero-padded numeric amp'        => array( '&#038;', '&' ),
			'hex amp entity'                 => array( '&#x26;', '&' ),
		);
	}
}
