<?php
/**
 * @group block-supports
 *
 * @covers ::wp_get_spacing_classes_and_styles
 */
class Tests_Block_Supports_WpGetSpacingClassesAndStyles extends WP_UnitTestCase {
	/**
	 * Tests that spacing classes and styles are generated from block attributes.
	 *
	 * @ticket 66269
	 *
	 * @dataProvider data_get_spacing_classes_and_styles
	 *
	 * @param mixed $block_attributes Block attributes.
	 * @param array $expected         Expected classes and styles.
	 */
	public function test_get_spacing_classes_and_styles( $block_attributes, $expected ) {
		$this->assertSame( $expected, wp_get_spacing_classes_and_styles( $block_attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_spacing_classes_and_styles() {
		return array(
			'custom values'        => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => array(
							'padding' => array(
								'top'  => '1px',
								'left' => '2em',
							),
							'margin'  => '3px',
						),
					),
				),
				'expected'         => array(
					'style' => 'padding-top:1px;padding-left:2em;margin:3px;',
				),
			),
			'presets'              => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => array(
							'padding' => 'var:preset|spacing|40',
							'margin'  => array( 'bottom' => 'var:preset|spacing|20' ),
						),
					),
				),
				'expected'         => array(
					'style' => 'padding:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--20);',
				),
			),
			'block gap is ignored' => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => array( 'blockGap' => '2em' ),
					),
				),
				'expected'         => array(),
			),
			'numeric values'       => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => array( 'padding' => array( 'top' => 10 ) ),
					),
				),
				'expected'         => array(),
			),
			'zero'                 => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => array( 'margin' => '0' ),
					),
				),
				'expected'         => array(
					'style' => 'margin:0;',
				),
			),
			'empty spacing'        => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => array(),
					),
				),
				'expected'         => array(),
			),
			'no style'             => array(
				'block_attributes' => array(),
				'expected'         => array(),
			),
			'malformed spacing'    => array(
				'block_attributes' => array(
					'style' => array(
						'spacing' => '10px',
					),
				),
				'expected'         => array(),
			),
			'malformed style'      => array(
				'block_attributes' => array(
					'style' => 'padding:10px',
				),
				'expected'         => array(),
			),
		);
	}
}
