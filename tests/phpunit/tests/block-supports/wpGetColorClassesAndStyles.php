<?php
/**
 * @group block-supports
 *
 * @covers ::wp_get_color_classes_and_styles
 */
class Tests_Block_Supports_WpGetColorClassesAndStyles extends WP_UnitTestCase {
	/**
	 * Tests that color classes and styles are generated from block attributes.
	 *
	 * @ticket 66269
	 *
	 * @dataProvider data_get_color_classes_and_styles
	 *
	 * @param mixed $block_attributes Block attributes.
	 * @param array $expected         Expected classes and styles.
	 */
	public function test_get_color_classes_and_styles( $block_attributes, $expected ) {
		$this->assertSame( $expected, wp_get_color_classes_and_styles( $block_attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_color_classes_and_styles() {
		return array(
			'custom'                       => array(
				'block_attributes' => array(
					'style' => array(
						'color' => array(
							'text'       => '#d92828',
							'background' => '#ffffff',
							'gradient'   => 'linear-gradient(135deg,rgb(6,147,227) 0%,rgb(155,81,224) 100%)',
						),
					),
				),
				'expected'         => array(
					'class' => 'has-text-color has-background',
					'style' => 'color:#d92828;background-color:#ffffff;background:linear-gradient(135deg,rgb(6,147,227) 0%,rgb(155,81,224) 100%);',
				),
			),
			'preset'                       => array(
				'block_attributes' => array(
					'textColor'       => 'contrast',
					'backgroundColor' => 'base',
					'gradient'        => 'vivid-cyan-blue-to-vivid-purple',
				),
				'expected'         => array(
					'class' => 'has-text-color has-contrast-color has-background has-base-background-color has-vivid-cyan-blue-to-vivid-purple-gradient-background',
				),
			),
			'preset wins over custom'      => array(
				'block_attributes' => array(
					'textColor' => 'contrast',
					'style'     => array(
						'color' => array(
							'text' => '#d92828',
						),
					),
				),
				'expected'         => array(
					'class' => 'has-text-color has-contrast-color',
				),
			),
			'null preset wins over custom' => array(
				'block_attributes' => array(
					'textColor' => null,
					'style'     => array(
						'color' => array(
							'text' => '#d92828',
						),
					),
				),
				'expected'         => array(
					'class' => 'has-text-color',
				),
			),
			'numeric'                      => array(
				'block_attributes' => array(
					'textColor' => 1,
				),
				'expected'         => array(
					'class' => 'has-text-color has-1-color',
				),
			),
			'zero'                         => array(
				'block_attributes' => array(
					'style' => array(
						'color' => array(
							'text' => '0',
						),
					),
				),
				'expected'         => array(
					'style' => 'color:0;',
				),
			),
			'empty'                        => array(
				'block_attributes' => array(),
				'expected'         => array(),
			),
			'malformed color'              => array(
				'block_attributes' => array(
					'style' => array(
						'color' => '#d92828',
					),
				),
				'expected'         => array(),
			),
			'malformed attributes'         => array(
				'block_attributes' => 'color',
				'expected'         => array(),
			),
		);
	}
}
