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
	const ANCHOR_BLOCK_TYPE = 'tests/anchor-block';

	const HOOKED_BLOCK_TYPE = 'tests/hooked-block';
	const HOOKED_BLOCK      = array(
		'blockName'    => 'tests/different-hooked-block',
		'attrs'        => array(),
		'innerContent' => array(),
	);

	const OTHER_HOOKED_BLOCK_TYPE = 'tests/other-hooked-block';
	const OTHER_HOOKED_BLOCK      = array(
		'blockName'    => self::OTHER_HOOKED_BLOCK_TYPE,
		'attrs'        => array(),
		'innerContent' => array(),
	);

	const HOOKED_BLOCKS = array(
		self::ANCHOR_BLOCK_TYPE => array(
			'after'  => array( self::HOOKED_BLOCK_TYPE ),
			'before' => array( self::OTHER_HOOKED_BLOCK_TYPE ),
		),
	);

	/**
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
}
