<?php

/**
 * Tests for the shortcode_parse_atts() function.
 *
 * @group shortcodes
 *
 * @covers ::shortcode_parse_atts
 */
class Tests_Shortcode_ShortcodeParseAtts extends WP_UnitTestCase {

	/**
	 * Tests parsing an empty arguments string.
	 *
	 * @ticket 59249
	 */
	public function test_shortcode_parse_atts_empty() {
		$out = shortcode_parse_atts( '' );
		$this->assertIsArray( $out, 'Return value is not an array' );
		$this->assertEmpty( $out, 'Returned array is not empty' );
	}

	/**
	 * Tests that attribute named 0 with double quotes is parsed.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_numeric_key_double_quotes() {
		$out = shortcode_parse_atts( '0="test_value"' );

		$this->assertSame(
			array( 0 => 'test_value' ),
			$out,
			'Attribute named 0 with double quotes should be parsed.'
		);
	}

	/**
	 * Tests that attribute named 0 with single quotes is parsed.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_numeric_key_single_quotes() {
		$out = shortcode_parse_atts( "0='test_value'" );

		$this->assertSame(
			array( 0 => 'test_value' ),
			$out,
			'Attribute named 0 with single quotes should be parsed.'
		);
	}

	/**
	 * Tests that attribute named 0 without quotes is parsed.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_numeric_key_unquoted() {
		$out = shortcode_parse_atts( '0=test_value' );

		$this->assertSame(
			array( 0 => 'test_value' ),
			$out,
			'Attribute named 0 without quotes should be parsed.'
		);
	}

	/**
	 * Tests that attribute named 0 with empty string value is parsed.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_numeric_key_empty_value() {
		$out_double = shortcode_parse_atts( '0=""' );
		$out_single = shortcode_parse_atts( "0=''" );

		$this->assertSame(
			array( 0 => '' ),
			$out_double,
			'Attribute named 0 with empty double quotes should be parsed.'
		);
		$this->assertSame(
			array( 0 => '' ),
			$out_single,
			'Attribute named 0 with empty single quotes should be parsed.'
		);
	}

	/**
	 * Tests parsing multiple numeric attribute keys.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_multiple_numeric_keys() {
		$out = shortcode_parse_atts( '0="first" 1="second" 2="third"' );

		$this->assertSame(
			array(
				0 => 'first',
				1 => 'second',
				2 => 'third',
			),
			$out,
			'Multiple numeric attribute keys should all be parsed.'
		);
	}

	/**
	 * Tests parsing mixed numeric and string attribute keys.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_mixed_numeric_and_string_keys() {
		$out = shortcode_parse_atts( 'id="test-box" 0="zero_val" class="highlight" 1="one_val"' );

		$this->assertSame(
			array(
				'id'    => 'test-box',
				0       => 'zero_val',
				'class' => 'highlight',
				1       => 'one_val',
			),
			$out,
			'Mixed string and numeric attribute keys should be parsed.'
		);
	}

	/**
	 * Tests parsing numeric attribute keys alongside positional attributes.
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_parse_atts_numeric_key_with_positional_attributes() {
		$out = shortcode_parse_atts( '0="named_zero" positional_one "quoted positional"' );

		$this->assertSame(
			array(
				0 => 'named_zero',
				1 => 'positional_one',
				2 => 'quoted positional',
			),
			$out,
			'Numeric attribute keys should coexist with positional attributes.'
		);
	}

	/**
	 * Tests integration of shortcode_parse_atts() with shortcode_atts().
	 *
	 * @ticket 59509
	 */
	public function test_shortcode_atts_combines_numeric_keys() {
		$defaults = array(
			0     => 'default_zero',
			'foo' => 'default_foo',
		);
		$parsed   = shortcode_parse_atts( '0="custom_zero" foo="custom_foo"' );
		$combined = shortcode_atts( $defaults, $parsed );

		$this->assertSame(
			array(
				0     => 'custom_zero',
				'foo' => 'custom_foo',
			),
			$combined,
			'shortcode_atts() should override numeric default with parsed numeric attribute.'
		);
	}

	/**
	 * Tests end-to-end do_shortcode() execution with attribute named 0.
	 *
	 * @ticket 59509
	 */
	public function test_do_shortcode_with_numeric_attribute() {
		$captured_atts = null;
		$tag           = 'test_shortcode_59509';

		add_shortcode(
			$tag,
			static function ( $atts ) use ( &$captured_atts ) {
				$captured_atts = $atts;
				return 'shortcode_output';
			}
		);

		$result = do_shortcode( "[{$tag} 0=\"hello\" id=\"test\"]" );

		remove_shortcode( $tag );

		$this->assertSame( 'shortcode_output', $result );
		$this->assertIsArray( $captured_atts );
		$this->assertArrayHasKey( 0, $captured_atts );
		$this->assertSame( 'hello', $captured_atts[0] );
		$this->assertSame( 'test', $captured_atts['id'] );
	}
}
