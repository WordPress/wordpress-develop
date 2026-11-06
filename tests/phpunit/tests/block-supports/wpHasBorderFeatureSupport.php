<?php

/**
 * Tests for the wp_has_border_feature_support() function.
 *
 * @group block-supports
 *
 * @covers ::wp_has_border_feature_support
 */
class Tests_Block_Supports_WpHasBorderFeatureSupport extends WP_UnitTestCase {

	/**
	 * Gets a block type with supports.
	 *
	 * @param array $supports Supports of the block type.
	 * @return WP_Block_Type Block type.
	 */
	private function get_block_type( array $supports ) {
		return new WP_Block_Type( 'test/border-support', array( 'supports' => $supports ) );
	}

	/**
	 * Tests that every feature is supported when the block type opts in to all the border features.
	 *
	 * @ticket 66509
	 *
	 * @dataProvider data_features
	 *
	 * @param string $feature Border feature.
	 */
	public function test_should_return_true_for_every_feature_when_the_block_opts_in_to_all( $feature ) {
		$block_type = $this->get_block_type( array( '__experimentalBorder' => true ) );

		$this->assertTrue( wp_has_border_feature_support( $block_type, $feature ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_features() {
		return array(
			'color'  => array( 'color' ),
			'radius' => array( 'radius' ),
			'style'  => array( 'style' ),
			'width'  => array( 'width' ),
		);
	}

	/**
	 * Tests that a feature is supported when the block type opts in to it individually.
	 *
	 * @ticket 66509
	 */
	public function test_should_return_true_for_a_feature_that_is_opted_in_individually() {
		$block_type = $this->get_block_type(
			array(
				'__experimentalBorder' => array(
					'color'  => true,
					'radius' => false,
				),
			)
		);

		$this->assertTrue( wp_has_border_feature_support( $block_type, 'color' ), 'The feature that is enabled should be supported.' );
		$this->assertFalse( wp_has_border_feature_support( $block_type, 'radius' ), 'The feature that is disabled should not be supported.' );
		$this->assertFalse( wp_has_border_feature_support( $block_type, 'width' ), 'The feature that is not set should not be supported.' );
	}

	/**
	 * Tests that the default value is used when the block type does not say anything about the borders.
	 *
	 * @ticket 66509
	 */
	public function test_should_use_the_default_value_when_the_block_has_no_border_support() {
		$block_type = $this->get_block_type( array( 'align' => true ) );

		$this->assertFalse( wp_has_border_feature_support( $block_type, 'color' ), 'The default value should be false.' );
		$this->assertFalse( wp_has_border_feature_support( $block_type, 'color', false ), 'A default value of false should be returned.' );
		$this->assertTrue( wp_has_border_feature_support( $block_type, 'color', true ), 'A default value of true should be returned.' );
	}

	/**
	 * Tests that the default value is used for a feature that is not set when the borders are set individually.
	 *
	 * @ticket 66509
	 */
	public function test_should_use_the_default_value_for_a_feature_that_is_not_set() {
		$block_type = $this->get_block_type( array( '__experimentalBorder' => array( 'color' => true ) ) );

		$this->assertTrue( wp_has_border_feature_support( $block_type, 'radius', true ), 'The default value should be used for a feature that is not set.' );
		$this->assertFalse( wp_has_border_feature_support( $block_type, 'radius' ), 'The default value should be false by default.' );
	}

	/**
	 * Tests that a feature that is disabled is not enabled by the default value.
	 *
	 * @ticket 66509
	 */
	public function test_should_not_use_the_default_value_for_a_feature_that_is_disabled() {
		$block_type = $this->get_block_type( array( '__experimentalBorder' => array( 'radius' => false ) ) );

		$this->assertFalse( wp_has_border_feature_support( $block_type, 'radius', true ) );
	}

	/**
	 * Tests that the value that is not `true` does not opt in to all the features.
	 *
	 * @ticket 66509
	 */
	public function test_should_not_opt_in_to_all_the_features_with_a_value_that_is_not_true() {
		$block_type = $this->get_block_type( array( '__experimentalBorder' => false ) );

		$this->assertFalse( wp_has_border_feature_support( $block_type, 'color' ) );
	}

	/**
	 * Tests that the default value is used when the block type is not a block type object.
	 *
	 * @ticket 66509
	 */
	public function test_should_use_the_default_value_when_the_block_type_is_not_an_object() {
		$this->assertFalse( wp_has_border_feature_support( null, 'color' ), 'A null block type should use the default value.' );
		$this->assertFalse( wp_has_border_feature_support( array( 'supports' => array( '__experimentalBorder' => true ) ), 'color' ), 'An array should not be read as a block type.' );

		$object           = new stdClass();
		$object->supports = array( '__experimentalBorder' => true );

		$this->assertFalse( wp_has_border_feature_support( $object, 'color' ), 'An object that is not a block type should not opt in to all the features.' );
	}
}
