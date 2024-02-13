<?php
/**
 * Tests for the set_ignored_hooked_blocks_metadata function.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.5.0
 *
 * @group blocks
 * @group block-hooks
 */
class Tests_Blocks_SetIgnoredHookedBlocksMetadata extends WP_UnitTestCase {
	/**
	 * @ticket 60506
	 *
	 * @covers ::set_ignored_hooked_blocks_metadata
	 */
	public function test_set_ignored_hooked_blocks_metadata() {
		$anchor_block = array(
			'blockName' => 'tests/anchor-block',
		);

		$hooked_blocks = array(
			'tests/anchor-block' => array(
				'after' => array( 'tests/hooked-block' ),
			)
		);

		set_ignored_hooked_blocks_metadata( $anchor_block, 'after', $hooked_blocks, null );
		$this->assertSame( $anchor_block[ 'attrs' ][ 'metadata' ][ 'ignoredHookedBlocks' ], array( 'tests/hooked-block' ) );
	}
}
