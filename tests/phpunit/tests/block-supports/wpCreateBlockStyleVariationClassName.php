<?php

/**
 * @group block-supports
 *
 * @covers ::wp_create_block_style_variation_class_name
 */
class Tests_Block_Supports_WpCreateBlockStyleVariationClassName extends WP_UnitTestCase {
	/**
	 * Tests that the block style variations block support create the correct classname.
	 *
	 * @ticket 61312
	 *
	 * @covers ::wp_create_block_style_variation_class_name
	 */
	public function test_block_style_variation_class_generation() {
		$block    = array( 'name' => 'test/block' );
		$actual   = wp_create_block_style_variation_class_name( $block, 'my-variation' );
		$expected = 'is-style-my-variation--' . md5( serialize( $block ) );

		$this->assertSame( $expected, $actual, 'Block style variation class name should be correct' );
	}
}
