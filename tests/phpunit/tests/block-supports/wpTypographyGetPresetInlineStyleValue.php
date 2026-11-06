<?php

/**
 * Tests for the wp_typography_get_preset_inline_style_value() function.
 *
 * @group block-supports
 *
 * @covers ::wp_typography_get_preset_inline_style_value
 */
class Tests_Block_Supports_WpTypographyGetPresetInlineStyleValue extends WP_UnitTestCase {

	/**
	 * Tests that a preset value is converted to the CSS variable of the preset.
	 *
	 * @ticket 66509
	 *
	 * @dataProvider data_preset_values
	 *
	 * @param string $value        Style value.
	 * @param string $css_property CSS property.
	 * @param string $expected     Expected value.
	 */
	public function test_should_convert_a_preset_value_to_a_css_variable( $value, $css_property, $expected ) {
		$this->assertSame( $expected, wp_typography_get_preset_inline_style_value( $value, $css_property ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_preset_values() {
		return array(
			'text decoration'       => array( 'var:preset|text-decoration|underline', 'text-decoration', 'var(--wp--preset--text-decoration--underline);' ),
			'font family'           => array( 'var:preset|font-family|system-sans', 'font-family', 'var(--wp--preset--font-family--system-sans);' ),
			'slug in camel case'    => array( 'var:preset|font-family|systemSans', 'font-family', 'var(--wp--preset--font-family--system-sans);' ),
			'slug with a number'    => array( 'var:preset|font-size|x-large', 'font-size', 'var(--wp--preset--font-size--x-large);' ),
			'slug with underscores' => array( 'var:preset|font-size|extra_large', 'font-size', 'var(--wp--preset--font-size--extra-large);' ),
			'slug with spaces'      => array( 'var:preset|font-size|extra large', 'font-size', 'var(--wp--preset--font-size--extra-large);' ),
		);
	}

	/**
	 * Tests that the last part after the pipe is the slug.
	 *
	 * @ticket 66509
	 */
	public function test_should_use_the_part_after_the_last_pipe_as_the_slug() {
		$this->assertSame(
			'var(--wp--preset--font-size--large);',
			wp_typography_get_preset_inline_style_value( 'var:preset|font-size|nested|large', 'font-size' )
		);
	}

	/**
	 * Tests that a value that is not a preset of the property is returned as it is.
	 *
	 * @ticket 66509
	 *
	 * @dataProvider data_values_that_are_not_presets
	 *
	 * @param mixed  $value        Style value.
	 * @param string $css_property CSS property.
	 */
	public function test_should_return_a_value_that_is_not_a_preset_as_it_is( $value, $css_property ) {
		$this->assertSame( $value, wp_typography_get_preset_inline_style_value( $value, $css_property ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_are_not_presets() {
		return array(
			'plain value'                   => array( 'underline', 'text-decoration' ),
			'css variable'                  => array( 'var(--wp--preset--text-decoration--underline)', 'text-decoration' ),
			'preset of another property'    => array( 'var:preset|font-size|large', 'text-decoration' ),
			'preset without the final pipe' => array( 'var:preset|text-decoration', 'text-decoration' ),
			'a size with a unit'            => array( '1.5rem', 'font-size' ),
			'empty string'                  => array( '', 'font-size' ),
			'null'                          => array( null, 'font-size' ),
			'zero as a string'              => array( '0', 'font-size' ),
			'the property in another place' => array( 'x var:preset|text-decoration', 'text-decoration' ),
		);
	}
}
