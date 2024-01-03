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
	/**
	 * @covers ::insert_hooked_blocks
	 */
	public function test_insert_hooked_blocks() {
		$anchor_block_name = 'tests/anchor-block';
		$anchor_block      = array(
			'blockName' => $anchor_block_name,
		);

		// Maybe move to class level and include other relative positions?
		// And/or data provider?
		$hooked_blocks = array(
			$anchor_block_name => array(
				'after' => array( 'tests/hooked-before' ),
			),
		);

		$actual = insert_hooked_blocks( $anchor_block, 'after', $hooked_blocks, array() );
		$this->assertSame( '<!-- wp:tests/hooked-before /-->', $actual );
	}
}
