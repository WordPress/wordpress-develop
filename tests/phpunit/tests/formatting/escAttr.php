<?php

/**
 * @group formatting
 *
 * @covers ::esc_attr
 */
class Tests_Formatting_EscAttr extends WP_UnitTestCase {
	public function test_esc_attr_quotes() {
		$attr = '"double quotes"';
		$this->assertSame( '&quot;double quotes&quot;', esc_attr( $attr ) );

		$attr = "'single quotes'";
		$this->assertSame( '&#039;single quotes&#039;', esc_attr( $attr ) );

		$attr = "'mixed' " . '"quotes"';
		$this->assertSame( '&#039;mixed&#039; &quot;quotes&quot;', esc_attr( $attr ) );

		// Handles double encoding?
		$attr = '"double quotes"';
		$this->assertSame( '&quot;double quotes&quot;', esc_attr( esc_attr( $attr ) ) );

		$attr = "'single quotes'";
		$this->assertSame( '&#039;single quotes&#039;', esc_attr( esc_attr( $attr ) ) );

		$attr = "'mixed' " . '"quotes"';
		$this->assertSame( '&#039;mixed&#039; &quot;quotes&quot;', esc_attr( esc_attr( $attr ) ) );
	}

	public function test_esc_attr_amp() {
		$out = esc_attr( 'foo & bar &baz; &nbsp;' );
		$this->assertSame( 'foo &amp; bar &amp;baz; &nbsp;', $out );
	}

	/**
	 * Verifies the conversion of various kinds of decimal numeric references.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @dataProvider data_decimal_numeric_references
	 *
	 * @param string $input  Attribute value with decimal character references.
	 * @param string $output How WordPress is expected to transform the attribute value.
	 */
	public function test_converts_decimal_numeric_references( $input, $output ) {
		$this->assertSame( $output, esc_attr( $input ), 'Failed to properly convert input.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_decimal_numeric_references() {
		return array(
			'Basic decimal'       => array( '&#8227;', '&#8227;' ),
			'Control character'   => array( '&#4;', '&amp;#4;' ),
			'Leading zeros'       => array( '&#00065;', '&#65;' ),
			'Leading zeros on \'' => array( '&#00039;', '&#039;' ),
			'Leading zero  on \'' => array( '&#039;', '&#039;' ),
			'Surrogate half'      => array( '&#55360;', '&amp;#55360;' ),
			'Code point too high' => array( '&#1114112;', '&amp;#1114112;' ),
		);
	}

	/**
	 * Verifies the conversion of various kinds of hexadecimal numeric references.
	 *
	 * @ticket {TICKET_NUMBER}
	 *
	 * @dataProvider data_hexadecimal_numeric_references
	 *
	 * @param string $input  Attribute value with hexadecimal character references.
	 * @param string $output How WordPress is expected to transform the attribute value.
	 */
	public function test_converts_hexadecimal_numeric_references( $input, $output ) {
		$this->assertSame( $output, esc_attr( $input ), 'Failed to properly convert input.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_hexadecimal_numeric_references() {
		return array(
			'Basic lower hex'     => array( '&#x2023;', '&#x2023;' ),
			'Basic upper hex'     => array( '&#X2023;', '&#X2023;' ),
			'Control character'   => array( '&#x4;', '&amp;#x4;' ),
			'Leading zeros'       => array( '&#x00065;', '&#x65;' ),
			'Leading zeros on \'' => array( '&#x00027;', '&#x27;' ),
			'Leading zero  on \'' => array( '&#x027;', '&#x27;' ),
			'Surrogate half'      => array( '&#xD83C;', '&amp;#xD83C;' ),
			'Code point too high' => array( '&#x2FFFF;', '&amp;#x2FFFF;' ),
		);
	}
}
