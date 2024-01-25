<?php
/**
 * Tests for the insert_hooked_blocks function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.5.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_InsertHookedBlocks extends WP_UnitTestCase {
	const ANCHOR_BLOCK_TYPE       = 'tests/anchor-block';
	const HOOKED_BLOCK_TYPE       = 'tests/hooked-block';
	const OTHER_HOOKED_BLOCK_TYPE = 'tests/other-hooked-block';

	const HOOKED_BLOCKS = array(
		self::ANCHOR_BLOCK_TYPE => array(
			'after'  => array( self::HOOKED_BLOCK_TYPE ),
			'before' => array( self::OTHER_HOOKED_BLOCK_TYPE ),
		),
	);

	/**
	 * @ticket 59572
	 * @ticket 60126
	 *
	 * @covers ::insert_hooked_blocks
	 */
	public function test_insert_hooked_blocks_adds_metadata() {
		$anchor_block = array(
			'blockName' => self::ANCHOR_BLOCK_TYPE,
		);

		$actual = insert_hooked_blocks( $anchor_block, 'after', self::HOOKED_BLOCKS, array() );
		$this->assertSame( array( self::HOOKED_BLOCK_TYPE ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '<!-- wp:' . self::HOOKED_BLOCK_TYPE . ' /-->', $actual );
	}

	/**
	 * @ticket 59572
	 * @ticket 60126
	 *
	 * @covers ::insert_hooked_blocks
	 */
	public function test_insert_hooked_blocks_if_block_is_already_hooked() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(
				'metadata' => array(
					'ignoredHookedBlocks' => array( self::HOOKED_BLOCK_TYPE ),
				),
			),
		);

		$actual = insert_hooked_blocks( $anchor_block, 'after', self::HOOKED_BLOCKS, array() );
		$this->assertSame( array( self::HOOKED_BLOCK_TYPE ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '', $actual );
	}

	/**
	 * @ticket 59572
	 * @ticket 60126
	 *
	 * @covers ::insert_hooked_blocks
	 */
	public function test_insert_hooked_blocks_adds_to_ignored_hooked_blocks() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(
				'metadata' => array(
					'ignoredHookedBlocks' => array( self::HOOKED_BLOCK_TYPE ),
				),
			),
		);

		$actual = insert_hooked_blocks( $anchor_block, 'before', self::HOOKED_BLOCKS, array() );
		$this->assertSame( array( self::HOOKED_BLOCK_TYPE, self::OTHER_HOOKED_BLOCK_TYPE ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '<!-- wp:' . self::OTHER_HOOKED_BLOCK_TYPE . ' /-->', $actual );
	}

	/**
	 * @ticket 59572
	 * @ticket 60126
	 *
	 * @covers ::insert_hooked_blocks
	 */
	public function test_insert_hooked_blocks_filter_can_set_attributes() {
		$anchor_block = array(
			'blockName'    => self::ANCHOR_BLOCK_TYPE,
			'attrs'        => array(
				'layout' => array(
					'type' => 'constrained',
				)
			),
			'innerContent' => array(),
		);

		$filter = function( $parsed_hooked_block, $relative_position, $parsed_anchor_block ) {
			// Is the hooked block adjacent to the anchor block?
			if ( 'before' !== $relative_position && 'after' !== $relative_position ) {
				return $parsed_hooked_block;
			}

			// Does the anchor block have a layout attribute?
			if ( isset( $parsed_anchor_block['attrs']['layout'] ) ) {
				// Copy the anchor block's layout attribute to the hooked block.
				$parsed_hooked_block['attrs']['layout'] = $parsed_anchor_block['attrs']['layout'];
			}

			return $parsed_hooked_block;
		};
		add_filter( 'hooked_block_' . SELF::HOOKED_BLOCK_TYPE, $filter, 10, 3 );
		$actual = insert_hooked_blocks( $anchor_block, 'after', self::HOOKED_BLOCKS, array() );
		remove_filter( 'hooked_block_' . SELF::HOOKED_BLOCK_TYPE, $filter, 10, 3 );

		$this->assertSame( array( self::HOOKED_BLOCK_TYPE ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '<!-- wp:' . self::HOOKED_BLOCK_TYPE . ' {"layout":{"type":"constrained"}} /-->', $actual );
	}
}
