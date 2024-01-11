<?php
/**
 * Tests for the get_hooked_block_markup function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.5.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_GetHookedBlockMarkup extends WP_UnitTestCase {
	const HOOKED_BLOCK_TYPE = 'tests/hooked-block';
	const HOOKED_BLOCK      = array(
		'blockName'    => 'tests/different-hooked-block',
		'attrs'        => array(),
		'innerContent' => array(),
	);

	/**
	 * @ticket 60008
	 * @ticket 60126
	 *
	 * @covers ::get_hooked_block_markup
	 */
	public function test_get_hooked_block_markup_adds_metadata() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
		);

		$actual = get_hooked_block_markup( self::HOOKED_BLOCK, self::HOOKED_BLOCK_TYPE, $anchor_block );
		$this->assertSame( array( self::HOOKED_BLOCK_TYPE ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '<!-- wp:' . self::HOOKED_BLOCK['blockName'] . ' /-->', $actual );
	}

	/**
	 * @ticket 60008
	 * @ticket 60126
	 *
	 * @covers ::get_hooked_block_markup
	 */
	public function test_get_hooked_block_markup_if_block_is_already_hooked() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(
				'metadata' => array(
					'ignoredHookedBlocks' => array( self::HOOKED_BLOCK_TYPE ),
				),
			),
		);

		$actual = get_hooked_block_markup( self::HOOKED_BLOCK, self::HOOKED_BLOCK_TYPE, $anchor_block );
		$this->assertSame( array( self::HOOKED_BLOCK_TYPE ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '', $actual );
	}

	/**
	 * @ticket 60008
	 * @ticket 60126
	 *
	 * @covers ::get_hooked_block_markup
	 */
	public function test_get_hooked_block_markup_adds_to_ignored_hooked_blocks() {
		$other_hooked_block_type = 'tests/other-hooked-block';
		$other_hooked_block      = array(
			'blockName'    => $other_hooked_block_type,
			'attrs'        => array(),
			'innerContent' => array(),
		);

		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
			'attrs'     => array(
				'metadata' => array(
					'ignoredHookedBlocks' => array( self::HOOKED_BLOCK_TYPE ),
				),
			),
		);

		$actual = get_hooked_block_markup( $other_hooked_block, $other_hooked_block_type, $anchor_block );
		$this->assertSame( array( self::HOOKED_BLOCK_TYPE, $other_hooked_block_type ), $anchor_block['attrs']['metadata']['ignoredHookedBlocks'] );
		$this->assertSame( '<!-- wp:' . $other_hooked_block_type . ' /-->', $actual );
	}
}
