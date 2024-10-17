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
 * @covers ::get_active_block_variation
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
		self::$block_type = new WP_Block_Type(
			'block/name',
			array(
				'attributes' => array(
					'testAttribute'       => array(
						'type' => 'number',
					),
					'firstTestAttribute'  => array(
						'type' => 'number',
					),
					'secondTestAttribute' => array(
						'type' => 'number',
					),
				),
				'variations' => self::mock_variation_callback(),
			)
		);
	}

	/**
	 * @ticket 61265
	 */
	public function test_get_active_block_variation_no_match() {
		$block_attributes = array(
			'testAttribute' => 5555,
		);

		$active_variation = get_active_block_variation( self::$block_type, $block_attributes );
		$this->assertNull( $active_variation );
	}

	/**
	 * @ticket 61265
	 */
	public function test_get_active_block_variation_match_with_is_active() {
		$active_variation = get_active_block_variation(
			self::$block_type,
			array(
				'firstTestAttribute'  => 1,
				'secondTestAttribute' => 10,
			)
		);
		$this->assertSame( 'variation-1', $active_variation['name'] );

		$active_variation = get_active_block_variation(
			self::$block_type,
			array(
				'firstTestAttribute'  => 2,
				'secondTestAttribute' => 20,
			)
		);
		$this->assertSame( 'variation-2', $active_variation['name'] );

		$active_variation = get_active_block_variation(
			self::$block_type,
			array(
				'firstTestAttribute'  => 1,
				'secondTestAttribute' => 20,
			)
		);
		$this->assertSame( 'variation-3', $active_variation['name'] );
	}

	/**
	 * Mock variation callback.
	 *
	 * @return array
	 */
	public static function mock_variation_callback() {
		return array(
			array(
				'name'       => 'variation-1',
				'attributes' => array(
					'firstTestAttribute'  => 1,
					'secondTestAttribute' => 10,
				),
				'isActive'   => array(
					'firstTestAttribute',
					'secondTestAttribute',
				),
			),
			array(
				'name'       => 'variation-2',
				'attributes' => array(
					'firstTestAttribute'  => 2,
					'secondTestAttribute' => 20,
				),
				'isActive'   => array(
					'firstTestAttribute',
					'secondTestAttribute',
				),
			),
			array(
				'name'       => 'variation-3',
				'attributes' => array(
					'firstTestAttribute'  => 1,
					'secondTestAttribute' => 20,
				),
				'isActive'   => array(
					'firstTestAttribute',
					'secondTestAttribute',
				),
			),
		);
	}
}
