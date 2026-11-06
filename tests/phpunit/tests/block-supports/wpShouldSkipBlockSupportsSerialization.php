<?php

/**
 * Tests for the wp_should_skip_block_supports_serialization() function.
 *
 * @group block-supports
 *
 * @covers ::wp_should_skip_block_supports_serialization
 */
class Tests_Block_Supports_WpShouldSkipBlockSupportsSerialization extends WP_UnitTestCase {

	/**
	 * Gets a block type with supports.
	 *
	 * @param array $supports Supports of the block type.
	 * @return WP_Block_Type Block type.
	 */
	private function get_block_type( array $supports ) {
		return new WP_Block_Type( 'test/skip-serialization', array( 'supports' => $supports ) );
	}

	/**
	 * Tests that the serialization is skipped when the feature set skips all of its features.
	 *
	 * @ticket 66509
	 */
	public function test_should_return_true_when_the_whole_feature_set_is_skipped() {
		$block_type = $this->get_block_type( array( 'color' => array( '__experimentalSkipSerialization' => true ) ) );

		$this->assertTrue( wp_should_skip_block_supports_serialization( $block_type, 'color' ) );
		$this->assertTrue( wp_should_skip_block_supports_serialization( $block_type, 'color', 'background' ), 'A feature should be skipped too.' );
	}

	/**
	 * Tests that the serialization is not skipped when the flag is false or missing.
	 *
	 * @ticket 66509
	 *
	 * @dataProvider data_supports_that_do_not_skip_the_serialization
	 *
	 * @param array $supports Supports of the block type.
	 */
	public function test_should_return_false_when_the_serialization_is_not_skipped( $supports ) {
		$block_type = $this->get_block_type( $supports );

		$this->assertFalse( wp_should_skip_block_supports_serialization( $block_type, 'color' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_supports_that_do_not_skip_the_serialization() {
		return array(
			'flag set to false'        => array( array( 'color' => array( '__experimentalSkipSerialization' => false ) ) ),
			'flag missing'             => array( array( 'color' => array( 'background' => true ) ) ),
			'feature set is a boolean' => array( array( 'color' => true ) ),
			'feature set missing'      => array( array( 'spacing' => array( '__experimentalSkipSerialization' => true ) ) ),
			'no supports'              => array( array() ),
		);
	}

	/**
	 * Tests that only the features of the list are skipped when the flag is an array.
	 *
	 * @ticket 66509
	 */
	public function test_should_only_skip_the_features_of_the_list() {
		$block_type = $this->get_block_type(
			array(
				'color' => array( '__experimentalSkipSerialization' => array( 'gradients', 'text' ) ),
			)
		);

		$this->assertTrue( wp_should_skip_block_supports_serialization( $block_type, 'color', 'gradients' ), 'A feature of the list should be skipped.' );
		$this->assertTrue( wp_should_skip_block_supports_serialization( $block_type, 'color', 'text' ), 'The other feature of the list should be skipped.' );
		$this->assertFalse( wp_should_skip_block_supports_serialization( $block_type, 'color', 'background' ), 'A feature that is not in the list should not be skipped.' );
		$this->assertFalse( wp_should_skip_block_supports_serialization( $block_type, 'color' ), 'Without a feature, the list does not skip the feature set.' );
	}

	/**
	 * Tests that the features of the list are compared strictly.
	 *
	 * @ticket 66509
	 */
	public function test_should_compare_the_feature_with_the_list_strictly() {
		$block_type = $this->get_block_type(
			array(
				'color' => array( '__experimentalSkipSerialization' => array( 1, 'text' ) ),
			)
		);

		$this->assertFalse( wp_should_skip_block_supports_serialization( $block_type, 'color', '1' ), 'A string should not match the integer 1.' );
		$this->assertTrue( wp_should_skip_block_supports_serialization( $block_type, 'color', 1 ), 'The same integer should match.' );
	}

	/**
	 * Tests that an empty feature set is not looked up, even when the supports have an empty key.
	 *
	 * @ticket 66509
	 */
	public function test_should_not_look_up_an_empty_feature_set() {
		$block_type = $this->get_block_type( array( '' => array( '__experimentalSkipSerialization' => true ) ) );

		$this->assertFalse( wp_should_skip_block_supports_serialization( $block_type, '' ) );
	}

	/**
	 * Tests that a value that is not a block type, or an empty feature set, returns false.
	 *
	 * @ticket 66509
	 *
	 * @dataProvider data_arguments_that_return_false
	 *
	 * @param mixed $block_type  Block type.
	 * @param mixed $feature_set Feature set.
	 */
	public function test_should_return_false_without_a_block_type_object_or_a_feature_set( $block_type, $feature_set ) {
		if ( 'object' === $block_type ) {
			$block_type = $this->get_block_type( array( 'color' => array( '__experimentalSkipSerialization' => true ) ) );
		}

		$this->assertFalse( wp_should_skip_block_supports_serialization( $block_type, $feature_set ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_arguments_that_return_false() {
		return array(
			'null block type'         => array( null, 'color' ),
			'array block type'        => array( array( 'supports' => array( 'color' => array( '__experimentalSkipSerialization' => true ) ) ), 'color' ),
			'string block type'       => array( 'core/paragraph', 'color' ),
			'empty feature set'       => array( 'object', '' ),
			'null feature set'        => array( 'object', null ),
			'false feature set'       => array( 'object', false ),
			'zero as the feature set' => array( 'object', 0 ),
		);
	}
}
