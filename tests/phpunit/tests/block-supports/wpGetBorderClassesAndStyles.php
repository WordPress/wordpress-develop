<?php
/**
 * @group block-supports
 *
 * @covers ::wp_get_border_classes_and_styles
 */
class Tests_Block_Supports_WpGetBorderClassesAndStyles extends WP_UnitTestCase {
	/**
	 * Tests that border classes and styles are generated from block attributes.
	 *
	 * @ticket 66269
	 *
	 * @dataProvider data_get_border_classes_and_styles
	 *
	 * @param mixed $block_attributes Block attributes.
	 * @param array $expected         Expected classes and styles.
	 */
	public function test_get_border_classes_and_styles( $block_attributes, $expected ) {
		$this->assertSame( $expected, wp_get_border_classes_and_styles( $block_attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_border_classes_and_styles() {
		return array(
			'custom'                  => array(
				'block_attributes' => array(
					'style' => array(
						'border' => array(
							'radius' => '10px',
							'style'  => 'solid',
							'width'  => '2px',
							'color'  => '#ff0000',
							'top'    => array(
								'style' => 'dashed',
								'color' => '#00ff00',
								'width' => '3px',
							),
						),
					),
				),
				'expected'         => array(
					'class' => 'has-border-color',
					'style' => 'border-color:#ff0000;border-radius:10px;border-style:solid;border-width:2px;border-top-width:3px;border-top-color:#00ff00;border-top-style:dashed;',
				),
			),
			'preset wins over custom' => array(
				'block_attributes' => array(
					'borderColor' => 'red',
					'style'       => array(
						'border' => array(
							'color' => '#ff0000',
						),
					),
				),
				'expected'         => array(
					'class' => 'has-border-color has-red-border-color',
				),
			),
			'preset only'             => array(
				'block_attributes' => array(
					'borderColor' => 'red',
				),
				'expected'         => array(
					'class' => 'has-border-color has-red-border-color',
				),
			),
			'numeric'                 => array(
				'block_attributes' => array(
					'style' => array(
						'border' => array(
							'radius' => 5,
							'width'  => 2,
						),
					),
				),
				'expected'         => array(
					'style' => 'border-radius:5px;border-width:2px;',
				),
			),
			'zero'                    => array(
				'block_attributes' => array(
					'style' => array(
						'border' => array(
							'radius' => 0,
							'width'  => 0,
						),
					),
				),
				'expected'         => array(
					'style' => 'border-radius:0px;border-width:0px;',
				),
			),
			'empty'                   => array(
				'block_attributes' => array(),
				'expected'         => array(),
			),
			'malformed border'        => array(
				'block_attributes' => array(
					'style' => array(
						'border' => 'solid',
					),
				),
				'expected'         => array(),
			),
			'malformed side'          => array(
				'block_attributes' => array(
					'style' => array(
						'border' => array(
							'top' => '1px solid',
						),
					),
				),
				'expected'         => array(),
			),
			'malformed style'         => array(
				'block_attributes' => array(
					'style' => 'border:1px solid',
				),
				'expected'         => array(),
			),
			'malformed non-array'     => array(
				'block_attributes' => 'border',
				'expected'         => array(),
			),
		);
	}
}
