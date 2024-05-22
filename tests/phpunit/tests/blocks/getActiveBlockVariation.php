<?php
/**
 * Tests for the get_active_block_variation function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.6.0
 *
 * @group blocks
 */
class Tests_Blocks_GetActiveBlockVariation extends WP_UnitTestCase {

	/**
	 * Block type.
	 *
	 * @var WP_Block_Type
	 */
	protected static $block_type;

	/**
	 * Set up before class.
	 */
	public static function wpSetUpBeforeClass() {
		self::$block_type = new WP_Block_Type( 'tests/block-type', array(
			'attributes' => array(
				'attribute1' => array(
					'type' => 'string',
				),
				'attribute2' => array(
					'type' => 'string',
				),
			),
			'variations' => self::mock_variation_callback(),
		) );
	}

	public function test_get_active_block_variation_no_match() {
		$block_attributes = array(
			'attribute1' => 'var1-attr1',
			'attribute2' => 'var1-attr2',
			'attribute3' => 'mismatch',
		);

		$active_variation = get_active_block_variation( self::$block_type, $block_attributes );
		$this->assertNull( $active_variation );
	}

	public function test_get_active_block_variation_match_without_is_active() {
		$block_attributes = array(
			'attribute1' => 'var1-attr1',
			'attribute2' => 'var1-attr2',
			'attribute3' => 'var1-attr3',
		);

		$active_variation = get_active_block_variation( self::$block_type, $block_attributes );
		$this->assertSame( 'variation_with_is_active', $active_variation['name'] );
	}

	public function test_get_active_block_variation_match_with_empty_is_active() {
		$block_attributes = array(
			'attribute1' => 'var2-attr1',
			'attribute2' => 'var2-attr2',
			'attribute3' => 'var2-attr3',
		);

		$active_variation = get_active_block_variation( self::$block_type, $block_attributes );
		$this->assertSame( 'variation_with_empty_is_active', $active_variation['name'] );
	}

	public function test_get_active_block_variation_match_with_is_active() {
		$block_attributes = array(
			'attribute1' => 'var3-attr1',
			'attribute2' => 'var3-attr2',
			'attribute3' => 'var3-attr3',
		);

		$active_variation = get_active_block_variation( self::$block_type, $block_attributes );
		$this->assertSame( 'variation_without_is_active', $active_variation['name'] );
	}

	/**
	 * Mock variation callback.
	 *
	 * @return array
	 */
	public static function mock_variation_callback() {
		return array(
			array(
				'name'       => 'variation_with_is_active',
				'attributes' => array(
					'attribute1' => 'var1-attr1',
					'attribute2' => 'var1-attr2',
					'attribute3' => 'var1-attr3',
				),
				'isActive' => array(
					'attribute1',
					'attribute3',
				),
			),
			array(
				'name'       => 'variation_with_empty_is_active',
				'attributes' => array(
					'attribute1' => 'var2-attr1',
					'attribute2' => 'var2-attr2',
					'attribute3' => 'var2-attr3',
				),
				'isActive' => array(),
			),
			array(
				'name'       => 'variation_without_is_active',
				'attributes' => array(
					'attribute1' => 'var3-attr1',
					'attribute2' => 'var3-attr2',
					'attribute3' => 'var3-attr3',
				),
			),
		);
	}
};
