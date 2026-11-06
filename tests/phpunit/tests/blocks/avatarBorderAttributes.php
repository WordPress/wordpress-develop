<?php
/**
 * Tests for get_block_core_avatar_border_attributes().
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::get_block_core_avatar_border_attributes
 */
class Tests_Blocks_AvatarBorderAttributes extends WP_UnitTestCase {

	/**
	 * Tests the classes and the styles for the border of the attributes.
	 *
	 * @ticket 66582
	 *
	 * @dataProvider data_borders
	 *
	 * @param array $attributes The attributes of the block.
	 * @param array $expected   The expected classes and styles.
	 */
	public function test_should_return_the_classes_and_styles_of_the_border( $attributes, $expected ) {
		$this->assertSame( $expected, get_block_core_avatar_border_attributes( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_borders() {
		return array(
			'no attributes'                      => array(
				array(),
				array(),
			),
			'attributes without a border'        => array(
				array( 'size' => 48 ),
				array(),
			),
			'a radius'                           => array(
				array( 'style' => array( 'border' => array( 'radius' => '10px' ) ) ),
				array( 'style' => 'border-radius:10px;' ),
			),
			'a style'                            => array(
				array( 'style' => array( 'border' => array( 'style' => 'dashed' ) ) ),
				array( 'style' => 'border-style:dashed;' ),
			),
			'a width'                            => array(
				array( 'style' => array( 'border' => array( 'width' => '2px' ) ) ),
				array( 'style' => 'border-width:2px;' ),
			),
			'a radius, a style and a width'      => array(
				array(
					'style' => array(
						'border' => array(
							'radius' => '10px',
							'style'  => 'dashed',
							'width'  => '2px',
						),
					),
				),
				array( 'style' => 'border-radius:10px;border-style:dashed;border-width:2px;' ),
			),
			'a preset color'                     => array(
				array( 'borderColor' => 'red' ),
				array( 'class' => 'has-border-color has-red-border-color' ),
			),
			'a custom color'                     => array(
				array( 'style' => array( 'border' => array( 'color' => '#ff0000' ) ) ),
				array(
					'class' => 'has-border-color',
					'style' => 'border-color:#ff0000;',
				),
			),
			'a preset and a custom color'        => array(
				array(
					'borderColor' => 'red',
					'style'       => array( 'border' => array( 'color' => '#00ff00' ) ),
				),
				array( 'class' => 'has-border-color has-red-border-color' ),
			),
			'the border of one side'             => array(
				array(
					'style' => array(
						'border' => array(
							'top' => array(
								'color' => '#111111',
								'style' => 'solid',
								'width' => '1px',
							),
						),
					),
				),
				array( 'style' => 'border-top-color:#111111;border-top-style:solid;border-top-width:1px;' ),
			),
			'the border of every side'           => array(
				array(
					'style' => array(
						'border' => array(
							'top'    => array( 'width' => '1px' ),
							'right'  => array( 'width' => '2px' ),
							'bottom' => array( 'width' => '3px' ),
							'left'   => array( 'width' => '4px' ),
						),
					),
				),
				array( 'style' => 'border-top-width:1px;border-right-width:2px;border-bottom-width:3px;border-left-width:4px;' ),
			),
			'a side with a part that is not set' => array(
				array( 'style' => array( 'border' => array( 'left' => array( 'style' => 'dotted' ) ) ) ),
				array( 'style' => 'border-left-style:dotted;' ),
			),
		);
	}
}
