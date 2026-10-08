<?php
/**
 * @group block-supports
 *
 * @covers ::wp_get_shadow_classes_and_styles
 */
class Tests_Block_Supports_WpGetShadowClassesAndStyles extends WP_UnitTestCase {
	/**
	 * Tests that shadow classes and styles are generated from block attributes.
	 *
	 * @ticket 66269
	 *
	 * @dataProvider data_get_shadow_classes_and_styles
	 *
	 * @param mixed $block_attributes Block attributes.
	 * @param array $expected         Expected classes and styles.
	 */
	public function test_get_shadow_classes_and_styles( $block_attributes, $expected ) {
		$this->assertSame( $expected, wp_get_shadow_classes_and_styles( $block_attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_shadow_classes_and_styles() {
		return array(
			'custom shadow'        => array(
				'block_attributes' => array( 'style' => array( 'shadow' => '1px 1px 1px #000' ) ),
				'expected'         => array( 'style' => 'box-shadow:1px 1px 1px #000;' ),
			),
			'preset shadow'        => array(
				'block_attributes' => array( 'style' => array( 'shadow' => 'var:preset|shadow|natural' ) ),
				'expected'         => array( 'style' => 'box-shadow:var(--wp--preset--shadow--natural);' ),
			),
			'numeric shadow'       => array(
				'block_attributes' => array( 'style' => array( 'shadow' => 5 ) ),
				'expected'         => array(),
			),
			'zero shadow'          => array(
				'block_attributes' => array( 'style' => array( 'shadow' => 0 ) ),
				'expected'         => array(),
			),
			'empty shadow'         => array(
				'block_attributes' => array( 'style' => array( 'shadow' => '' ) ),
				'expected'         => array(),
			),
			'no style attribute'   => array(
				'block_attributes' => array(),
				'expected'         => array(),
			),
			'non-array style'      => array(
				'block_attributes' => array( 'style' => 'box-shadow:1px 1px 1px #000' ),
				'expected'         => array(),
			),
			'array shadow'         => array(
				'block_attributes' => array( 'style' => array( 'shadow' => array( '1px 1px 1px #000' ) ) ),
				'expected'         => array(),
			),
			'non-array attributes' => array(
				'block_attributes' => null,
				'expected'         => array(),
			),
		);
	}
}
