<?php

/**
 * @group block-supports
 *
 * @covers ::wp_get_block_style_variation_name_from_class
 */
class Tests_Block_Supports_WpGetBlockStyleVariationNameFromClass extends WP_UnitTestCase {
	/**
	 * Tests variation names are extracted correctly from a CSS class string.
	 *
	 * @ticket 61312
	 *
	 * @covers ::wp_get_block_style_variation_name_from_class
	 *
	 * @dataProvider data_block_style_variation_name_extraction
	 *
	 * @param string|null   $class_string CSS class string.
	 * @param string[]|null $expected     Expected variation names.
	 */
	public function test_block_style_variation_name_extraction( $class_string, $expected ) {
		$actual = wp_get_block_style_variation_name_from_class( $class_string );

		$this->assertSame(
			$expected,
			$actual,
			'Block style variation names extracted from CSS class string should match'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_block_style_variation_name_extraction() {
		return array(
			// @ticket 61312
			'missing class string'                     => array(
				'class_string' => null,
				'expected'     => null,
			),
			// @ticket 61312
			'empty class string'                       => array(
				'class_string' => '',
				'expected'     => array(),
			),
			// @ticket 61312
			'no variation'                             => array(
				'class_string' => 'is-style no-variation',
				'expected'     => array(),
			),
			// @ticket 61312
			'single variation'                         => array(
				'class_string' => 'custom-class is-style-outline',
				'expected'     => array( 'outline' ),
			),
			// @ticket 61312
			'multiple variations'                      => array(
				'class_string' => 'is-style-light custom-class is-style-outline',
				'expected'     => array( 'light', 'outline' ),
			),
			// @ticket 65466
			'whitespace separators'                    => array(
				'class_string' => "is-style-light\tcustom-class\nis-style-outline",
				'expected'     => array( 'light', 'outline' ),
			),
			// @ticket 65466
			'prefix inside another class name'         => array(
				'class_string' => 'custom-is-style-outline',
				'expected'     => array(),
			),
			// @ticket 65466
			'variation ending in a non-word character' => array(
				'class_string' => 'is-style-outline-',
				'expected'     => array( 'outline-' ),
			),
			// @ticket 65466
			'default variation'                        => array(
				'class_string' => 'is-style-default is-style-outline',
				'expected'     => array( 'outline' ),
			),
		);
	}
}
