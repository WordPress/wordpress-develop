<?php

/**
 * Tests for the wp_is_explicit_aspect_ratio_value() function.
 *
 * @group block-supports
 *
 * @covers ::wp_is_explicit_aspect_ratio_value
 */
class Tests_Block_Supports_WpIsExplicitAspectRatioValue extends WP_UnitTestCase {

	/**
	 * Tests that a value that sets an aspect ratio is explicit.
	 *
	 * @ticket 66510
	 *
	 * @dataProvider data_explicit_values
	 *
	 * @param mixed $value Aspect ratio.
	 */
	public function test_should_return_true_for_a_value_that_sets_an_aspect_ratio( $value ) {
		$this->assertTrue( wp_is_explicit_aspect_ratio_value( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_explicit_values() {
		return array(
			'ratio with a slash'           => array( '16/9' ),
			'ratio with spaces'            => array( '16 / 9' ),
			'ratio with a colon'           => array( '4:3' ),
			'square'                       => array( '1' ),
			'decimal string'               => array( '1.5' ),
			'integer'                      => array( 1 ),
			'float'                        => array( 1.777 ),
			'zero'                         => array( 0 ),
			'zero as a string'             => array( '0' ),
			'ratio with whitespace around' => array( "  16/9 \n" ),
			'a word that starts with auto' => array( 'auto 16/9' ),
			'a word that contains auto'    => array( 'autofit' ),
			'inherit'                      => array( 'inherit' ),
		);
	}

	/**
	 * Tests that a value that does not set an aspect ratio is not explicit.
	 *
	 * @ticket 66510
	 *
	 * @dataProvider data_values_that_are_not_explicit
	 *
	 * @param mixed $value Value that is not an explicit aspect ratio.
	 */
	public function test_should_return_false_for_a_value_that_does_not_set_an_aspect_ratio( $value ) {
		$this->assertFalse( wp_is_explicit_aspect_ratio_value( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_are_not_explicit() {
		return array(
			'empty string'         => array( '' ),
			'whitespace only'      => array( "  \t\n" ),
			'auto'                 => array( 'auto' ),
			'auto in uppercase'    => array( 'AUTO' ),
			'auto in mixed case'   => array( 'Auto' ),
			'auto with whitespace' => array( '  auto ' ),
			'null'                 => array( null ),
			'false'                => array( false ),
			'true'                 => array( true ),
			'empty array'          => array( array() ),
			'array with a ratio'   => array( array( '16/9' ) ),
			'object'               => array( new stdClass() ),
		);
	}
}
